<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Both Renter/Owner only

$database = new Database();
$conn = $database->getConnection();


// Get date filter parameters
// ...existing code...
$user_id = $_SESSION['user_id'];

// Get unread notifications for navbar
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count_query = "SELECT COUNT(*) as cnt FROM notifications WHERE UserID = ? AND Not_IsRead = 0";
$notif_count_stmt = $conn->prepare($notif_count_query);
$notif_count_stmt->execute([$user_id]);
$notif_count = $notif_count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
$period = isset($_GET['period']) ? $_GET['period'] : 'this_month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Set date range based on period
switch($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('last month'));
        $end_date = date('Y-m-t', strtotime('last month'));
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        if(!$start_date) $start_date = date('Y-m-01');
        if(!$end_date) $end_date = date('Y-m-d');
        break;
}

// Get earnings statistics
$stats = [];

// Total earnings
$query = "SELECT SUM(b.Book_TotalAmount) as total FROM bookings b 
          JOIN products p ON b.ProductID = p.ProductID 
          WHERE p.OwnerID = ? AND b.Book_Status = 'Completed' 
          AND DATE(b.Book_UpdatedAt) BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $start_date);
$stmt->bindParam(3, $end_date);
$stmt->execute();
$stats['total_earnings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings b 
          JOIN products p ON b.ProductID = p.ProductID 
          WHERE p.OwnerID = ? AND b.Book_Status = 'Completed' 
          AND DATE(b.Book_UpdatedAt) BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $start_date);
$stmt->bindParam(3, $end_date);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Average booking value
$stats['avg_booking'] = $stats['total_bookings'] > 0 ? $stats['total_earnings'] / $stats['total_bookings'] : 0;

// Commission paid (assuming 10% commission rate)
$stats['commission_paid'] = $stats['total_earnings'] * 0.10;

// Net earnings
$stats['net_earnings'] = $stats['total_earnings'] - $stats['commission_paid'];

// Get daily earnings for chart
$chart_query = "SELECT DATE(b.Book_UpdatedAt) as date, SUM(b.Book_TotalAmount) as daily_total
                FROM bookings b 
                JOIN products p ON b.ProductID = p.ProductID 
                WHERE p.OwnerID = ? AND b.Book_Status = 'Completed' 
                AND DATE(b.Book_UpdatedAt) BETWEEN ? AND ?
                GROUP BY DATE(b.Book_UpdatedAt)
                ORDER BY DATE(b.Book_UpdatedAt)";
$stmt = $conn->prepare($chart_query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $start_date);
$stmt->bindParam(3, $end_date);
$stmt->execute();
$chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performing products
$products_query = "SELECT p.Prod_Name, COUNT(b.BookingID) as booking_count, 
                   SUM(b.Book_TotalAmount) as total_earnings
                   FROM bookings b 
                   JOIN products p ON b.ProductID = p.ProductID 
                   WHERE p.OwnerID = ? AND b.Book_Status = 'Completed' 
                   AND DATE(b.Book_UpdatedAt) BETWEEN ? AND ?
                   GROUP BY p.ProductID, p.Prod_Name
                   ORDER BY total_earnings DESC
                   LIMIT 5";
$stmt = $conn->prepare($products_query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $start_date);
$stmt->bindParam(3, $end_date);
$stmt->execute();
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$transactions_query = "SELECT b.*, p.Prod_Name, pi.PI_ImagePath, u.User_Name as Renter_Name
                       FROM bookings b
                       JOIN products p ON b.ProductID = p.ProductID
                       LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
                       JOIN user_accounts u ON b.RenterID = u.UserID
                       WHERE p.OwnerID = ? AND b.Book_Status = 'Completed' 
                       AND DATE(b.Book_UpdatedAt) BETWEEN ? AND ?
                       ORDER BY b.Book_UpdatedAt DESC
                       LIMIT 10";
$stmt = $conn->prepare($transactions_query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $start_date);
$stmt->bindParam(3, $end_date);
$stmt->execute();
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly comparison data
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$previous_month_start = date('Y-m-01', strtotime('last month'));
$previous_month_end = date('Y-m-t', strtotime('last month'));

// Current month earnings
$query = "SELECT SUM(b.Book_TotalAmount) as total FROM bookings b 
          JOIN products p ON b.ProductID = p.ProductID 
          WHERE p.OwnerID = ? AND b.Book_Status = 'Completed' 
          AND DATE(b.Book_UpdatedAt) BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $current_month_start);
