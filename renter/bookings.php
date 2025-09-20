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

// Function to format booking notes JSON into readable format
function formatBookingNotes($notes_json) {
    if (empty($notes_json)) return '';
    
    $notes = json_decode($notes_json, true);
    if (!$notes) return $notes_json; // Return original if not valid JSON
    
    $formatted = '<div class="booking-details">';
    
    // Contact Information
    if (isset($notes['renter_name']) || isset($notes['renter_phone']) || isset($notes['renter_email'])) {
        $formatted .= '<div class="mb-2"><strong>Contact Information:</strong><br>';
        if (isset($notes['renter_name'])) $formatted .= '<small>Name: ' . htmlspecialchars($notes['renter_name']) . '</small><br>';
        if (isset($notes['renter_phone'])) $formatted .= '<small>Phone: ' . htmlspecialchars($notes['renter_phone']) . '</small><br>';
        if (isset($notes['renter_email'])) $formatted .= '<small>Email: ' . htmlspecialchars($notes['renter_email']) . '</small><br>';
        if (isset($notes['emergency_contact']) && !empty($notes['emergency_contact'])) {
            $formatted .= '<small>Emergency Contact: ' . htmlspecialchars($notes['emergency_contact']) . '</small><br>';
        }
        $formatted .= '</div>';
    }
    
    // Address
    if (isset($notes['renter_address'])) {
        $formatted .= '<div class="mb-2"><strong>Address:</strong><br>';
        $formatted .= '<small>' . htmlspecialchars($notes['renter_address']) . '</small></div>';
    }
    
    // Pickup/Delivery
    if (isset($notes['pickup_delivery'])) {
        $formatted .= '<div class="mb-2"><strong>Service:</strong><br>';
        $formatted .= '<small>' . ucfirst(htmlspecialchars($notes['pickup_delivery'])) . '</small></div>';
    }
    
    // Payment Method
    if (isset($notes['payment_method'])) {
        $formatted .= '<div class="mb-2"><strong>Payment Method:</strong><br>';
        $formatted .= '<small>' . htmlspecialchars($notes['payment_method']) . '</small>';
        if (isset($notes['payment_account_name']) && !empty($notes['payment_account_name'])) {
            $formatted .= '<br><small>Account Name: ' . htmlspecialchars($notes['payment_account_name']) . '</small>';
        }
        if (isset($notes['payment_account_number']) && !empty($notes['payment_account_number'])) {
            $formatted .= '<br><small>Account Number: ' . htmlspecialchars($notes['payment_account_number']) . '</small>';
        }
        $formatted .= '</div>';
    }
    
    // Special Instructions
    if (isset($notes['special_instructions']) && !empty($notes['special_instructions'])) {
        $formatted .= '<div class="mb-2"><strong>Special Instructions:</strong><br>';
        $formatted .= '<small>' . htmlspecialchars($notes['special_instructions']) . '</small></div>';
    }
    
    $formatted .= '</div>';
    return $formatted;
}

