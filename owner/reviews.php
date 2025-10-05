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

// Get unread notifications for the owner
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 10";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

// Handle review response
if ($_POST && isset($_POST['respond_review'])) {
    $review_id = $_POST['review_id'];
    $response_content = trim($_POST['response_content']);
    
    if (!empty($response_content)) {
        $query = "UPDATE reviews SET Rev_OwnerResponse = ?, Rev_ResponseDate = NOW() WHERE ReviewID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $response_content);
        $stmt->bindParam(2, $review_id);
        
        if ($stmt->execute()) {
            $message = "Response added successfully!";
            $message_type = "success";

            // Send notification to renter
            // Get review details for notification
            $review_query = "SELECT r.*, b.RenterID, p.Prod_Name FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID JOIN products p ON b.ProductID = p.ProductID WHERE r.ReviewID = ?";
            $review_stmt = $conn->prepare($review_query);
            $review_stmt->execute([$review_id]);
            $review = $review_stmt->fetch(PDO::FETCH_ASSOC);
            if ($review) {
                $notif_query = "INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_RelatedID, Not_IsRead, Not_CreatedAt) VALUES (?, ?, ?, ?, ?, 0, NOW())";
                $notif_stmt = $conn->prepare($notif_query);
                $notif_stmt->execute([
                    $review['RenterID'],
                    'review_response',
                    'Owner Responded to Your Review',
                    'The owner responded to your review for product "' . htmlspecialchars($review['Prod_Name']) . '".',
                    $review_id
                ]);
            }
        } else {
            $message = "Failed to add response. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["p.OwnerID = ?"];
$params = [$user_id];

if ($rating_filter && $rating_filter != 'all') {
    $conditions[] = "r.Rev_Rating = ?";
    $params[] = $rating_filter;
}

if ($status_filter == 'responded') {
    $conditions[] = "r.Rev_OwnerResponse IS NOT NULL";
} elseif ($status_filter == 'pending') {
    $conditions[] = "r.Rev_OwnerResponse IS NULL";
}

// Sort options
$sort_options = [
    'newest' => 'r.Rev_CreatedAt DESC',
    'oldest' => 'r.Rev_CreatedAt ASC',
    'rating_high' => 'r.Rev_Rating DESC',
    'rating_low' => 'r.Rev_Rating ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'r.Rev_CreatedAt DESC';

// Get reviews
$query = "SELECT r.*, p.Prod_Name, pi.PI_ImagePath, u.User_Name as Reviewer_Name,
          b.Book_StartDate, b.Book_EndDate, b.Book_TotalAmount
          FROM reviews r
          JOIN bookings b ON r.BookingID = b.BookingID
          JOIN products p ON b.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON b.RenterID = u.UserID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total reviews
$query = "SELECT COUNT(*) as total FROM reviews r 
          JOIN bookings b ON r.BookingID = b.BookingID 
          JOIN products p ON b.ProductID = p.ProductID 
          WHERE p.OwnerID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_reviews'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Average rating
$query = "SELECT AVG(r.Rev_Rating) as avg_rating FROM reviews r 
          JOIN bookings b ON r.BookingID = b.BookingID 
          JOIN products p ON b.ProductID = p.ProductID 
          WHERE p.OwnerID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['avg_rating'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0, 1);

// Pending responses
$query = "SELECT COUNT(*) as total FROM reviews r 
          JOIN bookings b ON r.BookingID = b.BookingID 
          JOIN products p ON b.ProductID = p.ProductID 
          WHERE p.OwnerID = ? AND r.Rev_OwnerResponse IS NULL";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['pending_responses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Response rate
$stats['response_rate'] = $stats['total_reviews'] > 0 ? 
    round((($stats['total_reviews'] - $stats['pending_responses']) / $stats['total_reviews']) * 100, 1) : 0;

// Get rating distribution
$rating_distribution = [];
for ($i = 1; $i <= 5; $i++) {
    $query = "SELECT COUNT(*) as count FROM reviews r 
              JOIN bookings b ON r.BookingID = b.BookingID 
              JOIN products p ON b.ProductID = p.ProductID 
              WHERE p.OwnerID = ? AND r.Rev_Rating = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->bindParam(2, $i);
    $stmt->execute();
    $rating_distribution[$i] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

// Get top reviewed products
$query = "SELECT p.Prod_Name, COUNT(r.ReviewID) as review_count, AVG(r.Rev_Rating) as avg_rating
          FROM reviews r
          JOIN bookings b ON r.BookingID = b.BookingID
          JOIN products p ON b.ProductID = p.ProductID
          WHERE p.OwnerID = ?
          GROUP BY p.ProductID, p.Prod_Name
          ORDER BY review_count DESC, avg_rating DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Ratings - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .stat-card.rating { background: var(--primary-gradient); color: white; }
        .stat-card.pending { background: var(--primary-gradient); color: white; }
        .stat-card.response { background: var(--primary-gradient); color: white; }
        
        .review-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 1.5rem;
            border-left: 4px solid #11998e;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        
        .rating-stars.large {
            font-size: 2rem;
        }
        
        .rating-distribution {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .rating-bar {
            background: #e9ecef;
            height: 16px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .rating-bar .progress-fill {
            background: linear-gradient(90deg, #ffc107 0%, #ff9800 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
            min-width: 2px;
        }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .reviewer-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .review-content {
            background: rgba(17, 153, 142, 0.05);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #11998e;
            margin: 1rem 0;
        }
        
        .response-section {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #667eea;
            margin-top: 1rem;
        }
        
        .response-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
        }
        
        .btn-respond {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-respond:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
            color: white;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .top-product-item {
            padding: 1rem;
            border-radius: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .top-product-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .response-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .pending-badge {
            background: #ffc107;
            color: #000;
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
            
            .rating-distribution {
                padding: 1rem;
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
                    <a class="nav-link" href="products.php">
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
                    <a class="nav-link active" href="reviews.php">
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
                    <a class="nav-link" href="../index.php">
                        <i class="fas fa-arrow-left me-2"></i> Back to Site
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
                        <i class="fas fa-star text-warning me-2"></i>Reviews & Ratings
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
                                        Total Reviews
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_reviews']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card rating">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Average Rating
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['avg_rating']; ?>/5.0</div>
                                </div>
                                <div class="col-auto">
                                    <div class="rating-stars large">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $stats['avg_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card pending">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Pending Responses
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['pending_responses']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card response">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Response Rate
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['response_rate']; ?>%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-reply fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rating Distribution & Top Products -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="rating-distribution">
                        <h5 class="mb-4">
                            <i class="fas fa-chart-bar text-primary me-2"></i>Rating Distribution
                        </h5>
                        
                        <?php for($i = 5; $i >= 1; $i--): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="me-2"><?php echo $i; ?></span>
                                    <div class="rating-stars">
                                        <?php for($j = 1; $j <= $i; $j++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span class="text-muted"><?php echo $rating_distribution[$i]; ?> reviews</span>
                            </div>
                            <div class="rating-bar">
                                <div class="progress-fill" style="width: <?php echo $stats['total_reviews'] > 0 ? ($rating_distribution[$i] / $stats['total_reviews']) * 100 : 0; ?>%"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-trophy text-warning me-2"></i>Top Reviewed Products
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_products)): ?>
                                <div class="empty-state py-3">
                                    <i class="fas fa-star fa-2x"></i>
                                    <p class="text-muted mb-0">No reviews yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($top_products as $product): ?>
                                <div class="top-product-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                                            <div class="d-flex align-items-center">
                                                <div class="rating-stars me-2">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $product['avg_rating'] ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted"><?php echo $product['review_count']; ?> reviews</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo number_format($product['avg_rating'], 1); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-star me-2"></i>Rating Filter
                        </label>
                        <select class="form-select" name="rating">
                            <option value="all" <?php echo $rating_filter == 'all' ? 'selected' : ''; ?>>All Ratings</option>
                            <option value="5" <?php echo $rating_filter == '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter == '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter == '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter == '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter == '1' ? 'selected' : ''; ?>>1 Star</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Response Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Reviews</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Response</option>
                            <option value="responded" <?php echo $status_filter == 'responded' ? 'selected' : ''; ?>>Responded</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="rating_high" <?php echo $sort_by == 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="rating_low" <?php echo $sort_by == 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn w-100" style="background: var(--primary-gradient); color: white; border-radius: 15px;">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Reviews List -->
            <?php if(empty($reviews)): ?>
                <div class="empty-state">
                    <i class="fas fa-star-half-alt"></i>
                    <h4 class="text-muted">No reviews yet</h4>
                    <p class="text-muted">Customer reviews will appear here after completed bookings.</p>
                    <a href="products.php" class="btn btn-lg" style="background: var(--primary-gradient); color: white; border-radius: 25px;">
                        <i class="fas fa-box me-2"></i>Manage Products
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($reviews as $review): ?>
                <div class="review-card card">
                    <?php if($review['Rev_OwnerResponse']): ?>
                        <div class="response-badge">Responded</div>
                    <?php else: ?>
                        <div class="response-badge pending-badge">Pending</div>
                    <?php endif; ?>
                    
                    <div class="card-body p-4">
                        <div class="row">
                                <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($review['Prod_Name']); ?></h5>
                                        <div class="rating-stars mb-2">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['Rev_Rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2"><?php echo $review['Rev_Rating']; ?>/5</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <p class="text-muted small mb-1">
                                            <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($review['Rev_CreatedAt'])); ?>
                                        </p>
                                        <p class="text-muted small mb-0">
                                            Booking: ₱<?php echo number_format($review['Book_TotalAmount'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="reviewer-info">
                                    <h6 class="mb-2">
                                        <i class="fas fa-user me-2"></i>Review by <?php echo htmlspecialchars($review['Reviewer_Name']); ?>
                                    </h6>
                                    <p class="text-muted small mb-0">
                                        Rental period: <?php echo date('M j', strtotime($review['Book_StartDate'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($review['Book_EndDate'])); ?>
                                    </p>
                                </div>
                                
                                <div class="review-content">
                                    <h6 class="mb-2">
                                        <i class="fas fa-quote-left me-2"></i>Customer Review
                                    </h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['Rev_Comment'])); ?></p>
                                </div>
                                
                                <?php if($review['Rev_OwnerResponse']): ?>
                                <div class="response-section">
                                    <h6 class="mb-2">
                                        <i class="fas fa-reply me-2"></i>Your Response
                                        <small class="text-muted">(<?php echo date('M j, Y g:i A', strtotime($review['Rev_ResponseDate'])); ?>)</small>
                                    </h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['Rev_OwnerResponse'])); ?></p>
                                </div>
                                <?php else: ?>
                                <div class="response-form">
                                    <h6 class="mb-3">
                                        <i class="fas fa-reply me-2"></i>Respond to this Review
                                    </h6>
                                    <form method="POST">
                                        <input type="hidden" name="review_id" value="<?php echo $review['ReviewID']; ?>">
                                        <div class="mb-3">
                                            <textarea class="form-control" name="response_content" rows="3" 
                                                      placeholder="Write a professional response to thank the customer and address any concerns..." required></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" name="respond_review" class="btn btn-respond">
                                                <i class="fas fa-paper-plane me-2"></i>Send Response
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
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

        // Character counter for response textarea
        document.querySelectorAll('textarea[name="response_content"]').forEach(textarea => {
            const maxLength = 500;
            
            // Create character counter
            const counter = document.createElement('div');
            counter.className = 'text-muted small mt-1';
            counter.innerHTML = `0/${maxLength} characters`;
            textarea.parentNode.appendChild(counter);
            
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.innerHTML = `${length}/${maxLength} characters`;
                
                if (length > maxLength * 0.9) {
                    counter.className = 'text-warning small mt-1';
                } else {
                    counter.className = 'text-muted small mt-1';
                }
            });
        });

        // Response form validation
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="respond_review"]')) {
                form.addEventListener('submit', function(e) {
                    const textarea = this.querySelector('textarea[name="response_content"]');
                    const response = textarea.value.trim();
                    
                    if (response.length < 10) {
                        e.preventDefault();
                        alert('Please write a more detailed response (at least 10 characters).');
                        textarea.focus();
                        return false;
                    }
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[name="respond_review"]');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                    submitBtn.disabled = true;
                });
            }
        });

        // Animate rating bars on page load
        window.addEventListener('load', function() {
            const ratingBars = document.querySelectorAll('.rating-bar .progress-fill');
            ratingBars.forEach((bar, index) => {
                setTimeout(() => {
                    bar.style.width = bar.style.width;
                }, index * 100);
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
</body>
</html>