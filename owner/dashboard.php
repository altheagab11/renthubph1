<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Both Renter/Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Check subscription status
$query = "SELECT us.*, sp.Plan_Name, sp.Plan_MaxListings, sp.Plan_FeaturedListings 
          FROM user_subscriptions us 
          JOIN subscription_plans sp ON us.PlanID = sp.PlanID 
          WHERE us.UserID = ? AND us.Sub_Status = 'Active' AND us.Sub_EndDate > NOW()
          ORDER BY us.Sub_EndDate DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get owner statistics
$stats = [];

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE OwnerID = ? AND Book_Status IN ('Pending', 'Confirmed', 'In Progress')";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total earnings
$query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE OwnerID = ? AND Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_earnings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// This month earnings
$query = "SELECT SUM(Book_TotalAmount) as total FROM bookings 
          WHERE OwnerID = ? AND Book_Status = 'Completed' 
          AND MONTH(Book_CreatedAt) = MONTH(NOW()) AND YEAR(Book_CreatedAt) = YEAR(NOW())";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['month_earnings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent bookings
$query = "SELECT b.*, p.Prod_Name, p.Prod_RentalPrice, p.Prod_PriceType, pi.PI_ImagePath, u.User_Name as Renter_Name
          FROM bookings b
          JOIN products p ON b.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON b.RenterID = u.UserID
          WHERE b.OwnerID = ?
          ORDER BY b.Book_CreatedAt DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - RentHub PH</title>
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card.products { background: var(--primary-gradient); color: white; }
        .stat-card.bookings { background: var(--primary-gradient); color: white; }
        .stat-card.earnings { background: var(--primary-gradient); color: white; }
        .stat-card.month { background: var(--primary-gradient); color: white; }
        
        .subscription-card {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 15px;
        }
        
        .booking-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-radius: 10px;
            border-left: 4px solid #11998e;
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .status-badge {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .status-badge.pending { background: var(--primary-gradient); }
        .status-badge.confirmed { background: var(--primary-gradient); }
        .status-badge.in-progress { background: var(--primary-gradient); }
        .status-badge.completed { background: var(--primary-gradient); }
        .status-badge.cancelled { background: var(--primary-gradient); }

        .quick-action-btn {
            border-radius: 15px;
            padding: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
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
        
        .list-group-item {
            border: none;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
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
        }
        
        /* Custom scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <h5 class="mb-0">Owner Dashboard - <?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                2
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar-check text-success me-2"></i>New booking request</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-money-bill text-primary me-2"></i>Payment received</a></li>
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

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <!-- Subscription Status -->
            <?php if(!$subscription): ?>
            <div class="alert alert-warning mb-4 border-0" style="border-radius: 15px;">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="alert-heading mb-1">
                            <i class="fas fa-exclamation-triangle"></i> Subscription Required
                        </h5>
                        <p class="mb-0">You need an active subscription to list products and receive bookings.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="subscription.php" class="btn btn-warning" style="border-radius: 25px;">
                            <i class="fas fa-crown"></i> Subscribe Now
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card subscription-card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1">
                                <i class="fas fa-crown"></i> <?php echo htmlspecialchars($subscription['Plan_Name']); ?> Plan
                            </h5>
                            <p class="mb-0 opacity-75">
                                Active until <?php echo date('M j, Y', strtotime($subscription['Sub_EndDate'])); ?> • 
                                <?php echo $stats['total_products']; ?>/<?php echo $subscription['Plan_MaxListings']; ?> listings used
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="subscription.php" class="btn btn-light" style="border-radius: 25px;">
                                <i class="fas fa-cog"></i> Manage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card products">
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
                    <div class="card stat-card bookings">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Active Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card earnings">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Earnings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_earnings'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card month">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        This Month
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['month_earnings'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">
                                <i class="fas fa-bolt text-warning me-2"></i>Quick Actions
                            </h5>
                            <div class="row">
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="add-product.php" class="btn w-100 quick-action-btn" style="background: var(--primary-gradient); color: white;">
                                        <i class="fas fa-plus fa-2x mb-2"></i><br>
                                        <span>Add Product</span>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="bookings.php" class="btn w-100 quick-action-btn" style="background: var(--primary-gradient); color: white;">
                                        <i class="fas fa-calendar-check fa-2x mb-2"></i><br>
                                        <span>View Bookings</span>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="earnings.php" class="btn w-100 quick-action-btn" style="background: var(--primary-gradient); color: white;">
                                        <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                        <span>Earnings Report</span>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="messages.php" class="btn w-100 quick-action-btn" style="background: var(--primary-gradient); color: white;">
                                        <i class="fas fa-comments fa-2x mb-2"></i><br>
                                        <span>Messages</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings and Performance -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock text-primary me-2"></i>Recent Booking Requests
                            </h5>
                            <a href="bookings.php" class="btn btn-sm" style="background: var(--primary-gradient); color: white; border-radius: 20px;">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if(empty($recent_bookings)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                    <h6 class="text-muted">No booking requests yet</h6>
                                    <p class="text-muted">Start by adding products to your inventory!</p>
                                    <a href="add-product.php" class="btn" style="background: var(--primary-gradient); color: white; border-radius: 25px;">
                                        <i class="fas fa-plus"></i> Add Product
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach($recent_bookings as $booking): ?>
                                <div class="booking-card card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <img src="<?php echo $booking['PI_ImagePath'] ? htmlspecialchars($booking['PI_ImagePath']) : '../assets/images/no-image.jpg'; ?>" 
                                                     class="img-fluid rounded" style="height: 60px; width: 60px; object-fit: cover;" 
                                                     alt="<?php echo htmlspecialchars($booking['Prod_Name']); ?>"
                                                     src="<?php echo $booking['PI_ImagePath'] ? '../' . htmlspecialchars($booking['PI_ImagePath']) : '../assets/images/no-image.jpg'; ?>">
                                                <small class="text-muted d-block mt-1" style="font-size:10px;word-break:break-all;">
                                                    <?php echo $booking['PI_ImagePath']; ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['Prod_Name']); ?></h6>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-user me-1"></i>by <?php echo htmlspecialchars($booking['Renter_Name']); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-2">
                                                <strong class="text-success">₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?></strong>
                                            </div>
                                            <div class="col-md-2">
                                                <span class="badge status-badge <?php echo strtolower(str_replace(' ', '-', $booking['Book_Status'])); ?>">
                                                    <?php echo htmlspecialchars($booking['Book_Status']); ?>
                                                </span>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($booking['Book_CreatedAt'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-lightbulb text-warning me-2"></i>Performance Tips
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-camera text-success me-3"></i>
                                    <small>Add high-quality photos to your listings</small>
                                </div>
                                <div class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-edit text-primary me-3"></i>
                                    <small>Write detailed product descriptions</small>
                                </div>
                                <div class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-reply text-warning me-3"></i>
                                    <small>Respond to messages quickly</small>
                                </div>
                                <div class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-star text-danger me-3"></i>
                                    <small>Maintain good ratings and reviews</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt text-success me-2"></i>Account Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span><i class="fas fa-user-check me-2"></i>Profile Complete</span>
                                <span class="badge bg-success" style="border-radius: 15px;">100%</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span><i class="fas fa-crown me-2"></i>Subscription</span>
                                <?php if($subscription): ?>
                                    <span class="badge bg-success" style="border-radius: 15px;">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning" style="border-radius: 15px;">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-star me-2"></i>Rating</span>
                                <span class="text-warning">
                                    <i class="fas fa-star"></i> 4.8/5.0
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay d-md-none" id="sidebarOverlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        });

        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('show');
            this.classList.remove('show');
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
    </script>
</body>
</html>