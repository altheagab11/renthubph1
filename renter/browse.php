<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both Renter/Owner

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get current user information
$user_query = "SELECT User_Name, User_Email, User_Phone FROM user_accounts WHERE UserID = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get all user addresses
$addresses_query = "SELECT AddressID, UA_Street, UA_Barangay, UA_City, 
                           UA_Province, UA_ZipCode, UA_IsDefault, UA_AddressType
                    FROM user_addresses 
                    WHERE UserID = ? 
                    ORDER BY UA_IsDefault DESC, AddressID DESC";
$addresses_stmt = $conn->prepare($addresses_query);
$addresses_stmt->execute([$user_id]);
$user_addresses = $addresses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle booking form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    header('Content-Type: application/json');
    
    try {
        // Validate required fields
        $required_fields = ['product_id', 'rental_start_date', 'rental_end_date', 'renter_phone', 'renter_address', 'payment_method'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Get product details
        $product_query = "SELECT p.*, p.OwnerID FROM products p WHERE p.ProductID = ?";
        $product_stmt = $conn->prepare($product_query);
        $product_stmt->execute([$_POST['product_id']]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        // Calculate duration and total amount
        $start_date = new DateTime($_POST['rental_start_date']);
        $end_date = new DateTime($_POST['rental_end_date']);
        $interval = $start_date->diff($end_date);
        $duration_days = $interval->days + 1; // Include both start and end date
        
        $rental_price = $product['Prod_RentalPrice'];
        $security_deposit = $product['Prod_SecurityDeposit'] ?? 0;
        $price_type = $product['Prod_PriceType'];
        
        if (strpos(strtolower($price_type), 'hour') !== false) {
            $duration_hours = $interval->days * 24 + $interval->h;
            $rental_amount = $rental_price * $duration_hours;
        } else {
            $rental_amount = $rental_price * $duration_days;
        }
        
        // Add delivery fee if delivery is selected
        $delivery_fee = 0;
        if ($_POST['pickup_delivery'] === 'delivery') {
            // Get delivery fee from product_locations
            $delivery_query = "SELECT PL_DeliveryFee FROM product_locations WHERE ProductID = ?";
            $delivery_stmt = $conn->prepare($delivery_query);
            $delivery_stmt->execute([$_POST['product_id']]);
            $delivery_result = $delivery_stmt->fetch(PDO::FETCH_ASSOC);
            $delivery_fee = $delivery_result['PL_DeliveryFee'] ?? 0;
        }
        
        $total_amount = $rental_amount + $security_deposit + $delivery_fee;
        
        // Insert booking record
        $booking_query = "INSERT INTO bookings (
            ProductID, RenterID, OwnerID, 
            Book_StartDate, Book_EndDate, Book_TotalAmount,
            Book_Status, Book_Notes
        ) VALUES (
            ?, ?, ?, 
            ?, ?, ?,
            'Pending', ?
        )";
        
        // Prepare booking notes with all the additional details
        $booking_notes = json_encode([
            'payment_method' => $_POST['payment_method'],
            'renter_name' => $_POST['renter_name'],
            'renter_phone' => $_POST['renter_phone'],
            'renter_email' => $_POST['renter_email'],
            'emergency_contact' => $_POST['emergency_contact'] ?? '',
            'renter_address' => $_POST['renter_address'],
            'pickup_delivery' => $_POST['pickup_delivery'],
            'special_instructions' => $_POST['special_instructions'] ?? '',
            'payment_account_name' => $_POST['payment_account_name'] ?? '',
            'payment_account_number' => $_POST['payment_account_number'] ?? '',
            'terms_agreement' => $_POST['terms_agreement'] ?? ''
        ]);
        
        $booking_stmt = $conn->prepare($booking_query);
        $booking_result = $booking_stmt->execute([
            $_POST['product_id'],
            $user_id,
            $product['OwnerID'],
            $_POST['rental_start_date'],
            $_POST['rental_end_date'],
            $total_amount,
            $booking_notes
        ]);
        
        if ($booking_result) {
            echo json_encode(['success' => true, 'message' => 'Booking request sent successfully! Waiting for owner approval.']);
        } else {
            throw new Exception("Failed to create booking");
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$price_min = isset($_GET['price_min']) ? $_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) ? $_GET['price_max'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$show_all = isset($_GET['show_all']) ? $_GET['show_all'] : false;

// Check if table view is requested
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'grid';

// Build the WHERE clause for filters
$where_conditions = [];
$params = [];

// Only apply status filters if not showing all
if (!$show_all) {
    // Show products unless they are explicitly marked as unavailable or inactive
    $where_conditions[] = "(p.Prod_Availability != 'Unavailable' OR p.Prod_Availability IS NULL)";
    $where_conditions[] = "(p.Prod_Status != 'Inactive' OR p.Prod_Status IS NULL)";
}

// Exclude user's own products (owners shouldn't see their own items when browsing to rent)
$where_conditions[] = "p.OwnerID != ?";
$params[] = $user_id;

if (!empty($category_filter)) {
    $where_conditions[] = "p.CategoryID = ?";
    $params[] = $category_filter;
}

if (!empty($search_filter)) {
    $where_conditions[] = "(p.Prod_Name LIKE ? OR p.Prod_Description LIKE ? OR p.Prod_Brand LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($price_min)) {
    $where_conditions[] = "p.Prod_RentalPrice >= ?";
    $params[] = $price_min;
}

if (!empty($price_max)) {
    $where_conditions[] = "p.Prod_RentalPrice <= ?";
    $params[] = $price_max;
}

// Build ORDER BY clause
$order_by = "p.Prod_CreatedAt DESC";
switch ($sort_by) {
    case 'price_low':
        $order_by = "p.Prod_RentalPrice ASC";
        break;
    case 'price_high':
        $order_by = "p.Prod_RentalPrice DESC";
        break;
    case 'name':
        $order_by = "p.Prod_Name ASC";
        break;
    case 'newest':
    default:
        $order_by = "p.Prod_CreatedAt DESC";
        break;
}

// Get products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = $view_mode == 'table' ? 20 : 12;
$offset = ($page - 1) * $items_per_page;

// Build WHERE clause string
$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Count total products for pagination
$count_query = "SELECT COUNT(DISTINCT p.ProductID) as total 
                FROM products p 
                LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
                LEFT JOIN user_accounts u ON p.OwnerID = u.UserID 
                LEFT JOIN product_locations pl ON p.ProductID = pl.ProductID
                LEFT JOIN user_addresses ua ON pl.AddressID = ua.AddressID
                $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $items_per_page);

// Get products with main image and availability status
$query = "SELECT p.*, 
                 COALESCE(c.Cat_Name, 'Uncategorized') as Cat_Name, 
                 COALESCE(u.User_Name, 'Unknown Owner') as Owner_Name, 
                 main_img.PI_ImagePath as MainImage,
                 (SELECT COUNT(*) FROM favorites f WHERE f.ProductID = p.ProductID AND f.UserID = ?) as is_favorited,
                 (SELECT COUNT(*) FROM product_images pi WHERE pi.ProductID = p.ProductID) as total_images,
                 pa.PA_DateFrom,
                 pa.PA_DateTo,
                 pa.PA_IsAvailable,
                 pa.PA_Reason,
                 pa.PA_CreatedAt as AvailabilityLastUpdated,
                 CASE 
                    WHEN pa.PA_IsAvailable = 1 AND CURDATE() BETWEEN pa.PA_DateFrom AND pa.PA_DateTo THEN 'Available'
                    WHEN pa.PA_IsAvailable = 0 AND CURDATE() BETWEEN pa.PA_DateFrom AND pa.PA_DateTo THEN 'Unavailable'
                    WHEN pa.PA_DateTo < CURDATE() THEN 'Expired'
                    WHEN pa.PA_DateFrom > CURDATE() THEN 'Scheduled'
                    ELSE 'No Schedule'
                 END as AvailabilityStatus,
                 pl.LocationID,
                 pl.PL_PickupAvailable,
                 pl.PL_DeliveryAvailable,
                 pl.PL_DeliveryRadius,
                 pl.PL_DeliveryFee,
                 ua.UA_Street,
                 ua.UA_Barangay,
                 ua.UA_City,
                 ua.UA_Province,
                 ua.UA_ZipCode,
                 ua.UA_Latitude,
                 ua.UA_Longitude,
                 ua.UA_AddressType,
                 CONCAT_WS(', ', 
                    NULLIF(ua.UA_Street, ''), 
                    NULLIF(ua.UA_Barangay, ''), 
                    NULLIF(ua.UA_City, ''), 
                    NULLIF(ua.UA_Province, '')
                 ) as FullAddress
          FROM products p
          LEFT JOIN categories c ON p.CategoryID = c.CategoryID
          LEFT JOIN user_accounts u ON p.OwnerID = u.UserID
          LEFT JOIN product_images main_img ON p.ProductID = main_img.ProductID AND main_img.PI_IsMain = 1
          LEFT JOIN product_availability pa ON p.ProductID = pa.ProductID 
                AND pa.PA_CreatedAt = (
                    SELECT MAX(pa2.PA_CreatedAt) 
                    FROM product_availability pa2 
                    WHERE pa2.ProductID = p.ProductID
                )
          LEFT JOIN product_locations pl ON p.ProductID = pl.ProductID
          LEFT JOIN user_addresses ua ON pl.AddressID = ua.AddressID
          $where_clause
          ORDER BY $order_by
          LIMIT $items_per_page OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->execute(array_merge([$user_id], $params));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all images for each product (for gallery view)
$product_images = [];
if (!empty($products)) {
    $product_ids = array_column($products, 'ProductID');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    
    $images_query = "SELECT ProductID, PI_ImagePath, PI_ImageOrder, PI_IsMain 
                     FROM product_images 
                     WHERE ProductID IN ($placeholders) 
                     ORDER BY ProductID, PI_ImageOrder ASC";
    $images_stmt = $conn->prepare($images_query);
    $images_stmt->execute($product_ids);
    $all_images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group images by ProductID
    foreach ($all_images as $image) {
        $product_images[$image['ProductID']][] = $image;
    }
}

// Get all availability records for each product (for detailed view)
$product_availability = [];
if (!empty($products)) {
    $availability_query = "SELECT ProductID, PA_DateFrom, PA_DateTo, PA_IsAvailable, PA_Reason, PA_CreatedAt 
                          FROM product_availability 
                          WHERE ProductID IN ($placeholders) 
                          ORDER BY ProductID, PA_CreatedAt DESC";
    $availability_stmt = $conn->prepare($availability_query);
    $availability_stmt->execute($product_ids);
    $all_availability = $availability_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group availability by ProductID
    foreach ($all_availability as $availability) {
        $product_availability[$availability['ProductID']][] = $availability;
    }
}

// Get categories for filter
$cat_query = "SELECT * FROM categories ORDER BY Cat_Name";
$cat_stmt = $conn->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug information
$debug_query = "SELECT COUNT(*) as total_all FROM products";
$debug_stmt = $conn->prepare($debug_query);
$debug_stmt->execute();
$total_all_products = $debug_stmt->fetch(PDO::FETCH_ASSOC)['total_all'];

$sample_query = "SELECT ProductID, Prod_Name, Prod_Availability, Prod_Status, Prod_CreatedAt FROM products LIMIT 5";
$sample_stmt = $conn->prepare($sample_query);
$sample_stmt->execute();
$sample_products = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        .product-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
            cursor: pointer;
        }
        
        /* Product Carousel Styles */
        .product-carousel {
            height: 200px;
        }
        
        .product-carousel .carousel-indicators {
            bottom: 10px;
            margin-bottom: 0;
        }
        
        .product-carousel .carousel-indicators [data-bs-target] {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin: 0 2px;
            background-color: rgba(255, 255, 255, 0.5);
            border: none;
            transition: all 0.3s ease;
        }
        
        .product-carousel .carousel-indicators .active {
            background-color: rgba(255, 255, 255, 0.9);
            transform: scale(1.2);
        }
        
        /* Booking Modal Styles */
        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 60px;
            display: flex;
            align-items: center;
            text-align: center;
            justify-content: center;
        }
        
        .payment-option:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        
        .payment-option input:checked + label {
            color: #667eea;
            font-weight: 600;
        }
        
        .payment-option:has(input:checked) {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        
        .payment-option .form-check-input {
            display: none;
        }
        
        .payment-option .form-check-label {
            margin-bottom: 0;
            width: 100%;
            cursor: pointer;
        }
        
        .form-text {
            font-size: 0.8em;
            color: #6c757d;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        #productInfo {
            border-left: 4px solid #667eea;
        }
        
        .product-image-small {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 0.4rem 0.8rem;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .price-display {
            font-size: 1.1rem;
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .favorite-btn.favorited {
            color: #dc3545;
        }
        
        .image-indicator {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
        }
        
        .availability-indicator {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .filter-card {
            background: #f8f9fa;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .book-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 0.4rem 1rem;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            color: white;
        }
        
        .book-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .product-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .view-toggle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 0.375rem;
        }
        
        .view-toggle.active {
            background: #fff;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .table-view {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-available, .availability-available {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-unavailable, .availability-unavailable {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .status-scheduled, .availability-scheduled {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-expired, .availability-expired {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .status-no-schedule, .availability-no-schedule {
            background-color: #f5f5f5;
            color: #424242;
        }
        
        .status-rented {
            background-color: #fff3cd;
            color: #f57c00;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .price-display {
            font-weight: bold;
            color: #667eea;
        }
        
        .debug-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        /* Image Gallery Modal Styles */
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .gallery-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .gallery-image:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .main-gallery-image {
            border: 3px solid #667eea;
        }
        
        .no-image-placeholder {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }
        
        /* Availability Details */
        .availability-timeline {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .availability-item {
            border-left: 3px solid #667eea;
            padding-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .availability-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #667eea;
        }
        
        .availability-current {
            border-left-color: #28a745;
        }
        
        .availability-current::before {
            background: #28a745;
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as before) -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-1">
                <i class="fas fa-search"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Renter Dashboard</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="browse.php">
                        <i class="fas fa-search me-2"></i> Browse Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> My Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="favorites.php">
                        <i class="fas fa-heart me-2"></i> Favorites
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="fas fa-comments me-2"></i> Messages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reviews.php">
                        <i class="fas fa-star me-2"></i> Reviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payment-history.php">
                        <i class="fas fa-money-bill me-2"></i> Payment History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user me-2"></i> Profile Settings
                    </a>
                </li>
                <?php if($_SESSION['user_role'] == 3): ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="../owner/dashboard.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-store me-2"></i> Switch to Owner
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="upgrade.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-crown me-2"></i> Become an Owner
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation (same as before) -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-3" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0">Browse Available Items</h5>
                </div>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger badge-sm">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">Booking confirmed</a></li>
                            <li><a class="dropdown-item" href="#">New message received</a></li>
                            <li><a class="dropdown-item" href="#">Payment processed</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="notifications.php">View all</a></li>
                        </ul>
                    </div>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Browse Content -->
        <div class="container-fluid p-4">
            <!-- Filters (same as before) -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card filter-card">
                        <div class="card-body">
                            <form method="GET" action="browse.php" class="row g-3">
                                <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                                <?php if($show_all): ?>
                                <input type="hidden" name="show_all" value="1">
                                <?php endif; ?>
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Search items...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['CategoryID']; ?>" <?php echo $category_filter == $category['CategoryID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Min Price</label>
                                    <input type="number" class="form-control" name="price_min" value="<?php echo htmlspecialchars($price_min); ?>" placeholder="₱0" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Max Price</label>
                                    <input type="number" class="form-control" name="price_max" value="<?php echo htmlspecialchars($price_max); ?>" placeholder="₱10000" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Sort By</label>
                                    <select class="form-select" name="sort">
                                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Toggle and Results Summary -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h6 class="mb-0 me-3">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> items</h6>
                            <div class="btn-group" role="group">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" 
                                   class="btn view-toggle <?php echo $view_mode == 'grid' ? 'active' : ''; ?>">
                                    <i class="fas fa-th"></i> Grid
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'table'])); ?>" 
                                   class="btn view-toggle <?php echo $view_mode == 'table' ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i> Table
                                </a>
                            </div>
                        </div>
                        <?php if($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">Previous</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if($view_mode == 'table'): ?>
            <!-- Table View -->
            <div class="table-view">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Owner</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Price</th>
                                <th>Type</th>
                                <th>Availability</th>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Images</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($products)): ?>
                            <tr>
                                <td colspan="14" class="text-center py-4">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No items found</h5>
                                    <p class="text-muted">Try adjusting your search filters or browse all categories.</p>
                                    <?php if(!$show_all && $total_all_products > 0): ?>
                                    <a href="?show_all=1" class="btn btn-warning">Show All Products</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="position-relative d-inline-block">
                                            <?php if($product['MainImage']): ?>
                                                <img src="../<?php echo htmlspecialchars($product['MainImage']); ?>" 
                                                     class="product-image-small" 
                                                     alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>"
                                                     onclick="showImageGallery(<?php echo $product['ProductID']; ?>)"
                                                     onerror="this.src='../assets/images/no-image.jpg'">
                                                <?php if($product['total_images'] > 1): ?>
                                                <span class="position-absolute top-0 end-0 badge bg-primary rounded-pill" style="font-size: 0.6em; transform: translate(50%, -50%);">
                                                    <?php echo $product['total_images']; ?>
                                                </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="product-image-small no-image-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['Prod_Name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['Prod_Description'], 0, 50)) . (strlen($product['Prod_Description']) > 50 ? '...' : ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['Prod_Brand'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($product['Cat_Name']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['Owner_Name']); ?></td>
                                    <td>
                                        <?php if(!empty($product['FullAddress'])): ?>
                                            <div class="small">
                                                <i class="fas fa-map-marker-alt text-primary"></i> 
                                                <?php echo htmlspecialchars($product['FullAddress']); ?>
                                            </div>
                                            <?php if($product['PL_PickupAvailable'] == 1 || $product['PL_DeliveryAvailable'] == 1): ?>
                                            <div class="mt-1">
                                                <?php if($product['PL_PickupAvailable'] == 1): ?>
                                                <span class="badge bg-success me-1" style="font-size: 0.7em;"><i class="fas fa-hand-holding"></i> Pickup</span>
                                                <?php endif; ?>
                                                <?php if($product['PL_DeliveryAvailable'] == 1): ?>
                                                <span class="badge bg-info" style="font-size: 0.7em;"><i class="fas fa-truck"></i> Delivery
                                                <?php if($product['PL_DeliveryRadius'] > 0): ?>
                                                (<?php echo $product['PL_DeliveryRadius']; ?>km)
                                                <?php endif; ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No location set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['Prod_Condition'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td class="price-display">₱<?php echo number_format($product['Prod_RentalPrice'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['Prod_PriceType'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($product['Prod_Availability'] ?? 'unknown'); ?>">
                                            <?php echo htmlspecialchars($product['Prod_Availability'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($product['Prod_Status'] ?? 'unknown'); ?>">
                                            <?php echo htmlspecialchars($product['Prod_Status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge availability-<?php echo strtolower(str_replace(' ', '-', $product['AvailabilityStatus'])); ?>">
                                            <?php echo htmlspecialchars($product['AvailabilityStatus']); ?>
                                        </span>
                                        <?php if($product['PA_DateFrom']): ?>
                                        <br><small class="text-muted">
                                            <?php echo date('M j', strtotime($product['PA_DateFrom'])); ?> - 
                                            <?php echo date('M j', strtotime($product['PA_DateTo'])); ?>
                                        </small>
                                        <button class="btn btn-sm btn-outline-info ms-1" onclick="showAvailabilityDetails(<?php echo $product['ProductID']; ?>)">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-images"></i> <?php echo $product['total_images']; ?>
                                        </span>
                                        <?php if($product['total_images'] > 0): ?>
                                        <button class="btn btn-sm btn-outline-primary ms-1" onclick="showImageGallery(<?php echo $product['ProductID']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm me-1 <?php echo $product['is_favorited'] > 0 ? 'btn-danger' : 'btn-outline-danger'; ?>" 
                                                onclick="toggleFavorite(<?php echo $product['ProductID']; ?>, this)"
                                                title="Add to Favorites">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                        <button class="btn btn-sm book-btn <?php echo $product['AvailabilityStatus'] != 'Available' ? 'disabled' : ''; ?>" 
                                                onclick="bookProduct(<?php echo $product['ProductID']; ?>)"
                                                <?php echo $product['AvailabilityStatus'] != 'Available' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <!-- Grid View -->
            <div class="row">
                <?php if(empty($products)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No items found</h5>
                        <p class="text-muted">Try adjusting your search filters or browse all categories.</p>
                        <?php if(!$show_all && $total_all_products > 0): ?>
                        <a href="?show_all=1" class="btn btn-warning me-2">Show All Products</a>
                        <?php endif; ?>
                        <a href="browse.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card product-card h-100 d-flex flex-column"
                             data-security-deposit="<?php echo $product['Prod_SecurityDeposit'] ?? 0; ?>"
                             data-delivery-available="<?php echo $product['PL_DeliveryAvailable'] ?? 0; ?>"
                             data-delivery-fee="<?php echo $product['PL_DeliveryFee'] ?? 0; ?>">
                            <div class="position-relative flex-shrink-0">
                                <?php 
                                $product_imgs = isset($product_images[$product['ProductID']]) ? $product_images[$product['ProductID']] : [];
                                ?>
                                
                                <?php if(!empty($product_imgs)): ?>
                                <!-- Image Carousel -->
                                <div id="carousel<?php echo $product['ProductID']; ?>" class="carousel slide product-carousel" data-bs-ride="carousel" data-bs-interval="3000">
                                    <div class="carousel-inner">
                                        <?php foreach($product_imgs as $index => $image): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="../<?php echo htmlspecialchars($image['PI_ImagePath']); ?>" 
                                                 class="card-img-top product-image" 
                                                 alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>"
                                                 onerror="this.src='../assets/images/no-image.jpg'">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if(count($product_imgs) > 1): ?>
                                    <!-- Image Indicators -->
                                    <div class="carousel-indicators">
                                        <?php foreach($product_imgs as $index => $image): ?>
                                        <button type="button" data-bs-target="#carousel<?php echo $product['ProductID']; ?>" 
                                                data-bs-slide-to="<?php echo $index; ?>" 
                                                <?php echo $index === 0 ? 'class="active"' : ''; ?>></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                    <div class="card-img-top product-image no-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <button class="favorite-btn <?php echo $product['is_favorited'] > 0 ? 'favorited' : ''; ?>" 
                                        onclick="toggleFavorite(<?php echo $product['ProductID']; ?>, this)">
                                    <i class="fas fa-heart"></i>
                                </button>
                                
                                <!-- Availability Status Indicator -->
                                <div class="availability-indicator availability-<?php echo strtolower(str_replace(' ', '-', $product['AvailabilityStatus'])); ?>">
                                    <?php echo htmlspecialchars($product['AvailabilityStatus']); ?>
                                </div>
                            </div>
                            <div class="card-body p-3 d-flex flex-column">
                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                                <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars(substr($product['Prod_Description'], 0, 60)) . (strlen($product['Prod_Description']) > 60 ? '...' : ''); ?></p>
                                
                                <div class="product-meta mb-3 small flex-grow-1">
                                    <div class="mb-1"><i class="fas fa-tag text-muted me-1"></i><?php echo htmlspecialchars($product['Cat_Name']); ?></div>
                                    <div class="mb-1"><i class="fas fa-user text-muted me-1"></i><?php echo htmlspecialchars($product['Owner_Name']); ?></div>
                                    <?php if(!empty($product['FullAddress'])): ?>
                                    <div class="mb-1"><i class="fas fa-map-marker-alt text-muted me-1"></i><?php echo htmlspecialchars(substr($product['FullAddress'], 0, 30)) . (strlen($product['FullAddress']) > 30 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                    <?php if($product['PL_PickupAvailable'] == 1 || $product['PL_DeliveryAvailable'] == 1): ?>
                                    <div class="mb-1">
                                        <?php if($product['PL_PickupAvailable'] == 1): ?>
                                        <span class="badge bg-success me-1" style="font-size: 0.7em;">Pickup</span>
                                        <?php endif; ?>
                                        <?php if($product['PL_DeliveryAvailable'] == 1): ?>
                                        <span class="badge bg-info" style="font-size: 0.7em;">Delivery</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($show_all): ?>
                                    <div><i class="fas fa-info-circle"></i> Status: <?php echo htmlspecialchars($product['Prod_Status'] ?? 'N/A'); ?></div>
                                    <div><i class="fas fa-check-circle"></i> Availability: <?php echo htmlspecialchars($product['Prod_Availability'] ?? 'N/A'); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="price-display">
                                        <span class="fw-bold text-primary">₱<?php echo number_format($product['Prod_RentalPrice'], 0); ?></span>
                                        <small class="text-muted">/<?php echo $product['Prod_PriceType'] ?? 'day'; ?></small>
                                    </div>
                                    <button class="btn book-btn btn-sm <?php echo $product['AvailabilityStatus'] != 'Available' ? 'disabled' : ''; ?>" 
                                            onclick="bookProduct(<?php echo $product['ProductID']; ?>)"
                                            <?php echo $product['AvailabilityStatus'] != 'Available' ? 'disabled' : ''; ?>>
                                        <?php echo $product['AvailabilityStatus'] == 'Available' ? 'Book' : 'N/A'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Pagination (same as before) -->
            <?php if($total_pages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Availability Details Modal -->
    <div class="modal fade" id="availabilityModal" tabindex="-1" aria-labelledby="availabilityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="availabilityModalLabel">Availability Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="availabilityContent" class="availability-timeline">
                        <!-- Availability details will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Gallery Modal (same as before) -->
    <div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-labelledby="imageGalleryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageGalleryModalLabel">Product Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="imageGalleryContent" class="image-gallery">
                        <!-- Images will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingModalLabel">Book Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bookingForm" method="POST" action="">
                    <div class="modal-body">
                        <div id="productInfo" class="mb-4 p-3 bg-light rounded">
                            <!-- Product details will be loaded here -->
                        </div>
                        
                        <!-- Booking Details -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold mb-3"><i class="fas fa-calendar-alt me-2"></i>Rental Period</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="rental_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="rental_start_date" name="rental_start_date" required>
                                <div class="form-text">When do you want to start renting?</div>
                            </div>
                            <div class="col-md-6">
                                <label for="rental_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="rental_end_date" name="rental_end_date" required>
                                <div class="form-text">When will you return the item?</div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label for="rental_duration" class="form-label">Duration</label>
                                <input type="text" class="form-control bg-light" id="rental_duration" name="rental_duration" readonly>
                            </div>
                            <div class="col-md-5">
                                <label for="total_amount" class="form-label">Total Amount</label>
                                <div class="bg-light p-3 rounded border">
                                    <div class="fw-bold text-primary fs-5" id="total_amount_display">₱0</div>
                                    <div class="text-muted small" id="amount_breakdown" style="display: none;"></div>
                                </div>
                                <input type="hidden" id="total_amount" name="total_amount">
                            </div>
                            <div class="col-md-4">
                                <label for="pickup_delivery" class="form-label">Pickup/Delivery <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_delivery" name="pickup_delivery" required>
                                    <option value="">Choose option...</option>
                                    <option value="pickup">I'll pickup the item</option>
                                    <option value="delivery">Request delivery</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="special_instructions" class="form-label">Special Instructions</label>
                            <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3" placeholder="Any special requests, preferred pickup time, delivery address details, etc..."></textarea>
                            <div class="form-text">Optional: Add any special requirements or instructions</div>
                        </div>
                        
                        <!-- Renter Contact Details -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold mb-3"><i class="fas fa-user me-2"></i>Your Contact Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="renter_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="renter_name" name="renter_name" required 
                                       value="<?php echo htmlspecialchars($current_user['User_Name'] ?? ''); ?>">
                                <div class="form-text">Your full name for the booking</div>
                            </div>
                            <div class="col-md-6">
                                <label for="renter_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="renter_phone" name="renter_phone" required 
                                       pattern="[0-9]{11}" placeholder="09XXXXXXXXX"
                                       value="<?php echo htmlspecialchars($current_user['User_Phone'] ?? ''); ?>">
                                <div class="form-text">11-digit mobile number</div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="renter_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="renter_email" name="renter_email" required
                                       value="<?php echo htmlspecialchars($current_user['User_Email'] ?? ''); ?>">
                                <div class="form-text">For booking confirmations</div>
                            </div>
                            <div class="col-md-6">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       pattern="[0-9]{11}" placeholder="09XXXXXXXXX">
                                <div class="form-text">Optional: Alternative contact number</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            
                            <?php if (!empty($user_addresses)): ?>
                            <!-- Saved Addresses Selection -->
                            <div class="mb-3">
                                <label for="saved_addresses" class="form-label">Choose from saved addresses:</label>
                                <select class="form-select" id="saved_addresses" onchange="populateAddress(this.value)">
                                    <option value="">Select a saved address...</option>
                                    <?php foreach ($user_addresses as $address): ?>
                                        <?php
                                        $address_parts = array_filter([
                                            $address['UA_Street'],
                                            $address['UA_Barangay'],
                                            $address['UA_City'],
                                            $address['UA_Province'],
                                            $address['UA_ZipCode']
                                        ]);
                                        $formatted_address = implode(', ', $address_parts);
                                        $address_label = $address['UA_AddressType'] ? $address['UA_AddressType'] : 'Address';
                                        if ($address['UA_IsDefault']) $address_label .= ' (Default)';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($formatted_address); ?>" 
                                                <?php echo $address['UA_IsDefault'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($address_label . ': ' . $formatted_address); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="custom">Enter custom address...</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Address Textarea -->
                            <div>
                                <label for="renter_address" class="form-label">Complete Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="renter_address" name="renter_address" rows="2" required 
                                          placeholder="House/Unit No., Street, Barangay, City, Province"></textarea>
                                <div class="form-text">Full address for pickup/delivery coordination</div>
                            </div>
                        </div>
                        
                        <!-- Payment Details -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold mb-3"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Preferred Payment Method <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="form-check payment-option">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_gcash" value="GCash" required>
                                            <label class="form-check-label" for="payment_gcash">
                                                <i class="fas fa-mobile-alt me-1"></i>GCash
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="form-check payment-option">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_maya" value="Maya" required>
                                            <label class="form-check-label" for="payment_maya">
                                                <i class="fas fa-mobile-alt me-1"></i>Maya
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="form-check payment-option">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_bank" value="Bank Transfer" required>
                                            <label class="form-check-label" for="payment_bank">
                                                <i class="fas fa-university me-1"></i>Bank Transfer
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 col-12">
                                        <div class="form-check payment-option">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="Cash" required>
                                            <label class="form-check-label" for="payment_cash">
                                                <i class="fas fa-money-bill me-1"></i>Cash
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">Choose your preferred payment method</div>
                            </div>
                        </div>
                        
                        <div id="digital_payment_details" class="row mb-4" style="display: none;">
                            <div class="col-12 mb-2">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Payment Instructions:</strong> After booking confirmation, you'll receive payment details from the owner.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="payment_account_name" class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" id="payment_account_name" name="payment_account_name" 
                                       placeholder="Your name as it appears on your account">
                                <div class="form-text">Name registered to your payment account</div>
                            </div>
                            <div class="col-md-6">
                                <label for="payment_account_number" class="form-label">Account Number/Mobile</label>
                                <input type="text" class="form-control" id="payment_account_number" name="payment_account_number" 
                                       placeholder="Account number or mobile number">
                                <div class="form-text">Your payment account identifier</div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms_agreement" name="terms_agreement" required>
                                    <label class="form-check-label" for="terms_agreement">
                                        I agree to the <a href="#" class="text-primary">Terms and Conditions</a> and <a href="#" class="text-primary">Rental Agreement</a> <span class="text-danger">*</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="product_id" name="product_id">
                        <input type="hidden" id="owner_id" name="owner_id">
                        <input type="hidden" id="rental_price" name="rental_price">
                        <input type="hidden" id="price_type" name="price_type">
                        <input type="hidden" id="security_deposit" name="security_deposit">
                        <input type="hidden" id="delivery_available" name="delivery_available">
                        <input type="hidden" id="delivery_fee" name="delivery_fee">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Booking Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-pause carousel on hover
        document.addEventListener('DOMContentLoaded', function() {
            const carousels = document.querySelectorAll('.product-carousel');
            carousels.forEach(function(carousel) {
                const carouselInstance = new bootstrap.Carousel(carousel, {
                    interval: 3000,
                    ride: 'carousel'
                });
                
                // Pause on hover, resume on mouse leave
                carousel.addEventListener('mouseenter', function() {
                    carouselInstance.pause();
                });
                
                carousel.addEventListener('mouseleave', function() {
                    carouselInstance.cycle();
                });
            });
        });
        
        // Store product images and availability data
        const productImages = <?php echo json_encode($product_images); ?>;
        const productAvailability = <?php echo json_encode($product_availability); ?>;
        
        function showAvailabilityDetails(productId) {
            const availability = productAvailability[productId] || [];
            const availabilityContent = document.getElementById('availabilityContent');
            
            if (availability.length === 0) {
                availabilityContent.innerHTML = '<p class="text-center text-muted">No availability schedule found for this product.</p>';
            } else {
                availabilityContent.innerHTML = availability.map((item, index) => {
                    const isCurrentRecord = index === 0; // Latest record is first
                    const statusClass = item.PA_IsAvailable == 1 ? 'success' : 'danger';
                    const statusText = item.PA_IsAvailable == 1 ? 'Available' : 'Unavailable';
                    const dateFrom = new Date(item.PA_DateFrom).toLocaleDateString();
                    const dateTo = new Date(item.PA_DateTo).toLocaleDateString();
                    const createdAt = new Date(item.PA_CreatedAt).toLocaleDateString();
                    
                    return `
                        <div class="availability-item ${isCurrentRecord ? 'availability-current' : ''}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <span class="badge bg-${statusClass}">${statusText}</span>
                                        ${isCurrentRecord ? '<span class="badge bg-primary ms-2">Current</span>' : ''}
                                    </h6>
                                    <p class="mb-1">
                                        <strong>Period:</strong> ${dateFrom} - ${dateTo}
                                    </p>
                                    ${item.PA_Reason ? `<p class="mb-1"><strong>Reason:</strong> ${item.PA_Reason}</p>` : ''}
                                    <small class="text-muted">Updated: ${createdAt}</small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            const modal = new bootstrap.Modal(document.getElementById('availabilityModal'));
            modal.show();
        }
        
        function showImageGallery(productId) {
            const images = productImages[productId] || [];
            const galleryContent = document.getElementById('imageGalleryContent');
            
            if (images.length === 0) {
                galleryContent.innerHTML = '<p class="text-center text-muted">No images available for this product.</p>';
            } else {
                galleryContent.innerHTML = images.map(image => `
                    <img src="../${image.PI_ImagePath}" 
                         class="gallery-image ${image.PI_IsMain == 1 ? 'main-gallery-image' : ''}" 
                         alt="Product Image"
                         onclick="enlargeImage('../${image.PI_ImagePath}')"
                         onerror="this.src='../assets/images/no-image.jpg'">
                `).join('');
            }
            
            const modal = new bootstrap.Modal(document.getElementById('imageGalleryModal'));
            modal.show();
        }
        
        function enlargeImage(imageSrc) {
            // Create a new modal for enlarged image
            const enlargeModal = document.createElement('div');
            enlargeModal.className = 'modal fade';
            enlargeModal.innerHTML = `
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Product Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageSrc}" class="img-fluid" alt="Enlarged Product Image">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(enlargeModal);
            const modal = new bootstrap.Modal(enlargeModal);
            modal.show();
            
            // Remove modal from DOM when hidden
            enlargeModal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(enlargeModal);
            });
        }
        
        function toggleFavorite(productId, button) {
            // Disable button during request to prevent double-clicks
            button.disabled = true;
            
            fetch('../api/toggle-favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button appearance
                    button.classList.toggle('favorited');
                    
                    // For table view buttons
                    if (button.classList.contains('btn-outline-danger')) {
                        button.classList.remove('btn-outline-danger');
                        button.classList.add('btn-danger');
                    } else if (button.classList.contains('btn-danger')) {
                        button.classList.remove('btn-danger');
                        button.classList.add('btn-outline-danger');
                    }
                    
                    // Show success message
                    const action = data.action === 'added' ? 'added to' : 'removed from';
                    showToast(`Product ${action} favorites!`, 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                // Re-enable button
                button.disabled = false;
            });
        }
        
        // Simple toast notification function
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 3000);
        }

        function bookProduct(productId) {
            // Find product data from the page
            const productCard = document.querySelector(`[onclick="bookProduct(${productId})"]`).closest('.card, tr');
            let productName, productPrice, priceType, ownerName, ownerId, securityDeposit, deliveryAvailable, deliveryFee;
            
            if (productCard.classList.contains('card')) {
                // Grid view
                productName = productCard.querySelector('.card-title').textContent;
                const priceText = productCard.querySelector('.price-display .fw-bold').textContent.replace('₱', '').replace(',', '');
                productPrice = parseFloat(priceText);
                priceType = productCard.querySelector('.price-display small').textContent.replace('/', '');
                ownerName = productCard.querySelector('.fa-user').parentElement.textContent.trim();
                
                // Get additional data from data attributes
                securityDeposit = parseFloat(productCard.dataset.securityDeposit || 0);
                deliveryAvailable = parseInt(productCard.dataset.deliveryAvailable || 0);
                deliveryFee = parseFloat(productCard.dataset.deliveryFee || 0);
            } else {
                // Table view
                const cells = productCard.querySelectorAll('td');
                productName = cells[1].querySelector('strong').textContent;
                const priceText = cells[6].textContent.replace('₱', '').replace(',', '');
                productPrice = parseFloat(priceText);
                priceType = cells[7].textContent;
                ownerName = cells[4].textContent;
                
                // For table view, we'll need to get these from data attributes too
                // (assuming table rows also have these attributes)
                securityDeposit = 0; // TODO: Add data attributes to table rows
                deliveryAvailable = 0;
                deliveryFee = 0;
            }
            
            // Populate modal
            document.getElementById('product_id').value = productId;
            document.getElementById('rental_price').value = productPrice;
            document.getElementById('price_type').value = priceType;
            document.getElementById('security_deposit').value = securityDeposit;
            document.getElementById('delivery_available').value = deliveryAvailable;
            document.getElementById('delivery_fee').value = deliveryFee;
            
            // Show product info in modal
            document.getElementById('productInfo').innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="mb-1">${productName}</h6>
                        <p class="text-muted mb-1">Owner: ${ownerName}</p>
                        ${securityDeposit > 0 ? `<p class="text-muted mb-1">Security Deposit: ₱${securityDeposit.toLocaleString()}</p>` : ''}
                        ${deliveryAvailable == 1 ? `<p class="text-muted mb-1">Delivery Available: ₱${deliveryFee.toLocaleString()}</p>` : '<p class="text-muted mb-1">Pickup Only</p>'}
                    </div>
                    <div class="col-md-4 text-end">
                        <h6 class="text-primary">₱${productPrice.toLocaleString()}/${priceType}</h6>
                    </div>
                </div>
            `;
            
            // Set minimum date to today
            setMinimumDateTime();
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
            modal.show();
        }
        
        // Calculate rental duration and total amount
        document.getElementById('rental_start_date').addEventListener('change', calculateTotal);
        document.getElementById('rental_end_date').addEventListener('change', calculateTotal);
        document.addEventListener('change', function(e) {
            if (e.target.name === 'pickup_delivery') {
                calculateTotal();
            }
        });
        
        function calculateTotal() {
            const startDate = new Date(document.getElementById('rental_start_date').value);
            const endDate = new Date(document.getElementById('rental_end_date').value);
            const price = parseFloat(document.getElementById('rental_price').value);
            const priceType = document.getElementById('price_type').value;
            const securityDeposit = parseFloat(document.getElementById('security_deposit').value || 0);
            const deliveryFee = parseFloat(document.getElementById('delivery_fee').value || 0);
            const deliverySelected = document.querySelector('input[name="pickup_delivery"]:checked')?.value === 'delivery';
            
            if (startDate && endDate && endDate >= startDate) {
                let duration;
                let multiplier;
                
                if (priceType.toLowerCase().includes('day')) {
                    const timeDiff = endDate.getTime() - startDate.getTime();
                    duration = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // Include both start and end date
                    multiplier = duration;
                    document.getElementById('rental_duration').value = duration + ' day(s)';
                } else if (priceType.toLowerCase().includes('hour')) {
                    const timeDiff = endDate.getTime() - startDate.getTime();
                    duration = Math.ceil(timeDiff / (1000 * 3600));
                    multiplier = duration;
                    document.getElementById('rental_duration').value = duration + ' hour(s)';
                } else {
                    multiplier = 1;
                    document.getElementById('rental_duration').value = '1 unit';
                }
                
                const rentalAmount = price * multiplier;
                const deliveryAmount = deliverySelected ? deliveryFee : 0;
                const totalAmount = rentalAmount + securityDeposit + deliveryAmount;
                
                // Update the total amount display with breakdown
                const totalDisplay = document.getElementById('total_amount_display');
                const breakdownDisplay = document.getElementById('amount_breakdown');
                const hiddenTotal = document.getElementById('total_amount');
                
                if (totalDisplay) {
                    totalDisplay.textContent = `₱${totalAmount.toLocaleString()}`;
                    hiddenTotal.value = totalAmount;
                    
                    // Show breakdown if there are additional fees
                    if (securityDeposit > 0 || deliveryAmount > 0) {
                        let breakdown = `Rental: ₱${rentalAmount.toLocaleString()}`;
                        if (securityDeposit > 0) breakdown += ` + Deposit: ₱${securityDeposit.toLocaleString()}`;
                        if (deliveryAmount > 0) breakdown += ` + Delivery: ₱${deliveryAmount.toLocaleString()}`;
                        
                        breakdownDisplay.textContent = breakdown;
                        breakdownDisplay.style.display = 'block';
                    } else {
                        breakdownDisplay.style.display = 'none';
                    }
                }
            } else if (endDate < startDate) {
                alert('End date must be after start date');
                document.getElementById('rental_end_date').value = '';
            }
        }
        
        // Set minimum datetime to current time
        function setMinimumDateTime() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const minDateTime = now.toISOString().slice(0, 16);
            document.getElementById('rental_start_date').min = minDateTime;
            document.getElementById('rental_end_date').min = minDateTime;
        }
        
        // Phone number validation
        document.getElementById('renter_phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });
        
        document.getElementById('emergency_contact').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });
        
        // Function to populate address from saved addresses dropdown
        function populateAddress(selectedValue) {
            const addressTextarea = document.getElementById('renter_address');
            if (selectedValue === 'custom') {
                addressTextarea.value = '';
                addressTextarea.focus();
            } else if (selectedValue) {
                addressTextarea.value = selectedValue;
            }
        }
        
        // Auto-populate address on page load if default is selected
        document.addEventListener('DOMContentLoaded', function() {
            const savedAddressSelect = document.getElementById('saved_addresses');
            if (savedAddressSelect && savedAddressSelect.value) {
                populateAddress(savedAddressSelect.value);
            }
        });
        
        // Show/hide payment details based on payment method
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const digitalPaymentDiv = document.getElementById('digital_payment_details');
                const accountNameInput = document.getElementById('payment_account_name');
                const accountNumberInput = document.getElementById('payment_account_number');
                
                if (this.value === 'Cash') {
                    digitalPaymentDiv.style.display = 'none';
                    accountNameInput.required = false;
                    accountNumberInput.required = false;
                } else {
                    digitalPaymentDiv.style.display = 'block';
                    accountNameInput.required = true;
                    accountNumberInput.required = true;
                }
            });
        });
        
        // Handle booking form submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_booking');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Booking request submitted successfully! Please wait for the owner to approve your request.');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                    modal.hide();
                    // Reset form
                    document.getElementById('bookingForm').reset();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    </script>
</body>
</html>