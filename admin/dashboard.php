<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

// Get statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Users by role
$query = "SELECT 
          SUM(CASE WHEN User_Role = 2 THEN 1 ELSE 0 END) as renters,
          SUM(CASE WHEN User_Role = 3 THEN 1 ELSE 0 END) as owners
          FROM user_accounts WHERE User_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$user_breakdown = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['renters'] = $user_breakdown['renters'];
$stats['owners'] = $user_breakdown['owners'];

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Product statistics
$query = "SELECT 
          COUNT(*) as total_products,
          SUM(CASE WHEN Prod_Status = 'Active' THEN 1 ELSE 0 END) as active_products,
          SUM(CASE WHEN Prod_Status = 'Pending' THEN 1 ELSE 0 END) as pending_products,
          SUM(CASE WHEN Prod_IsFeatured = 1 THEN 1 ELSE 0 END) as featured_products
          FROM products WHERE Prod_Status != 'Deleted'";
$stmt = $conn->prepare($query);
$stmt->execute();
$product_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Total bookings
$query = "SELECT 
          COUNT(*) as total,
          SUM(CASE WHEN Book_Status = 'Active' THEN 1 ELSE 0 END) as active,
          SUM(CASE WHEN Book_Status = 'Pending' THEN 1 ELSE 0 END) as pending,
          SUM(CASE WHEN Book_Status = 'Completed' THEN 1 ELSE 0 END) as completed,
          SUM(CASE WHEN Book_Status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
          FROM bookings";
$stmt = $conn->prepare($query);
$stmt->execute();
$booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_bookings'] = $booking_stats['total'];

// Total revenue from completed bookings
$query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE Book_Status IN ('Active', 'Completed')";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Commission earned
$query = "SELECT SUM(Comm_Amount) as total FROM commission_payments WHERE Comm_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_commission'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Monthly revenue trend (last 6 months)
$query = "SELECT 
          DATE_FORMAT(Book_CreatedAt, '%Y-%m') as month,
          SUM(Book_TotalAmount) as revenue,
          COUNT(*) as bookings
          FROM bookings 
          WHERE Book_Status IN ('Active', 'Completed') 
          AND Book_CreatedAt >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(Book_CreatedAt, '%Y-%m')
          ORDER BY month DESC
          LIMIT 6";
$stmt = $conn->prepare($query);
$stmt->execute();
$monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activities - more comprehensive
$recent_activities = [];

// Recent user registrations
$query = "SELECT 'User Registration' as activity, User_Name as details, User_CreatedAt as created_at, 'user' as type
          FROM user_accounts 
          ORDER BY User_CreatedAt DESC 
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_activities = array_merge($recent_activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Recent product listings
$query = "SELECT 'Product Listed' as activity, 
          CONCAT(p.Prod_Name, ' by ', u.User_Name) as details, 
          p.Prod_CreatedAt as created_at, 'product' as type
          FROM products p
          JOIN user_accounts u ON p.OwnerID = u.UserID
          ORDER BY p.Prod_CreatedAt DESC 
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_activities = array_merge($recent_activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Recent bookings
$query = "SELECT 'New Booking' as activity,
          CONCAT(p.Prod_Name, ' booked by ', u.User_Name) as details,
          b.Book_CreatedAt as created_at, 'booking' as type
          FROM bookings b
          JOIN products p ON b.ProductID = p.ProductID  
          JOIN user_accounts u ON b.RenterID = u.UserID
          ORDER BY b.Book_CreatedAt DESC
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_activities = array_merge($recent_activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Sort all activities by date
usort($recent_activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Take only the 10 most recent
$recent_activities = array_slice($recent_activities, 0, 10);

// Top performing products
$query = "SELECT p.Prod_Name, u.User_Name as Owner_Name,
          COUNT(b.BookingID) as booking_count,
          SUM(b.Book_TotalAmount) as total_revenue
          FROM products p
          LEFT JOIN bookings b ON p.ProductID = b.ProductID AND b.Book_Status IN ('Active', 'Completed')
          JOIN user_accounts u ON p.OwnerID = u.UserID
          WHERE p.Prod_Status = 'Active'
          GROUP BY p.ProductID
          ORDER BY booking_count DESC, total_revenue DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - RentHub PH</title>
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
        }
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.users { border-left-color: #007bff; }
        .stat-card.products { border-left-color: #28a745; }
        .stat-card.bookings { border-left-color: #ffc107; }
        .stat-card.revenue { border-left-color: #dc3545; }
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
                <a class="nav-link active" href="dashboard.php">
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
                <a class="nav-link" href="refunds.php">
                    <i class="fas fa-undo"></i> Refunds Management
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
                <a class="nav-link" href="reports.php">
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
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <h5 class="mb-0">Admin Dashboard</h5>
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

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card users">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_users']); ?></div>
                                    <small class="text-muted">
                                        <?php echo $stats['renters']; ?> Renters, <?php echo $stats['owners']; ?> Owners
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card products">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Products
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_products']); ?></div>
                                    <small class="text-muted">
                                        <?php echo $product_stats['featured_products']; ?> Featured
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x text-success"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Total Bookings
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_bookings']); ?></div>
                                    <small class="text-muted">
                                        <?php echo $booking_stats['active']; ?> Active, <?php echo $booking_stats['pending']; ?> Pending
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card revenue">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Total Revenue
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                                    <small class="text-muted">
                                        Commission: ₱<?php echo number_format($stats['total_commission'], 2); ?>
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Statistics -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Monthly Revenue Trend (Last 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($monthly_revenue)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($monthly_revenue as $month): ?>
                                            <tr>
                                                <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                                <td><?php echo number_format($month['bookings']); ?></td>
                                                <td>₱<?php echo number_format($month['revenue'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No revenue data available for the last 6 months.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Booking Status Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Completed</span>
                                    <span class="badge bg-success"><?php echo $booking_stats['completed']; ?></span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $stats['total_bookings'] > 0 ? ($booking_stats['completed'] / $stats['total_bookings']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Active</span>
                                    <span class="badge bg-primary"><?php echo $booking_stats['active']; ?></span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $stats['total_bookings'] > 0 ? ($booking_stats['active'] / $stats['total_bookings']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Pending</span>
                                    <span class="badge bg-warning"><?php echo $booking_stats['pending']; ?></span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $stats['total_bookings'] > 0 ? ($booking_stats['pending'] / $stats['total_bookings']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Cancelled</span>
                                    <span class="badge bg-danger"><?php echo $booking_stats['cancelled']; ?></span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo $stats['total_bookings'] > 0 ? ($booking_stats['cancelled'] / $stats['total_bookings']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities and Top Products -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_activities as $activity): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-<?php 
                                            echo $activity['type'] == 'user' ? 'user-plus' : 
                                                ($activity['type'] == 'product' ? 'box' : 'calendar-check'); 
                                        ?> me-2 text-<?php 
                                            echo $activity['type'] == 'user' ? 'primary' : 
                                                ($activity['type'] == 'product' ? 'success' : 'warning'); 
                                        ?>"></i>
                                        <strong><?php echo htmlspecialchars($activity['activity']); ?>:</strong>
                                        <?php echo htmlspecialchars($activity['details']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top Performing Products</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($top_products)): ?>
                                <?php foreach($top_products as $index => $product): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="badge bg-primary rounded-pill me-3"><?php echo $index + 1; ?></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($product['Prod_Name']); ?></div>
                                        <small class="text-muted">by <?php echo htmlspecialchars($product['Owner_Name']); ?></small>
                                        <div class="small">
                                            <?php echo $product['booking_count']; ?> bookings • 
                                            ₱<?php echo number_format($product['total_revenue'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No product data available yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Manage Users
                                </a>
                                <a href="products.php" class="btn btn-success">
                                    <i class="fas fa-box"></i> Manage Products
                                </a>
                                <a href="bookings.php" class="btn btn-warning">
                                    <i class="fas fa-calendar-check"></i> Manage Bookings
                                </a>
                                <a href="commissions.php" class="btn btn-info">
                                    <i class="fas fa-percentage"></i> View Commissions
                                </a>
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="fas fa-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Server Status</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Database</span>
                                <span class="badge bg-success">Connected</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Active Users</span>
                                <span class="text-muted small"><?php echo $stats['total_users']; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Last Updated</span>
                                <span class="text-muted small"><?php echo date('M j, Y H:i'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>