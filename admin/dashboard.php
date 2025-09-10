<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

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

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$query = "SELECT SUM(PH_Amount) as total FROM payment_history WHERE PH_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent activities
$query = "SELECT 'User Registration' as activity, User_Name as details, User_CreatedAt as created_at 
          FROM user_accounts 
          ORDER BY User_CreatedAt DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <a class="nav-link" href="payments.php">
                    <i class="fas fa-money-bill"></i> Payments
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
            <li class="nav-item mt-3">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-arrow-left"></i> Back to Site
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
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities and Quick Actions -->
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
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Manage Users
                                </a>
                                <a href="categories.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Category
                                </a>
                                <a href="reports.php" class="btn btn-info">
                                    <i class="fas fa-download"></i> Generate Report
                                </a>
                                <a href="settings.php" class="btn btn-warning">
                                    <i class="fas fa-cog"></i> System Settings
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
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Last Backup</span>
                                <span class="text-muted small">2 hours ago</span>
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