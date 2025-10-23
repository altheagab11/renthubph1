<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both Renter/Owner
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
// Get current user information
$user_query = "SELECT User_Name, User_Email, User_Phone, User_IsVerified FROM user_accounts WHERE UserID = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
// Get unread notifications for the renter
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);
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
        
        // Check for date conflicts with existing bookings (excluding cancelled bookings)
        $conflict_query = "SELECT COUNT(*) as conflict_count FROM bookings 
                          WHERE ProductID = ? 
                          AND Book_Status NOT IN ('Cancelled', 'Completed')
                          AND (
                              (Book_StartDate <= ? AND Book_EndDate >= ?) OR
                              (Book_StartDate <= ? AND Book_EndDate >= ?) OR
                              (Book_StartDate >= ? AND Book_EndDate <= ?)
                          )";
        $conflict_stmt = $conn->prepare($conflict_query);
        $conflict_stmt->execute([
            $_POST['product_id'],
            $_POST['rental_start_date'], $_POST['rental_start_date'], // Check if start date conflicts
            $_POST['rental_end_date'], $_POST['rental_end_date'],     // Check if end date conflicts
            $_POST['rental_start_date'], $_POST['rental_end_date']    // Check if booking is within existing range
        ]);
        $conflict_result = $conflict_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conflict_result['conflict_count'] > 0) {
            throw new Exception("The selected dates are already booked. Please choose different dates.");
        }
        
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
            $security_deposit,
            $delivery_fee,
            $pickup_type,
            $booking_notes
        ]);
       
        if ($booking_result) {
            // Create notification for owner
            $notif_query = "INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_RelatedID, Not_IsRead, Not_CreatedAt) VALUES (?, ?, ?, ?, ?, 0, NOW())";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_title = 'New Booking Request';
            $notif_message = 'You have a new booking request for your product: ' . htmlspecialchars($product['Prod_Name']) . '.';
            $notif_stmt->execute([
                $product['OwnerID'],
                'booking',
                $notif_title,
                $notif_message,
                $_POST['product_id']
            ]);

            // Conversation logic: create if it doesn't exist yet
            $check_conv_query = "SELECT ConversationID FROM conversations WHERE ((User1ID = ? AND User2ID = ?) OR (User1ID = ? AND User2ID = ?)) AND ProductID = ?";
            $check_conv_stmt = $conn->prepare($check_conv_query);
            $check_conv_stmt->execute([
                $user_id, $product['OwnerID'],
                $product['OwnerID'], $user_id,
                $_POST['product_id']
            ]);
            if (!$check_conv_stmt->fetch()) {
                // No conversation exists, create it
                $create_conv_stmt = $conn->prepare("INSERT INTO conversations (User1ID, User2ID, ProductID) VALUES (?, ?, ?)");
                $create_conv_stmt->execute([$user_id, $product['OwnerID'], $_POST['product_id']]);
            }

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
// Always use grid view
$view_mode = 'grid';
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
// Exclude user's own products (owners shouldn't see their own items when browsing to rent)
$where_conditions[] = "p.OwnerID != ?";
$params[] = $user_id;
// Exclude products that the user has already booked and booking is not completed or cancelled
$where_conditions[] = "p.ProductID NOT IN (
    SELECT b.ProductID FROM bookings b
    WHERE b.RenterID = ? AND b.Book_Status NOT IN ('Completed', 'Cancelled')
)";
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
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;
// Always hide deleted/inactive products from renters
$where_conditions[] = "p.Prod_Status = 'Active'";
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
// Get products with main image and availability status, and owner status
$query = "SELECT p.*,
                      COALESCE(c.Cat_Name, 'Uncategorized') as Cat_Name,
                      COALESCE(u.User_Name, 'Unknown Owner') as Owner_Name,
                      u.User_Status as Owner_Status,
                      main_img.PI_ImagePath as MainImage,
                      (SELECT COUNT(*) FROM favorites f WHERE f.ProductID = p.ProductID AND f.UserID = ?) as is_favorited,
                      (SELECT COUNT(*) FROM product_images pi WHERE pi.ProductID = p.ProductID) as total_images,
                      (SELECT COUNT(*) FROM bookings WHERE ProductID = p.ProductID) as booking_count,
                      (SELECT AVG(Rev_Rating) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as avg_rating,
                      (SELECT COUNT(*) FROM flag_reports fr WHERE fr.ProductID = p.ProductID AND fr.FlagType = 'product') as product_flag_count,
                      (SELECT COUNT(*) FROM flag_reports fr WHERE fr.OwnerID = p.OwnerID AND fr.FlagType = 'owner') as owner_flag_count,
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
// DEBUG: Output fetched products for troubleshooting
// DEBUG: Output all filters applied for troubleshooting
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
        
        /* Top Navigation Styles */
        .navbar.sticky-top {
            background-color: #fff !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e9ecef;
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
        .booked-dates-warning {
            border-left: 4px solid #ff6b6b;
            background-color: #fff5f5;
            border-color: #ff6b6b;
            font-size: 0.9em;
        }
        
        .booked-dates-warning .text-danger {
            font-weight: 600;
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

        .product-card .d-flex.justify-content-between.align-items-center.gap-2.w-100 > .btn {
        min-width: 0;
        flex: 1 1 0;
        margin: 0;
        border-radius: 20px !important;
        font-size: 0.95em;
        white-space: nowrap;
    }

    /* SweetAlert2 Custom Styles */
    .custom-swal-popup {
        border-radius: 15px;
    }
    
    .swal2-popup {
        border-radius: 15px !important;
        font-family: inherit;
    }
    
    .swal2-title {
        color: #667eea !important;
        font-weight: 600;
    }
    
    .swal2-content {
        color: #6c757d;
    }
    
    .swal2-confirm {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
        border-radius: 8px !important;
        padding: 0.5rem 1.5rem !important;
        font-weight: 600 !important;
    }
    
    .swal2-confirm:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
    }

    /* Flag Indicator Styles */
    .flag-indicator {
        position: absolute;
        bottom: 8px;
        left: 8px;
        background: rgba(220, 53, 69, 0.95);
        color: white;
        padding: 4px 8px;
        border-radius: 15px;
        font-size: 0.65em;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        z-index: 5;
        max-width: calc(100% - 50px);
        white-space: nowrap;
    }
    
    /* Owner flagged positioning - moves up if no product flag */
    .flag-indicator.owner-flagged.has-product-flag {
        background: rgba(255, 193, 7, 0.95);
        color: #212529;
        bottom: 38px;
        left: 8px;
    }
    
    .flag-indicator.owner-flagged.no-product-flag {
        background: rgba(255, 193, 7, 0.95);
        color: #212529;
        bottom: 8px;
        left: 8px;
    }
    
    .flag-indicator .flag-text {
        font-size: 1em;
        font-weight: 600;
    }
    
    .flag-indicator .flag-count {
        font-size: 0.9em;
        font-weight: bold;
        opacity: 0.9;
    }
    
    .flag-indicator i {
        font-size: 0.9em;
    }
    
    /* Grid view flag positioning */
    .card .flag-indicator {
        position: absolute;
        bottom: 8px;
        left: 8px;
    }
    
    .card .flag-indicator.owner-flagged.has-product-flag {
        bottom: 38px;
        left: 8px;
    }
    
    .card .flag-indicator.owner-flagged.no-product-flag {
        bottom: 8px;
        left: 8px;
    }
    
    /* Favorite button positioning adjustment */
    .favorite-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 6;
    }
    
    /* Availability indicator positioning adjustment */
    .availability-indicator {
        position: absolute;
        top: 8px;
        left: 8px;
        z-index: 4;
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
        <!-- Top Navigation (same as before) -->
            <nav class="navbar navbar-expand-lg navbar-light sticky-top">
                <div class="container-fluid">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary d-md-none me-3" type="button" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h5 class="mb-0">
                            <i class="fas fa-search text-primary me-2"></i>Browse Available Items
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
                                    <button type="submit" class="btn w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
                                
                                <!-- Flag Indicators -->
                                <?php if($product['product_flag_count'] > 0): ?>
                                <div class="flag-indicator product-flagged" title="This product has been flagged <?php echo $product['product_flag_count']; ?> time(s)">
                                    <i class="fas fa-flag"></i>
                                    <span class="flag-text">Product Flagged</span>
                                    <span class="flag-count">(<?php echo $product['product_flag_count']; ?>)</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($product['owner_flag_count'] > 0): ?>
                                <div class="flag-indicator owner-flagged <?php echo $product['product_flag_count'] > 0 ? 'has-product-flag' : 'no-product-flag'; ?>" title="This owner has been flagged <?php echo $product['owner_flag_count']; ?> time(s)">
                                    <i class="fas fa-user-times"></i>
                                    <span class="flag-text">Owner Flagged</span>
                                    <span class="flag-count">(<?php echo $product['owner_flag_count']; ?>)</span>
                                </div>
                                <?php endif; ?>
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
                                    <div class="mb-1">
                                        <span class="text-warning"><i class="fas fa-star"></i> <?php echo $product['avg_rating'] ? number_format($product['avg_rating'], 1) : 'N/A'; ?></span>
                                        <span class="text-muted ms-2"><?php echo $product['booking_count']; ?> bookings</span>
                                    </div>
                                    <?php if($show_all): ?>
                                    <div>
                                        <i class="fas fa-info-circle"></i> Status: 
                                        <?php
                                            $final_status = $product['Prod_Status'];
                                            if (isset($product['Owner_Status']) && strtolower($product['Owner_Status']) === 'inactive') {
                                                $final_status = 'Inactive';
                                            }
                                            $badge_class = strtolower($final_status) === 'active' ? 'success' : 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($final_status); ?>
                                        </span>
                                    </div>
                                    <div><i class="fas fa-check-circle"></i> Availability: <?php echo htmlspecialchars($product['Prod_Availability'] ?? 'N/A'); ?></div>
                                    <?php endif; ?>
                                </div>
                               
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="w-100">
                                        <div class="price-display mb-2">
                                            <span class="fw-bold text-primary">₱<?php echo number_format($product['Prod_RentalPrice'], 0); ?></span>
                                            <small class="text-muted">/<?php echo $product['Prod_PriceType'] ?? 'day'; ?></small>
                                            <?php if($product['Prod_SecurityDeposit'] > 0): ?>
                                                <br><span class="security-deposit-badge mt-2 d-inline-block">Security Deposit: ₱<?php echo number_format($product['Prod_SecurityDeposit'], 0); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center gap-2 w-100">
                                            <button class="btn view-details-btn flex-fill" onclick="viewProductDetails(<?php echo $product['ProductID']; ?>)"><i class="fas fa-eye"></i> View Details</button>
                                            <button class="btn flag-btn flex-fill" onclick="openFlagModal(<?php echo $product['ProductID']; ?>, <?php echo $product['OwnerID']; ?>)"><i class="fas fa-flag"></i> Flag</button>
                                            <button class="btn book-btn flex-fill <?php echo $product['AvailabilityStatus'] != 'Available' ? 'disabled' : ''; ?>"
                                                onclick="bookProduct(<?php echo $product['ProductID']; ?>)"
                                                data-pickup-available="<?php echo $product['PL_PickupAvailable'] ?? 1; ?>"
                                                data-delivery-available="<?php echo $product['PL_DeliveryAvailable'] ?? 0; ?>"
                                                <?php echo $product['AvailabilityStatus'] != 'Available' ? 'disabled' : ''; ?> >
                                                <?php echo $product['AvailabilityStatus'] == 'Available' ? 'Book' : 'N/A'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
    <!-- Waiver Modal -->
    <!-- Flag Modal -->
    <div class="modal fade" id="flagModal" tabindex="-1" aria-labelledby="flagModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="flagModalLabel">Flag Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="flagForm">
                    <div class="modal-body">
                        <input type="hidden" id="flag_product_id" name="product_id">
                        <input type="hidden" id="flag_owner_id" name="owner_id">
                        <div class="mb-3">
                            <label class="form-label">What do you want to flag?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="flag_type" id="flag_type_product" value="product" required>
                                <label class="form-check-label" for="flag_type_product">Product</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="flag_type" id="flag_type_owner" value="owner" required>
                                <label class="form-check-label" for="flag_type_owner">Owner</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="flag_reason" class="form-label">Reason</label>
                            <textarea class="form-control" id="flag_reason" name="reason" rows="3" required placeholder="Describe your reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit Flag</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Product Details Modal -->
    <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productDetailsModalLabel">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary">Product Information</h6>
                            <div class="mb-3">
                                <label class="fw-bold">Product Name:</label>
                                <p id="detailsProductName" class="mb-1">-</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Description:</label>
                                <p id="detailsProductDescription" class="mb-1">-</p>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label class="fw-bold">Brand:</label>
                                        <p id="detailsProductBrand" class="mb-1">-</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label class="fw-bold">Category:</label>
                                        <p id="detailsProductCategory" class="mb-1">-</p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label class="fw-bold">Condition:</label>
                                        <p id="detailsProductCondition" class="mb-1">-</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label class="fw-bold">Price:</label>
                                        <p id="detailsProductPrice" class="mb-1 text-primary fw-bold">-</p>
                                        <small class="text-muted">Per <span id="detailsPriceType">-</span></small>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Security Deposit:</label>
                                <p id="detailsSecurityDeposit" class="mb-1 text-warning fw-bold">-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary">Owner & Location</h6>
                            <div class="mb-3">
                                <label class="fw-bold">Owner:</label>
                                <p id="detailsOwnerName" class="mb-1">-</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Location:</label>
                                <p id="detailsLocation" class="mb-1">-</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Delivery Options:</label>
                                <p id="detailsDeliveryOptions" class="mb-1">-</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Availability:</label>
                                <p id="detailsAvailability" class="mb-1">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="modalBookButton">
                        <i class="fas fa-calendar-check"></i> Book This Item
                    </button>
                </div>
            </div>
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
                                <div id="availability-status" class="alert alert-info" style="display: none;">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span id="availability-text"></span>
                                </div>
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
                        
                        <!-- Quick Date Suggestions -->
                        <div class="row mb-3" id="date-suggestions" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-info py-2">
                                    <small><strong>Quick suggestions:</strong></small>
                                    <div class="mt-1" id="suggested-dates"></div>
                                </div>
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
                                    <?php if (!empty($product) && isset($location)) { ?>
                                        <?php if (!empty($location['PL_PickupAvailable'])) { ?>
                                            <option value="pickup">I'll pickup the item</option>
                                        <?php } ?>
                                        <?php if (!empty($location['PL_DeliveryAvailable'])) { ?>
                                            <option value="delivery">Request delivery</option>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <option value="pickup">I'll pickup the item</option>
                                        <option value="delivery">Request delivery</option>
                                    <?php } ?>
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
                       
                        <!-- Payment account fields removed: will only show at payment completion step -->
                       
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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Flag modal logic
    function openFlagModal(productId, ownerId) {
        document.getElementById('flag_product_id').value = productId;
        document.getElementById('flag_owner_id').value = ownerId;
        document.getElementById('flagForm').reset();
        var flagModal = new bootstrap.Modal(document.getElementById('flagModal'));
        flagModal.show();
    }

    document.getElementById('flagForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('../api/flag-report.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Report Submitted',
                    text: 'Flag report submitted successfully.',
                    confirmButtonColor: '#667eea'
                });
                bootstrap.Modal.getInstance(document.getElementById('flagModal')).hide();
            } else {
                // Show the actual error from backend
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error: ' + data.message,
                    confirmButtonColor: '#667eea'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred: ' + error,
                confirmButtonColor: '#667eea'
            });
        });
    });
