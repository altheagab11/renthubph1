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
            right: 15px;
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
        .action-btn.share { background: var(--secondary-gradient); color: white; }
        
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
                            <button type="submit" name="clear_all_favorites" class="btn action-btn remove"
                                    onclick="return confirm('Are you sure you want to remove all favorites?')">
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
                    <a href="../browse.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
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
                                        <a href="../product.php?id=<?php echo $favorite['ProductID']; ?>" 
                                           class="btn action-btn book">
                                            <i class="fas fa-calendar-plus me-1"></i>Book Now
                                        </a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="product_id" value="<?php echo $favorite['ProductID']; ?>">
                                            <button type="submit" name="remove_favorite" class="btn btn-outline-danger w-100"
                                                    onclick="return confirm('Remove this item from favorites?')"
                                                    style="border-radius: 15px;">
                                                <i class="fas fa-heart-broken me-1"></i>Remove from Favorites
                                            </button>
                                        </form>
                                        
                                        <button class="btn action-btn share" onclick="shareProduct(<?php echo $favorite['ProductID']; ?>, '<?php echo addslashes($favorite['Prod_Name']); ?>')">
                                            <i class="fas fa-share me-1"></i>Share
                                        </button>
                                        
                                        <a href="../product.php?id=<?php echo $favorite['ProductID']; ?>" 
                                           class="btn btn-outline-primary btn-sm" style="border-radius: 15px;">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Share product function
        function shareProduct(productId, productName) {
            if (navigator.share) {
                navigator.share({
                    title: productName + ' - RentHub PH',
                    text: 'Check out this awesome rental: ' + productName,
                    url: window.location.origin + '/product.php?id=' + productId
                });
            } else {
                // Fallback - copy to clipboard
                const url = window.location.origin + '/product.php?id=' + productId;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Product link copied to clipboard!');
                });
            }
        }

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