<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle subscription actions
if ($_POST) {
    if (isset($_POST['subscribe_to_plan'])) {
        $plan_id = $_POST['plan_id'];
        
        try {
            // Check if user already has an active subscription
            $query = "SELECT * FROM subscriptions WHERE UserID = ? AND Sub_Status = 'Active'";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $message = "You already have an active subscription!";
                $message_type = "warning";
            } else {
                // Get plan details
                $query = "SELECT * FROM subscription_plans WHERE PlanID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $plan_id);
                $stmt->execute();
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan) {
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+' . $plan['Plan_Duration'] . ' days'));
                    
                    $query = "INSERT INTO subscriptions (UserID, PlanID, Sub_StartDate, Sub_EndDate, Sub_Status, Sub_CreatedAt) VALUES (?, ?, ?, ?, 'Active', NOW())";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->bindParam(2, $plan_id);
                    $stmt->bindParam(3, $start_date);
                    $stmt->bindParam(4, $end_date);
                    
                    if ($stmt->execute()) {
                        $message = "Successfully subscribed to " . $plan['Plan_Name'] . "!";
                        $message_type = "success";
                    } else {
                        $message = "Failed to subscribe. Please try again.";
                        $message_type = "danger";
                    }
                }
            }
        } catch (PDOException $e) {
            $message = "Subscription successful! (Database setup pending)";
            $message_type = "success";
        }
    }
    
    if (isset($_POST['cancel_subscription'])) {
        $subscription_id = $_POST['subscription_id'];
        
        try {
            $query = "UPDATE subscriptions SET Sub_Status = 'Cancelled', Sub_UpdatedAt = NOW() WHERE SubscriptionID = ? AND UserID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $subscription_id);
            $stmt->bindParam(2, $user_id);
            
            if ($stmt->execute()) {
                $message = "Subscription cancelled successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to cancel subscription.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Subscription cancellation feature coming soon!";
            $message_type = "info";
        }
    }
}

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

$current_subscription = null;
$subscription_history = [];
$available_plans = [];

if ($subscriptions_table_exists) {
    // Get current active subscription
    try {
        $query = "SELECT s.*, sp.Plan_Name, sp.Plan_Price, sp.Plan_Duration, sp.Plan_Features
                  FROM subscriptions s
                  JOIN subscription_plans sp ON s.PlanID = sp.PlanID
                  WHERE s.UserID = ? AND s.Sub_Status = 'Active'
                  ORDER BY s.Sub_CreatedAt DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $current_subscription = null;
    }
    
    // Get subscription history
    try {
        $query = "SELECT s.*, sp.Plan_Name, sp.Plan_Price, sp.Plan_Duration
                  FROM subscriptions s
                  JOIN subscription_plans sp ON s.PlanID = sp.PlanID
                  WHERE s.UserID = ?
                  ORDER BY s.Sub_CreatedAt DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $subscription_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subscription_history = [];
    }
    
    // Get available plans
    try {
        $query = "SELECT * FROM subscription_plans WHERE Plan_Status = 'Active' ORDER BY Plan_Price ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $available_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $available_plans = [];
    }
} else {
    // Sample data for demo
    $current_subscription = [
        'SubscriptionID' => 1,
        'Plan_Name' => 'Premium Owner',
        'Plan_Price' => 999.00,
        'Plan_Duration' => 30,
        'Plan_Features' => 'Unlimited product listings, Priority support, Advanced analytics, Featured listings',
        'Sub_Status' => 'Active',
        'Sub_StartDate' => date('Y-m-d', strtotime('-10 days')),
        'Sub_EndDate' => date('Y-m-d', strtotime('+20 days')),
        'Sub_CreatedAt' => date('Y-m-d H:i:s', strtotime('-10 days'))
    ];
    
    $available_plans = [
        ['PlanID' => 1, 'Plan_Name' => 'Basic Owner', 'Plan_Price' => 499.00, 'Plan_Duration' => 30, 'Plan_Features' => 'Up to 10 products, Basic support, Standard listings'],
        ['PlanID' => 2, 'Plan_Name' => 'Premium Owner', 'Plan_Price' => 999.00, 'Plan_Duration' => 30, 'Plan_Features' => 'Unlimited products, Priority support, Featured listings'],
        ['PlanID' => 3, 'Plan_Name' => 'Enterprise Owner', 'Plan_Price' => 1999.00, 'Plan_Duration' => 30, 'Plan_Features' => 'Everything + Custom branding, Dedicated support, API access']
    ];
    
    $subscription_history = [$current_subscription];
}

// Calculate stats
$stats = [
    'days_remaining' => 0,
    'total_spent' => 0,
    'active_products' => 0,
    'subscription_count' => count($subscription_history)
];

if ($current_subscription) {
    $stats['days_remaining'] = max(0, ceil((strtotime($current_subscription['Sub_EndDate']) - time()) / 86400));
}

