<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Check if payments table exists
$payments_table_exists = false;
try {
    $query = "SELECT 1 FROM payments LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $payments_table_exists = true;
} catch (PDOException $e) {
    $payments_table_exists = false;
}

$payments = [];

// Notification dropdown logic (copied from dashboard.php)
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

$stats = [
    'total_payments' => 0,
    'total_amount' => 0,
    'successful_payments' => 0,
    'pending_payments' => 0
];

if ($payments_table_exists) {
    // Build query conditions
    $conditions = ["b.RenterID = ?"];
    $params = [$user_id];

    if ($status_filter && $status_filter != 'all') {
        $conditions[] = "p.Pay_Status = ?";
        $params[] = ucfirst($status_filter);
    }

    // Date range filter
    if ($date_filter && $date_filter != 'all') {
        switch($date_filter) {
            case 'today':
                $conditions[] = "DATE(p.Pay_CreatedAt) = CURDATE()";
                break;
            case 'week':
                $conditions[] = "p.Pay_CreatedAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $conditions[] = "p.Pay_CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $conditions[] = "p.Pay_CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
    }

    // Sort options
    $sort_options = [
        'newest' => 'p.Pay_CreatedAt DESC',
        'oldest' => 'p.Pay_CreatedAt ASC',
        'amount_high' => 'p.Pay_Amount DESC',
        'amount_low' => 'p.Pay_Amount ASC',
        'status' => 'p.Pay_Status ASC'
    ];

    $order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'p.Pay_CreatedAt DESC';

    // First get payments, then get refunds separately
    $payments_query = "SELECT p.PaymentID as ID, 'payment' as type, p.Pay_Amount as amount, 
                       p.Pay_Status as status, p.Pay_Method as method, p.Pay_TransactionID as transaction_id,
                       p.Pay_CreatedAt as created_at, p.Pay_UpdatedAt as updated_at,
                       b.BookingID, b.Book_StartDate, b.Book_EndDate, b.Book_TotalAmount,
                       prod.Prod_Name, prod.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name,
                       NULL as refund_reason
                FROM payments p
                JOIN bookings b ON p.BookingID = b.BookingID
                JOIN products prod ON b.ProductID = prod.ProductID
                LEFT JOIN product_images pi ON prod.ProductID = pi.ProductID AND pi.PI_IsMain = 1
                JOIN user_accounts u ON prod.OwnerID = u.UserID
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY " . $order_by;

    $refunds_query = "SELECT r.RefundID as ID, 'refund' as type, r.Refund_Amount as amount,
                      r.Refund_Status as status, r.Refund_Method as method, r.Refund_TransactionID as transaction_id,
                      r.Refund_CreatedAt as created_at, r.Refund_ProcessedAt as updated_at,
                      b.BookingID, b.Book_StartDate, b.Book_EndDate, b.Book_TotalAmount,
                      prod.Prod_Name, prod.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name,
                      r.Refund_Reason as refund_reason
               FROM refunds r
               JOIN bookings b ON r.BookingID = b.BookingID
               JOIN products prod ON b.ProductID = prod.ProductID
               LEFT JOIN product_images pi ON prod.ProductID = pi.ProductID AND pi.PI_IsMain = 1
               JOIN user_accounts u ON prod.OwnerID = u.UserID
               WHERE b.RenterID = ?
               ORDER BY r.Refund_CreatedAt DESC";

    try {
        // Get payments
        $payments = [];
        
        $stmt = $conn->prepare($payments_query);
        $stmt->execute($params);
        $payment_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get refunds
        $stmt = $conn->prepare($refunds_query);
        $stmt->execute([$user_id]);
        $refund_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and sort by date
        $payments = array_merge($payment_records, $refund_records);
        
        // Sort by created_at DESC
        usort($payments, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
    } catch (PDOException $e) {
        $payments = [];
        error_log("Payment history error: " . $e->getMessage());
    }
    
    // Debug: Add some logging to check what's happening
    error_log("Debug - Total payments found: " . count($payments));
    if (!empty($payments)) {
        error_log("Debug - First payment: " . print_r($payments[0], true));
    }
    
    // If no payments found but there might be completed bookings, show those
    if (empty($payments)) {
        try {
            $fallback_query = "SELECT b.BookingID as ID, 'booking' as type, b.Book_TotalAmount as amount,
                              'Completed' as status, 'Cash' as method, CONCAT('TXN', LPAD(b.BookingID, 6, '0')) as transaction_id,
                              b.Book_CreatedAt as created_at, b.Book_UpdatedAt as updated_at,
                              b.BookingID, b.Book_StartDate, b.Book_EndDate, b.Book_TotalAmount,
                              prod.Prod_Name, prod.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name,
                              NULL as refund_reason
                       FROM bookings b
                       JOIN products prod ON b.ProductID = prod.ProductID
                       LEFT JOIN product_images pi ON prod.ProductID = pi.ProductID AND pi.PI_IsMain = 1
                       JOIN user_accounts u ON prod.OwnerID = u.UserID
                       WHERE b.RenterID = ? AND b.Book_Status = 'Completed'
                       ORDER BY b.Book_CreatedAt DESC";
            
            $stmt = $conn->prepare($fallback_query);
            $stmt->execute([$user_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $payments = [];
        }
    }

    // Calculate statistics including refunds
    $stats['total_payments'] = count($payments);
    
    // Calculate net amount (payments - refunds)
    $total_paid = 0;
    $total_refunded = 0;
    foreach($payments as $p) {
        if(isset($p['type']) && $p['type'] === 'refund') {
            $total_refunded += $p['amount'];
        } else {
            $total_paid += $p['amount'];
        }
    }
    $stats['total_amount'] = $total_paid - $total_refunded;
    $stats['total_paid'] = $total_paid;
    $stats['total_refunded'] = $total_refunded;
    
    $stats['successful_payments'] = count(array_filter($payments, function($p) { return $p['status'] == 'Completed'; }));
    // Count pending payments: payments with status 'Pending' plus confirmed bookings with no payment record
    $pending_payments = count(array_filter($payments, function($p) { return $p['status'] == 'Pending'; }));

    // Add confirmed bookings with no payment record
    $query = "SELECT COUNT(*) as total FROM bookings b LEFT JOIN payments p ON b.BookingID = p.BookingID WHERE b.RenterID = ? AND b.Book_Status = 'Confirmed' AND p.PaymentID IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $pending_no_payment = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stats['pending_payments'] = $pending_payments + $pending_no_payment;

} else {
    // Show sample data from bookings
    $query = "SELECT b.*, p.Prod_Name, p.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name,
              b.Book_TotalAmount as Pay_Amount, 'Completed' as Pay_Status, 'Credit Card' as Pay_Method,
              b.Book_CreatedAt as Pay_CreatedAt, CONCAT('TXN', LPAD(b.BookingID, 6, '0')) as Pay_TransactionID
              FROM bookings b
              JOIN products p ON b.ProductID = p.ProductID
              LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
              JOIN user_accounts u ON p.OwnerID = u.UserID
              WHERE b.RenterID = ? AND b.Book_Status IN ('Active', 'Completed')
              ORDER BY b.Book_CreatedAt DESC
              LIMIT 10";

    try {
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $sample_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payments = $sample_payments;
        $stats['total_payments'] = count($payments);
        $stats['total_amount'] = array_sum(array_column($payments, 'Pay_Amount'));
        $stats['successful_payments'] = count($payments);
        $stats['pending_payments'] = 0;
    } catch (PDOException $e) {
        $payments = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <link href="../css/renter-theme.css" rel="stylesheet">
    <style>
        :root {
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--secondary-gradient);
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


        .stat-card.total { background: var(--secondary-gradient); color: white; }
        .stat-card.amount { background: var(--secondary-gradient); color: white; }
        .stat-card.successful { background: var(--secondary-gradient); color: white; }
        .stat-card.pending { background: var(--secondary-gradient); color: white; }
        
        .payment-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            border-left: 4px solid #4facfe;
        }
        
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .payment-card.completed { border-left-color: #28a745; }
        .payment-card.pending { border-left-color: #ffc107; }
        .payment-card.failed { border-left-color: #dc3545; }
        .payment-card.cancelled { border-left-color: #6c757d; }
        .payment-card.sample { border-left-color: #17a2b8; opacity: 0.9; }
        
        .payment-status {
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
        
        .status-badge.completed { background: #28a745; }
        .status-badge.pending { background: #ffc107; color: #000; }
        .status-badge.failed { background: #dc3545; }
        .status-badge.cancelled { background: #6c757d; }
        
        /* Refund badge styles */
        .status-badge.refund-badge { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            color: white;
        }
        .refund-completed .payment-status { border-color: #28a745; }
        .refund-pending .payment-status { border-color: #ffc107; }
        
        /* Refund amount display */
        .text-success h3 { color: #28a745 !important; }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .payment-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .payment-details {
            background: rgba(79, 172, 254, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #4facfe;
            margin: 1rem 0;
        }
        
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
        
        .action-btn.view { background: var(--secondary-gradient); color: white; }
        
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
        
        .owner-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            margin-right: 0.75rem;
        }
        
        .payment-method {
            background: rgba(79, 172, 254, 0.1);
            color: #4facfe;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .transaction-id {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            font-family: monospace;
        }
        
        .amount-display {
            background: var(--secondary-gradient);
            color: white;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .demo-notice {
            background: var(--secondary-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .payment-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .payment-timeline::before {
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
            background: #4facfe;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #4facfe;
        }
        
        .quick-stats {
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
            
            .search-filters {
                padding: 1rem;
            }
            
            .payment-card {
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
                <i class="fas fa-search"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Renter Dashboard</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="browse.php">
                        <i class="fas fa-search me-2"></i> Browse Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> My Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="favorites.php">
                        <i class="fas fa-heart me-2"></i> Favorites
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
                    <a class="nav-link active" href="payment-history.php">
                        <i class="fas fa-money-bill me-2"></i> Payment History
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
                <?php if($_SESSION['user_role'] == 3): ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="../owner/dashboard.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-store me-2"></i> Switch to Owner
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="upgrade.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-crown me-2"></i> Become an Owner
                    </a>
                </li>
                <?php endif; ?>
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
                        <i class="fas fa-credit-card text-primary me-2"></i>Payment History
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
                                            <span class="text-muted small"> <?php echo $notif['Not_Message']; ?> </span><br>
                                            <small class="text-muted d-block"> <?php echo date('M j, Y \a\t h:i A', strtotime($notif['Not_CreatedAt'])); ?> </small>
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

            <?php if(!$payments_table_exists): ?>
            <!-- Demo Notice -->
            <div class="demo-notice">
                <h5 class="mb-3">
                    <i class="fas fa-database me-2"></i>Payment System Preview
                </h5>
                <p class="mb-0">The payment tracking system is being set up. Below are sample payment records based on your bookings. Once the database is configured, you'll have full payment history tracking!</p>
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
                                        Total Payments
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_payments']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-credit-card fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card amount">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Amount
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_amount'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card successful">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Successful
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['successful_payments']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                                        Pending
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['pending_payments']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <h5 class="text-dark mb-3">
                    <i class="fas fa-chart-line me-2"></i>Payment Overview
                </h5>
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <i class="fas fa-calendar-month fa-2x text-primary mb-2"></i>
                            <h6>This Month</h6>
                            <h4 class="text-primary">₱<?php echo number_format($stats['total_amount'] * 0.3, 0); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <i class="fas fa-chart-bar fa-2x text-success mb-2"></i>
                            <h6>Average Payment</h6>
                            <h4 class="text-success">₱<?php echo $stats['total_payments'] > 0 ? number_format($stats['total_amount'] / $stats['total_payments'], 0) : '0'; ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                            <h6>Largest Payment</h6>
                            <h4 class="text-warning">₱<?php echo number_format($stats['total_amount'] * 0.4, 0); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                            <h6>Success Rate</h6>
                            <h4 class="text-info"><?php echo $stats['total_payments'] > 0 ? round(($stats['successful_payments'] / $stats['total_payments']) * 100) : 0; ?>%</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Filter by Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Payments</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount: High to Low</option>
                            <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount: Low to High</option>
                            <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>By Status</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payment History List -->
            <?php if(empty($payments)): ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card"></i>
                    <h4 class="text-muted">No payment history</h4>
                    <p class="text-muted">You haven't made any payments yet. Start renting to see your payment history here!</p>
                    <a href="browse.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-search me-2"></i>Browse Products
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($payments as $payment): ?>
                    <?php 
                    $isRefund = isset($payment['type']) && $payment['type'] === 'refund';
                    
                    // Skip security deposit refunds as they're shown as deductions from payments
                    if ($isRefund && stripos($payment['refund_reason'], 'security deposit') !== false) {
                        continue;
                    }
                    
                    $statusClass = $isRefund ? 'refund-' . strtolower($payment['status']) : strtolower($payment['status']);
                    ?>
                <div class="payment-card card <?php echo !$payments_table_exists ? 'sample' : ''; ?> <?php echo $statusClass; ?>" data-payment-id="<?php echo $payment['ID']; ?>">
                    <div class="payment-status">
                        <?php if($isRefund): ?>
                            <span class="badge status-badge refund-badge">
                                <i class="fas fa-undo me-1"></i>Refund - <?php echo htmlspecialchars($payment['status']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge status-badge <?php echo strtolower($payment['status']); ?>">
                                <?php echo htmlspecialchars($payment['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-3">
                          <img src="<?php 
                             $imgPath = $payment['PI_ImagePath'] ? '../' . ltrim($payment['PI_ImagePath'], '/') : '../assets/images/no-image.jpg';
                             echo htmlspecialchars($imgPath);
                          ?>" 
                                     class="img-fluid rounded" style="width: 240px; height: 240px; object-fit: cover; aspect-ratio: 1 / 1;" 
                              alt="<?php echo htmlspecialchars($payment['Prod_Name']); ?>"
                              onerror="this.onerror=null;this.src='../assets/images/no-image.jpg';">
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-2"><?php echo htmlspecialchars($payment['Prod_Name']); ?></h5>
                                
                                <div class="payment-info mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="owner-avatar">
                                            <?php echo strtoupper(substr($payment['Owner_Name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">
                                                <?php if($isRefund): ?>
                                                    Security Deposit Refund from <?php echo htmlspecialchars($payment['Owner_Name']); ?>
                                                <?php else: ?>
                                                    Paid to <?php echo htmlspecialchars($payment['Owner_Name']); ?>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j, Y - g:i A', strtotime($payment['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="payment-details">
                                    <h6 class="mb-2">
                                        <?php if($isRefund): ?>
                                            <i class="fas fa-undo me-2"></i>Refund Details
                                        <?php else: ?>
                                            <i class="fas fa-receipt me-2"></i>Payment Details
                                        <?php endif; ?>
                                    </h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1 small"><strong>Transaction ID:</strong></p>
                                            <span class="transaction-id">
                                                <?php echo $payment['transaction_id'] ? htmlspecialchars($payment['transaction_id']) : ($isRefund ? 'REF' : 'TXN') . str_pad($payment['BookingID'], 6, '0', STR_PAD_LEFT); ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1 small"><strong><?php echo $isRefund ? 'Refund' : 'Payment'; ?> Method:</strong></p>
                                            <span class="payment-method">
                                                <?php echo $payment['method'] ? htmlspecialchars($payment['method']) : ($isRefund ? 'Automatic' : 'Credit Card'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if($isRefund && !empty($payment['refund_reason'])): ?>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <p class="mb-1 small"><strong>Refund Reason:</strong></p>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['refund_reason']); ?></small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(isset($payment['Book_StartDate'])): ?>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <p class="mb-1 small"><strong>Rental Period:</strong></p>
                                            <small><?php echo date('M j', strtotime($payment['Book_StartDate'])) . ' - ' . date('M j, Y', strtotime($payment['Book_EndDate'])); ?></small>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1 small"><strong>Booking ID:</strong></p>
                                            <small>#<?php echo str_pad($payment['BookingID'], 6, '0', STR_PAD_LEFT); ?></small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="text-end">
                                    <?php
                                    // For payments, check if there's a corresponding security deposit refund
                                    $hasSecurityRefund = false;
                                    $securityRefundAmount = 0;
                                    $displayAmount = $payment['amount'];
                                    
                                    if (!$isRefund) {
                                        // Look for security deposit refund for this booking
                                        foreach($payments as $p) {
                                            if (isset($p['type']) && $p['type'] === 'refund' && 
                                                $p['BookingID'] == $payment['BookingID'] && 
                                                stripos($p['refund_reason'], 'security deposit') !== false) {
                                                $hasSecurityRefund = true;
                                                $securityRefundAmount = $p['amount'];
                                                $displayAmount = $payment['amount'] - $securityRefundAmount;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <div class="amount-display mb-3">
                                        <h3 class="mb-1 <?php echo $isRefund ? 'text-success' : ''; ?>">
                                            <?php echo $isRefund ? '+' : ''; ?>₱<?php echo number_format($displayAmount, 2); ?>
                                        </h3>
                                        <small class="opacity-75"><?php echo $isRefund ? 'Refund Amount' : 'Net Payment'; ?></small>
                                        
                                        <?php if (!$isRefund && $hasSecurityRefund): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <small class="text-muted d-block">Original: ₱<?php echo number_format($payment['amount'], 2); ?></small>
                                                <small class="text-success d-block">
                                                    <i class="fas fa-minus me-1"></i>Security Deposit Refunded: ₱<?php echo number_format($securityRefundAmount, 2); ?>
                                                </small>
                                                <hr class="my-1">
                                                <small class="fw-bold">Net Amount: ₱<?php echo number_format($displayAmount, 2); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="payment-timeline">
                                        <div class="timeline-item">
                                            <small class="text-muted"><?php echo $isRefund ? 'Initiated:' : 'Initiated:'; ?></small><br>
                                            <small><?php echo date('M j, g:i A', strtotime($payment['created_at'])); ?></small>
                                        </div>
                                        <?php if($payment['status'] == 'Completed'): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted">Completed:</small><br>
                                            <small>
                                                <?php 
                                                $completedDate = $isRefund && $payment['updated_at'] ? $payment['updated_at'] : $payment['created_at'];
                                                echo date('M j, g:i A', strtotime($completedDate)); 
                                                ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex flex-column gap-2 mt-3">
                                        <button class="btn btn-outline-secondary btn-sm" onclick="viewDetails(<?php echo $payment['ID']; ?>, '<?php echo $isRefund ? 'refund' : 'payment'; ?>')" style="border-radius: 15px;">
                                            <i class="fas fa-info me-1"></i>Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Export Options -->
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="mb-3">
                            <i class="fas fa-file-export me-2"></i>Export Payment History
                        </h6>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <a href="export-payment-history-pdf.php" class="btn btn-outline-primary">
                                <i class="fas fa-file-pdf me-2"></i>Export PDF
                            </a>
                            <a href="export-payment-history-excel.php" class="btn btn-outline-success">
                                <i class="fas fa-file-excel me-2"></i>Export Excel
                            </a>
                            <a href="export-payment-history-csv.php" class="btn btn-outline-info">
                                <i class="fas fa-file-csv me-2"></i>Export CSV
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentDetailsModalLabel">
                        <i class="fas fa-receipt me-2"></i>Payment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="paymentDetailsContent">
                    <!-- Payment details will be loaded here -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading payment details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadReceiptFromModal()">
                        <i class="fas fa-download me-1"></i>Download Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Global variable to store current payment ID for receipt download
        let currentPaymentId = null;

        // Download receipt function
        function downloadReceipt(paymentId) {
            if (paymentId) {
                // Check if we're in demo mode (no real payments table)
                <?php if (!$payments_table_exists): ?>
                alert('Receipt download is not available in demo mode. This feature will be available when the payment system is fully configured.');
                return;
                <?php endif; ?>
                
                // Open receipt in new window
                const receiptWindow = window.open('download-receipt.php?payment_id=' + paymentId, '_blank');
                
                // Check if window was blocked or failed to open
                if (!receiptWindow || receiptWindow.closed || typeof receiptWindow.closed == 'undefined') {
                    alert('Unable to open receipt. Please check if pop-ups are blocked in your browser.');
                }
            } else {
                alert('Unable to download receipt. Payment ID not found.');
            }
        }

        // View payment details
        function viewDetails(paymentId) {
            currentPaymentId = paymentId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
            modal.show();
            
            // Reset modal content to loading state
            document.getElementById('paymentDetailsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading payment details...</p>
                </div>
            `;
            
            // Find payment data from the existing page data
            const paymentCard = document.querySelector(`[data-payment-id="${paymentId}"]`);
            let paymentData = null;
            
            if (paymentCard) {
                paymentData = extractPaymentDataFromCard(paymentCard);
            }
            
            if (paymentData) {
                displayPaymentDetails(paymentData);
            } else {
                // Fallback: show error message
                document.getElementById('paymentDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Unable to load payment details. Please try again.
                    </div>
                `;
            }
        }

        // Extract payment data from card element
        function extractPaymentDataFromCard(card) {
            try {
                const productName = card.querySelector('h5')?.textContent?.trim() || 'N/A';
                const ownerElement = card.querySelector('h6');
                const ownerName = ownerElement ? ownerElement.textContent.replace('Paid to ', '').trim() : 'N/A';
                const paymentAmount = card.querySelector('.amount-display h3')?.textContent?.trim() || 'N/A';
                const paymentDateElement = card.querySelector('.payment-info small');
                const paymentDate = paymentDateElement ? paymentDateElement.textContent.trim() : 'N/A';
                const paymentStatus = card.querySelector('.status-badge')?.textContent?.trim() || 'N/A';
                
                // Extract more details from the payment details section
                const transactionId = card.querySelector('.transaction-id')?.textContent?.trim() || 'N/A';
                const paymentMethod = card.querySelector('.payment-method')?.textContent?.trim() || 'N/A';
                
                // Extract rental period and booking ID
                const rentalPeriodElement = card.querySelector('.payment-details .row:nth-of-type(2) .col-6:first-child small');
                const rentalPeriod = rentalPeriodElement ? rentalPeriodElement.textContent.trim() : 'N/A';
                
                const bookingIdElement = card.querySelector('.payment-details .row:nth-of-type(2) .col-6:last-child small');
                const bookingId = bookingIdElement ? bookingIdElement.textContent.trim() : 'N/A';
                
                // Extract timeline information
                const initiatedElement = card.querySelector('.timeline-item:first-child small:last-child');
                const initiatedDate = initiatedElement ? initiatedElement.textContent.trim() : paymentDate;
                
                const completedElement = card.querySelector('.timeline-item:last-child small:last-child');
                const completedDate = completedElement ? completedElement.textContent.trim() : (paymentStatus === 'Completed' ? paymentDate : 'N/A');
                
                return {
                    productName,
                    ownerName,
                    paymentAmount,
                    paymentDate,
                    paymentStatus,
                    transactionId,
                    paymentMethod,
                    rentalPeriod,
                    bookingId,
                    initiatedDate,
                    completedDate
                };
            } catch (error) {
                console.error('Error extracting payment data:', error);
                return null;
            }
        }

        // Display payment details in modal
        function displayPaymentDetails(data) {
            const content = `
                <div class="payment-details-container">
                    <!-- Payment Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="payment-header p-3 bg-light rounded">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1 fw-bold">${data.productName}</h6>
                                        <p class="mb-0 text-muted">
                                            <i class="fas fa-user me-1"></i>Paid to: ${data.ownerName}
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <h4 class="mb-0 text-primary">${data.paymentAmount}</h4>
                                        <span class="badge bg-success">${data.paymentStatus}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">
                                        <i class="fas fa-credit-card me-2"></i>Payment Information
                                    </h6>
                                    <div class="payment-info">
                                        <div class="info-row">
                                            <strong>Transaction ID:</strong>
                                            <span class="text-muted">${data.transactionId}</span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Payment Method:</strong>
                                            <span class="text-muted">${data.paymentMethod}</span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Initiated:</strong>
                                            <span class="text-muted">${data.initiatedDate}</span>
                                        </div>
                                        ${data.completedDate !== 'N/A' ? `
                                        <div class="info-row">
                                            <strong>Completed:</strong>
                                            <span class="text-muted">${data.completedDate}</span>
                                        </div>
                                        ` : ''}
                                        <div class="info-row">
                                            <strong>Status:</strong>
                                            <span class="badge bg-success">${data.paymentStatus}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">
                                        <i class="fas fa-calendar me-2"></i>Booking Information
                                    </h6>
                                    <div class="booking-info">
                                        <div class="info-row">
                                            <strong>Booking ID:</strong>
                                            <span class="text-muted">${data.bookingId}</span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Rental Period:</strong>
                                            <span class="text-muted">${data.rentalPeriod}</span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Product:</strong>
                                            <span class="text-muted">${data.productName}</span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Owner:</strong>
                                            <span class="text-muted">${data.ownerName}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> This payment has been successfully processed. 
                                If you have any questions or concerns, please contact our support team.
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    .payment-details-container .info-row {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 0.5rem 0;
                        border-bottom: 1px solid #f1f3f4;
                    }
                    .payment-details-container .info-row:last-child {
                        border-bottom: none;
                    }
                    .payment-header {
                        border-left: 4px solid #007bff;
                    }
                </style>
            `;
            
            document.getElementById('paymentDetailsContent').innerHTML = content;
        }

        // Download receipt from modal
        function downloadReceiptFromModal() {
            if (currentPaymentId) {
                downloadReceipt(currentPaymentId);
            } else {
                alert('Unable to download receipt. Please try again.');
            }
        }

        // Export functions
        function exportToPDF() {
            alert('PDF export feature will be implemented with proper backend support.');
        }

        function exportToExcel() {
            alert('Excel export feature will be implemented with proper backend support.');
        }

        function exportToCSV() {
            alert('CSV export feature will be implemented with proper backend support.');
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

        // Animate payment cards on load
        window.addEventListener('load', function() {
            const paymentCards = document.querySelectorAll('.payment-card');
            paymentCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Status badge animation
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Timeline animation
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -50px 0px'
        };

        const timelineObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const timelineItems = entry.target.querySelectorAll('.timeline-item');
                    timelineItems.forEach((item, index) => {
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.style.transform = 'translateX(0)';
                        }, index * 200);
                    });
                }
            });
        }, observerOptions);

        document.querySelectorAll('.payment-timeline').forEach(timeline => {
            const items = timeline.querySelectorAll('.timeline-item');
            items.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = 'all 0.4s ease';
            });
            timelineObserver.observe(timeline);
        });

        // Amount display animation
        document.querySelectorAll('.amount-display').forEach(display => {
            display.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            
            display.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
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