$stmt->bindParam(3, $current_month_end);
$stmt->execute();
$current_month_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Previous month earnings
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $previous_month_start);
$stmt->bindParam(3, $previous_month_end);
$stmt->execute();
$previous_month_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Calculate percentage change
$percentage_change = 0;
if ($previous_month_earnings > 0) {
    $percentage_change = (($current_month_earnings - $previous_month_earnings) / $previous_month_earnings) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings Report - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.earnings { background: var(--primary-gradient); color: white; }
        .stat-card.bookings { background: var(--primary-gradient); color: white; }
        .stat-card.average { background: var(--primary-gradient); color: white; }
        .stat-card.commission { background: var(--primary-gradient); color: white; }
        
        .chart-container {
            position: relative;
            height: 400px;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .performance-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .performance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .transaction-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #11998e;
            margin-bottom: 1rem;
        }
        
        .transaction-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .period-selector {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .period-btn {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            margin: 0.25rem;
        }
        
        .period-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: #11998e;
        }
        
        .comparison-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .comparison-indicator.positive {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .comparison-indicator.negative {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .comparison-indicator.neutral {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
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
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
        }
        
        .progress-modern {
            height: 8px;
            border-radius: 10px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .progress-modern .progress-bar {
            background: var(--primary-gradient);
            border-radius: 10px;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
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
            
            .period-selector {
                padding: 1rem;
            }
            
            .chart-container {
                padding: 1rem;
                height: 300px;
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
                    <a class="nav-link active" href="earnings.php">
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
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-success me-2"></i>Earnings Report
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item me-3">
                        <button class="btn" onclick="window.print()" style="background: var(--primary-gradient); color: white; border-radius: 25px;">
                            <i class="fas fa-download me-2"></i>Export Report
                        </button>
                    </div>
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
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
            <!-- Period Selector -->
            <div class="period-selector">
                <form method="GET" class="row align-items-end" id="periodForm">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Time Period
                        </label>
                        <div class="d-flex flex-wrap">
                            <button type="button" class="btn period-btn <?php echo $period == 'today' ? 'active' : 'var(--primary-gradient)'; ?>" onclick="setPeriod('today')">Today</button>
                            <button type="button" class="btn period-btn <?php echo $period == 'this_week' ? 'active' : 'var(--primary-gradient)'; ?>" onclick="setPeriod('this_week')">This Week</button>
                            <button type="button" class="btn period-btn <?php echo $period == 'this_month' ? 'active' : 'var(--primary-gradient)'; ?>" onclick="setPeriod('this_month')">This Month</button>
                            <button type="button" class="btn period-btn <?php echo $period == 'last_month' ? 'active' : 'var(--primary-gradient)'; ?>" onclick="setPeriod('last_month')">Last Month</button>
                            <button type="button" class="btn period-btn <?php echo $period == 'this_year' ? 'active' : 'var(--primary-gradient)'; ?>" onclick="setPeriod('this_year')">This Year</button>
                            <button type="button" class="btn period-btn <?php echo $period == 'custom' ? 'active' : 'var(--primary-gradient)'; ?>" onclick="setPeriod('custom')">Custom</button>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3" id="customDateRange" style="display: <?php echo $period == 'custom' ? 'block' : 'none'; ?>;">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="period" id="periodInput" value="<?php echo $period; ?>">
                </form>
            </div>

            <!-- Current Month Comparison -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-calendar-alt text-primary me-2"></i>Monthly Performance
                                    </h6>
                                    <h4 class="mb-1">₱<?php echo number_format($current_month_earnings, 2); ?></h4>
                                    <p class="text-muted mb-0">This month's earnings</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if($percentage_change > 0): ?>
                                        <span class="comparison-indicator positive">
                                            <i class="fas fa-arrow-up me-1"></i>+<?php echo number_format($percentage_change, 1); ?>%
                                        </span>
                                    <?php elseif($percentage_change < 0): ?>
                                        <span class="comparison-indicator negative">
                                            <i class="fas fa-arrow-down me-1"></i><?php echo number_format($percentage_change, 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="comparison-indicator neutral">
                                            <i class="fas fa-minus me-1"></i>0%
                                        </span>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-1">vs last month</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
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
                    <div class="card stat-card bookings">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Completed Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                                        Average Booking
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['avg_booking'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-bar fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card commission">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Net Earnings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['net_earnings'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-wallet fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Performance -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="chart-container">
                        <h5 class="mb-4">
                            <i class="fas fa-chart-line text-primary me-2"></i>Earnings Trend
                        </h5>
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card performance-card h-100">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-trophy text-warning me-2"></i>Top Performing Products
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_products)): ?>
                                <div class="empty-state py-3">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                    <p class="text-muted mb-0">No data for selected period</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($top_products as $index => $product): ?>
                                <div class="top-product-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                                            <small class="text-muted"><?php echo $product['booking_count']; ?> bookings</small>
                                        </div>
                                        <div class="text-end">
                                            <strong>₱<?php echo number_format($product['total_earnings'], 2); ?></strong>
                                        </div>
                                    </div>
                                    <div class="progress-modern mt-2">
                                        <div class="progress-bar" style="width: <?php echo ($product['total_earnings'] / $stats['total_earnings']) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history text-info me-2"></i>Recent Transactions
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($recent_transactions)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <h6 class="text-muted">No transactions found</h6>
                                    <p class="text-muted">Completed transactions will appear here</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($recent_transactions as $transaction): ?>
                                <div class="transaction-card card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($transaction['Prod_Name']); ?></h6>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-user me-1"></i>by <?php echo htmlspecialchars($transaction['Renter_Name']); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted">Booking #</small>
                                                <p class="mb-0"><?php echo $transaction['BookingID']; ?></p>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted">Rental Period</small>
                                                <p class="mb-0"><?php echo date('M j', strtotime($transaction['Book_StartDate'])); ?> - <?php echo date('M j', strtotime($transaction['Book_EndDate'])); ?></p>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <h6 class="text-success mb-1">₱<?php echo number_format($transaction['Book_TotalAmount'], 2); ?></h6>
                                                <small class="text-muted"><?php echo date('M j, Y', strtotime($transaction['Book_UpdatedAt'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Period selector functions
        function setPeriod(period) {
            document.getElementById('periodInput').value = period;
            
            if (period === 'custom') {
                document.getElementById('customDateRange').style.display = 'block';
            } else {
                document.getElementById('customDateRange').style.display = 'none';
                document.getElementById('periodForm').submit();
            }
        }

        // Auto-submit on custom date change
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', function() {
                if (document.getElementById('periodInput').value === 'custom') {
                    document.getElementById('periodForm').submit();
                }
            });
        });

        // Earnings chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        const labels = chartData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const data = chartData.map(item => parseFloat(item.daily_total));
        
        const earningsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Earnings',
                    data: data,
                    borderColor: '#11998e',
                    backgroundColor: 'rgba(17, 153, 142, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#11998e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBorderWidth: 3
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    </script>
</body>
</html>