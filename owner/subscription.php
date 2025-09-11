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

// Handle plan selection and subscription creation with payment
if ($_POST) {
    if (isset($_POST['select_plan'])) {
        $plan_id = $_POST['plan_id'];
        
        try {
            // Check if user already has an active subscription
            $query = "SELECT * FROM user_subscriptions WHERE UserID = ? AND Sub_Status = 'Active'";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $message = "You already have an active subscription! Cancel your current subscription first.";
                $message_type = "warning";
            } else {
                // Get plan details
                $query = "SELECT * FROM subscription_plans WHERE PlanID = ? AND Plan_IsActive = 1";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $plan_id);
                $stmt->execute();
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan) {
                    // Create the subscription with Pending status
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+' . $plan['Plan_Duration'] . ' days'));
                    $sub_status = 'Pending';
                    $sub_autorenew = 0;
                    $sub_payment_method = 'Pending';
                    
                    $query = "INSERT INTO user_subscriptions (UserID, PlanID, Sub_StartDate, Sub_EndDate, Sub_Status, Sub_AutoRenew, Sub_PaymentMethod, Sub_CreatedAt, Sub_UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->bindParam(2, $plan_id);
                    $stmt->bindParam(3, $start_date);
                    $stmt->bindParam(4, $end_date);
                    $stmt->bindParam(5, $sub_status);
                    $stmt->bindParam(6, $sub_autorenew);
                    $stmt->bindParam(7, $sub_payment_method);
                    
                    if ($stmt->execute()) {
                        $subscription_id = $conn->lastInsertId();
                        
                        // Create payment record in subscription_payments
                        $payment_amount = $plan['Plan_Price'];
                        $payment_method = 'Credit Card';
                        $payment_status = 'Pending';
                        $transaction_id = 'TXN' . time() . rand(1000, 9999);
                        $due_date = date('Y-m-d', strtotime('+7 days'));
                        $payment_type = 'Subscription';
                        
                        $query = "INSERT INTO subscription_payments (SubscriptionID, SubPay_Amount, SubPay_PaymentMethod, SubPay_Status, SubPay_TransactionID, SubPay_ProcessedAt, SubPay_CreatedAt, SubPay_DueDate, SubPay_Type) VALUES (?, ?, ?, ?, ?, NULL, NOW(), ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(1, $subscription_id);
                        $stmt->bindParam(2, $payment_amount);
                        $stmt->bindParam(3, $payment_method);
                        $stmt->bindParam(4, $payment_status);
                        $stmt->bindParam(5, $transaction_id);
                        $stmt->bindParam(6, $due_date);
                        $stmt->bindParam(7, $payment_type);
                        
                        if ($stmt->execute()) {
                            $message = "Subscription created! Please complete your payment of ₱" . number_format($payment_amount, 0) . " to activate your " . htmlspecialchars($plan['Plan_Name']) . " plan.";
                            $message_type = "info";
                        } else {
                            $message = "Subscription created but payment setup failed. Please contact support.";
                            $message_type = "warning";
                        }
                    } else {
                        $message = "Failed to create subscription. Please try again.";
                        $message_type = "danger";
                    }
                } else {
                    $message = "Selected plan is not available.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Error processing subscription: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['complete_payment'])) {
        $payment_id = $_POST['payment_id'];
        $payment_method = $_POST['payment_method'];
        
        try {
            // Update payment status
            $query = "UPDATE subscription_payments SET SubPay_Status = 'Completed', SubPay_PaymentMethod = ?, SubPay_ProcessedAt = NOW() WHERE SubPaymentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $payment_method);
            $stmt->bindParam(2, $payment_id);
            
            if ($stmt->execute()) {
                // Get subscription ID from payment
                $query = "SELECT SubscriptionID FROM subscription_payments WHERE SubPaymentID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $payment_id);
                $stmt->execute();
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($payment) {
                    // Activate the subscription
                    $query = "UPDATE user_subscriptions SET Sub_Status = 'Active', Sub_PaymentMethod = ?, Sub_UpdatedAt = NOW() WHERE SubscriptionID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $payment_method);
                    $stmt->bindParam(2, $payment['SubscriptionID']);
                    $stmt->execute();
                    
                    $message = "Payment completed successfully! Your subscription is now active.";
                    $message_type = "success";
                }
            }
        } catch (PDOException $e) {
            $message = "Error processing payment: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['cancel_subscription'])) {
        $subscription_id = $_POST['subscription_id'];
        
        try {
            $query = "UPDATE user_subscriptions SET Sub_Status = 'Cancelled', Sub_UpdatedAt = NOW() WHERE SubscriptionID = ? AND UserID = ?";
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
            $message = "Error cancelling subscription: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Check if tables exist
$subscription_plans_table_exists = false;
$user_subscriptions_table_exists = false;
$subscription_payments_table_exists = false;

try {
    $query = "SELECT 1 FROM subscription_plans LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subscription_plans_table_exists = true;
} catch (PDOException $e) {
    $subscription_plans_table_exists = false;
}

try {
    $query = "SELECT 1 FROM user_subscriptions LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $user_subscriptions_table_exists = true;
} catch (PDOException $e) {
    $user_subscriptions_table_exists = false;
}

try {
    $query = "SELECT 1 FROM subscription_payments LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subscription_payments_table_exists = true;
} catch (PDOException $e) {
    $subscription_payments_table_exists = false;
}

$available_plans = [];
$current_subscription = null;
$subscription_history = [];
$pending_payments = [];
$payment_history = [];

if ($subscription_plans_table_exists) {
    // Get available plans from subscription_plans table
    try {
        $query = "SELECT * FROM subscription_plans WHERE Plan_IsActive = 1 ORDER BY Plan_Price ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $available_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $available_plans = [];
    }
}

if ($user_subscriptions_table_exists && $subscription_plans_table_exists) {
    // Get current active subscription
    try {
        $query = "SELECT us.*, sp.Plan_Name, sp.Plan_Description, sp.Plan_Price, sp.Plan_Duration, sp.Plan_MaxListings, sp.Plan_FeaturedListings, sp.Plan_CommissionRate
                  FROM user_subscriptions us
                  JOIN subscription_plans sp ON us.PlanID = sp.PlanID
                  WHERE us.UserID = ? AND us.Sub_Status = 'Active'
                  ORDER BY us.Sub_CreatedAt DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $current_subscription = null;
    }
    
    // Get subscription history
    try {
        $query = "SELECT us.*, sp.Plan_Name, sp.Plan_Price, sp.Plan_Duration
                  FROM user_subscriptions us
                  JOIN subscription_plans sp ON us.PlanID = sp.PlanID
                  WHERE us.UserID = ?
                  ORDER BY us.Sub_CreatedAt DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $subscription_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subscription_history = [];
    }
}

if ($subscription_payments_table_exists && $user_subscriptions_table_exists) {
    // Get pending payments
    try {
        $query = "SELECT spt.*, us.*, sp.Plan_Name 
                  FROM subscription_payments spt
                  JOIN user_subscriptions us ON spt.SubscriptionID = us.SubscriptionID
                  JOIN subscription_plans sp ON us.PlanID = sp.PlanID
                  WHERE us.UserID = ? AND spt.SubPay_Status = 'Pending'
                  ORDER BY spt.SubPay_CreatedAt DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pending_payments = [];
    }

    // Get payment history
    try {
        $query = "SELECT spt.*, us.*, sp.Plan_Name 
                  FROM subscription_payments spt
                  JOIN user_subscriptions us ON spt.SubscriptionID = us.SubscriptionID
                  JOIN subscription_plans sp ON us.PlanID = sp.PlanID
                  WHERE us.UserID = ?
                  ORDER BY spt.SubPay_CreatedAt DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $payment_history = [];
    }
}

// Calculate stats
$stats = [
    'available_plans' => count($available_plans),
    'days_remaining' => 0,
    'total_spent' => 0,
    'active_products' => 0,
    'subscription_count' => count($subscription_history),
    'pending_payments' => count($pending_payments),
    'total_paid' => 0
];

if (!empty($available_plans)) {
    $prices = array_column($available_plans, 'Plan_Price');
    $stats['lowest_price'] = min($prices);
    $stats['highest_price'] = max($prices);
}

if ($current_subscription) {
    $stats['days_remaining'] = max(0, ceil((strtotime($current_subscription['Sub_EndDate']) - time()) / 86400));
}

foreach($payment_history as $payment) {
    if ($payment['SubPay_Status'] == 'Completed') {
        $stats['total_paid'] += $payment['SubPay_Amount'];
    }
}

$stats['total_spent'] = $stats['total_paid'];

// Get active products count
try {
    $query = "SELECT COUNT(*) as count FROM products WHERE OwnerID = ? AND Prod_Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_products'] = $result ? $result['count'] : 0;
} catch (PDOException $e) {
    $stats['active_products'] = 0;
}

// Helper function to format plan features
function formatPlanFeatures($plan) {
    $features = [];
    
    if ($plan['Plan_MaxListings'] == -1) {
        $features[] = 'Unlimited product listings';
    } else {
        $features[] = 'Up to ' . number_format($plan['Plan_MaxListings']) . ' product listings';
    }
    
    if ($plan['Plan_FeaturedListings'] == -1) {
        $features[] = 'Unlimited featured listings';
    } elseif ($plan['Plan_FeaturedListings'] > 0) {
        $features[] = number_format($plan['Plan_FeaturedListings']) . ' featured listings included';
    } else {
        $features[] = 'No featured listings';
    }
    
    if ($plan['Plan_CommissionRate'] == 0) {
        $features[] = 'No commission fees (0%)';
    } else {
        $features[] = number_format($plan['Plan_CommissionRate'], 1) . '% commission rate';
    }
    
    // Add premium benefits for high-value plans
    if ($plan['Plan_Price'] >= 1500) {
        $features[] = 'Priority customer support';
        $features[] = 'Advanced analytics dashboard';
        $features[] = 'Premium listing badge';
    }
    
    if (!empty($plan['Plan_Description'])) {
        $features[] = $plan['Plan_Description'];
    }
    
    return $features;
}

// Helper function to get plan type for styling
function getPlanType($plan_name) {
    $name_lower = strtolower($plan_name);
    if (strpos($name_lower, 'premium') !== false || strpos($name_lower, 'birthday') !== false) {
        return 'premium';
    } elseif (strpos($name_lower, 'enterprise') !== false || strpos($name_lower, 'pro') !== false) {
        return 'enterprise';
    } else {
        return 'basic';
    }
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
        
        .payment-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border-left: 4px solid #f6d365;
            background: white;
        }
        
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
        .status-badge.pending { background: #ffc107; color: #000; }
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
        
        .btn-select-plan {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-select-plan:hover {
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

        .btn-pay-now {
            background: var(--warning-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-pay-now:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(246, 211, 101, 0.4);
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
        
        .plan-highlight {
            position: relative;
            overflow: hidden;
        }
        
        .plan-highlight::before {
            content: 'POPULAR';
            position: absolute;
            top: 20px;
            right: -30px;
            background: var(--accent-gradient);
            color: white;
            padding: 0.5rem 2rem;
            transform: rotate(45deg);
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 10;
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

        .plan-benefits {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1rem;
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

        .payment-alert {
            background: var(--warning-gradient);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
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
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card me-2"></i> Payment History
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

            <!-- Pending Payments Alert -->
            <?php if(!empty($pending_payments)): ?>
            <div class="payment-alert">
                <h5 class="mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>Payment Required
                </h5>
                <p class="mb-3">You have pending payments that need to be completed to activate your subscription.</p>
                <button class="btn btn-light" onclick="scrollToPendingPayments()">
                    <i class="fas fa-credit-card me-2"></i>Complete Payment Now
                </button>
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
                        <?php if(!$current_subscription && !empty($available_plans)): ?>
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
                                        Total Paid
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_paid'], 0); ?></div>
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
                                        Pending Payments
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['pending_payments']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-credit-card fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Payments Section -->
            <?php if(!empty($pending_payments)): ?>
            <div id="pending-payments" class="mb-5">
                <h5 class="text-warning mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>Pending Payments
                </h5>
                
                <?php foreach($pending_payments as $payment): ?>
                <div class="payment-card">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-2">
                                    <i class="fas fa-crown text-warning me-2"></i>
                                    <?php echo htmlspecialchars($payment['Plan_Name']); ?>
                                </h6>
                                <p class="text-muted mb-2">
                                    Transaction ID: <?php echo htmlspecialchars($payment['SubPay_TransactionID']); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    Due Date: <?php echo date('M j, Y', strtotime($payment['SubPay_DueDate'])); ?>
                                </p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-warning mb-1">₱<?php echo number_format($payment['SubPay_Amount'], 0); ?></h4>
                                <small class="text-muted">Amount Due</small>
                            </div>
                            <div class="col-md-3">
                                <form method="POST" class="d-grid">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['SubPaymentID']; ?>">
                                    <div class="mb-2">
                                        <select name="payment_method" class="form-select form-select-sm" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="GCash">GCash</option>
                                            <option value="PayMaya">PayMaya</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="complete_payment" class="btn btn-pay-now">
                                        <i class="fas fa-credit-card me-2"></i>Pay Now
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

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
                                $features = formatPlanFeatures($current_subscription);
                                foreach($features as $feature): 
                                ?>
                                <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if(!empty($current_subscription['Plan_Description'])): ?>
                            <div class="plan-benefits">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-gift me-2"></i>Plan Description
                                </h6>
                                <p class="mb-0"><?php echo htmlspecialchars($current_subscription['Plan_Description']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="price-display">
                                <h2 class="mb-2">₱<?php echo number_format($current_subscription['Plan_Price'], 0); ?></h2>
                                <p class="mb-3 opacity-75"><?php echo $current_subscription['Plan_Duration']; ?> days subscription</p>
                                <small class="opacity-75">Subscribed: <?php echo date('M j, Y', strtotime($current_subscription['Sub_StartDate'])); ?></small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-select-plan" onclick="renewSubscription()">
                                    <i class="fas fa-redo me-2"></i>Renew Subscription
                                </button>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="subscription_id" value="<?php echo $current_subscription['SubscriptionID']; ?>">
                                    <button type="submit" name="cancel_subscription" class="btn btn-cancel w-100"
                                            onclick="return confirm('Are you sure you want to cancel your subscription?')">
                                        <i class="fas fa-times me-2"></i>Cancel Subscription
                                    </button>
                                </form>
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
                
                <?php if(!empty($available_plans)): ?>
                <div class="row">
                    <?php foreach($available_plans as $index => $plan): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="plan-card <?php echo getPlanType($plan['Plan_Name']); ?> <?php echo $plan['Plan_Price'] >= 1500 ? 'plan-highlight' : ''; ?>">
                            <div class="card-body p-4 text-center">
                                <h5 class="mb-3"><?php echo htmlspecialchars($plan['Plan_Name']); ?></h5>
                                <div class="price-display mb-4">
                                    <h2 class="mb-1">₱<?php echo number_format($plan['Plan_Price'], 0); ?></h2>
                                    <small class="opacity-75"><?php echo $plan['Plan_Duration']; ?> days</small>
                                </div>
                                
                                <?php if(!empty($plan['Plan_Description'])): ?>
                                <div class="plan-benefits text-start mb-3">
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <?php echo htmlspecialchars($plan['Plan_Description']); ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <ul class="feature-list text-start mb-4">
                                    <?php 
                                    $features = formatPlanFeatures($plan);
                                    foreach($features as $feature): 
                                    ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>

                                <!-- Plan Details -->
                                <div class="plan-benefits text-start mb-4">
                                    <h6 class="text-primary mb-2">
                                        <i class="fas fa-star me-1"></i>Plan Details
                                    </h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="h6 text-primary mb-0"><?php echo $plan['Plan_MaxListings'] == -1 ? '∞' : $plan['Plan_MaxListings']; ?></div>
                                            <small class="text-muted">Listings</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h6 text-success mb-0"><?php echo $plan['Plan_FeaturedListings'] == -1 ? '∞' : $plan['Plan_FeaturedListings']; ?></div>
                                            <small class="text-muted">Featured</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h6 text-info mb-0"><?php echo number_format($plan['Plan_CommissionRate'], 1); ?>%</div>
                                            <small class="text-muted">Commission</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if($current_subscription && $current_subscription['PlanID'] == $plan['PlanID']): ?>
                                    <button class="btn btn-success w-100" disabled>
                                        <i class="fas fa-check me-2"></i>Current Plan
                                    </button>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['PlanID']; ?>">
                                        <button type="submit" name="select_plan" class="btn btn-select-plan w-100"
                                                onclick="return confirm('Subscribe to <?php echo htmlspecialchars($plan['Plan_Name']); ?> for ₱<?php echo number_format($plan['Plan_Price'], 0); ?>? You will need to complete payment to activate.')">
                                            <i class="fas fa-crown me-2"></i>
                                            <?php echo $current_subscription ? 'Upgrade to This Plan' : 'Select This Plan'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-layer-group"></i>
                    <h5 class="text-muted">No Subscription Plans Available</h5>
                    <p class="text-muted">No subscription plans are currently available. Please check back later or contact support.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment History -->
            <?php if(!empty($payment_history)): ?>
            <div class="mt-5">
                <h5 class="text-primary mb-4">
                    <i class="fas fa-credit-card me-2"></i>Payment History
                </h5>
                
                <div class="row">
                    <?php foreach(array_slice($payment_history, 0, 6) as $payment): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($payment['Plan_Name']); ?></h6>
                                    <span class="badge bg-<?php echo $payment['SubPay_Status'] == 'Completed' ? 'success' : ($payment['SubPay_Status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo $payment['SubPay_Status']; ?>
                                    </span>
                                </div>
                                <p class="h5 text-primary mb-2">₱<?php echo number_format($payment['SubPay_Amount'], 0); ?></p>
                                <p class="text-muted small mb-1">
                                    Method: <?php echo htmlspecialchars($payment['SubPay_PaymentMethod']); ?>
                                </p>
                                <p class="text-muted small mb-0">
                                    Date: <?php echo date('M j, Y', strtotime($payment['SubPay_CreatedAt'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(count($payment_history) > 6): ?>
                <div class="text-center mt-3">
                    <a href="payments.php" class="btn btn-outline-primary">
                        <i class="fas fa-history me-2"></i>View All Payment History
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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
                                <span class="badge bg-<?php echo $sub['Sub_Status'] == 'Active' ? 'success' : ($sub['Sub_Status'] == 'Expired' ? 'danger' : ($sub['Sub_Status'] == 'Pending' ? 'warning' : 'secondary')); ?>">
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

        // Scroll to pending payments
        function scrollToPendingPayments() {
            document.getElementById('pending-payments').scrollIntoView({ 
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
            const cards = document.querySelectorAll('.plan-card, .current-plan-card, .payment-card');
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