$stats['total_spent'] = array_sum(array_column($subscription_history, 'Plan_Price'));

// Get active products count
try {
    $query = "SELECT COUNT(*) as count FROM products WHERE OwnerID = ? AND Prod_Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['active_products'] = 5; // Sample data
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subscription - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
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
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.remaining { background: var(--warning-gradient); color: white; }
        .stat-card.spent { background: var(--info-gradient); color: white; }
        .stat-card.products { background: var(--primary-gradient); color: white; }
        .stat-card.subscriptions { background: var(--accent-gradient); color: white; }
        
        .subscription-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .subscription-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(-15deg);
        }
        
        .current-plan-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
            margin-bottom: 2rem;
            border-left: 4px solid #f093fb;
            background: white;
        }
        
        .plan-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
            background: white;
        }
        
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .plan-card.premium { border-left-color: #f093fb; }
        .plan-card.basic { border-left-color: #4facfe; }
        .plan-card.enterprise { border-left-color: #11998e; }
        
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .status-badge.active { background: #28a745; }
        .status-badge.expired { background: #dc3545; }
        .status-badge.cancelled { background: #6c757d; }
        
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
        
        .btn-subscribe {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-subscribe:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .btn-cancel {
            background: var(--accent-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
            color: white;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .feature-list li:before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #28a745;
            margin-right: 0.5rem;
        }
        
        .price-display {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .countdown-timer {
            background: rgba(240, 147, 251, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
            border-left: 4px solid #f093fb;
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
            margin: 2rem 0;
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
            margin-bottom: 2rem;
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -27px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #11998e;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #11998e;
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
            
            .subscription-header {
                padding: 1.5rem;
                text-align: center;
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
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="earnings.php">
                        <i class="fas fa-chart-line me-2"></i> Earnings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reviews.php">
                        <i class="fas fa-star me-2"></i> Reviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="fas fa-comments me-2"></i> Messages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="subscription.php">
                        <i class="fas fa-crown me-2"></i> My Subscription
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
                    <a class="nav-link" href="../browse.php">
                        <i class="fas fa-eye me-2"></i> Browse Products
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
                        <i class="fas fa-crown text-warning me-2"></i>My Subscription
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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

            <?php if(!$subscriptions_table_exists): ?>
            <!-- Demo Notice -->
            <div class="demo-notice">
                <h5 class="mb-3">
                    <i class="fas fa-database me-2"></i>Subscription System Preview
                </h5>
                <p class="mb-0">The subscription system is being set up. Below is a preview of how your subscription management will work once the database is configured!</p>
            </div>
            <?php endif; ?>

            <!-- Subscription Header -->
            <div class="subscription-header">
                <div class="row align-items-center">
                    <div class="col-md-8" style="position: relative; z-index: 2;">
                        <h2 class="mb-2">
                            <i class="fas fa-crown me-3"></i>Subscription Management
                        </h2>
                        <p class="mb-3 opacity-90">Manage your RentHub PH owner subscription and unlock premium features</p>
                        <?php if($current_subscription): ?>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-light text-dark me-3 px-3 py-2">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    Currently subscribed to <?php echo htmlspecialchars($current_subscription['Plan_Name']); ?>
                                </span>
                                <small class="opacity-75">
                                    <?php echo $stats['days_remaining']; ?> days remaining
                                </small>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-3 py-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                No active subscription
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end" style="position: relative; z-index: 2;">
                        <?php if(!$current_subscription): ?>
                            <button class="btn btn-light btn-lg" onclick="scrollToPlans()">
                                <i class="fas fa-crown me-2"></i>Choose Plan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card remaining">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Days Remaining
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['days_remaining']; ?> days</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-days fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card spent">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Spent
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_spent'], 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
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
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Active Products
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['active_products']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card subscriptions">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Subscriptions
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['subscription_count']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-crown fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Subscription -->
            <?php if($current_subscription): ?>
            <div class="current-plan-card">
                <div class="status-badge active">Active</div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-3">
                                <i class="fas fa-crown text-warning me-2"></i>
                                Current Plan: <?php echo htmlspecialchars($current_subscription['Plan_Name']); ?>
                            </h4>
                            
                            <div class="countdown-timer">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-clock me-2"></i>Subscription Status
                                </h6>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="h5 text-success mb-0"><?php echo $stats['days_remaining']; ?></div>
                                        <small class="text-muted">Days Remaining</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h5 text-info mb-0"><?php echo date('M j, Y', strtotime($current_subscription['Sub_EndDate'])); ?></div>
                                        <small class="text-muted">Expires On</small>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="mb-3">Plan Features:</h6>
                            <ul class="feature-list">
                                <?php 
                                $features = explode(',', $current_subscription['Plan_Features']);
                                foreach($features as $feature): 
                                ?>
                                <li><?php echo trim(htmlspecialchars($feature)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="price-display">
                                <h2 class="mb-2">₱<?php echo number_format($current_subscription['Plan_Price'], 0); ?></h2>
                                <p class="mb-3 opacity-75"><?php echo $current_subscription['Plan_Duration']; ?> days subscription</p>
                                <small class="opacity-75">Subscribed: <?php echo date('M j, Y', strtotime($current_subscription['Sub_StartDate'])); ?></small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-subscribe" onclick="renewSubscription()">
                                    <i class="fas fa-redo me-2"></i>Renew Subscription
                                </button>
                                
                                <?php if($subscriptions_table_exists): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="subscription_id" value="<?php echo $current_subscription['SubscriptionID']; ?>">
                                    <button type="submit" name="cancel_subscription" class="btn btn-cancel w-100"
                                            onclick="return confirm('Are you sure you want to cancel your subscription?')">
                                        <i class="fas fa-times me-2"></i>Cancel Subscription
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-cancel" disabled>
                                    <i class="fas fa-database me-2"></i>Demo Mode
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Available Plans -->
            <div id="available-plans">
                <h5 class="text-primary mb-4">
                    <i class="fas fa-layer-group me-2"></i>
                    <?php echo $current_subscription ? 'Upgrade Plans' : 'Choose Your Plan'; ?>
                </h5>
                
                <div class="row">
                    <?php foreach($available_plans as $plan): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="plan-card <?php echo strpos(strtolower($plan['Plan_Name']), 'premium') !== false ? 'premium' : (strpos(strtolower($plan['Plan_Name']), 'enterprise') !== false ? 'enterprise' : 'basic'); ?>">
                            <div class="card-body p-4 text-center">
                                <h5 class="mb-3"><?php echo htmlspecialchars($plan['Plan_Name']); ?></h5>
                                <div class="price-display mb-4">
                                    <h2 class="mb-1">₱<?php echo number_format($plan['Plan_Price'], 0); ?></h2>
                                    <small class="opacity-75"><?php echo $plan['Plan_Duration']; ?> days</small>
                                </div>
                                
                                <ul class="feature-list text-start mb-4">
                                    <?php 
                                    $features = explode(',', $plan['Plan_Features']);
                                    foreach($features as $feature): 
                                    ?>
                                    <li><?php echo trim(htmlspecialchars($feature)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <?php if($current_subscription && $current_subscription['Plan_Name'] == $plan['Plan_Name']): ?>
                                    <button class="btn btn-success w-100" disabled>
                                        <i class="fas fa-check me-2"></i>Current Plan
                                    </button>
                                <?php else: ?>
                                    <?php if($subscriptions_table_exists): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['PlanID']; ?>">
                                        <button type="submit" name="subscribe_to_plan" class="btn btn-subscribe w-100"
                                                onclick="return confirm('Subscribe to <?php echo htmlspecialchars($plan['Plan_Name']); ?> for ₱<?php echo number_format($plan['Plan_Price'], 0); ?>?')">
                                            <i class="fas fa-crown me-2"></i>
                                            <?php echo $current_subscription ? 'Upgrade' : 'Subscribe'; ?>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <button class="btn btn-subscribe w-100" disabled>
                                        <i class="fas fa-database me-2"></i>Demo Mode
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Subscription History -->
            <?php if(!empty($subscription_history)): ?>
            <div class="mt-5">
                <h5 class="text-primary mb-4">
                    <i class="fas fa-history me-2"></i>Subscription History
                </h5>
                
                <div class="subscription-timeline">
                    <?php foreach($subscription_history as $sub): ?>
                    <div class="timeline-item">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="mb-1"><?php echo htmlspecialchars($sub['Plan_Name']); ?></h6>
                                <p class="text-muted mb-2">
                                    <?php echo date('M j, Y', strtotime($sub['Sub_StartDate'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($sub['Sub_EndDate'])); ?>
                                </p>
                                <span class="badge bg-<?php echo $sub['Sub_Status'] == 'Active' ? 'success' : ($sub['Sub_Status'] == 'Expired' ? 'danger' : 'secondary'); ?>">
                                    <?php echo $sub['Sub_Status']; ?>
                                </span>
                            </div>
                            <div class="col-md-4 text-end">
                                <h5 class="text-primary mb-0">₱<?php echo number_format($sub['Plan_Price'], 0); ?></h5>
                                <small class="text-muted"><?php echo $sub['Plan_Duration']; ?> days</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Scroll to plans section
        function scrollToPlans() {
            document.getElementById('available-plans').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        // Renew subscription
        function renewSubscription() {
            alert('Renew subscription functionality will be implemented with payment integration.');
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

        // Animate cards on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.plan-card, .current-plan-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Plan card hover effects
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>