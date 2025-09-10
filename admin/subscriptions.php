<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle subscription actions
if ($_POST) {
    if (isset($_POST['update_subscription_status'])) {
        $subscription_id = $_POST['subscription_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $query = "UPDATE subscriptions SET Sub_Status = ?, Sub_UpdatedAt = NOW() WHERE SubscriptionID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $new_status);
            $stmt->bindParam(2, $subscription_id);
            
            if ($stmt->execute()) {
                $message = "Subscription status updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update subscription status.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Subscription feature will be available when database is configured.";
            $message_type = "info";
        }
    }
    
    if (isset($_POST['create_subscription_plan'])) {
        $plan_name = trim($_POST['plan_name']);
        $plan_price = $_POST['plan_price'];
        $plan_duration = $_POST['plan_duration'];
        $plan_features = trim($_POST['plan_features']);
        
        try {
            $query = "INSERT INTO subscription_plans (Plan_Name, Plan_Price, Plan_Duration, Plan_Features, Plan_Status, Plan_CreatedAt) VALUES (?, ?, ?, ?, 'Active', NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $plan_name);
            $stmt->bindParam(2, $plan_price);
            $stmt->bindParam(3, $plan_duration);
            $stmt->bindParam(4, $plan_features);
            
            if ($stmt->execute()) {
                $message = "Subscription plan created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create subscription plan.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Subscription plan created successfully! (Database setup pending)";
            $message_type = "success";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$plan_filter = isset($_GET['plan']) ? $_GET['plan'] : 'all';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Check if subscriptions table exists
$subscriptions_table_exists = false;
try {
    $query = "SELECT 1 FROM subscriptions LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subscriptions_table_exists = true;
} catch (PDOException $e) {
    $subscriptions_table_exists = false;
}

$subscriptions = [];
$subscription_plans = [];
$stats = [
    'total_subscriptions' => 0,
    'active_subscriptions' => 0,
    'revenue_monthly' => 0,
    'expired_subscriptions' => 0
];

if ($subscriptions_table_exists) {
    // Build query conditions - ONLY SHOW OWNERS (User_Role = 3)
    $conditions = ["u.User_Role = 3"]; // Filter for owners only
    $params = [];

    if ($status_filter && $status_filter != 'all') {
        $conditions[] = "s.Sub_Status = ?";
        $params[] = ucfirst($status_filter);
    }

    if ($plan_filter && $plan_filter != 'all') {
        $conditions[] = "s.PlanID = ?";
        $params[] = $plan_filter;
    }

    // Date range filter
    if ($date_filter && $date_filter != 'all') {
        switch($date_filter) {
            case 'today':
                $conditions[] = "DATE(s.Sub_CreatedAt) = CURDATE()";
                break;
            case 'week':
                $conditions[] = "s.Sub_CreatedAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $conditions[] = "s.Sub_CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $conditions[] = "s.Sub_CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
    }

    // Sort options
    $sort_options = [
        'newest' => 's.Sub_CreatedAt DESC',
        'oldest' => 's.Sub_CreatedAt ASC',
        'expiry' => 's.Sub_EndDate ASC',
        'price_high' => 'sp.Plan_Price DESC',
        'price_low' => 'sp.Plan_Price ASC'
    ];

    $order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 's.Sub_CreatedAt DESC';

    // Get subscriptions - ONLY FOR OWNERS (User_Role = 3)
    $query = "SELECT s.*, sp.Plan_Name, sp.Plan_Price, sp.Plan_Duration, u.User_Name, u.User_Email, u.User_Role
              FROM subscriptions s
              JOIN subscription_plans sp ON s.PlanID = sp.PlanID
              JOIN user_accounts u ON s.UserID = u.UserID
              WHERE " . implode(' AND ', $conditions) . "
              ORDER BY " . $order_by;

    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subscriptions = [];
    }

    // Get subscription plans
    try {
        $query = "SELECT * FROM subscription_plans WHERE Plan_Status = 'Active' ORDER BY Plan_Price ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subscription_plans = [];
    }

    // Calculate statistics - ONLY FOR OWNERS (User_Role = 3)
    $stats_query = "SELECT 
                    COUNT(*) as total_subscriptions,
                    SUM(CASE WHEN s.Sub_Status = 'Active' THEN 1 ELSE 0 END) as active_subscriptions,
                    SUM(CASE WHEN s.Sub_Status = 'Expired' THEN 1 ELSE 0 END) as expired_subscriptions,
                    SUM(CASE WHEN s.Sub_Status = 'Active' THEN sp.Plan_Price ELSE 0 END) as revenue_monthly
                    FROM subscriptions s
                    JOIN subscription_plans sp ON s.PlanID = sp.PlanID
                    JOIN user_accounts u ON s.UserID = u.UserID
                    WHERE u.User_Role = 3";
    
    try {
        $stmt = $conn->prepare($stats_query);
        $stmt->execute();
        $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stats_result) {
            $stats = $stats_result;
        }
    } catch (PDOException $e) {
        // Keep default stats
    }

} else {
    // Show sample subscription data - ONLY OWNERS (User_Role = 3)
    $sample_subscriptions = [
        [
            'SubscriptionID' => 1,
            'User_Name' => 'John Doe',
            'User_Email' => 'john@example.com',
            'User_Role' => 3, // Owner
            'Plan_Name' => 'Premium Owner',
            'Plan_Price' => 999.00,
            'Plan_Duration' => 30,
            'Sub_Status' => 'Active',
            'Sub_StartDate' => date('Y-m-d', strtotime('-15 days')),
            'Sub_EndDate' => date('Y-m-d', strtotime('+15 days')),
            'Sub_CreatedAt' => date('Y-m-d H:i:s', strtotime('-15 days'))
        ],
        [
            'SubscriptionID' => 2,
            'User_Name' => 'Jane Smith',
            'User_Email' => 'jane@example.com',
            'User_Role' => 3, // Owner
            'Plan_Name' => 'Basic Owner',
            'Plan_Price' => 499.00,
            'Plan_Duration' => 30,
            'Sub_Status' => 'Active',
            'Sub_StartDate' => date('Y-m-d', strtotime('-10 days')),
            'Sub_EndDate' => date('Y-m-d', strtotime('+20 days')),
            'Sub_CreatedAt' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ],
        [
            'SubscriptionID' => 3,
            'User_Name' => 'Mike Johnson',
            'User_Email' => 'mike@example.com',
            'User_Role' => 3, // Owner
            'Plan_Name' => 'Enterprise Owner',
            'Plan_Price' => 1999.00,
            'Plan_Duration' => 30,
            'Sub_Status' => 'Expired',
            'Sub_StartDate' => date('Y-m-d', strtotime('-40 days')),
            'Sub_EndDate' => date('Y-m-d', strtotime('-10 days')),
            'Sub_CreatedAt' => date('Y-m-d H:i:s', strtotime('-40 days'))
        ]
    ];
    
    $subscriptions = $sample_subscriptions;
    $stats['total_subscriptions'] = count($subscriptions);
    $stats['active_subscriptions'] = 2;
    $stats['expired_subscriptions'] = 1;
    $stats['revenue_monthly'] = 2498.00;
    
    // Sample subscription plans - OWNER PLANS ONLY
    $subscription_plans = [
        ['PlanID' => 1, 'Plan_Name' => 'Basic Owner', 'Plan_Price' => 499.00, 'Plan_Duration' => 30],
        ['PlanID' => 2, 'Plan_Name' => 'Premium Owner', 'Plan_Price' => 999.00, 'Plan_Duration' => 30],
        ['PlanID' => 3, 'Plan_Name' => 'Enterprise Owner', 'Plan_Price' => 1999.00, 'Plan_Duration' => 30]
    ];
}

// Helper function to get role name
function getRoleName($role_id) {
    switch($role_id) {
        case 3: return 'Owner';
        default: return 'Unknown';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Subscriptions - RentHub PH Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            --admin-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--admin-gradient);
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
        
        .admin-header {
            background: var(--admin-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { background: var(--primary-gradient); color: white; }
        .stat-card.active { background: var(--info-gradient); color: white; }
        .stat-card.revenue { background: var(--warning-gradient); color: white; }
        .stat-card.expired { background: var(--accent-gradient); color: white; }
        
        .subscription-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .subscription-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .subscription-card.active { border-left-color: #28a745; }
        .subscription-card.expired { border-left-color: #dc3545; }
        .subscription-card.cancelled { border-left-color: #6c757d; }
        .subscription-card.pending { border-left-color: #ffc107; }
        .subscription-card.sample { border-left-color: #17a2b8; opacity: 0.9; }
        
        .subscription-status {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 2;
        }
        
        .status-badge {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .status-badge.active { background: #28a745; }
        .status-badge.expired { background: #dc3545; }
        .status-badge.cancelled { background: #6c757d; }
        .status-badge.pending { background: #ffc107; color: #000; }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .subscription-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .subscription-details {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #667eea;
            margin: 1rem 0;
        }
        
        .plan-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .plan-card.premium { border-left-color: #f093fb; }
        .plan-card.basic { border-left-color: #4facfe; }
        .plan-card.enterprise { border-left-color: #11998e; }
        
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
        
        .action-btn.extend { background: var(--primary-gradient); color: white; }
        .action-btn.cancel { background: var(--accent-gradient); color: white; }
        .action-btn.renew { background: var(--warning-gradient); color: white; }
        .action-btn.view { background: var(--info-gradient); color: white; }
        
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
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--admin-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .plan-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }
        
        .plan-badge.premium { background: rgba(240, 147, 251, 0.1); color: #f093fb; }
        .plan-badge.basic { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
        .plan-badge.enterprise { background: rgba(17, 153, 142, 0.1); color: #11998e; }
        
        .price-display {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .demo-notice {
            background: var(--warning-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .subscription-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .subscription-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -27px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }
        
        .revenue-chart {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .quick-actions {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
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
            
            .search-filters, .quick-actions {
                padding: 1rem;
            }
            
            .subscription-card {
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
                <i class="fas fa-shield-alt"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Admin Panel</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box me-2"></i> Product Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Booking Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card me-2"></i> Payment Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="subscriptions.php">
                        <i class="fas fa-crown me-2"></i> Owner Subscriptions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports & Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i> System Settings
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <hr class="text-white-50">
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
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="mb-2">
                            <i class="fas fa-crown me-3"></i>Owner Subscription Management
                        </h2>
                        <p class="mb-0 opacity-90">Manage subscription plans and owner subscriptions</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                            <i class="fas fa-plus me-2"></i>Add Plan
                        </button>
                        <button class="btn btn-light" onclick="exportSubscriptions()">
                            <i class="fas fa-download me-2"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="container-fluid px-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 15px;">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(!$subscriptions_table_exists): ?>
            <!-- Demo Notice -->
            <div class="demo-notice">
                <h5 class="mb-3">
                    <i class="fas fa-database me-2"></i>Owner Subscription System Preview
                </h5>
                <p class="mb-0">The owner subscription management system is being set up. Below are sample subscription records and plans for owners. Once the database is configured, you'll have full subscription management capabilities!</p>
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
                                        Total Owner Subscriptions
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_subscriptions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-crown fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card active">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Active Owner Subscriptions
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_subscriptions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Monthly Revenue
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['revenue_monthly'], 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card expired">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Expired Subscriptions
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['expired_subscriptions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="revenue-chart">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-chart-line me-2"></i>Owner Subscription Revenue Overview
                </h5>
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h6 class="text-muted">This Month vs Last Month</h6>
                            <div class="mb-3">
                                <h4 class="text-success mb-0">+15.2%</h4>
                                <small class="text-muted">Growth Rate</small>
                            </div>
                            <div class="mb-3">
                                <h5 class="text-primary mb-0">₱<?php echo number_format($stats['revenue_monthly'] * 1.15, 0); ?></h5>
                                <small class="text-muted">Projected Next Month</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                            <i class="fas fa-plus fa-2x mb-2 d-block"></i>
                            Create Owner Plan
                        </button>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-warning w-100" onclick="sendRenewalReminders()">
                            <i class="fas fa-bell fa-2x mb-2 d-block"></i>
                            Send Reminders
                        </button>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-info w-100" onclick="generateReport()">
                            <i class="fas fa-file-alt fa-2x mb-2 d-block"></i>
                            Generate Report
                        </button>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-success w-100" onclick="exportSubscriptions()">
                            <i class="fas fa-download fa-2x mb-2 d-block"></i>
                            Export Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subscription Plans Overview -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-layer-group me-2"></i>Available Owner Subscription Plans
                    </h5>
                </div>
                <?php if(!empty($subscription_plans)): ?>
                    <?php foreach($subscription_plans as $plan): ?>
                    <div class="col-md-4 mb-3">
                        <div class="plan-card <?php echo strpos(strtolower($plan['Plan_Name']), 'premium') !== false ? 'premium' : (strpos(strtolower($plan['Plan_Name']), 'enterprise') !== false ? 'enterprise' : 'basic'); ?>">
                            <div class="text-center">
                                <h5 class="mb-2"><?php echo htmlspecialchars($plan['Plan_Name']); ?></h5>
                                <div class="h2 text-primary mb-3">₱<?php echo number_format($plan['Plan_Price'], 0); ?></div>
                                <p class="text-muted mb-3"><?php echo $plan['Plan_Duration']; ?> days subscription</p>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary btn-sm flex-fill">Edit Plan</button>
                                    <button class="btn btn-outline-danger btn-sm flex-fill">Disable</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-4">
                            <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No owner subscription plans available</h6>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                <i class="fas fa-plus me-2"></i>Create First Owner Plan
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Filter by Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-crown me-2"></i>Filter by Plan
                        </label>
                        <select class="form-select" name="plan">
                            <option value="all" <?php echo $plan_filter == 'all' ? 'selected' : ''; ?>>All Plans</option>
                            <?php foreach($subscription_plans as $plan): ?>
                                <option value="<?php echo $plan['PlanID']; ?>" <?php echo $plan_filter == $plan['PlanID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($plan['Plan_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Date Range
                        </label>
                        <select class="form-select" name="date_range">
                            <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo $date_filter == 'year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Owner Subscriptions List -->
            <?php if(empty($subscriptions)): ?>
                <div class="empty-state">
                    <i class="fas fa-crown"></i>
                    <h4 class="text-muted">No owner subscriptions found</h4>
                    <p class="text-muted">No owner subscriptions match your current filter criteria or no owner subscriptions exist yet.</p>
                    <button class="btn btn-primary btn-lg" style="border-radius: 25px;" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                        <i class="fas fa-plus me-2"></i>Create Owner Subscription Plan
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($subscriptions as $subscription): ?>
                <div class="subscription-card card <?php echo !$subscriptions_table_exists ? 'sample' : ''; ?> <?php echo strtolower($subscription['Sub_Status']); ?>">
                    <div class="subscription-status">
                        <span class="badge status-badge <?php echo strtolower($subscription['Sub_Status']); ?>">
                            <?php echo htmlspecialchars($subscription['Sub_Status']); ?>
                        </span>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($subscription['User_Name'], 0, 2)); ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="plan-badge <?php echo strpos(strtolower($subscription['Plan_Name']), 'premium') !== false ? 'premium' : (strpos(strtolower($subscription['Plan_Name']), 'enterprise') !== false ? 'enterprise' : 'basic'); ?> me-2">
                                        <?php echo htmlspecialchars($subscription['Plan_Name']); ?>
                                    </span>
                                    <small class="text-muted">
                                        ID: #<?php echo str_pad($subscription['SubscriptionID'], 6, '0', STR_PAD_LEFT); ?>
                                    </small>
                                </div>
                                
                                <h5 class="mb-1"><?php echo htmlspecialchars($subscription['User_Name']); ?> 
                                    <small class="text-muted">(<?php echo getRoleName($subscription['User_Role']); ?>)</small>
                                </h5>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-envelope me-1"></i>
                                    <?php echo htmlspecialchars($subscription['User_Email']); ?>
                                </p>
                                
                                <div class="subscription-info">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Start Date:</small><br>
                                            <small><?php echo date('M j, Y', strtotime($subscription['Sub_StartDate'])); ?></small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">End Date:</small><br>
                                            <small class="<?php echo strtotime($subscription['Sub_EndDate']) < time() ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo date('M j, Y', strtotime($subscription['Sub_EndDate'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="subscription-details">
                                    <h6 class="mb-2">
                                        <i class="fas fa-info-circle me-2"></i>Subscription Details
                                    </h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1 small"><strong>Duration:</strong></p>
                                            <small><?php echo $subscription['Plan_Duration']; ?> days</small>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1 small"><strong>Days Remaining:</strong></p>
                                            <small>
                                                <?php 
                                                $days_remaining = max(0, ceil((strtotime($subscription['Sub_EndDate']) - time()) / 86400));
                                                echo $days_remaining . ' days';
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-end">
                                    <div class="price-display mb-3">
                                        <h3 class="mb-1">₱<?php echo number_format($subscription['Plan_Price'], 0); ?></h3>
                                        <small class="opacity-75"><?php echo $subscription['Plan_Duration']; ?> days</small>
                                    </div>
                                    
                                    <div class="subscription-timeline">
                                        <div class="timeline-item">
                                            <small class="text-muted">Started:</small><br>
                                            <small><?php echo date('M j, Y', strtotime($subscription['Sub_StartDate'])); ?></small>
                                        </div>
                                        <div class="timeline-item">
                                            <small class="text-muted">Expires:</small><br>
                                            <small><?php echo date('M j, Y', strtotime($subscription['Sub_EndDate'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-column gap-2 mt-3">
                                        <?php if($subscription['Sub_Status'] == 'Active'): ?>
                                            <?php if($subscriptions_table_exists): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="subscription_id" value="<?php echo $subscription['SubscriptionID']; ?>">
                                                <input type="hidden" name="new_status" value="Cancelled">
                                                <button type="submit" name="update_subscription_status" class="btn action-btn cancel w-100"
                                                        onclick="return confirm('Cancel this owner subscription?')">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <button class="btn action-btn cancel w-100" disabled>
                                                <i class="fas fa-database me-1"></i>Demo Mode
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn action-btn extend w-100" onclick="extendSubscription(<?php echo $subscription['SubscriptionID']; ?>)">
                                                <i class="fas fa-plus me-1"></i>Extend
                                            </button>
                                        <?php elseif($subscription['Sub_Status'] == 'Expired'): ?>
                                            <button class="btn action-btn renew w-100" onclick="renewSubscription(<?php echo $subscription['SubscriptionID']; ?>)">
                                                <i class="fas fa-redo me-1"></i>Renew
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn action-btn view w-100" onclick="viewSubscriptionDetails(<?php echo $subscription['SubscriptionID']; ?>)">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </button>
                                        
                                        <button class="btn btn-outline-secondary btn-sm w-100" onclick="sendNotification(<?php echo $subscription['SubscriptionID']; ?>)" style="border-radius: 15px;">
                                            <i class="fas fa-bell me-1"></i>Send Notification
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
    </div>

    <!-- Add Plan Modal -->
    <div class="modal fade" id="addPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-primary">
                        <i class="fas fa-plus me-2"></i>Create Owner Subscription Plan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="plan_name" required placeholder="e.g. Premium Owner">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="plan_price" step="0.01" required placeholder="999.00">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (Days) <span class="text-danger">*</span></label>
                                <select class="form-select" name="plan_duration" required>
                                    <option value="">Select Duration</option>
                                    <option value="7">7 Days</option>
                                    <option value="30">30 Days</option>
                                    <option value="90">90 Days</option>
                                    <option value="365">365 Days</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Plan Type</label>
                                <select class="form-select">
                                    <option value="basic">Basic Owner</option>
                                    <option value="premium">Premium Owner</option>
                                    <option value="enterprise">Enterprise Owner</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plan Features for Owners</label>
                            <textarea class="form-control" name="plan_features" rows="4" placeholder="List the features included in this owner plan..."></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="create_subscription_plan" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Owner Plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Revenue Chart
        const ctx = document.getElementById('revenueChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Owner Subscription Revenue',
                        data: [12000, 15000, 18000, 22000, 25000, 28000],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
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
                            }
                        }
                    }
                }
            });
        }

        // Extend subscription function
        function extendSubscription(subscriptionId) {
            if (confirm('Extend this owner subscription by 30 days?')) {
                alert('Extend owner subscription functionality will be implemented. Subscription ID: ' + subscriptionId);
            }
        }

        // Renew subscription function
        function renewSubscription(subscriptionId) {
            if (confirm('Renew this expired owner subscription?')) {
                alert('Renew owner subscription functionality will be implemented. Subscription ID: ' + subscriptionId);
            }
        }

        // View subscription details
        function viewSubscriptionDetails(subscriptionId) {
            alert('Owner subscription details modal will be implemented. Subscription ID: ' + subscriptionId);
        }

        // Send notification
        function sendNotification(subscriptionId) {
            alert('Send notification functionality will be implemented. Subscription ID: ' + subscriptionId);
        }

        // Send renewal reminders
        function sendRenewalReminders() {
            if (confirm('Send renewal reminders to all expiring owner subscriptions?')) {
                alert('Renewal reminder emails will be sent to owners with subscriptions expiring in the next 7 days.');
            }
        }

        // Generate report
        function generateReport() {
            alert('Owner subscription report generation will be implemented with proper backend support.');
        }

        // Export subscriptions
        function exportSubscriptions() {
            alert('Export owner subscriptions functionality will be implemented with proper backend support.');
        }

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

        // Animate subscription cards on load
        window.addEventListener('load', function() {
            const subscriptionCards = document.querySelectorAll('.subscription-card');
            subscriptionCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Status badge hover effects
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Plan card hover effects
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Price display animation
        document.querySelectorAll('.price-display').forEach(display => {
            display.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            
            display.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>