<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
// Get unread notifications for the renter
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

// Get current user information
$user_query = "SELECT User_Name, User_Email, User_Phone, User_IsVerified FROM user_accounts WHERE UserID = ?";
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
        
        // Get product location details
        $location_query = "SELECT PL_DeliveryFee, PL_PickupAvailable, PL_DeliveryAvailable FROM product_locations WHERE ProductID = ?";
        $location_stmt = $conn->prepare($location_query);
        $location_stmt->execute([$_POST['product_id']]);
        $location = $location_stmt->fetch(PDO::FETCH_ASSOC);
        
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
        
        // Set delivery fee and pickup type based on user selection
        $pickup_type = 'Pickup'; // Default
        $delivery_fee = 0;
        
        if (isset($_POST['pickup_delivery'])) {
            $pickup_delivery = strtolower(trim($_POST['pickup_delivery']));
            if ($pickup_delivery === 'delivery') {
                $pickup_type = 'Delivery';
                $delivery_fee = $location['PL_DeliveryFee'] ?? 0;
            } elseif ($pickup_delivery === 'pickup') {
                $pickup_type = 'Pickup';
                $delivery_fee = 0;
            }
        }
        
        $total_amount = $rental_amount + $security_deposit + $delivery_fee;
        
        // Insert booking record
        $booking_query = "INSERT INTO bookings (
            ProductID, RenterID, OwnerID,
            Book_StartDate, Book_EndDate, Book_TotalAmount,
            Book_SecurityDeposit, Book_DeliveryFee, Book_PickupType,
            Book_Status, Book_Notes
        ) VALUES (
            ?, ?, ?,
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
            'renter_address' => $_POST['renter_address'],
            'emergency_contact' => $_POST['emergency_contact'] ?? '',
            'special_instructions' => $_POST['special_instructions'] ?? ''
        ]);
        
        $booking_stmt = $conn->prepare($booking_query);
        $booking_result = $booking_stmt->execute([
            $_POST['product_id'],
            $user_id,
            $product['OwnerID'],
            $_POST['rental_start_date'],
            $_POST['rental_end_date'],
            $total_amount,
            $security_deposit,
            $delivery_fee,
            $pickup_type,
            $booking_notes
        ]);
        
        if ($booking_result) {
            echo json_encode(['success' => true, 'message' => 'Booking request submitted successfully']);
        } else {
            throw new Exception("Failed to create booking");
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle favorite actions
if ($_POST) {
    if (isset($_POST['remove_favorite'])) {
        $product_id = $_POST['product_id'];
        
        $query = "DELETE FROM favorites WHERE UserID = ? AND ProductID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product removed from favorites!";
            $message_type = "success";
        } else {
            $message = "Failed to remove from favorites. Please try again.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['clear_all_favorites'])) {
        $query = "DELETE FROM favorites WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        
        if ($stmt->execute()) {
            $message = "All favorites cleared successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to clear favorites. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$price_filter = isset($_GET['price']) ? $_GET['price'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["f.UserID = ?", "p.Prod_Status = 'Active'", "p.Prod_Availability = 1"];
$params = [$user_id];

if ($category_filter) {
    $conditions[] = "p.CategoryID = ?";
    $params[] = $category_filter;
}

if ($price_filter) {
    switch($price_filter) {
        case 'under_500':
            $conditions[] = "p.Prod_RentalPrice < 500";
            break;
        case '500_1000':
            $conditions[] = "p.Prod_RentalPrice BETWEEN 500 AND 1000";
            break;
        case '1000_5000':
            $conditions[] = "p.Prod_RentalPrice BETWEEN 1000 AND 5000";
            break;
        case 'over_5000':
            $conditions[] = "p.Prod_RentalPrice > 5000";
            break;
    }
}

// Sort options
$sort_options = [
    'newest' => 'f.Fav_AddedAt DESC',
    'oldest' => 'f.Fav_AddedAt ASC',
    'price_low' => 'p.Prod_RentalPrice ASC',
    'price_high' => 'p.Prod_RentalPrice DESC',
    'name_asc' => 'p.Prod_Name ASC',
    'popular' => 'booking_count DESC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'f.Fav_AddedAt DESC';

// Get favorites with product details
$query = "SELECT p.*, pi.PI_ImagePath, u.User_Name as Owner_Name, ua.UA_City, ua.UA_Province,
          c.Cat_Name, f.Fav_AddedAt as Favorited_At,
          (SELECT COUNT(*) FROM bookings WHERE ProductID = p.ProductID) as booking_count,
          (SELECT AVG(Rev_Rating) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as avg_rating,
          (SELECT COUNT(*) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as review_count,
          pl.PL_PickupAvailable, pl.PL_DeliveryAvailable, pl.PL_DeliveryFee,
          CONCAT_WS(', ', 
                NULLIF(ua.UA_Street, ''), 
                NULLIF(ua.UA_Barangay, ''), 
                NULLIF(ua.UA_City, ''), 
                NULLIF(ua.UA_Province, '')
          ) as FullAddress
          FROM favorites f
          JOIN products p ON f.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON p.OwnerID = u.UserID
          LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
          LEFT JOIN categories c ON p.CategoryID = c.CategoryID
          LEFT JOIN product_locations pl ON p.ProductID = pl.ProductID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$query = "SELECT DISTINCT c.* FROM categories c
          JOIN products p ON c.CategoryID = p.CategoryID
          JOIN favorites f ON p.ProductID = f.ProductID
          WHERE f.UserID = ? AND p.Prod_Status = 'Active'
          ORDER BY c.Cat_Name";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total favorites
$stats['total_favorites'] = count($favorites);

// Total value of favorited items
$total_value = 0;
foreach($favorites as $favorite) {
    $total_value += $favorite['Prod_RentalPrice'];
}
$stats['total_value'] = $total_value;

// Average price
$stats['avg_price'] = $stats['total_favorites'] > 0 ? $total_value / $stats['total_favorites'] : 0;

// Most expensive item
$most_expensive = 0;
foreach($favorites as $favorite) {
    if($favorite['Prod_RentalPrice'] > $most_expensive) {
        $most_expensive = $favorite['Prod_RentalPrice'];
    }
}
$stats['most_expensive'] = $most_expensive;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <link href="../css/renter-theme.css" rel="stylesheet">
    <style>
        :root {
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--secondary-gradient);
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
            opacity: 1 !important;
            filter: none !important;
            transform: none !important;
            transition: none !important;
        }
        
        .stat-card.total { background: var(--secondary-gradient); color: white; }
        .stat-card.value { background: var(--secondary-gradient); color: white; }
        .stat-card.average { background: var(--secondary-gradient); color: white; }
        .stat-card.expensive { background: var(--secondary-gradient); color: white; }
        
        .favorite-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            border-left: 4px solid #f093fb;
        }
        .favorite-card:hover {
            transform: translateY(-10px) !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
        }
        
        .favorite-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--secondary-gradient);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            animation: heartbeat 2s ease-in-out infinite;
        }
        
        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .product-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .favorite-details {
            background: rgba(240, 147, 251, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #f093fb;
            margin: 1rem 0;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1rem;
        }
        
        .price-tag {
            background: var(--secondary-gradient);
            color: white;
            border-radius: 15px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .action-btn {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            margin: 0.25rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .action-btn.remove { background: var(--secondary-gradient); color: white; }
        .action-btn.book { background: var(--secondary-gradient); color: white; }

        /* Waiver Modal Styles */
        .waiver-modal .modal-content {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .waiver-modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .waiver-modal .modal-body {
            max-height: 500px;
            overflow-y: auto;
        }

        .waiver-modal .form-check-label {
            cursor: pointer;
        }

        .waiver-modal .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .waiver-modal .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Booking Modal Styles */
        .modal-lg {
            max-width: 900px;
        }

        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        .form-label {
            font-weight: 500;
            color: #333;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

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

        #productInfo {
            border-left: 4px solid #667eea;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 500;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 500;
        }
        
        .navbar {
            border-bottom: 1px solid #e9ecef;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .category-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .owner-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            margin-right: 0.75rem;
        }
        
        .favorited-date {
            background: rgba(240, 147, 251, 0.1);
            color: #f093fb;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .bulk-actions {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #f093fb;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .search-filters, .bulk-actions {
                padding: 1rem;
            }
            
            .favorite-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
                    <a class="nav-link" href="browse.php">
                        <i class="fas fa-search me-2"></i> Browse Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> My Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="favorites.php">
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
                <li class="nav-item mt-3">
                    <hr class="text-white-50">
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
        <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light sticky-top">
                <div class="container-fluid">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary d-md-none me-3" type="button" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h5 class="mb-0">
                            <i class="fas fa-heart text-danger me-2"></i>My Favorites
                        </h5>
                    </div>
                    <div class="navbar-nav ms-auto d-flex flex-row">
                            <div class="nav-item dropdown me-3">
                                <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-bell"></i>
                                    <span id="notifCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                        <?php echo $notif_count; ?>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header">Notifications</h6></li>
                                    <?php if ($unread_notifications && count($unread_notifications) > 0): ?>
                                        <?php foreach ($unread_notifications as $notif): ?>
                                            <li>
                                                <a class="dropdown-item" href="notifications.php">
                                                    <i class="fas fa-info-circle text-primary me-2"></i>
                                                    <strong><?php echo htmlspecialchars($notif['Not_Title']); ?></strong><br>
                                                    <span class="text-muted small"> <?php echo $notif['Not_Message']; ?> </span><br>
                                                    <small class="text-muted d-block"> <?php echo date('M j, Y \a\t h:i A', strtotime($notif['Not_CreatedAt'])); ?> </small>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><span class="dropdown-item text-muted">No new notifications</span></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="notifications.php">View all notifications</a></li>
                                </ul>
                            </div>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i> <?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

        <!-- Content -->
        <div class="container-fluid p-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 15px;">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card total">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Favorites
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_favorites']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-heart fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card value">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Value
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_value'], 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card average">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Average Price
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['avg_price'], 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card expensive">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Most Expensive
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['most_expensive'], 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-gem fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <?php if(!empty($favorites)): ?>
            <div class="bulk-actions">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-primary">
                            <i class="fas fa-layer-group me-2"></i>Bulk Actions
                        </h6>
                        <p class="text-muted small mb-0">Manage multiple favorites at once</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="selectAll()">
                            <i class="fas fa-check-square me-2"></i>Select All
                        </button>
                        <form method="POST" style="display: inline;">
                            <button type="button" name="clear_all_favorites" class="btn action-btn remove"
                                    onclick="confirmClearAllFavorites()">
                                <i class="fas fa-trash me-2"></i>Clear All
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tags me-2"></i>Filter by Category
                        </label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['CategoryID']; ?>" 
                                        <?php echo $category_filter == $category['CategoryID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-peso-sign me-2"></i>Price Range
                        </label>
                        <select class="form-select" name="price">
                            <option value="">All Prices</option>
                            <option value="under_500" <?php echo $price_filter == 'under_500' ? 'selected' : ''; ?>>Under ₱500</option>
                            <option value="500_1000" <?php echo $price_filter == '500_1000' ? 'selected' : ''; ?>>₱500 - ₱1,000</option>
                            <option value="1000_5000" <?php echo $price_filter == '1000_5000' ? 'selected' : ''; ?>>₱1,000 - ₱5,000</option>
                            <option value="over_5000" <?php echo $price_filter == 'over_5000' ? 'selected' : ''; ?>>Over ₱5,000</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Recently Added</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                            <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Favorites List -->
            <?php if(empty($favorites)): ?>
                <div class="empty-state">
                    <i class="fas fa-heart-broken"></i>
                    <h4 class="text-muted">No favorites yet</h4>
                    <p class="text-muted">Start adding products to your favorites to see them here. Browse our amazing collection to find items you love!</p>
                    <a href="browse.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-search me-2"></i>Browse Products
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($favorites as $favorite): ?>
                <div class="favorite-card card">
                    <div class="favorite-badge">
                        <i class="fas fa-heart"></i>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if (!empty($favorite['PI_ImagePath'])): ?>
                                    <img src="<?php echo '../' . htmlspecialchars($favorite['PI_ImagePath']); ?>" 
                                         class="img-fluid rounded" style="height: 180px; width: 100%; object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($favorite['Prod_Name']); ?>"
                                         onerror="this.src='../assets/images/no-image.jpg'">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-light border rounded" style="height: 180px; width: 100%;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <?php if($favorite['Cat_Name']): ?>
                                        <span class="category-badge me-2"><?php echo htmlspecialchars($favorite['Cat_Name']); ?></span>
                                    <?php endif; ?>
                                    <span class="favorited-date">
                                        <i class="fas fa-heart me-1"></i>
                                        Added <?php echo date('M j, Y', strtotime($favorite['Favorited_At'])); ?>
                                    </span>
                                </div>
                                
                                <h5 class="mb-2"><?php echo htmlspecialchars($favorite['Prod_Name']); ?></h5>
                                <p class="text-muted mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($favorite['Prod_Description']); ?>
                                </p>
                                
                                <div class="product-info">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="owner-avatar">
                                            <?php echo strtoupper(substr($favorite['Owner_Name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($favorite['Owner_Name']); ?></h6>
                                            <?php if($favorite['UA_City']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($favorite['UA_City'] . ', ' . $favorite['UA_Province']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if($favorite['avg_rating']): ?>
                                <div class="favorite-details">
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars me-2">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $favorite['avg_rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="me-3"><?php echo number_format($favorite['avg_rating'], 1); ?>/5.0</span>
                                        <small class="text-muted">
                                            (<?php echo $favorite['review_count']; ?> reviews, <?php echo $favorite['booking_count']; ?> bookings)
                                        </small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="text-end">
                                    <div class="price-tag mb-3">
                                        ₱<?php echo number_format($favorite['Prod_RentalPrice'], 0); ?>
                                        <small>/<?php echo htmlspecialchars($favorite['Prod_PriceType']); ?></small>
                                    </div>
                                    
                                    <div class="d-flex flex-column gap-2">
                                        <button class="btn action-btn book" 
                                                onclick="bookProduct(<?php echo $favorite['ProductID']; ?>)"
                                                data-product-id="<?php echo $favorite['ProductID']; ?>"
                                                data-owner-id="<?php echo $favorite['OwnerID']; ?>"
                                                data-security-deposit="<?php echo $favorite['Prod_SecurityDeposit'] ?? 0; ?>"
                                                data-delivery-available="<?php echo isset($favorite['PL_DeliveryAvailable']) ? $favorite['PL_DeliveryAvailable'] : 0; ?>"
                                                data-delivery-fee="<?php echo isset($favorite['PL_DeliveryFee']) ? $favorite['PL_DeliveryFee'] : 0; ?>"
                                                data-pickup-available="<?php echo isset($favorite['PL_PickupAvailable']) ? $favorite['PL_PickupAvailable'] : 1; ?>">
                                            <i class="fas fa-calendar-plus me-1"></i>Book Now
                                        </button>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="product_id" value="<?php echo $favorite['ProductID']; ?>">
                                            <button type="button" name="remove_favorite" class="btn btn-outline-danger w-100"
                                                    onclick="confirmRemoveFavorite(<?php echo $favorite['ProductID']; ?>, '<?php echo addslashes($favorite['Prod_Name']); ?>')"
                                                    style="border-radius: 15px;">
                                                <i class="fas fa-heart-broken me-1"></i>Remove from Favorites
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-outline-primary btn-sm" style="border-radius: 15px;"
                                                onclick="viewProductDetails(<?php echo $favorite['ProductID']; ?>)">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Available since <?php echo date('M Y', strtotime($favorite['Prod_CreatedAt'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Waiver Modal -->
    <div class="modal fade waiver-modal" id="waiverModal" tabindex="-1" aria-labelledby="waiverModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="waiverModalLabel">Rental Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="waiverContent">
                        <h6>Rental Agreement Terms</h6>
                        <p>
                            By renting this item, you agree to the following terms and conditions:
                        </p>
                        <ul>
                            <li>The renter is responsible for any damage to the item during the rental period.</li>
                            <li>The item must be returned in the same condition as received.</li>
                            <li>A security deposit, if applicable, will be refunded upon satisfactory return of the item.</li>
                            <li>Late returns may incur additional charges as per the owner's policy.</li>
                            <li>The renter agrees to use the item only for its intended purpose.</li>
                            <li>Any disputes will be resolved through RentHub PH's dispute resolution process.</li>
                        </ul>
                        <p>
                            Please read the full <a href="#" class="text-primary">Terms and Conditions</a> and <a href="#" class="text-primary">Privacy Policy</a> for more details.
                        </p>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="waiver_agreement" required>
                            <label class="form-check-label" for="waiver_agreement">
                                I have read and agree to the Rental Agreement <span class="text-danger">*</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="proceedToBooking" disabled>Proceed</button>
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
                                    value="<?php echo htmlspecialchars($current_user['User_Name'] ?? ''); ?>" readonly>
                                <div class="form-text">Your full name for the booking</div>
                            </div>
                            <div class="col-md-6">
                                <label for="renter_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="renter_phone" name="renter_phone" required
                                    pattern="\+?[0-9]{10,15}" placeholder="+639XXXXXXXXX"
                                    value="<?php echo htmlspecialchars($current_user['User_Phone'] ?? ''); ?>" readonly>
                                <div class="form-text">11-digit mobile number</div>
                            </div>
                        </div>
                       
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="renter_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="renter_email" name="renter_email" required
                                    value="<?php echo htmlspecialchars($current_user['User_Email'] ?? ''); ?>" readonly>
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
                       
                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="alert alert-info p-2 mb-2" style="font-size: 0.95em;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Note:</strong> You can only complete your payment after the owner accepts your booking request.
                                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        // SweetAlert Functions
        function confirmClearAllFavorites() {
            Swal.fire({
                title: 'Remove All Favorites?',
                text: 'This will remove all items from your favorites list. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, clear all!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="clear_all_favorites" value="1">';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function confirmRemoveFavorite(productId, productName) {
            Swal.fire({
                title: 'Remove from Favorites?',
                html: `Remove <strong>${productName}</strong> from your favorites?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, remove it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="product_id" value="${productId}">
                        <input type="hidden" name="remove_favorite" value="1">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);

        // Select all functionality
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name="selected_favorites[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            
            updateBulkActions();
        }

        // Update bulk actions based on selection
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name="selected_favorites[]"]:checked');
            const bulkActions = document.querySelector('.bulk-actions');
            
            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
            } else {
                bulkActions.style.display = 'block'; // Always show for "Select All" button
            }
        }

        // Add loading state to action buttons
        document.querySelectorAll('form button').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.type === 'submit' && !this.onclick) {
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
                        this.disabled = true;
                    }, 100);
                }
            });
        });

        // Animate favorite cards on load
        window.addEventListener('load', function() {
            const favoriteCards = document.querySelectorAll('.favorite-card');
            favoriteCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Favorite badge animation on hover
        document.querySelectorAll('.favorite-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.2)';
                this.style.animationPlayState = 'paused';
            });
            
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.animationPlayState = 'running';
            });
        });

        // Price tag hover effect
        document.querySelectorAll('.price-tag').forEach(tag => {
            tag.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            
            tag.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Animate stats on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.opacity = '1';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.transform = 'translateY(20px)';
            card.style.opacity = '0';
            card.style.transition = 'all 0.6s ease';
            statsObserver.observe(card);
        });

        // Booking functionality
        function bookProduct(productId) {
            // Find product data from the page
            const productButton = document.querySelector(`[onclick="bookProduct(${productId})"]`);
            
            if (!productButton) {
                alert('Product not found');
                return;
            }

            // Get data from button attributes
            const ownerId = productButton.getAttribute('data-owner-id');
            const securityDeposit = parseFloat(productButton.getAttribute('data-security-deposit') || 0);
            const deliveryAvailable = parseInt(productButton.getAttribute('data-delivery-available') || 0);
            const deliveryFee = parseFloat(productButton.getAttribute('data-delivery-fee') || 0);
            const pickupAvailable = parseInt(productButton.getAttribute('data-pickup-available') || 1);

            // Find the favorite card containing this button
            const favoriteCard = productButton.closest('.favorite-card');
            if (!favoriteCard) {
                alert('Product information not found');
                return;
            }

            // Extract product information from the card
            const productName = favoriteCard.querySelector('h5').textContent.trim();
            const priceElement = favoriteCard.querySelector('.price-tag');
            const priceText = priceElement.textContent.replace('₱', '').replace(',', '');
            const productPrice = parseFloat(priceText);
            const priceType = priceElement.querySelector('small').textContent.replace('/', '');
            const ownerName = favoriteCard.querySelector('.text-muted').textContent.trim();

            // Store product data in sessionStorage to use after waiver agreement
            sessionStorage.setItem('bookingProduct', JSON.stringify({
                productId,
                productName,
                productPrice,
                priceType,
                ownerName,
                ownerId,
                securityDeposit,
                deliveryAvailable,
                deliveryFee,
                pickupAvailable
            }));

            // Show waiver modal first
            const waiverModal = new bootstrap.Modal(document.getElementById('waiverModal'));
            waiverModal.show();
        }

        // Handle waiver agreement checkbox
        document.getElementById('waiver_agreement').addEventListener('change', function() {
            document.getElementById('proceedToBooking').disabled = !this.checked;
        });

        // Handle Proceed button click
        document.getElementById('proceedToBooking').addEventListener('click', function() {
            if (document.getElementById('waiver_agreement').checked) {
                // Hide waiver modal
                const waiverModal = bootstrap.Modal.getInstance(document.getElementById('waiverModal'));
                waiverModal.hide();

                // Retrieve product data from sessionStorage
                const productData = JSON.parse(sessionStorage.getItem('bookingProduct'));

                // Populate booking modal
                document.getElementById('product_id').value = productData.productId;
                document.getElementById('owner_id').value = productData.ownerId;
                document.getElementById('rental_price').value = productData.productPrice;
                document.getElementById('price_type').value = productData.priceType;
                document.getElementById('security_deposit').value = productData.securityDeposit;
                document.getElementById('delivery_available').value = productData.deliveryAvailable;
                document.getElementById('delivery_fee').value = productData.deliveryFee;

                // Show product info in booking modal
                document.getElementById('productInfo').innerHTML = `
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-1">${productData.productName}</h6>
                            <p class="text-muted mb-1">Owner: ${productData.ownerName}</p>
                            ${productData.securityDeposit > 0 ? `<p class="text-muted mb-1">Security Deposit: ₱${productData.securityDeposit.toLocaleString()}</p>` : ''}
                            ${productData.deliveryAvailable == 1 ? `<p class="text-muted mb-1">Delivery Available: ₱${productData.deliveryFee.toLocaleString()}</p>` : '<p class="text-muted mb-1">Pickup Only</p>'}
                        </div>
                        <div class="col-md-4 text-end">
                            <h6 class="text-primary">₱${productData.productPrice.toLocaleString()}/${productData.priceType}</h6>
                        </div>
                    </div>
                `;

                // Dynamically update Pickup/Delivery dropdown
                const pickupDeliverySelect = document.getElementById('pickup_delivery');
                pickupDeliverySelect.innerHTML = '<option value="">Choose option...</option>';
                if (productData.pickupAvailable == 1) {
                    pickupDeliverySelect.innerHTML += '<option value="pickup">I\'ll pickup the item</option>';
                }
                if (productData.deliveryAvailable == 1) {
                    pickupDeliverySelect.innerHTML += '<option value="delivery">Request delivery</option>';
                }

                // Set minimum date to today
                setMinimumDateTime();

                // Show booking modal
                const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
                bookingModal.show();

                // Clear sessionStorage
                sessionStorage.removeItem('bookingProduct');
            }
        });

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

<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailsModalLabel">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <h5 id="detailsProductName">Loading...</h5>
                        <p class="text-muted mb-2">Category: <span id="detailsProductCategory">-</span></p>
                        <p class="text-muted mb-2">Brand: <span id="detailsProductBrand">-</span></p>
                        <p class="text-muted mb-2">Condition: <span id="detailsProductCondition">-</span></p>
                        
                        <div class="bg-light p-3 rounded mb-3">
                            <h6 class="text-primary mb-1">Rental Price</h6>
                            <h4 class="text-success mb-0">
                                <span id="detailsProductPrice">₱0</span>
                                <small class="text-muted">/<span id="detailsPriceType">day</span></small>
                            </h4>
                            <p class="text-muted mb-0">Security Deposit: <span id="detailsSecurityDeposit">₱0</span></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Owner Information</h6>
                            <p class="text-muted mb-1">Name: <span id="detailsOwnerName">-</span></p>
                            <p class="text-muted mb-1">Location: <span id="detailsLocation">-</span></p>
                            <p class="text-muted mb-1">Delivery Options: <span id="detailsDeliveryOptions">-</span></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Availability</h6>
                            <p class="text-muted mb-0" id="detailsAvailability">-</p>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <p id="detailsProductDescription">Loading...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modalBookButton">Book Now</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewProductDetails(productId) {
    // Fetch product details via AJAX
    fetch('../api/get-product-details.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const product = data.product;
                
                // Populate modal with product details
                document.getElementById('detailsProductName').textContent = product.Prod_Name || 'N/A';
                document.getElementById('detailsProductDescription').textContent = product.Prod_Description || 'No description available';
                document.getElementById('detailsProductBrand').textContent = product.Prod_Brand || 'N/A';
                document.getElementById('detailsProductCategory').textContent = product.Cat_Name || 'Uncategorized';
                document.getElementById('detailsProductCondition').textContent = product.Prod_Condition || 'N/A';
                document.getElementById('detailsProductPrice').textContent = '₱' + parseFloat(product.Prod_RentalPrice || 0).toLocaleString();
                document.getElementById('detailsPriceType').textContent = product.Prod_PriceType || 'N/A';
                document.getElementById('detailsSecurityDeposit').textContent = '₱' + parseFloat(product.Prod_SecurityDeposit || 0).toLocaleString();
                
                // Owner information
                document.getElementById('detailsOwnerName').textContent = product.Owner_Name || 'Unknown';
                
                // Location information
                if (data.location && (data.location.UA_Street || data.location.UA_City)) {
                    const address = [
                        data.location.UA_Street,
                        data.location.UA_Barangay,
                        data.location.UA_City,
                        data.location.UA_Province
                    ].filter(Boolean).join(', ');
                    
                    document.getElementById('detailsLocation').textContent = address || 'No location set';
                    
                    // Delivery/Pickup options
                    let deliveryOptions = [];
                    if (data.location.PL_PickupAvailable == 1) {
                        deliveryOptions.push('Pickup Available');
                    }
                    if (data.location.PL_DeliveryAvailable == 1) {
                        let deliveryText = 'Delivery Available';
                        if (data.location.PL_DeliveryFee && data.location.PL_DeliveryFee > 0) {
                            deliveryText += ` (₱${parseFloat(data.location.PL_DeliveryFee).toLocaleString()})`;
                        }
                        if (data.location.PL_DeliveryRadius && data.location.PL_DeliveryRadius > 0) {
                            deliveryText += ` - ${data.location.PL_DeliveryRadius}km radius`;
                        }
                        deliveryOptions.push(deliveryText);
                    }
                    document.getElementById('detailsDeliveryOptions').textContent = deliveryOptions.length > 0 ? deliveryOptions.join(', ') : 'No delivery options';
                } else {
                    document.getElementById('detailsLocation').textContent = 'No location set';
                    document.getElementById('detailsDeliveryOptions').textContent = 'Pickup Available';
                }
                
                // Availability information
                if (data.availability && data.availability.AvailabilityStatus) {
                    let availabilityText = data.availability.AvailabilityStatus;
                    if (data.availability.PA_DateFrom && data.availability.PA_DateTo) {
                        const fromDate = new Date(data.availability.PA_DateFrom).toLocaleDateString();
                        const toDate = new Date(data.availability.PA_DateTo).toLocaleDateString();
                        availabilityText += ` (${fromDate} - ${toDate})`;
                    }
                    if (data.availability.PA_Reason) {
                        availabilityText += ` - ${data.availability.PA_Reason}`;
                    }
                    document.getElementById('detailsAvailability').textContent = availabilityText;
                } else {
                    document.getElementById('detailsAvailability').textContent = 'Available';
                }
                
                // Update Book button in modal
                const modalBookBtn = document.getElementById('modalBookButton');
                modalBookBtn.onclick = () => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('productDetailsModal'));
                    modal.hide();
                    bookProduct(productId);
                };
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
                modal.show();
                
            } else {
                alert('Failed to load product details: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading product details.');
        });
}

function enlargeImage(imagePath) {
    // Create modal for enlarged image
    const imageModal = `
        <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Product Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${imagePath}" class="img-fluid" alt="Product Image" onerror="this.src='../assets/images/no-image.jpg'">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing image modal if any
    const existingModal = document.getElementById('imageModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', imageModal);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
    
    // Remove modal from DOM when hidden
    document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notifDropdown = document.querySelector('.nav-link.dropdown-toggle[role="button"]');
    notifDropdown?.addEventListener('show.bs.dropdown', function() {
        fetch('../api/mark-notifications-read.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('notifCount').textContent = '0';
                }
            });
    });
});
</script>
</body>
</html>