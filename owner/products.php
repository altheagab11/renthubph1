<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Both Renter/Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get unread notifications for navbar (must be before HTML output)
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count_query = "SELECT COUNT(*) as cnt FROM notifications WHERE UserID = ? AND Not_IsRead = 0";
$notif_count_stmt = $conn->prepare($notif_count_query);
$notif_count_stmt->execute([$user_id]);
$notif_count = $notif_count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;

// Handle product actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_availability':
                $product_id = $_POST['product_id'];
                $current_status = $_POST['current_status'];
                $new_status = $current_status == 1 ? 0 : 1;
                
                $query = "UPDATE products SET Prod_Availability = ? WHERE ProductID = ? AND OwnerID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $new_status);
                $stmt->bindParam(2, $product_id);
                $stmt->bindParam(3, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Product availability updated successfully!";
                    $message_type = "success";
                }
                break;
                
            case 'delete_product':
                $product_id = $_POST['product_id'];
                // Remove featured status as well
                $query = "UPDATE products SET Prod_Status = 'Deleted', Prod_IsFeatured = 0, Prod_FeaturedUntil = NULL WHERE ProductID = ? AND OwnerID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $product_id);
                $stmt->bindParam(2, $user_id);
                if ($stmt->execute()) {
                    $message = "Product deleted successfully!";
                    $message_type = "success";
                }
                break;
                
            case 'feature_product':
                $product_id = $_POST['product_id'];
                // Get current active subscription and featured limit
                $plan_limit = null;
                $query = "SELECT sp.Plan_FeaturedListings FROM user_subscriptions us JOIN subscription_plans sp ON us.PlanID = sp.PlanID WHERE us.UserID = ? AND us.Sub_Status = 'Active' ORDER BY us.Sub_CreatedAt DESC LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $user_id);
                $stmt->execute();
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($plan && isset($plan['Plan_FeaturedListings'])) {
                    $plan_limit = (int)$plan['Plan_FeaturedListings'];
                }
                // Count currently featured products
                $query = "SELECT COUNT(*) as cnt FROM products WHERE OwnerID = ? AND Prod_IsFeatured = 1 AND Prod_FeaturedUntil > NOW() AND Prod_Status = 'Active'";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $user_id);
                $stmt->execute();
                $current_featured = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
                if ($plan_limit !== null && $current_featured >= $plan_limit) {
                    $message = "You have reached your featured listing limit (" . $plan_limit . "). Upgrade your plan or unfeature another product.";
                    $message_type = "danger";
                } else {
                    $featured_until = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $query = "UPDATE products SET Prod_IsFeatured = 1, Prod_FeaturedUntil = ? WHERE ProductID = ? AND OwnerID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $featured_until);
                    $stmt->bindParam(2, $product_id);
                    $stmt->bindParam(3, $user_id);
                    if ($stmt->execute()) {
                        $message = "Product featured successfully!";
                        $message_type = "success";
                    }
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Active';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["p.OwnerID = ?"];
$params = [$user_id];

if ($search) {
    $conditions[] = "(p.Prod_Name LIKE ? OR p.Prod_Description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $conditions[] = "p.CategoryID = ?";
    $params[] = $category_filter;
}

if ($status_filter && $status_filter != 'All') {
    $conditions[] = "p.Prod_Status = ?";
    $params[] = $status_filter;
}

// Sort options
$sort_options = [
    'newest' => 'p.Prod_CreatedAt DESC',
    'oldest' => 'p.Prod_CreatedAt ASC',
    'name_asc' => 'p.Prod_Name ASC',
    'name_desc' => 'p.Prod_Name DESC',
    'price_asc' => 'p.Prod_RentalPrice ASC',
    'price_desc' => 'p.Prod_RentalPrice DESC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'p.Prod_CreatedAt DESC';

// Get products with better image handling
$query = "SELECT p.*, c.Cat_Name, pi.PI_ImagePath,
          (SELECT COUNT(*) FROM bookings WHERE ProductID = p.ProductID) as booking_count,
          (SELECT AVG(Rev_Rating) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as avg_rating,
          pl.PL_PickupAvailable, pl.PL_DeliveryAvailable, pl.PL_DeliveryRadius, pl.PL_DeliveryFee,
          ua.UA_Street, ua.UA_Barangay, ua.UA_City, ua.UA_Province
          FROM products p
          LEFT JOIN categories c ON p.CategoryID = c.CategoryID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          LEFT JOIN product_locations pl ON p.ProductID = pl.ProductID
          LEFT JOIN user_addresses ua ON pl.AddressID = ua.AddressID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all images for each product
foreach ($products as &$product) {
    $image_query = "SELECT PI_ImagePath, PI_IsMain, PI_ImageOrder 
                    FROM product_images 
                    WHERE ProductID = ? 
                    ORDER BY PI_IsMain DESC, PI_ImageOrder ASC";
    $img_stmt = $conn->prepare($image_query);
    $img_stmt->execute([$product['ProductID']]);
    $product['all_images'] = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get categories for filter - FIXED: Get all categories, not just parent categories
$query = "SELECT * FROM categories ORDER BY Cat_Name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ? AND Prod_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Available products
$query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ? AND Prod_Status = 'Active' AND Prod_Availability = 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['available_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Featured products
$query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ? AND Prod_Status = 'Active' AND Prod_IsFeatured = 1 AND Prod_FeaturedUntil > NOW()";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['featured_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--primary-gradient);
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
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { background: var(--primary-gradient); color: white; }
        .stat-card.available { background: var(--primary-gradient); color: white; }
        .stat-card.featured { background: var(--primary-gradient); color: white; }
        .stat-card.bookings { background: var(--primary-gradient); color: white; }
        
        .product-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .product-card .card-img-top {
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .card-img-top {
            transform: scale(1.1);
        }
        
        .product-status {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
        }
        
        .product-badge {
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .product-badge.available { background: #28a745; color: white; }
        .product-badge.unavailable { background: #dc3545; color: white; }
        .product-badge.featured { background: #ffc107; color: #000; }
        .product-badge.inactive { background: #6c757d; color: white; }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .filter-btn {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .filter-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: #11998e;
        }
        
        .action-btn {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        
        .rating-stars {
            color: #ffc107;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .no-image-placeholder {
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3rem;
        }
        
        /* Product Image Carousel */
        .product-card .carousel {
            border-radius: 20px 20px 0 0;
            overflow: hidden;
        }
        
        .product-card .carousel-item img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .product-card .carousel-control-prev,
        .product-card .carousel-control-next {
            width: 40px;
            height: 40px;
            background: rgba(0,0,0,0.6);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .product-card:hover .carousel-control-prev,
        .product-card:hover .carousel-control-next {
            opacity: 1;
        }
        
        .product-card .carousel-control-prev {
            left: 10px;
        }
        
        .product-card .carousel-control-next {
            right: 10px;
        }
        
        .product-card .carousel-control-prev-icon,
        .product-card .carousel-control-next-icon {
            width: 20px;
            height: 20px;
        }
        
        .product-card .carousel-indicators {
            bottom: 10px;
            margin-bottom: 0;
        }
        
        .product-card .carousel-indicators button {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin: 0 3px;
            background-color: rgba(255,255,255,0.5);
            border: none;
        }
        
        .product-card .carousel-indicators button.active {
            background-color: rgba(255,255,255,0.9);
        }

        /* Product Actions Row - Horizontal Button Row */
        .product-actions-row {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: stretch;
            gap: 0.3rem;
            width: 100%;
        }
        .product-actions-row .action-col {
            flex: 1;
            display: flex;
            align-items: stretch;
        }
        .product-actions-row .action-col form {
            width: 100%;
            display: flex;
            align-items: stretch;
            margin: 0 !important;
        }
        .product-actions-row .btn {
            width: 100%;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 36px;
            min-height: 36px;
            max-height: 36px;
            padding: 0 4px;
            font-size: 0.875rem;
        }
        .product-actions-row .btn i {
            font-size: 1rem;
            margin: 0;
            line-height: 1;
        }

        /* Image Gallery Modal */
        #imageGalleryModal .carousel-item img {
            border-radius: 10px;
        }
        
        #imageGalleryModal .carousel-control-prev,
        #imageGalleryModal .carousel-control-next {
            width: 50px;
            height: 50px;
            background: rgba(0,0,0,0.7);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }
        
        #imageGalleryModal .carousel-control-prev {
            left: 20px;
        }
        
        #imageGalleryModal .carousel-control-next {
            right: 20px;
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
            
            .search-filters {
                padding: 1rem;
            }
            
            /* Mobile button adjustments */
            .product-actions-row {
                gap: 0.2rem;
            }
            
            .product-actions-row .btn {
                height: 32px;
                min-height: 32px;
                max-height: 32px;
                padding: 0 2px;
            }
            
            .product-actions-row .btn i {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-1">
                <i class="fas fa-home"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Owner Dashboard</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="products.php">
                        <i class="fas fa-box me-2"></i> My Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add-product.php">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Booking Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="earnings.php">
                        <i class="fas fa-chart-line me-2"></i> Earnings
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
                    <a class="nav-link" href="subscription.php">
                        <i class="fas fa-crown me-2"></i> Subscription
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
                <li class="nav-item">
                    <a class="nav-link" href="../renter/dashboard.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-search me-2"></i> Switch to Renter
                    </a>
                </li>
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
                        <i class="fas fa-box text-success me-2"></i>My Products
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item me-3">
                        <a href="add-product.php" class="btn" style="background: var(--primary-gradient); color: white; border-radius: 25px;">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </a>
                    </div>
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
                                            <span class="text-muted small"> <?php echo htmlspecialchars($notif['Not_Message']); ?> </span>
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
                                        Total Products
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card available">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Available
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['available_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card featured">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Featured
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['featured_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bookings">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search Products
                        </label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or description..." style="border-radius: 15px;">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tags me-2"></i>Category
                        </label>
                        <select class="form-select" name="category" style="border-radius: 15px;">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['CategoryID']; ?>" <?php echo $category_filter == $category['CategoryID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Status
                        </label>
                        <select class="form-select" name="status" style="border-radius: 15px;">
                            <option value="All" <?php echo $status_filter == 'All' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Under Review" <?php echo $status_filter == 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort" style="border-radius: 15px;">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="price_asc" <?php echo $sort_by == 'price_asc' ? 'selected' : ''; ?>>Price (Low-High)</option>
                            <option value="price_desc" <?php echo $sort_by == 'price_desc' ? 'selected' : ''; ?>>Price (High-Low)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn w-100" style="background: var(--primary-gradient); color: white; border-radius: 15px;">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <?php if(empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h4 class="text-muted">No products found</h4>
                    <p class="text-muted">Start by adding your first product to begin earning!</p>
                    <a href="add-product.php" class="btn btn-success btn-lg" style="background: var(--primary-gradient); color: white; border-radius: 25px;">
                        <i class="fas fa-plus me-2"></i>Add Your First Product
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($products as $product): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <?php if(!empty($product['all_images'])): ?>
                                    <!-- Image Carousel -->
                                    <div id="carousel<?php echo $product['ProductID']; ?>" class="carousel slide" data-bs-ride="false">
                                        <div class="carousel-inner">
                                            <?php foreach($product['all_images'] as $index => $image): ?>
                                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                                    <img src="../<?php echo htmlspecialchars($image['PI_ImagePath']); ?>" 
                                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>"
                                                         style="cursor: pointer;"
                                                         onclick="openImageGallery(<?php echo $product['ProductID']; ?>, <?php echo $index; ?>)">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php if(count($product['all_images']) > 1): ?>
                                            <!-- Carousel Controls -->
                                            <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?php echo $product['ProductID']; ?>" data-bs-slide="prev">
                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                <span class="visually-hidden">Previous</span>
                                            </button>
                                            <button class="carousel-control-next" type="button" data-bs-target="#carousel<?php echo $product['ProductID']; ?>" data-bs-slide="next">
                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                <span class="visually-hidden">Next</span>
                                            </button>
                                            
                                            <!-- Image Indicators -->
                                            <div class="carousel-indicators">
                                                <?php foreach($product['all_images'] as $index => $image): ?>
                                                    <button type="button" data-bs-target="#carousel<?php echo $product['ProductID']; ?>" 
                                                            data-bs-slide-to="<?php echo $index; ?>" 
                                                            <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                                            aria-label="Slide <?php echo $index + 1; ?>"></button>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- Image Counter Badge -->
                                            <div class="position-absolute top-0 start-0 m-2">
                                                <span class="badge bg-dark bg-opacity-75" style="border-radius: 15px;">
                                                    <i class="fas fa-images me-1"></i><?php echo count($product['all_images']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Hidden data for JavaScript -->
                                    <script type="application/json" id="product-images-<?php echo $product['ProductID']; ?>">
                                        <?php echo json_encode($product['all_images']); ?>
                                    </script>
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-status">
                                    <?php if($product['Prod_Status'] == 'Inactive'): ?>
                                        <span class="badge product-badge inactive">Inactive</span>
                                    <?php elseif($product['Prod_IsFeatured'] && $product['Prod_FeaturedUntil'] > date('Y-m-d H:i:s')): ?>
                                        <span class="badge product-badge featured">Featured</span>
                                    <?php elseif($product['Prod_Availability']): ?>
                                        <span class="badge product-badge available">Available</span>
                                    <?php else: ?>
                                        <span class="badge product-badge unavailable">Unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <h6 class="card-title mb-2"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['Cat_Name'] ?? 'Uncategorized'); ?>
                                </p>
                                
                                <!-- Address Information -->
                                <?php if($product['UA_City'] || $product['UA_Province']): ?>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-map-marker-alt me-1 text-primary"></i>
                                    <?php 
                                    $address_parts = [];
                                    if($product['UA_Street']) $address_parts[] = $product['UA_Street'];
                                    if($product['UA_Barangay']) $address_parts[] = $product['UA_Barangay'];
                                    if($product['UA_City']) $address_parts[] = $product['UA_City'];
                                    if($product['UA_Province']) $address_parts[] = $product['UA_Province'];
                                    echo htmlspecialchars(implode(', ', $address_parts));
                                    ?>
                                </p>
                                <?php endif; ?>
                                
                                <!-- Delivery/Pickup Options -->
                                <div class="mb-2">
                                    <?php if($product['PL_PickupAvailable']): ?>
                                        <span class="badge bg-success me-1" style="font-size: 0.7rem;">
                                            <i class="fas fa-hand-paper me-1"></i>Pickup
                                        </span>
                                    <?php endif; ?>
                                    <?php if($product['PL_DeliveryAvailable']): ?>
                                        <span class="badge bg-info me-1" style="font-size: 0.7rem;">
                                            <i class="fas fa-truck me-1"></i>Delivery
                                            <?php if($product['PL_DeliveryRadius']): ?>
                                                (<?php echo $product['PL_DeliveryRadius']; ?>km)
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong class="text-success">₱<?php echo number_format($product['Prod_RentalPrice'], 2); ?></strong>
                                        <small class="text-muted">/<?php echo htmlspecialchars($product['Prod_PriceType'] ?? 'Day'); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <?php if($product['avg_rating']): ?>
                                            <div class="rating-stars">
                                                <i class="fas fa-star"></i> <?php echo number_format($product['avg_rating'], 1); ?>
                                            </div>
                                        <?php endif; ?>
                                        <small class="text-muted"><?php echo $product['booking_count']; ?> bookings</small>
                                    </div>
                                </div>
                                
                                <p class="card-text small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($product['Prod_Description']); ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="product-actions-row w-100">
                                        <!-- Edit Button -->
                                        <div class="action-col">
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                        <!-- View Button -->
                                            <div class="action-col">
                                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" title="View Details"
                                                    onclick='showProductDetails(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, "UTF-8"); ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        <!-- Toggle Availability Button (or placeholder) -->
                                        <div class="action-col">
                                            <?php if($product['Prod_Status'] == 'Active'): ?>
                                                <form method="POST" class="m-0">
                                                    <input type="hidden" name="action" value="toggle_availability">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $product['Prod_Availability']; ?>">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm w-100" title="<?php echo $product['Prod_Availability'] ? 'Make Unavailable' : 'Make Available'; ?>">
                                                        <i class="fas fa-<?php echo $product['Prod_Availability'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="d-block" style="visibility:hidden;">&nbsp;</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Feature Button (or placeholder) -->
                                        <div class="action-col">
                                            <?php if($product['Prod_Status'] == 'Active'): ?>
                                                <?php if($product['Prod_IsFeatured'] && $product['Prod_FeaturedUntil'] > date('Y-m-d H:i:s')): ?>
                                                    <button type="button" class="btn btn-outline-warning btn-sm w-100 toggle-featured-btn" data-product-id="<?php echo $product['ProductID']; ?>" data-featured="1" title="Unfeature">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-success btn-sm w-100 toggle-featured-btn" data-product-id="<?php echo $product['ProductID']; ?>" data-featured="0" title="Feature">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="d-block" style="visibility:hidden;">&nbsp;</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Delete Button (or placeholder) -->
                                        <div class="action-col">
                                            <?php if($product['Prod_Status'] == 'Active'): ?>
                                                <button class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                        data-product-id="<?php echo $product['ProductID']; ?>" 
                                                        data-product-name="<?php echo htmlspecialchars($product['Prod_Name']); ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="d-block" style="visibility:hidden;">&nbsp;</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Product Modal -->
        <!-- Product Details Modal -->
        <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="border-radius: 20px;">
                    <div class="modal-header border-0">
                        <h5 class="modal-title" id="productDetailsModalLabel">
                            <i class="fas fa-eye me-2"></i>Product Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="productDetailsBody">
                        <!-- Details will be injected here -->
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
            <!-- Product Edit Modal -->
            <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 20px;">
                        <form id="editProductForm" enctype="multipart/form-data">
                            <div class="modal-header border-0">
                                <h5 class="modal-title" id="editProductModalLabel">
                                    <i class="fas fa-edit me-2"></i>Edit Product
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="editProductBody">
                                <!-- Form fields will be injected here by JS -->
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" id="deleteProductId">
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h5>Are you sure you want to delete this product?</h5>
                            <p class="text-muted">Product: <strong id="deleteProductName"></strong></p>
                            <p class="text-muted">This action cannot be undone.</p>
                        </div>
                        
                        <div class="alert alert-warning" style="border-radius: 15px;">
                            <i class="fas fa-info-circle me-2"></i>
                            Deleting this product will remove it from all listings and cancel any pending bookings.
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Gallery Modal -->
    <div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-labelledby="imageGalleryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="imageGalleryModalLabel">
                        <i class="fas fa-images me-2"></i>Product Images
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="galleryCarousel" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner" id="galleryCarouselInner">
                            <!-- Images will be loaded here dynamically -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
        <script>
        // Toggle featured/unfeatured AJAX
        document.addEventListener('click', function(e) {
            if(e.target.closest('.toggle-featured-btn')) {
                const btn = e.target.closest('.toggle-featured-btn');
                const productId = btn.dataset.productId;
                const isFeatured = btn.dataset.featured == '1';
                btn.disabled = true;
                fetch('../api/toggle-featured.php', {
                    method: 'POST',
                    headers: {},
                    body: new URLSearchParams({
                        product_id: productId,
                        action: isFeatured ? 'unfeature' : 'feature'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        window.location.reload();
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to update featured status',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                        btn.disabled = false;
                    }
                })
                .catch(() => {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update featured status',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                    btn.disabled = false;
                });
            }
        });
        // Save Changes AJAX handler
        document.addEventListener('DOMContentLoaded', function() {
            const editForm = document.getElementById('editProductForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(editForm);

                    // Collect removed images
                    const removedImages = [];
                    document.querySelectorAll('#editProductImages img[data-remove="1"]').forEach(img => {
                        if (img.dataset.imgPath) removedImages.push(img.dataset.imgPath);
                    });
                    formData.append('removed_images', JSON.stringify(removedImages));

                    fetch('../api/update-product.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Product updated successfully!',
                                icon: 'success',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: data.message || 'Failed to update product.',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(() => {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to update product.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                });
            }
        });
            // Open Edit Product Modal and fetch details
            function openEditProductModal(product) {
                // Show loading spinner while fetching
                document.getElementById('editProductBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
                const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
                modal.show();

                // Fetch full product details via AJAX
                fetch('../api/get-owner-address.php?product_id=' + product.ProductID)
                    .then(res => res.json())
                    .then(data => {
                        // Build form fields for all product columns
                        let html = `<input type='hidden' name='ProductID' value='${product.ProductID}'>`;
                        html += `<div class='row'>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Name</label><input type='text' class='form-control' name='Prod_Name' value='${product.Prod_Name ?? ''}' required></div>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Brand</label><input type='text' class='form-control' name='Prod_Brand' value='${product.Prod_Brand ?? ''}'></div>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Model</label><input type='text' class='form-control' name='Prod_Model' value='${product.Prod_Model ?? ''}'></div>`;
                        html += `</div>`;
                        html += `<div class='row'>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Condition</label><select class='form-select' name='Prod_Condition'>` +
                            ["New", "Like New", "Good", "Fair", "Poor"].map(opt => `<option value='${opt}' ${product.Prod_Condition === opt ? "selected" : ""}>${opt}</option>`).join('') +
                            `</select></div>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Category</label><select class='form-select' name='CategoryID' id='editProductCategorySelect'></select></div>`;
                        html += `</div>`;
                        html += `<div class='row'>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Rental Price</label><input type='number' step='0.01' class='form-control' name='Prod_RentalPrice' value='${product.Prod_RentalPrice ?? ''}' required></div>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Price Type</label><input type='text' class='form-control' name='Prod_PriceType' value='${product.Prod_PriceType ?? ''}'></div>`;
                        html += `<div class='col-md-4 mb-3'><label class='form-label'>Security Deposit</label><input type='number' step='0.01' class='form-control' name='Prod_SecurityDeposit' value='${product.Prod_SecurityDeposit ?? ''}'></div>`;
                        html += `</div>`;
                        html += `<div class='row'>`;
                        html += `<div class='col-md-6 mb-3'><label class='form-label'>Min Rental Duration</label><input type='number' class='form-control' name='Prod_MinRentalDuration' value='${product.Prod_MinRentalDuration ?? ''}'></div>`;
                        html += `<div class='col-md-6 mb-3'><label class='form-label'>Max Rental Duration</label><input type='number' class='form-control' name='Prod_MaxRentalDuration' value='${product.Prod_MaxRentalDuration ?? ''}'></div>`;
                        html += `</div>`;
                        // ...availability field removed...
                        html += `<div class='row'>`;
                        html += `<div class='col-md-6 mb-3'><label class='form-label'>Description</label><textarea class='form-control' name='Prod_Description' rows='3'>${product.Prod_Description ?? ''}</textarea></div>`;
                        html += `</div>`;

                        // Product Location section
                        html += `<div class='row'>`;
                        html += `<div class='col-md-6 mb-3'><label class='form-label'>Product Address</label><select class='form-select' name='AddressID' id='editProductAddressSelect'></select></div>`;
                        html += `<div class='col-md-3 mb-3'><label class='form-label'>Pickup Available</label><select class='form-select' name='PL_PickupAvailable'><option value='1' ${(product.PL_PickupAvailable == 1) ? 'selected' : ''}>Yes</option><option value='0' ${(product.PL_PickupAvailable == 0) ? 'selected' : ''}>No</option></select></div>`;
                        html += `<div class='col-md-3 mb-3'><label class='form-label'>Delivery Available</label><select class='form-select' name='PL_DeliveryAvailable'><option value='1' ${(product.PL_DeliveryAvailable == 1) ? 'selected' : ''}>Yes</option><option value='0' ${(product.PL_DeliveryAvailable == 0) ? 'selected' : ''}>No</option></select></div>`;
                        html += `</div>`;
                        html += `<div class='row'>`;
                        html += `<div class='col-md-6 mb-3'><label class='form-label'>Delivery Radius (km)</label><input type='number' step='0.1' class='form-control' name='PL_DeliveryRadius' value='${product.PL_DeliveryRadius ?? ''}'></div>`;
                        html += `<div class='col-md-6 mb-3'><label class='form-label'>Delivery Fee (₱)</label><input type='number' step='0.01' class='form-control' name='PL_DeliveryFee' value='${product.PL_DeliveryFee ?? ''}'></div>`;
                        html += `</div>`;

                        // Images section
                        html += `<div class='mb-3'><label class='form-label'>Images</label><div id='editProductImages' class='d-flex flex-wrap gap-2'></div>`;
                        html += `<input type='file' name='images[]' id='editProductImageInput' multiple accept='image/*' class='form-control mt-2'>`;
                        html += `<small class='text-muted'>Upload multiple images. Click an image to remove before saving.</small></div>`;

                        document.getElementById('editProductBody').innerHTML = html;

                        // Fetch categories and populate dropdown
                        fetch('../api/get-categories.php')
                            .then(res => res.json())
                            .then(categories => {
                                const select = document.getElementById('editProductCategorySelect');
                                select.innerHTML = categories.map(cat => {
                                    return `<option value='${cat.CategoryID}' ${cat.CategoryID == product.CategoryID ? 'selected' : ''}>${cat.Cat_Name}</option>`;
                                }).join('');
                            });

                        // Fetch owner's addresses and populate dropdown
                        fetch('../api/get-owner-address.php?owner_id=' + product.OwnerID)
                            .then(res => res.json())
                            .then(addresses => {
                                const select = document.getElementById('editProductAddressSelect');
                                select.innerHTML = addresses.map(addr => {
                                    let label = `${addr.UA_Street}, ${addr.UA_Barangay}, ${addr.UA_City}, ${addr.UA_Province}`;
                                    return `<option value='${addr.AddressID}' ${addr.AddressID == product.AddressID ? 'selected' : ''}>${label}</option>`;
                                }).join('');
                            });

                        // Show existing images
                        if(product.all_images) {
                            const imgDiv = document.getElementById('editProductImages');
                            product.all_images.forEach((img, idx) => {
                                // Create image wrapper
                                const wrapper = document.createElement('div');
                                wrapper.style = 'position:relative;display:inline-block;margin:2px;';
                                // Create image element
                                const imgElem = document.createElement('img');
                                imgElem.src = '../' + img.PI_ImagePath;
                                imgElem.className = 'rounded border';
                                imgElem.style = 'height:80px;width:80px;object-fit:cover;';
                                imgElem.dataset.imgPath = img.PI_ImagePath;
                                // Create delete button
                                const delBtn = document.createElement('button');
                                delBtn.innerHTML = '&times;';
                                delBtn.type = 'button';
                                delBtn.className = 'btn btn-sm btn-danger';
                                delBtn.style = 'position:absolute;top:2px;right:2px;padding:0 6px;line-height:1;font-size:16px;border-radius:50%;z-index:2;';
                                delBtn.title = 'Delete image';
                                delBtn.onclick = function(e) {
                                    e.stopPropagation();
                                    delBtn.disabled = true;
                                    fetch('../api/delete-product-image.php', {
                                        method: 'POST',
                                        headers: { },
                                        body: new URLSearchParams({
                                            ProductID: product.ProductID,
                                            PI_ImagePath: img.PI_ImagePath
                                        })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        if(data.success) {
                                            wrapper.remove();
                                        } else {
                                            Swal.fire({
                                                title: 'Error!',
                                                text: 'Failed to delete image',
                                                icon: 'error',
                                                confirmButtonColor: '#dc3545'
                                            });
                                            delBtn.disabled = false;
                                        }
                                    })
                                    .catch(() => {
                                        Swal.fire({
                                            title: 'Error!',
                                            text: 'Failed to delete image',
                                            icon: 'error',
                                            confirmButtonColor: '#dc3545'
                                        });
                                        delBtn.disabled = false;
                                    });
                                };
                                // Append image and button to wrapper
                                wrapper.appendChild(imgElem);
                                wrapper.appendChild(delBtn);
                                imgDiv.appendChild(wrapper);
                            });
                        }

                        // Preview newly selected images
                        document.getElementById('editProductImageInput').addEventListener('change', function(e) {
                            const imgDiv = document.getElementById('editProductImages');
                            Array.from(e.target.files).forEach(file => {
                                const reader = new FileReader();
                                reader.onload = function(ev) {
                                    const imgElem = document.createElement('img');
                                    imgElem.src = ev.target.result;
                                    imgElem.className = 'rounded border';
                                    imgElem.style = 'height:80px;width:80px;object-fit:cover;cursor:pointer;';
                                    imgElem.title = 'Click to remove';
                                    imgElem.onclick = function() { imgElem.remove(); };
                                    imgElem.dataset.newFile = file.name;
                                    imgDiv.appendChild(imgElem);
                                };
                                reader.readAsDataURL(file);
                            });
                        });
                    });
            }
            window.openEditProductModal = openEditProductModal;
        function showProductDetails(product) {
            let html = `<div class='container-fluid'>`;
            html += `<div class='row'><div class='col-md-6'><strong>Name:</strong> ${product.Prod_Name}</div><div class='col-md-6'><strong>Category:</strong> ${product.Cat_Name ?? 'Uncategorized'}</div></div>`;
            html += `<div class='row'><div class='col-md-6'><strong>Status:</strong> ${product.Prod_Status}</div><div class='col-md-6'><strong>Availability:</strong> ${product.Prod_Availability ? 'Available' : 'Unavailable'}</div></div>`;
            html += `<div class='row'><div class='col-md-12'><strong>Description:</strong> ${product.Prod_Description}</div></div>`;
        html += `<div class='row'><div class='col-md-6'><strong>Rental Price:</strong> ₱${parseFloat(product.Prod_RentalPrice).toFixed(2)} / ${product.Prod_PriceType}</div><div class='col-md-6'><strong>Security Deposit:</strong> ₱${parseFloat(product.Prod_SecurityDeposit ?? 0).toFixed(2)}</div></div>`;
            html += `<div class='row'><div class='col-md-6'><strong>Booking Count:</strong> ${product.booking_count ?? 0}</div><div class='col-md-6'><strong>Average Rating:</strong> ${product.avg_rating ?? 'N/A'}</div></div>`;
            html += `<div class='row'><div class='col-md-12'><strong>Address:</strong> ${[product.UA_Street, product.UA_Barangay, product.UA_City, product.UA_Province].filter(Boolean).join(', ')}</div></div>`;
            html += `<div class='row'><div class='col-md-6'><strong>Pickup Available:</strong> ${product.PL_PickupAvailable ? 'Yes' : 'No'}</div><div class='col-md-6'><strong>Delivery Available:</strong> ${product.PL_DeliveryAvailable ? 'Yes' : 'No'}`;
            if(product.PL_DeliveryAvailable && product.PL_DeliveryRadius) html += ` (Radius: ${product.PL_DeliveryRadius}km)`;
            html += `</div></div>`;
        html += `<div class='row'><div class='col-md-12'><strong>Date Added:</strong> ${product.Prod_CreatedAt ?? ''}</div></div>`;
            html += `<div class='row'><div class='col-md-12'><strong>Featured Until:</strong> ${product.Prod_FeaturedUntil ?? 'N/A'}</div></div>`;
            html += `<div class='row'><div class='col-md-12'><strong>Product ID:</strong> ${product.ProductID}</div></div>`;
            html += `</div>`;
            document.getElementById('productDetailsBody').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
            modal.show();
        }
        </script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Delete modal
        document.querySelectorAll('[data-bs-target="#deleteModal"]').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('deleteProductId').value = this.dataset.productId;
                document.getElementById('deleteProductName').textContent = this.dataset.productName;
            });
        });

        // Image Gallery Modal Function
        function openImageGallery(productId, startIndex = 0) {
            const imagesData = document.getElementById(`product-images-${productId}`);
            if (!imagesData) return;
            
            const images = JSON.parse(imagesData.textContent);
            const carouselInner = document.getElementById('galleryCarouselInner');
            
            // Clear previous images
            carouselInner.innerHTML = '';
            
            // Add images to modal carousel
            images.forEach((image, index) => {
                const carouselItem = document.createElement('div');
                carouselItem.className = `carousel-item ${index === startIndex ? 'active' : ''}`;
                carouselItem.innerHTML = `
                    <img src="../${image.PI_ImagePath}" 
                         class="d-block w-100" 
                         alt="Product Image ${index + 1}"
                         style="max-height: 500px; object-fit: contain;">
                `;
                carouselInner.appendChild(carouselItem);
            });
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('imageGalleryModal'));
            modal.show();
        }

        // Make openImageGallery available globally
        window.openImageGallery = openImageGallery;

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);

        // Confirm forms before submission
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            if (!form.querySelector('input[name="action"][value="delete_product"]')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const action = this.querySelector('input[name="action"]').value;
                    let title = '';
                    let text = '';
                    let confirmButtonText = '';
                    
                    switch(action) {
                        case 'toggle_availability':
                            const currentStatus = this.querySelector('input[name="current_status"]').value;
                            if (currentStatus == '1') {
                                title = 'Make Product Unavailable?';
                                text = 'This product will no longer be available for rent.';
                                confirmButtonText = 'Yes, make unavailable';
                            } else {
                                title = 'Make Product Available?';
                                text = 'This product will be available for rent again.';
                                confirmButtonText = 'Yes, make available';
                            }
                            break;
                        case 'feature_product':
                            title = 'Feature Product?';
                            text = 'This product will be featured for 30 days and appear at the top of search results.';
                            confirmButtonText = 'Yes, feature it';
                            break;
                    }
                    
                    if (title) {
                        Swal.fire({
                            title: title,
                            text: text,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: confirmButtonText,
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    } else {
                        this.submit();
                    }
                });
            }
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
</body>
</html>