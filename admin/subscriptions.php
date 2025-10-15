<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

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
            $query = "UPDATE user_subscriptions SET Sub_Status = ?, Sub_UpdatedAt = NOW() WHERE SubscriptionID = ?";
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
            $message = "Error updating subscription: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['update_payment_status'])) {
        $payment_id = $_POST['payment_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $query = "UPDATE subscription_payments SET SubPay_Status = ?, SubPay_ProcessedAt = NOW() WHERE SubPaymentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $new_status);
            $stmt->bindParam(2, $payment_id);
            
            if ($stmt->execute()) {
                // If payment is completed, activate the subscription
                if ($new_status == 'Completed') {
                    $query = "SELECT SubscriptionID FROM subscription_payments WHERE SubPaymentID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $payment_id);
                    $stmt->execute();
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($payment) {
                        $query = "UPDATE user_subscriptions SET Sub_Status = 'Active', Sub_UpdatedAt = NOW() WHERE SubscriptionID = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(1, $payment['SubscriptionID']);
                        $stmt->execute();
                    }
                }
                
                $message = "Payment status updated successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error updating payment: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['create_subscription_plan'])) {
        $plan_name = trim($_POST['plan_name']);
        $plan_description = trim($_POST['plan_description']);
        $plan_price = $_POST['plan_price'];
        $plan_duration = $_POST['plan_duration'];
        $plan_max_listings = $_POST['plan_max_listings'];
        $plan_featured_listings = $_POST['plan_featured_listings'];
        $plan_commission_rate = $_POST['plan_commission_rate'];
        $plan_is_active = 1; // Active by default
        
        try {
            $query = "INSERT INTO subscription_plans (Plan_Name, Plan_Description, Plan_Price, Plan_Duration, Plan_MaxListings, Plan_FeaturedListings, Plan_CommissionRate, Plan_IsActive, Plan_CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $plan_name);
            $stmt->bindParam(2, $plan_description);
            $stmt->bindParam(3, $plan_price);
            $stmt->bindParam(4, $plan_duration);
            $stmt->bindParam(5, $plan_max_listings);
            $stmt->bindParam(6, $plan_featured_listings);
            $stmt->bindParam(7, $plan_commission_rate);
            $stmt->bindParam(8, $plan_is_active);
            
            if ($stmt->execute()) {
                $message = "Subscription plan created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create subscription plan.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error creating subscription plan: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Check if tables exist
$user_subscriptions_table_exists = false;
$subscription_plans_table_exists = false;
$subscription_payments_table_exists = false;

try {
    $query = "SELECT 1 FROM user_subscriptions LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $user_subscriptions_table_exists = true;
} catch (PDOException $e) {
    $user_subscriptions_table_exists = false;
}

try {
    $query = "SELECT 1 FROM subscription_plans LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subscription_plans_table_exists = true;
} catch (PDOException $e) {
    $subscription_plans_table_exists = false;
}

try {
    $query = "SELECT 1 FROM subscription_payments LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subscription_payments_table_exists = true;
} catch (PDOException $e) {
    $subscription_payments_table_exists = false;
}

// Initialize stats
$stats = [
    'total_subscriptions' => 0,
    'active_subscriptions' => 0,
    'pending_subscriptions' => 0,
    'cancelled_subscriptions' => 0,
    'total_payments' => 0,
    'completed_payments' => 0,
    'pending_payments' => 0,
    'total_revenue' => 0
];

// Get subscription statistics from user_subscriptions table
if ($user_subscriptions_table_exists && $subscription_plans_table_exists) {
    try {
        // Total subscriptions for owners only
        $query = "SELECT COUNT(*) as total FROM user_subscriptions us 
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Active subscriptions for owners only
        $query = "SELECT COUNT(*) as total FROM user_subscriptions us 
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3 AND us.Sub_Status = 'Active'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['active_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pending subscriptions for owners only
        $query = "SELECT COUNT(*) as total FROM user_subscriptions us 
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3 AND us.Sub_Status = 'Pending'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['pending_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Cancelled subscriptions for owners only
        $query = "SELECT COUNT(*) as total FROM user_subscriptions us 
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3 AND us.Sub_Status = 'Cancelled'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['cancelled_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        // Keep default stats if queries fail
    }
}

// Get payment statistics from subscription_payments table
if ($subscription_payments_table_exists && $user_subscriptions_table_exists) {
    try {
        // Total payments
        $query = "SELECT COUNT(*) as total FROM subscription_payments sp 
                  JOIN user_subscriptions us ON sp.SubscriptionID = us.SubscriptionID
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Completed payments
        $query = "SELECT COUNT(*) as total FROM subscription_payments sp 
                  JOIN user_subscriptions us ON sp.SubscriptionID = us.SubscriptionID
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3 AND sp.SubPay_Status = 'Completed'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['completed_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pending payments
        $query = "SELECT COUNT(*) as total FROM subscription_payments sp 
                  JOIN user_subscriptions us ON sp.SubscriptionID = us.SubscriptionID
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3 AND sp.SubPay_Status = 'Pending'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total revenue from completed payments
        $query = "SELECT SUM(sp.SubPay_Amount) as total FROM subscription_payments sp 
                  JOIN user_subscriptions us ON sp.SubscriptionID = us.SubscriptionID
                  JOIN user_accounts u ON us.UserID = u.UserID 
                  WHERE u.User_Role = 3 AND sp.SubPay_Status = 'Completed'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        // Keep default stats if queries fail
    }
}

// Get recent subscription activities from user_subscriptions
$recent_activities = [];
if ($user_subscriptions_table_exists && $subscription_plans_table_exists) {
    $query = "SELECT 'User Subscription' as activity, 
                     CONCAT(sp.Plan_Name, ' - ', u.User_Name) as details, 
                     us.Sub_CreatedAt as created_at 
              FROM user_subscriptions us
              JOIN subscription_plans sp ON us.PlanID = sp.PlanID
              JOIN user_accounts u ON us.UserID = u.UserID
              WHERE u.User_Role = 3
              ORDER BY us.Sub_CreatedAt DESC 
              LIMIT 5";
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_activities = [];
    }
}

// Get current user subscriptions
$user_subscriptions = [];
if ($user_subscriptions_table_exists && $subscription_plans_table_exists) {
    $query = "SELECT us.*, sp.Plan_Name, sp.Plan_Description, sp.Plan_Price, sp.Plan_Duration, sp.Plan_MaxListings, sp.Plan_FeaturedListings, sp.Plan_CommissionRate, u.User_Name, u.User_Email, u.User_Role
              FROM user_subscriptions us
              JOIN subscription_plans sp ON us.PlanID = sp.PlanID
              JOIN user_accounts u ON us.UserID = u.UserID
              WHERE u.User_Role = 3
              ORDER BY us.Sub_CreatedAt DESC";
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $user_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $user_subscriptions = [];
    }
}

// Get subscription payments
$subscription_payments = [];
if ($subscription_payments_table_exists && $user_subscriptions_table_exists && $subscription_plans_table_exists) {
    $query = "SELECT sp.*, us.UserID, u.User_Name, spl.Plan_Name
              FROM subscription_payments sp
              JOIN user_subscriptions us ON sp.SubscriptionID = us.SubscriptionID
              JOIN user_accounts u ON us.UserID = u.UserID
              JOIN subscription_plans spl ON us.PlanID = spl.PlanID
              WHERE u.User_Role = 3
              ORDER BY sp.SubPay_CreatedAt DESC";
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $subscription_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subscription_payments = [];
    }
}

// Get available subscription plans
$subscription_plans = [];
if ($subscription_plans_table_exists) {
    $query = "SELECT * FROM subscription_plans WHERE Plan_IsActive = 1 ORDER BY Plan_Price ASC";
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subscription_plans = [];
    }
}

function getRoleName($role_id) {
    switch($role_id) {
        case 3: return 'Owner';
        default: return 'Unknown';
    }
}

function getDaysRemaining($end_date) {
    $days = ceil((strtotime($end_date) - time()) / 86400);
    return max(0, $days);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Subscriptions - RentHub PH</title>
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
        .stat-card.subscriptions { border-left-color: #007bff; }
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.revenue { border-left-color: #dc3545; }
        
        .subscription-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        .subscription-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .subscription-card.active { border-left-color: #28a745; }
        .subscription-card.pending { border-left-color: #ffc107; }
        .subscription-card.cancelled { border-left-color: #6c757d; }
        
        .plan-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .plan-card.basic { border-left-color: #007bff; }
        .plan-card.premium { border-left-color: #28a745; }
        .plan-card.enterprise { border-left-color: #ffc107; }
        
        .payment-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        .payment-card.completed { border-left-color: #28a745; }
        .payment-card.pending { border-left-color: #ffc107; }
        .payment-card.failed { border-left-color: #dc3545; }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
        }
        .plan-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            background-color: #e9ecef;
            color: #495057;
            border-radius: 0.25rem;
            font-weight: 600;
        }
        .plan-badge.premium { background-color: #e7f3ff; color: #0066cc; }
        .plan-badge.basic { background-color: #e8f5e8; color: #2d7d32; }
        .plan-badge.enterprise { background-color: #fff8e1; color: #f57c00; }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .feature-list li {
            padding: 0.25rem 0;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .feature-list li:before {
            content: "✓";
            color: #28a745;
            margin-right: 0.5rem;
            font-weight: bold;
        }
        
        .price-highlight {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }

        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .plan-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .plan-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }

        .plan-detail-item:last-child {
            border-bottom: none;
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
                <a class="nav-link active" href="subscriptions.php">
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
                <h5 class="mb-0">Owner Subscription Management</h5>
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
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card subscriptions">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Subscriptions
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_subscriptions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-crown fa-2x text-primary"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Subscriptions
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['active_subscriptions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Payments
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['pending_payments']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-warning"></i>
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

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="subscriptions-tab" data-bs-toggle="tab" data-bs-target="#subscriptions" type="button" role="tab">
                        <i class="fas fa-crown me-2"></i>User Subscriptions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                        <i class="fas fa-credit-card me-2"></i>Payment Records
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="plans-tab" data-bs-toggle="tab" data-bs-target="#plans" type="button" role="tab">
                        <i class="fas fa-layer-group me-2"></i>Subscription Plans
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="managementTabsContent">
                <!-- User Subscriptions Tab -->
                <div class="tab-pane fade show active" id="subscriptions" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-crown me-2"></i>Current Owner Subscriptions
                            </h5>
                            <span class="badge bg-primary">
                                <?php echo count($user_subscriptions); ?> Total
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($user_subscriptions)): ?>
                                <?php foreach($user_subscriptions as $subscription): ?>
                                <div class="subscription-card card <?php echo strtolower($subscription['Sub_Status']); ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-1">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($subscription['User_Name'], 0, 2)); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="plan-badge <?php echo strpos(strtolower($subscription['Plan_Name']), 'premium') !== false ? 'premium' : (strpos(strtolower($subscription['Plan_Name']), 'enterprise') !== false ? 'enterprise' : 'basic'); ?> me-2">
                                                        <?php echo htmlspecialchars($subscription['Plan_Name']); ?>
                                                    </span>
                                                    <span class="badge bg-<?php echo $subscription['Sub_Status'] == 'Active' ? 'success' : ($subscription['Sub_Status'] == 'Pending' ? 'warning' : 'secondary'); ?> status-badge">
                                                        <?php echo $subscription['Sub_Status']; ?>
                                                    </span>
                                                    <small class="text-muted ms-auto">
                                                        ID: #<?php echo str_pad($subscription['SubscriptionID'], 6, '0', STR_PAD_LEFT); ?>
                                                    </small>
                                                </div>
                                                
                                                <h6 class="mb-1"><?php echo htmlspecialchars($subscription['User_Name']); ?> 
                                                    <small class="text-muted">(<?php echo getRoleName($subscription['User_Role']); ?>)</small>
                                                </h6>
                                                <p class="text-muted mb-2">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($subscription['User_Email']); ?>
                                                </p>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Duration:</small><br>
                                                        <small><?php echo $subscription['Plan_Duration']; ?> days</small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Days Remaining:</small><br>
                                                        <small class="<?php echo getDaysRemaining($subscription['Sub_EndDate']) > 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo getDaysRemaining($subscription['Sub_EndDate']); ?> days
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <div class="plan-details mt-2">
                                                    <div class="plan-detail-item">
                                                        <span>Max Listings:</span>
                                                        <strong><?php echo $subscription['Plan_MaxListings'] == -1 ? 'Unlimited' : number_format($subscription['Plan_MaxListings']); ?></strong>
                                                    </div>
                                                    <div class="plan-detail-item">
                                                        <span>Featured Listings:</span>
                                                        <strong><?php echo $subscription['Plan_FeaturedListings'] == -1 ? 'Unlimited' : number_format($subscription['Plan_FeaturedListings']); ?></strong>
                                                    </div>
                                                    <div class="plan-detail-item">
                                                        <span>Commission Rate:</span>
                                                        <strong><?php echo number_format($subscription['Plan_CommissionRate'], 1); ?>%</strong>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h4 class="text-primary mb-1">₱<?php echo number_format($subscription['Plan_Price'], 0); ?></h4>
                                                    <p class="text-muted small mb-3"><?php echo $subscription['Plan_Duration']; ?> days</p>
                                                    
                                                    <div class="mb-2">
                                                        <small class="text-muted">Start:</small> <?php echo date('M j, Y', strtotime($subscription['Sub_StartDate'])); ?><br>
                                                        <small class="text-muted">End:</small> <?php echo date('M j, Y', strtotime($subscription['Sub_EndDate'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <div class="d-grid gap-2">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="subscription_id" value="<?php echo $subscription['SubscriptionID']; ?>">
                                                        <select name="new_status" class="form-select form-select-sm mb-2" required>
                                                            <option value="">Update Status</option>
                                                            <option value="Active" <?php echo $subscription['Sub_Status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="Pending" <?php echo $subscription['Sub_Status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="Cancelled" <?php echo $subscription['Sub_Status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            <option value="Expired">Expired</option>
                                                        </select>
                                                        <button type="submit" name="update_subscription_status" class="btn btn-sm btn-outline-primary w-100">
                                                            <i class="fas fa-sync me-1"></i>Update
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-crown"></i>
                                    <h5 class="text-muted">No Owner Subscriptions Found</h5>
                                    <p class="text-muted">No owners have subscribed to any plans yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Records Tab -->
                <div class="tab-pane fade" id="payments" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-credit-card me-2"></i>Payment Records
                            </h5>
                            <span class="badge bg-info">
                                <?php echo count($subscription_payments); ?> Total
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($subscription_payments)): ?>
                                <?php foreach($subscription_payments as $payment): ?>
                                <div class="payment-card card <?php echo strtolower($payment['SubPay_Status']); ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h6 class="mb-0 me-2"><?php echo htmlspecialchars($payment['User_Name']); ?></h6>
                                                    <span class="badge bg-<?php echo $payment['SubPay_Status'] == 'Completed' ? 'success' : ($payment['SubPay_Status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo $payment['SubPay_Status']; ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted mb-1">
                                                    <strong><?php echo htmlspecialchars($payment['Plan_Name']); ?></strong>
                                                </p>
                                                <p class="text-muted mb-0">
                                                    <small>Transaction: <?php echo htmlspecialchars($payment['SubPay_TransactionID']); ?></small><br>
                                                    <small>Method: <?php echo htmlspecialchars($payment['SubPay_PaymentMethod']); ?></small>
                                                </p>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <h5 class="text-primary mb-1">₱<?php echo number_format($payment['SubPay_Amount'], 0); ?></h5>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['SubPay_Type']); ?></small>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <small class="text-muted">Created:</small><br>
                                                <small><?php echo date('M j, Y', strtotime($payment['SubPay_CreatedAt'])); ?></small>
                                                <?php if($payment['SubPay_ProcessedAt']): ?>
                                                <br><small class="text-muted">Processed:</small><br>
                                                <small><?php echo date('M j, Y', strtotime($payment['SubPay_ProcessedAt'])); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-1">
                                                <?php if($payment['SubPay_Status'] == 'Pending'): ?>
                                                <form method="POST" class="d-grid gap-1">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['SubPaymentID']; ?>">
                                                    <button type="submit" name="update_payment_status" value="Completed" 
                                                            class="btn btn-sm btn-success" onclick="this.form.new_status.value='Completed'">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="submit" name="update_payment_status" value="Failed" 
                                                            class="btn btn-sm btn-danger" onclick="this.form.new_status.value='Failed'">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <input type="hidden" name="new_status" value="">
                                                </form>
                                                <?php else: ?>
                                                <div class="text-center">
                                                    <i class="fas fa-<?php echo $payment['SubPay_Status'] == 'Completed' ? 'check-circle text-success' : 'times-circle text-danger'; ?> fa-2x"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-credit-card"></i>
                                    <h5 class="text-muted">No Payment Records Found</h5>
                                    <p class="text-muted">Payment records will appear here when users make subscription payments.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Subscription Plans Tab -->
                <div class="tab-pane fade" id="plans" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-layer-group me-2"></i>Available Subscription Plans
                            </h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                <i class="fas fa-plus me-1"></i>Add New Plan
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($subscription_plans)): ?>
                                <div class="row">
                                    <?php foreach($subscription_plans as $plan): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="plan-card card <?php echo strpos(strtolower($plan['Plan_Name']), 'premium') !== false ? 'premium' : (strpos(strtolower($plan['Plan_Name']), 'enterprise') !== false ? 'enterprise' : 'basic'); ?>">
                                            <div class="card-body text-center">
                                                <h5 class="card-title mb-3"><?php echo htmlspecialchars($plan['Plan_Name']); ?></h5>
                                                
                                                <div class="price-highlight mb-3">
                                                    <h3 class="mb-1">₱<?php echo number_format($plan['Plan_Price'], 0); ?></h3>
                                                    <small><?php echo $plan['Plan_Duration']; ?> days</small>
                                                </div>
                                                
                                                <?php if(!empty($plan['Plan_Description'])): ?>
                                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($plan['Plan_Description']); ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="plan-details text-start">
                                                    <div class="plan-detail-item">
                                                        <span><i class="fas fa-list me-2"></i>Max Listings:</span>
                                                        <strong><?php echo $plan['Plan_MaxListings'] == -1 ? 'Unlimited' : number_format($plan['Plan_MaxListings']); ?></strong>
                                                    </div>
                                                    <div class="plan-detail-item">
                                                        <span><i class="fas fa-star me-2"></i>Featured Listings:</span>
                                                        <strong><?php echo $plan['Plan_FeaturedListings'] == -1 ? 'Unlimited' : number_format($plan['Plan_FeaturedListings']); ?></strong>
                                                    </div>
                                                    <div class="plan-detail-item">
                                                        <span><i class="fas fa-percentage me-2"></i>Commission Rate:</span>
                                                        <strong><?php echo number_format($plan['Plan_CommissionRate'], 1); ?>%</strong>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-grid gap-2 mt-3">
                                                    <button class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-edit me-1"></i>Edit Plan
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm">
                                                        <i class="fas fa-trash me-1"></i>Delete Plan
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-layer-group"></i>
                                    <h5 class="text-muted">No Subscription Plans Available</h5>
                                    <p class="text-muted">Create subscription plans for owners to purchase.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                        <i class="fas fa-plus me-2"></i>Create First Plan
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities and Quick Actions -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($recent_activities)): ?>
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
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No recent subscription activities</p>
                                </div>
                            <?php endif; ?>
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
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                    <i class="fas fa-plus"></i> Add Subscription Plan
                                </button>
                                <button class="btn btn-success" onclick="exportSubscriptions()">
                                    <i class="fas fa-download"></i> Export Subscriptions
                                </button>
                                <a href="reports.php" class="btn btn-info">
                                    <i class="fas fa-chart-bar"></i> Generate Report
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
                                <span class="badge bg-<?php echo ($user_subscriptions_table_exists && $subscription_plans_table_exists && $subscription_payments_table_exists) ? 'success' : 'warning'; ?>">
                                    <?php echo ($user_subscriptions_table_exists && $subscription_plans_table_exists && $subscription_payments_table_exists) ? 'Connected' : 'Setup Required'; ?>
                                </span>
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

    <!-- Add Plan Modal -->
    <div class="modal fade" id="addPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Create Owner Subscription Plan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="plan_name" required placeholder="e.g. Premium Owner Plan">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Plan Price (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="plan_price" step="0.01" required placeholder="999.00">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Plan Description</label>
                            <textarea class="form-control" name="plan_description" rows="3" placeholder="Brief description of this subscription plan..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Duration (Days) <span class="text-danger">*</span></label>
                                <select class="form-select" name="plan_duration" required>
                                    <option value="">Select Duration</option>
                                    <option value="7">7 Days</option>
                                    <option value="30">30 Days (1 Month)</option>
                                    <option value="90">90 Days (3 Months)</option>
                                    <option value="180">180 Days (6 Months)</option>
                                    <option value="365">365 Days (1 Year)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Listings <span class="text-danger">*</span></label>
                                <select class="form-select" name="plan_max_listings" required>
                                    <option value="">Select Limit</option>
                                    <option value="5">5 Listings</option>
                                    <option value="10">10 Listings</option>
                                    <option value="25">25 Listings</option>
                                    <option value="50">50 Listings</option>
                                    <option value="100">100 Listings</option>
                                    <option value="-1">Unlimited</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Featured Listings <span class="text-danger">*</span></label>
                                <select class="form-select" name="plan_featured_listings" required>
                                    <option value="">Select Limit</option>
                                    <option value="0">0 Featured</option>
                                    <option value="1">1 Featured</option>
                                    <option value="3">3 Featured</option>
                                    <option value="5">5 Featured</option>
                                    <option value="10">10 Featured</option>
                                    <option value="-1">Unlimited</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Commission Rate (%) <span class="text-danger">*</span></label>
                                <select class="form-select" name="plan_commission_rate" required>
                                    <option value="">Select Rate</option>
                                    <option value="0">0% (No Commission)</option>
                                    <option value="2.5">2.5%</option>
                                    <option value="5">5%</option>
                                    <option value="7.5">7.5%</option>
                                    <option value="10">10%</option>
                                    <option value="15">15%</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Plan Guidelines:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Lower commission rates for higher-tier plans</li>
                                <li>More featured listings for premium plans</li>
                                <li>Longer durations typically offer better value</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_subscription_plan" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Export subscriptions
        function exportSubscriptions() {
            alert('Export owner subscriptions functionality will be implemented.');
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
    </script>
</body>
</html>