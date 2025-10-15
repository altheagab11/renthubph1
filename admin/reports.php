<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

// Date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Platform Statistics
$platform_stats = [];

try {
    // Total users by role
    $query = "SELECT User_Role, COUNT(*) as count FROM user_accounts WHERE User_Status != 'Deleted' GROUP BY User_Role";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $user_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $platform_stats['total_users'] = 0;
    $platform_stats['admins'] = 0;
    $platform_stats['renters'] = 0;
    $platform_stats['owners'] = 0;
    
    foreach($user_roles as $role) {
        $platform_stats['total_users'] += $role['count'];
        switch($role['User_Role']) {
            case 1: $platform_stats['admins'] = $role['count']; break;
            case 2: $platform_stats['renters'] = $role['count']; break;
            case 3: $platform_stats['owners'] = $role['count']; break;
        }
    }
    
    // Total products
    $query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status != 'Deleted'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $platform_stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active products
    $query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $platform_stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total bookings
    $query = "SELECT COUNT(*) as total FROM bookings";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $platform_stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active bookings
    $query = "SELECT COUNT(*) as total FROM bookings WHERE Book_Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $platform_stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total revenue
    $query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE Book_Status IN ('Active', 'Completed')";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $platform_stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Flagged content
    $query = "SELECT COUNT(DISTINCT ProductID) as products, COUNT(DISTINCT OwnerID) as users FROM flag_reports";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $flag_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $platform_stats['flagged_products'] = $flag_data['products'] ?? 0;
    $platform_stats['flagged_users'] = $flag_data['users'] ?? 0;
    
} catch (PDOException $e) {
    $platform_stats = [
        'total_users' => 0, 'admins' => 0, 'renters' => 0, 'owners' => 0,
        'total_products' => 0, 'active_products' => 0,
        'total_bookings' => 0, 'active_bookings' => 0,
        'total_revenue' => 0, 'flagged_products' => 0, 'flagged_users' => 0
    ];
}

// Period Statistics (based on date range)
$period_stats = [];