// Handle booking actions
if ($_POST) {
    if (isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];
        
        // Check if booking can be cancelled (only pending bookings)
        $query = "SELECT Book_Status FROM bookings WHERE BookingID = ? AND RenterID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $booking_id);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking && $booking['Book_Status'] == 'Pending') {
            $query = "UPDATE bookings SET Book_Status = 'Cancelled', Book_UpdatedAt = NOW() WHERE BookingID = ? AND RenterID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $booking_id);
            $stmt->bindParam(2, $user_id);
            
            if ($stmt->execute()) {
                $message = "Booking cancelled successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to cancel booking. Please try again.";
                $message_type = "danger";
            }
        } else {
            $message = "This booking cannot be cancelled.";
            $message_type = "warning";
        }
    }
    
    if (isset($_POST['confirm_payment'])) {
        $booking_id = $_POST['booking_id'];
        
        // First check if booking is confirmed and has a payment record
        $check_query = "SELECT b.Book_Status, b.Book_TotalAmount, b.Book_Notes 
                       FROM bookings b 
                       WHERE b.BookingID = ? AND b.RenterID = ? AND b.Book_Status = 'Confirmed'";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$booking_id, $user_id]);
        $booking_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking_data) {
            // Check if payment record exists, if not create it
            $payment_check = "SELECT PaymentID FROM payments WHERE BookingID = ?";
            $payment_check_stmt = $conn->prepare($payment_check);
            $payment_check_stmt->execute([$booking_id]);
            $existing_payment = $payment_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_payment) {
                // Create payment record
                $notes = json_decode($booking_data['Book_Notes'], true);
                $payment_method = $notes['payment_method'] ?? 'Cash';

                // Generate unique transaction ID
                $user_id = $_SESSION['user_id'];
                $now = date('YmdHis');
                $random = mt_rand(1000, 9999);
                $pay_transaction_id = "TXN{$now}-USER{$user_id}-{$random}";

                $create_payment = "INSERT INTO payments (BookingID, Pay_Amount, Pay_Type, Pay_Method, Pay_Status, Pay_TransactionID, Pay_CreatedAt) 
                                  VALUES (?, ?, 'Rental Payment', ?, 'Pending', ?, NOW())";
                $create_payment_stmt = $conn->prepare($create_payment);
                $create_payment_stmt->execute([
                    $booking_id,
                    $booking_data['Book_TotalAmount'],
                    $payment_method,
                    $pay_transaction_id
                ]);
            }
            
            // Update payment status to completed
            $payment_query = "UPDATE payments SET Pay_Status = 'Completed', Pay_ProcessedAt = NOW() 
                             WHERE BookingID = ? AND Pay_Status = 'Pending'";
            $payment_stmt = $conn->prepare($payment_query);
            $payment_stmt->execute([$booking_id]);
            
            // Update booking status to Active (rental is now active)
            $booking_query = "UPDATE bookings SET Book_Status = 'Active', Book_UpdatedAt = NOW() 
                             WHERE BookingID = ? AND RenterID = ?";
            $booking_stmt = $conn->prepare($booking_query);
            $booking_stmt->execute([$booking_id, $user_id]);
            
            $message = "Payment confirmed successfully! Your rental is now active.";
            $message_type = "success";
        } else {
            $message = "Cannot process payment. Booking must be approved by owner first.";
            $message_type = "warning";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["b.RenterID = ?"];
$params = [$user_id];

if ($status_filter && $status_filter != 'all') {
    $conditions[] = "b.Book_Status = ?";
    $params[] = ucfirst($status_filter);
}

// Sort options
$sort_options = [
    'newest' => 'b.Book_CreatedAt DESC',
    'oldest' => 'b.Book_CreatedAt ASC',
    'start_date' => 'b.Book_StartDate ASC',
    'amount_high' => 'b.Book_TotalAmount DESC',
    'amount_low' => 'b.Book_TotalAmount ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'b.Book_CreatedAt DESC';

// Get bookings
$query = "SELECT b.*, p.Prod_Name, p.Prod_Description, pi.PI_ImagePath, u.User_Name as Owner_Name, u.User_Phone as Owner_Phone,
          ua.UA_City, ua.UA_Province,
          pay.PaymentID, pay.Pay_Amount, pay.Pay_Type, pay.Pay_Method, pay.Pay_Status, pay.Pay_CreatedAt as Payment_CreatedAt, pay.Pay_ProcessedAt,
          (SELECT COUNT(*) FROM reviews WHERE BookingID = b.BookingID) as has_review
          FROM bookings b
          JOIN products p ON b.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON p.OwnerID = u.UserID
          LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
          LEFT JOIN payments pay ON b.BookingID = pay.BookingID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE RenterID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE RenterID = ? AND Book_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total spent
$query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE RenterID = ? AND Book_Status IN ('Active', 'Completed')";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Completed bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE RenterID = ? AND Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['completed_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <link href="../css/renter-theme.css" rel="stylesheet">
    <style>
        /* Ensure modal appears above backdrop and other elements */
        .modal {
            z-index: 1055 !important; /* Higher than default backdrop z-index (1050) */
        }

        /* Customize backdrop to appear below modal */
        .modal-backdrop {
            z-index: 1050 !important; /* Default Bootstrap backdrop z-index */
            background-color: #000 !important;
            opacity: 0.5 !important;
        }

        /* Ensure modal dialog is fully interactive */
        .modal-dialog {
            z-index: 1060 !important; /* Higher than modal and backdrop */
        }

        /* Ensure modal submit button is green and clickable */
        #paymentCompletionModal .modal-footer .btn-success {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
            color: #fff !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        #paymentCompletionModal .modal-footer .btn-success:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
            opacity: 0.65 !important;
            pointer-events: none !important;
        }

        /* Ensure sidebar doesn't interfere with modal */
        .sidebar {
            z-index: 1000 !important; /* Lower than modal */
        }

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
        
        .stat-card.total { background: var(--primary-gradient); color: white; }
        .stat-card.active { background: var(--warning-gradient); color: white; }
        .stat-card.spent { background: var(--info-gradient); color: white; }
        .stat-card.completed { background: var(--secondary-gradient); color: white; }
        
        .booking-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            border-left: 4px solid #11998e;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .booking-status {
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
        
        .status-badge.pending { background: #ffc107; color: #000; }
        .status-badge.confirmed { background: #17a2b8; }
        .status-badge.active { background: #28a745; }
        .status-badge.completed { background: #6c757d; }
        .status-badge.cancelled { background: #dc3545; }
        
        .payment-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
        }
        .payment-status.pending { background: #ffeaa7; color: #2d3436; }
        .payment-status.completed { background: #00b894; color: white; }
        .payment-status.failed { background: #e17055; color: white; }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .owner-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .booking-details {
            background: rgba(17, 153, 142, 0.05);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #11998e;
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
        
        .action-btn.cancel { background: var(--accent-gradient); color: white; }
        .action-btn.contact { background: var(--info-gradient); color: white; }
        .action-btn.review { background: var(--warning-gradient); color: white; }
        .action-btn.complete { background: var(--primary-gradient); color: white; }
        
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
        
        .booking-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .booking-timeline::before {
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
            margin-bottom: 1.5rem;
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
        
        .duration-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
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
            
            .booking-card {
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
                    <a class="nav-link active" href="bookings.php">
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
                    <a class="nav-link" href="payment-history.php">
                        <i class="fas fa-money-bill me-2"></i> Payment History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user me-2"></i> Profile Settings
                    </a>
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
                        <i class="fas fa-calendar-check text-primary me-2"></i>My Bookings
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $stats['active_bookings']; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-check-circle text-success me-2"></i>Booking confirmed</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar text-info me-2"></i>Rental starts tomorrow</a></li>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card total">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
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
                                        Active Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
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
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_spent'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card completed">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Completed
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['completed_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Filter by Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Bookings</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="start_date" <?php echo $sort_by == 'start_date' ? 'selected' : ''; ?>>Start Date</option>
                            <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount: High to Low</option>
                            <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount: Low to High</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bookings List -->
            <?php if(empty($bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h4 class="text-muted">No bookings found</h4>
                    <p class="text-muted">You haven't made any bookings yet. Start exploring products to rent!</p>
                    <a href="../browse.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-search me-2"></i>Browse Products
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($bookings as $booking): ?>
                <div class="booking-card card">
                    <div class="booking-status">
                        <?php if($booking['PaymentID']): ?>
                        <span class="badge payment-status <?php echo strtolower($booking['Pay_Status']); ?> ms-2">
                            Payment: <?php echo htmlspecialchars($booking['Pay_Status']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if (!empty($booking['PI_ImagePath'])): ?>
                                    <img src="<?php echo '../' . htmlspecialchars($booking['PI_ImagePath']); ?>" 
                                         class="img-fluid rounded" style="height: 180px; width: 100%; object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($booking['Prod_Name']); ?>"
                                         onerror="this.src='../assets/images/no-image.jpg'">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-light border rounded" style="height: 180px; width: 100%;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-2"><?php echo htmlspecialchars($booking['Prod_Name']); ?></h5>
                                <p class="text-muted mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($booking['Prod_Description']); ?>
                                </p>
                                
                                <div class="owner-info">
                                    <h6 class="mb-2">
                                        <i class="fas fa-user me-2"></i>Owner Information
                                    </h6>
                                    <p class="mb-1"><strong><?php echo htmlspecialchars($booking['Owner_Name']); ?></strong></p>
                                    <?php if($booking['UA_City']): ?>
                                        <p class="mb-0 small text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($booking['UA_City'] . ', ' . $booking['UA_Province']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="booking-details mt-3">
                                    <h6 class="mb-2 fw-bold">
                                        <i class="fas fa-calendar me-2"></i>Booking Details
                                    </h6>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <div class="small"><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($booking['Book_StartDate'])); ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small"><strong>End Date:</strong> <?php echo date('M j, Y', strtotime($booking['Book_EndDate'])); ?></div>
                                        </div>
                                    </div>
                                    <?php if($booking['Book_Notes']): ?>
                                        <div class="mb-1 small fw-semibold">Booking Details:</div>
                                        <div class="mb-0 small"><?php echo formatBookingNotes($booking['Book_Notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="d-flex align-items-center justify-content-between mb-2" style="gap: 0.5rem;">
                                    <h4 class="text-primary mb-0">₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?></h4>
                                    <?php if($booking['Book_Status'] == 'Pending'): ?>
                                        <span class="badge status-badge px-3 py-2 fs-6 bg-warning text-dark" style="border-radius: 1rem;">Pending</span>
                                    <?php elseif($booking['Book_Status'] == 'Confirmed'): ?>
                                        <span class="badge status-badge px-3 py-2 fs-6 bg-success" style="border-radius: 1rem;">Confirmed</span>
                                    <?php elseif($booking['Book_Status'] == 'Cancelled'): ?>
                                        <span class="badge status-badge px-3 py-2 fs-6 bg-danger" style="border-radius: 1rem;">Cancelled</span>
                                    <?php elseif($booking['Book_Status'] == 'Completed'): ?>
                                        <span class="badge status-badge px-3 py-2 fs-6 bg-secondary" style="border-radius: 1rem;">Completed</span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $start_date = new DateTime($booking['Book_StartDate']);
                                $end_date = new DateTime($booking['Book_EndDate']);
                                $duration = $start_date->diff($end_date)->days + 1;
                                ?>
                                <div class="duration-badge mb-3">
                                    <?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?>
                                </div>
                                <div class="booking-timeline">
                                    <div class="timeline-item">
                                        <small class="text-muted">Booked:</small><br>
                                        <small><?php echo date('M j, Y', strtotime($booking['Book_CreatedAt'])); ?></small>
                                    </div>
                                    <?php if($booking['Book_UpdatedAt'] != $booking['Book_CreatedAt']): ?>
                                    <div class="timeline-item">
                                        <small class="text-muted">Updated:</small><br>
                                        <small><?php echo date('M j, Y', strtotime($booking['Book_UpdatedAt'])); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex flex-column gap-2 mt-3">
                                    <?php if($booking['Book_Status'] == 'Pending'): ?>
                                        <!-- Cancel Booking Button (for pending bookings) -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['BookingID']; ?>">
                                            <button type="submit" name="cancel_booking" class="btn action-btn cancel btn-sm" 
                                                    onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                <i class="fas fa-times me-1"></i>Cancel
                                            </button>
                                        </form>
                                        <div class="text-info small mt-2">
                                            <i class="fas fa-clock me-1"></i>Waiting for owner approval
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($booking['Book_Status'] == 'Confirmed'): ?>
                                        <!-- Payment options for confirmed bookings -->
                                        <?php if(!$booking['PaymentID'] || $booking['Pay_Status'] == 'Pending'): ?>
                                        <button type="button" class="btn btn-success btn-sm" onclick="showPaymentModal(<?php echo (int)$booking['BookingID']; ?>); return false;">
                                            <i class="fas fa-credit-card me-1"></i>Confirm Payment
                                        </button>
                                        <div class="text-warning small mt-2">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Please make payment to activate rental
                                        </div>
                                        <?php elseif($booking['Pay_Status'] == 'Completed'): ?>
                                        <div class="text-success small">
                                            <i class="fas fa-check-circle me-1"></i>Payment Confirmed
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if($booking['Owner_Phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($booking['Owner_Phone']); ?>" 
                                           class="btn action-btn contact btn-sm">
                                            <i class="fas fa-phone me-1"></i>Contact
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($booking['Book_Status'] == 'Completed' && $booking['has_review'] == 0): ?>
                                        <a href="add-review.php?booking=<?php echo $booking['BookingID']; ?>" 
                                           class="btn action-btn review btn-sm">
                                            <i class="fas fa-star me-1"></i>Review
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="../product.php?id=<?php echo $booking['ProductID']; ?>" 
                                       class="btn btn-outline-primary btn-sm" style="border-radius: 15px;">
                                        <i class="fas fa-eye me-1"></i>View Product
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Payment Completion Modal -->
                <div class="modal fade" id="paymentCompletionModal" tabindex="-1" aria-labelledby="paymentCompletionModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="paymentCompletionModalLabel">Complete Payment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="paymentCompletionForm" method="POST">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="payment_account_name_complete" class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" id="payment_account_name_complete" name="payment_account_name_complete" required placeholder="Your name as it appears on your account" />
                                    </div>
                                    <div class="mb-3">
                                        <label for="payment_account_number_complete" class="form-label">Account Number/Mobile</label>
                                        <input type="text" class="form-control" id="payment_account_number_complete" name="payment_account_number_complete" required placeholder="Account number or mobile number" />
                                    </div>
                                    <input type="hidden" id="payment_booking_id" name="booking_id" />
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> These details are for payment simulation only and will not be saved.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success" name="confirm_payment">Submit Payment</button>
                                </div>
                            </form>
                        </div>
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

        // Add loading state to action buttons
        document.querySelectorAll('form button').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.type === 'submit') {
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
                        this.disabled = true;
                    }, 100);
                }
            });
        });

        // Animate booking cards on load
        window.addEventListener('load', function() {
            const bookingCards = document.querySelectorAll('.booking-card');
            bookingCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Status badge color animation
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

        document.querySelectorAll('.booking-timeline').forEach(timeline => {
            const items = timeline.querySelectorAll('.timeline-item');
            items.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = 'all 0.4s ease';
            });
            timelineObserver.observe(timeline);
        });

        // Payment modal handling
        function showPaymentModal(bookingId) {
            // Set the booking ID in the hidden input
            document.getElementById('payment_booking_id').value = bookingId;
            // Initialize and show the modal
            var modalElement = document.getElementById('paymentCompletionModal');
            var modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static', // Prevent closing by clicking outside
                keyboard: true // Allow closing with ESC key
            });
            modal.show();
        }

        // Handle payment form submission
        document.getElementById('paymentCompletionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var bookingId = document.getElementById('payment_booking_id').value;
            var form = this;
            var formData = new FormData(form);
            formData.append('confirm_payment', '1');
            formData.append('booking_id', bookingId);

            // Add loading state to submit button
            var submitButton = form.querySelector('.btn-success');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            submitButton.disabled = true;

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                var modal = bootstrap.Modal.getInstance(document.getElementById('paymentCompletionModal'));
                modal.hide();
                setTimeout(function() {
                    location.reload();
                }, 300);
            })
            .catch(error => {
                console.error('Error:', error);
                submitButton.innerHTML = 'Submit Payment';
                submitButton.disabled = false;
                alert('An error occurred while processing the payment. Please try again.');
            });
        });
    </script>
</body>
</html>