window.renterIsVerified = <?php echo isset($current_user['User_IsVerified']) && $current_user['User_IsVerified'] == 1 ? 'true' : 'false'; ?>;
</script>

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
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to load product details: ' + (data.message || 'Unknown error'),
                            confirmButtonColor: '#667eea'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load product details. Please try again.',
                        confirmButtonColor: '#667eea'
                    });
                });
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

        // Modified bookProduct function to show waiver modal first
        function bookProduct(productId) {

            if (typeof window.renterIsVerified !== 'undefined' && window.renterIsVerified === false) {
                // Show a modal if you have one, or use SweetAlert as fallback
                if (document.getElementById('notVerifiedModal')) {
                    var notVerifiedModal = new bootstrap.Modal(document.getElementById('notVerifiedModal'));
                    notVerifiedModal.show();
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Account Not Verified',
                        text: 'Your account is not yet verified. You cannot book until your account is verified by the admin.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#667eea',
                        customClass: {
                            popup: 'custom-swal-popup'
                        }
                    });
                }
                return;
            }

            if (typeof window.renterHasAddress !== 'undefined' && window.renterHasAddress === false) {
    var noAddressModal = new bootstrap.Modal(document.getElementById('noAddressModal'));
    noAddressModal.show();
    return;
}
            // Find product data from the page
            const productCard = document.querySelector(`[onclick="bookProduct(${productId})"]`).closest('.card, tr');
            let productName, productPrice, priceType, ownerName, ownerId, securityDeposit, deliveryAvailable, deliveryFee, pickupAvailable;

            if (productCard.classList.contains('card')) {
                // Grid view
                productName = productCard.querySelector('.card-title').textContent;
                const priceText = productCard.querySelector('.price-display .fw-bold').textContent.replace('₱', '').replace(',', '');
                productPrice = parseFloat(priceText);
                priceType = productCard.querySelector('.price-display small').textContent.replace('/', '');
                ownerName = productCard.querySelector('.fa-user').parentElement.textContent.trim();
                securityDeposit = parseFloat(productCard.dataset.securityDeposit || 0);
                deliveryAvailable = parseInt(productCard.dataset.deliveryAvailable || 0);
                deliveryFee = parseFloat(productCard.dataset.deliveryFee || 0);
                pickupAvailable = productCard.getAttribute('data-pickup-available') !== null ? parseInt(productCard.getAttribute('data-pickup-available')) : 1;
            }

            // Store product data in sessionStorage to use after waiver agreement
            sessionStorage.setItem('bookingProduct', JSON.stringify({
                productId,
                productName,
                productPrice,
                priceType,
                ownerName,
                securityDeposit,
                deliveryAvailable,
                deliveryFee,
                pickupAvailable
            }));

            // Show waiver modal
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

                // Load booked dates for this product
                loadBookedDates(productData.productId);

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
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Date Range',
                    text: 'End date must be after start date',
                    confirmButtonColor: '#667eea'
                });
                document.getElementById('rental_end_date').value = '';
            }
        }
       
        // Store booked dates globally
        let bookedDatesArray = [];
        
        // Load booked dates for a specific product
        function loadBookedDates(productId) {
            fetch(`../api/get-booked-dates.php?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bookedDatesArray = data.booked_dates;
                        updateDateInputs();
                        addBookedDatesInfo();
                    } else {
                        console.error('Failed to load booked dates:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading booked dates:', error);
                });
        }
        
        // Function to check if a date is booked
        function isDateBooked(date) {
            const dateStr = date.toISOString().split('T')[0];
            return bookedDatesArray.includes(dateStr);
        }
        
        // Function to check if date range conflicts with booked dates
        function hasDateConflict(startDate, endDate) {
            const current = new Date(startDate);
            while (current <= endDate) {
                if (isDateBooked(current)) {
                    return true;
                }
                current.setDate(current.getDate() + 1);
            }
            return false;
        }
        
        // Add visual indication of booked dates
        function addBookedDatesInfo() {
            const availabilityStatus = document.getElementById('availability-status');
            const availabilityText = document.getElementById('availability-text');
            
            if (bookedDatesArray.length > 0) {
                availabilityStatus.className = 'alert alert-warning';
                availabilityStatus.style.display = 'block';
                
                const formattedDates = bookedDatesArray
                    .map(date => new Date(date).toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric' 
                    }))
                    .join(', ');
                
                availabilityText.innerHTML = `
                    <strong>Unavailable Dates:</strong> ${formattedDates}
                    <br><small>Please select different dates for your booking.</small>
                `;
            } else {
                availabilityStatus.className = 'alert alert-success';
                availabilityStatus.style.display = 'block';
                availabilityText.innerHTML = `
                    <strong>Great!</strong> This item is available for booking.
                    <br><small>Select your preferred dates below.</small>
                `;
            }
        }
        
        // Update date inputs with validation
        function updateDateInputs() {
            const startDateInput = document.getElementById('rental_start_date');
            const endDateInput = document.getElementById('rental_end_date');
            
            // Add change listeners for validation
            startDateInput.addEventListener('change', function() {
                validateDateSelection();
                updateDateAvailabilityFeedback();
            });
            endDateInput.addEventListener('change', function() {
                validateDateSelection();
                updateDateAvailabilityFeedback();
            });
        }
        
        // Real-time feedback for date selection
        function updateDateAvailabilityFeedback() {
            const startDateInput = document.getElementById('rental_start_date');
            const endDateInput = document.getElementById('rental_end_date');
            
            // Remove existing feedback
            const existingFeedback = document.querySelector('.date-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
            
            if (startDateInput.value && endDateInput.value) {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                // Check if dates are valid (end after start)
                if (endDate >= startDate) {
                    const conflictDates = [];
                    const current = new Date(startDate);
                    while (current <= endDate) {
                        if (isDateBooked(current)) {
                            conflictDates.push(new Date(current));
                        }
                        current.setDate(current.getDate() + 1);
                    }
                    
                    // Only show feedback if there are conflicts
                    if (conflictDates.length > 0) {
                        const feedbackDiv = document.createElement('div');
                        feedbackDiv.className = 'date-feedback mt-2';
                        
                        const conflictDatesFormatted = conflictDates.map(date => 
                            date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
                        ).join(', ');
                        
                        feedbackDiv.innerHTML = `
                            <div class="alert alert-danger py-2">
                                <i class="fas fa-times-circle me-2"></i>
                                <strong>Conflict:</strong> ${conflictDatesFormatted} already booked.
                            </div>
                        `;
                        
                        endDateInput.parentElement.appendChild(feedbackDiv);
                    }
                    
                    // Show suggestions if there are conflicts
                    if (conflictDates.length > 0) {
                        showDateSuggestions();
                    } else {
                        hideDataSuggestions();
                    }
                } else {
                    hideDataSuggestions();
                }
            }
        }
        
        // Show available date suggestions
        function showDateSuggestions() {
            const suggestionsDiv = document.getElementById('date-suggestions');
            const suggestedDatesDiv = document.getElementById('suggested-dates');
            
            // Find next 3 available periods
            const suggestions = findAvailableDateSuggestions();
            
            if (suggestions.length > 0) {
                suggestedDatesDiv.innerHTML = suggestions.map(suggestion => 
                    `<button type="button" class="btn btn-outline-primary btn-sm me-2 mb-1" 
                             onclick="applySuggestedDates('${suggestion.start}', '${suggestion.end}')">
                        ${suggestion.display}
                    </button>`
                ).join('');
                
                suggestionsDiv.style.display = 'block';
            }
        }
        
        // Hide date suggestions
        function hideDataSuggestions() {
            const suggestionsDiv = document.getElementById('date-suggestions');
            suggestionsDiv.style.display = 'none';
        }
        
        // Find available date periods
        function findAvailableDateSuggestions() {
            const suggestions = [];
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let i = 0; i < 30 && suggestions.length < 3; i++) {
                const checkDate = new Date(today);
                checkDate.setDate(today.getDate() + i);
                
                if (!isDateBooked(checkDate)) {
                    // Check if we can get at least 1-2 days from this date
                    const endDate1Day = new Date(checkDate);
                    endDate1Day.setDate(checkDate.getDate() + 1);
                    
                    const endDate2Day = new Date(checkDate);
                    endDate2Day.setDate(checkDate.getDate() + 2);
                    
                    if (!isDateBooked(endDate1Day)) {
                        const endToUse = !isDateBooked(endDate2Day) ? endDate2Day : endDate1Day;
                        const startFormatted = checkDate.toISOString().slice(0, 16);
                        const endFormatted = endToUse.toISOString().slice(0, 16);
                        
                        suggestions.push({
                            start: startFormatted,
                            end: endFormatted,
                            display: `${checkDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endToUse.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`
                        });
                        
                        // Skip ahead to avoid overlapping suggestions
                        i += 2;
                    }
                }
            }
            
            return suggestions;
        }
        
        // Apply suggested dates
        function applySuggestedDates(startDateTime, endDateTime) {
            document.getElementById('rental_start_date').value = startDateTime;
            document.getElementById('rental_end_date').value = endDateTime;
            
            // Trigger validation and calculation
            validateDateSelection();
            updateDateAvailabilityFeedback();
            calculateTotal();
            
            hideDataSuggestions();
        }
        
        // Validate date selection against booked dates
        function validateDateSelection() {
            const startDateInput = document.getElementById('rental_start_date');
            const endDateInput = document.getElementById('rental_end_date');
            
            if (startDateInput.value && endDateInput.value) {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                // Check if end date is before start date
                if (endDate < startDate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Date Range',
                        text: 'End date must be after start date.',
                        confirmButtonColor: '#667eea'
                    });
                    endDateInput.value = '';
                    return false;
                }
                
                // Check for booking conflicts
                const conflictDates = [];
                const current = new Date(startDate);
                while (current <= endDate) {
                    if (isDateBooked(current)) {
                        conflictDates.push(new Date(current).toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric' 
                        }));
                    }
                    current.setDate(current.getDate() + 1);
                }
                
                if (conflictDates.length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Date Conflict Detected',
                        html: `The following dates in your selection are already booked:<br><br>
                               <strong class="text-danger">${conflictDates.join(', ')}</strong><br><br>
                               Please choose different dates for your booking.`,
                        confirmButtonColor: '#667eea',
                        confirmButtonText: 'Choose Different Dates'
                    });
                    
                    // Clear both inputs to force user to select new dates
                    startDateInput.value = '';
                    endDateInput.value = '';
                    
                    return false;
                }
            }
            return true;
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
            
            // Validate dates before submission
            if (!validateDateSelection()) {
                return;
            }
           
            const formData = new FormData(this);
            formData.append('action', 'create_booking');
           
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Booking Submitted!',
                        text: 'Booking request submitted successfully! Please wait for the owner to approve your request.',
                        confirmButtonColor: '#667eea'
                    });
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                    modal.hide();
                    // Reset form
                    document.getElementById('bookingForm').reset();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Booking Error',
                        text: 'Error: ' + data.message,
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#667eea'
                });
            });
        });
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

<!-- Modal for no address -->
<div class="modal fade" id="noAddressModal" tabindex="-1" aria-labelledby="noAddressModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="noAddressModalLabel">No Address Found</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>You need to add an address to your profile before you can book a product.</p>
        <a href="../renter/profile.php" class="btn btn-primary">Go to Profile & Add Address</a>
      </div>
    </div>
  </div>
</div>

<script>
window.renterHasAddress = <?php echo !empty($user_addresses) ? 'true' : 'false'; ?>;
window.renterIsVerified = <?php echo isset($current_user['User_IsVerified']) && $current_user['User_IsVerified'] == 1 ? 'true' : 'false'; ?>;
</script>

</body>
</html>