try {
    // New users in period
    $query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_CreatedAt BETWEEN ? AND ? AND User_Role != 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $period_stats['new_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New products in period
    $query = "SELECT COUNT(*) as total FROM products WHERE Prod_CreatedAt BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $period_stats['new_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New bookings in period
    $query = "SELECT COUNT(*) as total FROM bookings WHERE Book_CreatedAt BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $period_stats['new_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Revenue in period
    $query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE Book_CreatedAt BETWEEN ? AND ? AND Book_Status IN ('Active', 'Completed')";
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $period_stats['period_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    $period_stats = [
        'new_users' => 0, 'new_products' => 0,
        'new_bookings' => 0, 'period_revenue' => 0
    ];
}

// Top performers
$top_performers = [];

try {
    // Top owners by revenue
    $query = "SELECT u.User_Name, u.User_Email, SUM(b.Book_TotalAmount) as total_revenue, COUNT(b.BookingID) as total_bookings
              FROM user_accounts u 
              JOIN products p ON u.UserID = p.OwnerID 
              JOIN bookings b ON p.ProductID = b.ProductID 
              WHERE b.Book_Status IN ('Active', 'Completed')
              GROUP BY u.UserID 
              ORDER BY total_revenue DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $top_performers['owners'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top products by bookings
    $query = "SELECT p.Prod_Name, u.User_Name as Owner_Name, COUNT(b.BookingID) as booking_count, SUM(b.Book_TotalAmount) as revenue
              FROM products p 
              JOIN user_accounts u ON p.OwnerID = u.UserID 
              JOIN bookings b ON p.ProductID = b.ProductID 
              WHERE b.Book_Status IN ('Active', 'Completed')
              GROUP BY p.ProductID 
              ORDER BY booking_count DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $top_performers['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top renters by spending
    $query = "SELECT u.User_Name, u.User_Email, SUM(b.Book_TotalAmount) as total_spent, COUNT(b.BookingID) as total_bookings
              FROM user_accounts u 
              JOIN bookings b ON u.UserID = b.RenterID 
              WHERE b.Book_Status IN ('Active', 'Completed')
              GROUP BY u.UserID 
              ORDER BY total_spent DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $top_performers['renters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $top_performers = ['owners' => [], 'products' => [], 'renters' => []];
}

// Monthly growth data for charts
$monthly_growth = [];
try {
    // Get last 6 months data
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        // Users
        $query = "SELECT COUNT(*) as count FROM user_accounts WHERE User_CreatedAt BETWEEN ? AND ? AND User_Role != 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([$month_start, $month_end . ' 23:59:59']);
        $users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Products
        $query = "SELECT COUNT(*) as count FROM products WHERE Prod_CreatedAt BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$month_start, $month_end . ' 23:59:59']);
        $products = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Bookings
        $query = "SELECT COUNT(*) as count FROM bookings WHERE Book_CreatedAt BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$month_start, $month_end . ' 23:59:59']);
        $bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $monthly_growth[] = [
            'month' => $month_name,
            'users' => $users,
            'products' => $products,
            'bookings' => $bookings
        ];
    }
} catch (PDOException $e) {
    $monthly_growth = [];
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding-top: 70px;
            min-height: 100vh;
        }
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        
        .text-gray-300 {
            color: #dddfeb !important;
        }
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        
        .progress {
            height: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .border-right {
            border-right: 1px solid #e3e6f0;
        }
        
        .btn-block {
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed top-0 start-0" style="width: 250px; z-index: 1000;">
        <div class="p-3">
            <h4 class="text-white">
                <i class="fas fa-home"></i> RentHub PH
            </h4>
            <p class="text-muted small">Admin Panel</p>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i> Users Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php">
                    <i class="fas fa-box"></i> Products Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="bookings.php">
                    <i class="fas fa-calendar-check"></i> Bookings Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions.php">
                    <i class="fas fa-credit-card"></i> Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="left:250px;">
            <div class="container-fluid">
                <h5 class="mb-0">Reports</h5>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        <div class="container-fluid p-4">
            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Report Filters</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Platform Overview Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Platform Overview</h4>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($platform_stats['total_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($platform_stats['active_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Bookings</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($platform_stats['total_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($platform_stats['total_revenue']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Period Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Period Statistics (<?php echo date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)); ?>)</h4>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">New Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($period_stats['new_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">New Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($period_stats['new_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">New Bookings</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($period_stats['new_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-plus fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Period Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($period_stats['period_revenue']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Role Breakdown -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">User Role Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Renters</span>
                                            <span class="font-weight-bold"><?php echo number_format($platform_stats['renters']); ?></span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" style="width: <?php echo $platform_stats['total_users'] > 0 ? ($platform_stats['renters'] / $platform_stats['total_users'] * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Owners</span>
                                            <span class="font-weight-bold"><?php echo number_format($platform_stats['owners']); ?></span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: <?php echo $platform_stats['total_users'] > 0 ? ($platform_stats['owners'] / $platform_stats['total_users'] * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Admins</span>
                                            <span class="font-weight-bold"><?php echo number_format($platform_stats['admins']); ?></span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $platform_stats['total_users'] > 0 ? ($platform_stats['admins'] / $platform_stats['total_users'] * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <canvas id="roleChart" width="150" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flagged Content -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-danger">Flagged Content</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-right">
                                        <div class="h4 mb-0 font-weight-bold text-danger"><?php echo number_format($platform_stats['flagged_products']); ?></div>
                                        <div class="text-muted">Flagged Products</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="h4 mb-0 font-weight-bold text-danger"><?php echo number_format($platform_stats['flagged_users']); ?></div>
                                    <div class="text-muted">Flagged Users</div>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <a href="products.php" class="btn btn-outline-primary btn-sm me-2">Review Products</a>
                                <a href="users.php" class="btn btn-outline-success btn-sm">Review Users</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="row mb-4">
                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success">Top Owners by Revenue</h6>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_performers['owners'])): ?>
                                <p class="text-muted text-center">No data available</p>
                            <?php else: ?>
                                <?php foreach($top_performers['owners'] as $index => $owner): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <span class="badge bg-success"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($owner['User_Name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($owner['User_Email']); ?></div>
                                        <div class="small">
                                            <strong><?php echo formatCurrency($owner['total_revenue']); ?></strong> 
                                            (<?php echo $owner['total_bookings']; ?> bookings)
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-info">Top Products by Bookings</h6>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_performers['products'])): ?>
                                <p class="text-muted text-center">No data available</p>
                            <?php else: ?>
                                <?php foreach($top_performers['products'] as $index => $product): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <span class="badge bg-info"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($product['Prod_Name']); ?></div>
                                        <div class="text-muted small">by <?php echo htmlspecialchars($product['Owner_Name']); ?></div>
                                        <div class="small">
                                            <strong><?php echo $product['booking_count']; ?> bookings</strong> 
                                            (<?php echo formatCurrency($product['revenue']); ?>)
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top Renters by Spending</h6>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_performers['renters'])): ?>
                                <p class="text-muted text-center">No data available</p>
                            <?php else: ?>
                                <?php foreach($top_performers['renters'] as $index => $renter): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <span class="badge bg-primary"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($renter['User_Name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($renter['User_Email']); ?></div>
                                        <div class="small">
                                            <strong><?php echo formatCurrency($renter['total_spent']); ?></strong> 
                                            (<?php echo $renter['total_bookings']; ?> bookings)
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Growth Chart -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">6-Month Growth Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="growthChart" width="400" height="100"></canvas>
                </div>
            </div>

            <!-- Export Options -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-secondary">Export Reports</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">Generate and download detailed reports for the selected period.</p>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-success btn-block" onclick="exportToCSV()">
                                <i class="fas fa-file-csv"></i> Export to CSV
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-danger btn-block" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export to PDF
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-primary btn-block" onclick="printReport()">
                                <i class="fas fa-print"></i> Print Report
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-info btn-block" onclick="emailReport()">
                                <i class="fas fa-envelope"></i> Email Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Role Distribution Pie Chart
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        const roleChart = new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: ['Renters', 'Owners', 'Admins'],
                datasets: [{
                    data: [
                        <?php echo $platform_stats['renters']; ?>,
                        <?php echo $platform_stats['owners']; ?>,
                        <?php echo $platform_stats['admins']; ?>
                    ],
                    backgroundColor: [
                        '#36b9cc',
                        '#1cc88a',
                        '#f6c23e'
                    ],
                    borderColor: [
                        '#36b9cc',
                        '#1cc88a',
                        '#f6c23e'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Monthly Growth Line Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthChart = new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach($monthly_growth as $month): ?>
                        '<?php echo $month['month']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        label: 'New Users',
                        data: [
                            <?php foreach($monthly_growth as $month): ?>
                                <?php echo $month['users']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'New Products',
                        data: [
                            <?php foreach($monthly_growth as $month): ?>
                                <?php echo $month['products']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'New Bookings',
                        data: [
                            <?php foreach($monthly_growth as $month): ?>
                                <?php echo $month['bookings']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#36b9cc',
                        backgroundColor: 'rgba(54, 185, 204, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
        
        // Export Functions
        function exportToCSV() {
            alert('CSV export functionality will be implemented here. This would generate a CSV file with all the report data.');
        }
        
        function exportToPDF() {
            alert('PDF export functionality will be implemented here. This would generate a PDF report.');
        }
        
        function printReport() {
            window.print();
        }
        
        function emailReport() {
            alert('Email report functionality will be implemented here. This would send the report via email.');
        }
    </script>
</body>
</html>
