<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle user actions (unchanged from original)
// Handle verify user
if (isset($_POST['verify_user']) && !empty($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $conn->prepare("UPDATE user_accounts SET User_IsVerified = 1 WHERE UserID = ?");
    $stmt->execute([$user_id]);
    // Optionally add a success message or redirect
}
// Handle unverify user
if (isset($_POST['unverify_user']) && !empty($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $conn->prepare("UPDATE user_accounts SET User_IsVerified = 0 WHERE UserID = ?");
    $stmt->execute([$user_id]);
    // Optionally add a success message or redirect
}
if ($_POST) {
    if (isset($_POST['update_user_status'])) {
        $user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update user status
            $query = "UPDATE user_accounts SET User_Status = ? WHERE UserID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $new_status);
            $stmt->bindParam(2, $user_id);
            $stmt->execute();
            
            if ($new_status === 'Inactive' || $new_status === 'Suspended') {
                // Get user info to check if they are an owner
                $user_check = $conn->prepare("SELECT User_Role, User_Name, User_Email FROM user_accounts WHERE UserID = ?");
                $user_check->execute([$user_id]);
                $user_info = $user_check->fetch(PDO::FETCH_ASSOC);
                
                // If user is owner (role 2 or 3), handle their products and bookings
                if ($user_info && in_array($user_info['User_Role'], [2, 3])) {
                    // 1. Suspend/Deactivate all their products based on user status
                    $product_status = $new_status === 'Suspended' ? 'Suspended' : 'Inactive';
                    $prod_stmt = $conn->prepare("UPDATE products SET Prod_Status = ? WHERE OwnerID = ?");
                    $prod_stmt->execute([$product_status, $user_id]);
                    
                    // 2. Get all pending bookings for this owner
                    $pending_bookings_query = "SELECT b.BookingID, b.RenterID, b.Book_TotalAmount, b.Book_SecurityDeposit, 
                                             p.Prod_Name, r.User_Name as Renter_Name, r.User_Email as Renter_Email,
                                             pay.PaymentID, pay.Pay_Status, pay.Pay_Amount
                                             FROM bookings b
                                             JOIN products p ON b.ProductID = p.ProductID
                                             JOIN user_accounts r ON b.RenterID = r.UserID
                                             LEFT JOIN payments pay ON b.BookingID = pay.BookingID
                                             WHERE b.OwnerID = ? AND b.Book_Status = 'Pending'";
                    $pending_stmt = $conn->prepare($pending_bookings_query);
                    $pending_stmt->execute([$user_id]);
                    $pending_bookings = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 3. Cancel all pending bookings
                    if (!empty($pending_bookings)) {
                        $cancel_pending = $conn->prepare("UPDATE bookings SET Book_Status = 'Cancelled', 
                                                         Book_CancelReason = ?, 
                                                         Book_UpdatedAt = NOW() 
                                                         WHERE OwnerID = ? AND Book_Status = 'Pending'");
                        $cancel_reason = $new_status === 'Suspended' ? 'Owner account suspended by administrator' : 'Owner account deactivated by administrator';
                        $cancel_pending->execute([$cancel_reason, $user_id]);
                        
                        // 4. Create notifications for affected renters and handle refunds
                        foreach ($pending_bookings as $booking) {
                            // Create notification for renter
                            $action_text = $new_status === 'Suspended' ? 'suspension' : 'deactivation';
                            $notification_msg = "Your booking for '{$booking['Prod_Name']}' has been cancelled due to owner account {$action_text}. ";
                            
                            // Handle refunds for paid bookings
                            if ($booking['PaymentID'] && $booking['Pay_Status'] == 'Completed') {
                                // Create refund record
                                $refund_amount = $booking['Pay_Amount'];
                                // For suspended accounts, mark refund as approved automatically
                                $refund_status = $new_status === 'Suspended' ? 'Approved' : 'Pending';
                                $refund_query = "INSERT INTO refunds (BookingID, Refund_Amount, Refund_Status, Refund_Reason, Refund_CreatedAt) 
                                               VALUES (?, ?, ?, ?, NOW())";
                                $refund_reason = $new_status === 'Suspended' ? 'Owner account suspended - auto-approved by admin' : 'Owner account deactivated';
                                $refund_stmt = $conn->prepare($refund_query);
                                $refund_stmt->execute([$booking['BookingID'], $refund_amount, $refund_status, $refund_reason]);
                                
                                if ($new_status === 'Suspended') {
                                    $notification_msg .= "A refund of ₱" . number_format($refund_amount, 2) . " has been automatically approved and will be processed within 3-5 business days.";
                                } else {
                                    $notification_msg .= "A refund of ₱" . number_format($refund_amount, 2) . " will be processed within 3-5 business days.";
                                }
                            } else {
                                $notification_msg .= "No payment was processed for this booking.";
                            }
                            
                            // Insert notification
                            $notif_query = "INSERT INTO notifications (UserID, Not_Title, Not_Message, Not_Type, Not_CreatedAt) 
                                          VALUES (?, ?, ?, 'booking', NOW())";
                            $notif_title = $new_status === 'Suspended' ? 'Booking Cancelled - Owner Suspended' : 'Booking Cancelled - Owner Deactivated';
                            $notif_stmt = $conn->prepare($notif_query);
                            $notif_stmt->execute([
                                $booking['RenterID'], 
                                $notif_title,
                                $notification_msg
                            ]);
                        }
                    }
                    
                    // 5. Handle active/ongoing bookings more comprehensively
                    $active_bookings_query = "SELECT b.BookingID, b.RenterID, b.Book_TotalAmount, b.Book_SecurityDeposit, 
                                             b.Book_StartDate, b.Book_EndDate, b.Book_Status,
                                             p.Prod_Name, r.User_Name as Renter_Name, r.User_Email as Renter_Email,
                                             pay.PaymentID, pay.Pay_Status, pay.Pay_Amount
                                             FROM bookings b
                                             JOIN products p ON b.ProductID = p.ProductID
                                             JOIN user_accounts r ON b.RenterID = r.UserID
                                             LEFT JOIN payments pay ON b.BookingID = pay.BookingID
                                             WHERE b.OwnerID = ? AND b.Book_Status IN ('Confirmed', 'Active')";
                    $active_stmt = $conn->prepare($active_bookings_query);
                    $active_stmt->execute([$user_id]);
                    $active_bookings = $active_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $confirmed_count = 0;
                    $ongoing_count = 0;
                    
                    foreach ($active_bookings as $booking) {
                        if ($booking['Book_Status'] == 'Confirmed') {
                            // Confirmed but not yet started - can be cancelled with refund
                            $confirmed_count++;
                            
                            // Cancel the booking
                            $cancel_confirmed = $conn->prepare("UPDATE bookings SET Book_Status = 'Cancelled', 
                                                               Book_CancelReason = ?, 
                                                               Book_UpdatedAt = NOW() 
                                                               WHERE BookingID = ?");
                            $cancel_reason = $new_status === 'Suspended' ? 'Owner account suspended by administrator' : 'Owner account deactivated by administrator';
                            $cancel_confirmed->execute([$cancel_reason, $booking['BookingID']]);
                            
                            // Create refund for confirmed bookings if paid
                            if ($booking['PaymentID'] && $booking['Pay_Status'] == 'Completed') {
                                $refund_amount = $booking['Pay_Amount'];
                                // For suspended accounts, mark refund as approved automatically
                                $refund_status = $new_status === 'Suspended' ? 'Approved' : 'Pending';
                                $refund_query = "INSERT INTO refunds (BookingID, Refund_Amount, Refund_Status, Refund_Reason, Refund_CreatedAt) 
                                               VALUES (?, ?, ?, ?, NOW())";
                                $refund_reason = $new_status === 'Suspended' ? 'Owner account suspended - confirmed booking cancelled - auto-approved' : 'Owner account deactivated - confirmed booking cancelled';
                                $refund_stmt = $conn->prepare($refund_query);
                                $refund_stmt->execute([$booking['BookingID'], $refund_amount, $refund_status, $refund_reason]);
                                
                                if ($new_status === 'Suspended') {
                                    $notification_msg = "Your confirmed booking for '{$booking['Prod_Name']}' has been cancelled due to owner account {$action_text}. A full refund of ₱" . number_format($refund_amount, 2) . " has been automatically approved and will be processed within 3-5 business days.";
                                } else {
                                    $notification_msg = "Your confirmed booking for '{$booking['Prod_Name']}' has been cancelled due to owner account {$action_text}. A full refund of ₱" . number_format($refund_amount, 2) . " will be processed within 3-5 business days.";
                                }
                            } else {
                                $notification_msg = "Your confirmed booking for '{$booking['Prod_Name']}' has been cancelled due to owner account {$action_text}.";
                            }
                            
                            // Send notification to renter
                            $notif_query = "INSERT INTO notifications (UserID, Not_Title, Not_Message, Not_Type, Not_CreatedAt) 
                                          VALUES (?, ?, ?, 'booking', NOW())";
                            $notif_title = $new_status === 'Suspended' ? 'Confirmed Booking Cancelled - Owner Suspended' : 'Confirmed Booking Cancelled - Owner Deactivated';
                            $notif_stmt = $conn->prepare($notif_query);
                            $notif_stmt->execute([
                                $booking['RenterID'], 
                                $notif_title,
                                $notification_msg
                            ]);
                            
                        } else if ($booking['Book_Status'] == 'Active') {
                            // Ongoing rental - needs special handling
                            $ongoing_count++;
                            
                            // Check if rental period has already started
                            $start_date = new DateTime($booking['Book_StartDate']);
                            $end_date = new DateTime($booking['Book_EndDate']);
                            $current_date = new DateTime();
                            
                            if ($current_date >= $start_date && $current_date <= $end_date) {
                                // Rental is currently ongoing - mark for admin review
                                $update_active = $conn->prepare("UPDATE bookings SET Book_Status = 'Under Review', 
                                                                Book_CancelReason = ?, 
                                                                Book_UpdatedAt = NOW() 
                                                                WHERE BookingID = ?");
                                $review_reason = $new_status === 'Suspended' ? 'Owner suspended during active rental - requires admin review' : 'Owner deactivated during active rental - requires admin review';
                                $update_active->execute([$review_reason, $booking['BookingID']]);
                                
                                // For suspended accounts, also create refund for active rentals (admin can decide later)
                                if ($new_status === 'Suspended' && $booking['PaymentID'] && $booking['Pay_Status'] == 'Completed') {
                                    $refund_amount = $booking['Pay_Amount'];
                                    $refund_query = "INSERT INTO refunds (BookingID, Refund_Amount, Refund_Status, Refund_Reason, Refund_CreatedAt) 
                                                   VALUES (?, ?, 'Pending', ?, NOW())";
                                    $refund_reason = 'Owner suspended during active rental - admin review required';
                                    $refund_stmt = $conn->prepare($refund_query);
                                    $refund_stmt->execute([$booking['BookingID'], $refund_amount, $refund_reason]);
                                }
                                
                                // Create admin notification for ongoing rental
                                $admin_notif = "URGENT: Active rental (Booking #{$booking['BookingID']}) for '{$booking['Prod_Name']}' requires immediate attention. Owner was {$action_text} during ongoing rental period. Renter: {$booking['Renter_Name']} ({$booking['Renter_Email']}). Rental period: " . date('M j, Y', strtotime($booking['Book_StartDate'])) . " to " . date('M j, Y', strtotime($booking['Book_EndDate'])) . ".";
                                
                                $admin_notif_query = "INSERT INTO notifications (UserID, Not_Title, Not_Message, Not_Type, Not_CreatedAt) 
                                                    VALUES (1, ?, ?, 'urgent', NOW())";
                                $admin_stmt = $conn->prepare($admin_notif_query);
                                $admin_stmt->execute([
                                    "Urgent: Active Rental Requires Review",
                                    $admin_notif
                                ]);
                                
                                // Notify renter about the situation
                                $renter_msg = "We regret to inform you that the owner of your current rental '{$booking['Prod_Name']}' has been {$action_text}. Your rental is under administrative review. Our support team will contact you within 24 hours to resolve this matter. Your rental rights are protected.";
                                
                                $renter_notif_query = "INSERT INTO notifications (UserID, Not_Title, Not_Message, Not_Type, Not_CreatedAt) 
                                                     VALUES (?, ?, ?, 'urgent', NOW())";
                                $renter_stmt = $conn->prepare($renter_notif_query);
                                $renter_stmt->execute([
                                    $booking['RenterID'],
                                    "Rental Under Review - Action Required",
                                    $renter_msg
                                ]);
                            }
                        }
                    }
                    
                    $cancelled_count = count($pending_bookings);
                    $action_text = $new_status === 'Suspended' ? 'suspended' : 'deactivated';
                    $product_status_text = $new_status === 'Suspended' ? 'suspended' : 'deactivated';
                    $message = "User {$action_text} successfully! ";
                    $message .= "Products {$product_status_text}. ";
                    if ($cancelled_count > 0) {
                        if ($new_status === 'Suspended') {
                            $message .= "{$cancelled_count} pending booking(s) cancelled with auto-approved refunds. ";
                        } else {
                            $message .= "{$cancelled_count} pending booking(s) cancelled with refunds pending. ";
                        }
                    }
                    if ($confirmed_count > 0) {
                        if ($new_status === 'Suspended') {
                            $message .= "{$confirmed_count} confirmed booking(s) cancelled with auto-approved full refunds. ";
                        } else {
                            $message .= "{$confirmed_count} confirmed booking(s) cancelled with full refunds pending. ";
                        }
                    }
                    if ($ongoing_count > 0) {
                        $message .= "{$ongoing_count} active rental(s) marked for admin review and renter notification sent.";
                    }
                } else {
                    $action_text = $new_status === 'Suspended' ? 'suspended' : 'deactivated';
                    $message = "User {$action_text} successfully!";
                }
            } else {
                // Reactivating user
                $message = "User reactivated successfully!";
            }
            
            // Commit transaction
            $conn->commit();
            $message_type = "success";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Failed to update user status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    // ... (other action handlers remain unchanged)
}

// Get filter parameters (unchanged from original)
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions (unchanged from original)
$conditions = ["User_Role != 1"];
$params = [];

if ($role_filter && $role_filter != 'all') {
    $conditions[] = "User_Role = ?";
    $params[] = $role_filter;
}

if ($status_filter && $status_filter != 'all') {
    $conditions[] = "User_Status = ?";
    $params[] = ucfirst($status_filter);
}

if ($search_term) {
    $conditions[] = "(User_Name LIKE ? OR User_Email LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Sort options (unchanged from original)
$sort_options = [
    'newest' => 'User_CreatedAt DESC',
    'oldest' => 'User_CreatedAt ASC',
    'name_asc' => 'User_Name ASC',
    'name_desc' => 'User_Name DESC',
    'email_asc' => 'User_Email ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'User_CreatedAt DESC';

// Get users (with flag count and recent flag info added)
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM bookings WHERE RenterID = u.UserID) as total_bookings,
          (SELECT COUNT(*) FROM products WHERE OwnerID = u.UserID) as total_products,
          (SELECT SUM(Book_TotalAmount) FROM bookings WHERE RenterID = u.UserID AND Book_Status IN ('Active', 'Completed')) as total_spent,
          (SELECT COUNT(*) FROM flag_reports WHERE OwnerID = u.UserID AND FlagType = 'owner') as flag_count,
          (SELECT Reason FROM flag_reports WHERE OwnerID = u.UserID AND FlagType = 'owner' ORDER BY FlagID DESC LIMIT 1) as recent_flag_reason
          FROM user_accounts u 
          WHERE " . implode(' AND ', $conditions) . " 
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (unchanged from original)
$stats = [];

// Total users (excluding admins)
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status != 'Deleted' AND User_Role != 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active users (excluding admins)
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Active' AND User_Role != 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Flagged users (excluding admins)
$query = "SELECT COUNT(DISTINCT OwnerID) as total FROM flag_reports WHERE FlagType = 'owner' AND OwnerID IS NOT NULL";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['flagged_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Suspended users (excluding admins)
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Suspended' AND User_Role != 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['suspended_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Users by role (excluding admins)
$query = "SELECT User_Role, COUNT(*) as count FROM user_accounts WHERE User_Status != 'Deleted' AND User_Role != 1 GROUP BY User_Role";
$stmt = $conn->prepare($query);
$stmt->execute();
$role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats['owners'] = 0;
$stats['renters'] = 0;

foreach($role_counts as $role) {
    switch($role['User_Role']) {
        case 2: $stats['renters'] = $role['count']; break;
        case 3: $stats['owners'] = $role['count']; break;
    }
}

// Role mapping (unchanged from original)
function getRoleName($role_id) {
    switch($role_id) {
        case 1: return 'Admin';
        case 2: return 'Renter';
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
    <title>User Management - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .dropdown-menu {
            z-index: 2000 !important;
            max-height: 400px;
            overflow-y: auto;
        }
        .card, .card-body {
            overflow: visible !important;
        }
        .user-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: visible !important;
            position: relative;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .user-avatar {
            width: 35px;
            height: 35px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .role-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .stat-card {
            border-left: 4px solid;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-height: 94px;
            height: 100%;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        .stat-card.users { border-left-color: #007bff; }
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.new { border-left-color: #ffc107; }
        .stat-card.roles { border-left-color: #dc3545; }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .main-content {
            margin-left: 250px;
            padding-top: 70px;
            min-height: 100vh;
            transition: all 0.3s;
            max-width: 100%; /* Prevent excessive stretching */
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #343a40;
            transition: all 0.3s;
            z-index: 1000;
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
        .navbar {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 1000;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            .navbar {
                left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        /* Enhanced fix for action button overlap */
        .user-card .actions-top {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1001;
        }
        .btn-action {
            white-space: nowrap;
            padding: 0.25rem 1rem;
        }
        .dropdown-menu {
            min-width: 180px;
            left: auto !important;
            right: 0;
            margin-top: 0.25rem;
        }
        .user-card .col.ps-3 {
            max-width: 70%;
            flex: 0 0 70%;
            padding-top: 40px; /* Adjust padding to accommodate top actions */
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
                <a class="nav-link active" href="users.php">
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
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h5 class="mb-0">User Management</h5>
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
            <div class="row mb-4 g-3 d-flex">
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card stat-card users w-100">
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

                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card stat-card active w-100">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['active_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card stat-card flagged w-100">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Flagged Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['flagged_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-flag fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card stat-card suspended w-100">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Suspended Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['suspended_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-slash fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search/Filter Bar -->
            <form method="GET" class="mb-4">
                <div class="filter-card row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label mb-1"><i class="fas fa-search me-1"></i>Search Users</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search by name or email...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1"><i class="fas fa-flag me-1"></i>Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1"><i class="fas fa-user me-1"></i>Role</label>
                        <select class="form-select" name="role">
                            <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="2" <?php echo $role_filter == '2' ? 'selected' : ''; ?>>Renter</option>
                            <option value="3" <?php echo $role_filter == '3' ? 'selected' : ''; ?>>Owner</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1"><i class="fas fa-sort me-1"></i>Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            <option value="email_asc" <?php echo $sort_by == 'email_asc' ? 'selected' : ''; ?>>Email A-Z</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
            </form>

            <!-- Users List -->
            <div class="row">
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users me-2"></i>Users Management
                                <span class="badge bg-primary ms-2"><?php echo count($users); ?> Found</span>
                            </h5>
                            <div>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus me-1"></i>Add User
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="bulkSuspendUsers()">
                                    <i class="fas fa-user-slash me-1"></i>Bulk Suspend
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <form id="bulkSuspendForm" method="POST">
                                <?php foreach($users as $user): ?>
                                    <div class="user-card position-relative mb-0" style="border-radius:0; border-width:0 0 1px 0;">
                                        <div class="card-body py-3 px-4">
                                            <div class="actions-top">
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if(!$user['User_IsVerified']): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                            <button type="submit" name="verify_user" class="btn btn-outline-success btn-sm" title="Verify"><i class="fas fa-user-check"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                            <button type="submit" name="unverify_user" class="btn btn-outline-warning btn-sm" title="Unverify"><i class="fas fa-user-times"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if($user['User_Status'] == 'Active'): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                            <input type="hidden" name="new_status" value="Inactive">
                                                            <button type="submit" name="update_user_status" class="btn btn-outline-warning btn-sm" title="Deactivate"><i class="fas fa-ban"></i></button>
                                                        </form>
                                                        <form method="POST" style="display:inline;" id="suspendForm_<?php echo $user['UserID']; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                            <input type="hidden" name="new_status" value="Suspended">
                                                            <input type="hidden" name="update_user_status" value="1">
                                                            <button type="button" class="btn btn-outline-danger btn-sm" title="Suspend" onclick="confirmSuspend(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['User_Name'], ENT_QUOTES); ?>')"><i class="fas fa-user-slash"></i></button>
                                                        </form>
                                                    <?php elseif($user['User_Status'] == 'Suspended'): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                            <input type="hidden" name="new_status" value="Active">
                                                            <button type="submit" name="update_user_status" class="btn btn-outline-success btn-sm" title="Reactivate"><i class="fas fa-user-check"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                            <input type="hidden" name="new_status" value="Active">
                                                            <button type="submit" name="update_user_status" class="btn btn-outline-success btn-sm" title="Activate"><i class="fas fa-check-circle"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" id="deleteForm_<?php echo $user['UserID']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                        <input type="hidden" name="delete_user" value="1">
                                                        <button type="button" class="btn btn-outline-danger btn-sm" title="Delete" onclick="confirmDelete(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['User_Name'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="row align-items-center g-0 flex-nowrap">
                                                <div class="col-auto d-flex flex-column align-items-center justify-content-center" style="width: 60px;">
                                                    <input type="checkbox" class="form-check-input bulk-user-checkbox mb-2" name="bulk_user_ids[]" value="<?php echo $user['UserID']; ?>">
                                                    <div class="user-avatar mx-auto">
                                                        <?php echo strtoupper(substr($user['User_Name'], 0, 2)); ?>
                                                    </div>
                                                </div>
                                                <div class="col ps-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <span class="badge bg-<?php 
                                                            echo $user['User_Status'] == 'Active' ? 'success' : 
                                                                ($user['User_Status'] == 'Suspended' ? 'danger' : 'secondary'); 
                                                        ?> status-badge me-2">
                                                            <?php echo $user['User_Status']; ?>
                                                        </span>
                                                        <span class="role-badge me-2"><?php echo $user['User_Role'] == 2 ? 'Renter' : 'Owner'; ?></span>
                                                        <?php if($user['User_IsVerified']): ?>
                                                            <span class="badge bg-success">Verified</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Unverified</span>
                                                        <?php endif; ?>
                                                        <?php if(($user['flag_count'] ?? 0) > 0): ?>
                                                            <?php 
                                                            $tooltip = "Click to view all flag details\n";
                                                            $tooltip .= "Total flags: " . $user['flag_count'];
                                                            if ($user['recent_flag_reason']) {
                                                                $tooltip .= "\nRecent reason: " . substr($user['recent_flag_reason'], 0, 50) . (strlen($user['recent_flag_reason']) > 50 ? '...' : '');
                                                            }
                                                            ?>
                                                            <span class="badge bg-danger ms-2 cursor-pointer" 
                                                                  title="<?php echo htmlspecialchars($tooltip); ?>" 
                                                                  onclick="viewFlagReasons(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['User_Name'], ENT_QUOTES); ?>')" 
                                                                  style="cursor: pointer;">
                                                                <i class="fas fa-flag"></i> <?php echo $user['flag_count']; ?> Flag<?php echo $user['flag_count'] > 1 ? 's' : ''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="fw-bold mb-1" style="font-size:1.1rem;"> <?php echo htmlspecialchars($user['User_Name']); ?> </div>
                                                    <div class="text-muted small mb-1"> <?php echo htmlspecialchars($user['User_Email']); ?> </div>
                                                    <div class="d-flex flex-wrap align-items-center mb-1">
                                                        <div class="me-3"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['User_Phone'] ?: 'N/A'); ?></div>
                                                        <div class="me-3"><i class="fas fa-calendar-plus me-1"></i> Joined: <?php echo date('M j, Y', strtotime($user['User_CreatedAt'])); ?></div>
                                                        <div class="me-3">Bookings: <span class="fw-bold"><?php echo $user['total_bookings'] ?? 0; ?></span></div>
                                                        <div class="me-3">Products: <span class="fw-bold"><?php echo $user['total_products'] ?? 0; ?></span></div>
                                                        <div class="me-3">Flags: <span class="fw-bold <?php echo ($user['flag_count'] ?? 0) > 0 ? 'text-danger' : ''; ?>"><?php echo $user['flag_count'] ?? 0; ?></span></div>
                                                    </div>
                                                    <?php if(($user['flag_count'] ?? 0) > 0 && $user['recent_flag_reason']): ?>
                                                    <div class="small text-danger mt-1">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <strong>Recent flag:</strong> <?php echo htmlspecialchars(substr($user['recent_flag_reason'], 0, 80) . (strlen($user['recent_flag_reason']) > 80 ? '...' : '')); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus"></i> Add User
                                </button>
                                <button class="btn btn-warning" onclick="bulkSuspendUsers()">
                                    <i class="fas fa-user-slash"></i> Bulk Suspend
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Fetch the 5 most recent users (excluding admins)
                            $recentUsersStmt = $conn->prepare("SELECT User_Name, User_CreatedAt FROM user_accounts WHERE User_Role != 1 ORDER BY User_CreatedAt DESC LIMIT 5");
                            $recentUsersStmt->execute();
                            $recentUsers = $recentUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php if (count($recentUsers) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentUsers as $recentUser): ?>
                                        <li class="list-group-item d-flex align-items-center justify-content-between px-0">
                                            <span><i class="fas fa-user me-2 text-primary"></i><?php echo htmlspecialchars($recentUser['User_Name']); ?></span>
                                            <span class="text-muted small"><?php echo date('M j, Y', strtotime($recentUser['User_CreatedAt'])); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0 small">No recent activities</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Users Table</span>
                                <span class="badge bg-success">Ready</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Total Users</span>
                                <strong class="text-success"><?php echo number_format($stats['total_users']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Active Users</span>
                                <strong class="text-success"><?php echo number_format($stats['active_users']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="user_name" required placeholder="Enter full name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="user_email" required placeholder="Enter email address">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="user_password" required placeholder="Enter password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="user_phone" placeholder="Enter phone number">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">User Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="user_role" required>
                                    <option value="">Select Role</option>
                                    <option value="2">Renter</option>
                                    <option value="3">Owner</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="user_gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Birth Date</label>
                                <input type="date" class="form-control" name="user_birthdate">
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>User Account Guidelines:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Email must be unique in the system</li>
                                <li>Password will be securely hashed</li>
                                <li>User will be created as Active by default</li>
                                <li>User verification status will be set to unverified</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function bulkSuspendUsers() {
            const checkboxes = document.querySelectorAll('.bulk-user-checkbox:checked');
            if (checkboxes.length === 0) {
                Swal.fire({
                    title: 'No Users Selected',
                    text: 'Please select at least one user to suspend.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Bulk Suspend Users',
                text: `Are you sure you want to suspend ${checkboxes.length} selected user(s)? This will cancel their pending bookings and deactivate their products.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, suspend users',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkSuspendForm').submit();
                }
            });
        }

        function confirmSuspend(userId, userName) {
            Swal.fire({
                title: 'Suspend User',
                text: `Are you sure you want to suspend ${userName}? This will cancel their pending bookings and deactivate their products.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, suspend user',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('suspendForm_' + userId).submit();
                }
            });
        }

        function confirmDelete(userId, userName) {
            Swal.fire({
                title: 'Delete User',
                text: `Are you sure you want to permanently delete ${userName}? This action cannot be undone!`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete user',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    document.getElementById('deleteForm_' + userId).submit();
                }
            });
        }

        function viewFlagReasons(userId, userName) {
            // Show loading state
            Swal.fire({
                title: `Flag Reports for ${userName}`,
                html: '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading flag reports...</p></div>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // Fetch flag details
            fetch('get_flag_details.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let flagsHtml = '';
                        if (data.flags.length === 0) {
                            flagsHtml = '<p class="text-muted">No flag reports found.</p>';
                        } else {
                            flagsHtml = '<div class="flag-reports">';
                            data.flags.forEach((flag, index) => {
                                flagsHtml += `
                                    <div class="flag-report mb-3 p-3" style="border: 1px solid #dee2e6; border-radius: 0.375rem; background-color: #f8f9fa;">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-${flag.FlagType === 'owner' ? 'warning' : 'info'}">${flag.FlagType.toUpperCase()}</span>
                                            <small class="text-muted">Flag #${flag.FlagID}</small>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Reason:</strong> ${flag.Reason}
                                        </div>
                                        ${flag.Description ? `<div class="mb-2"><strong>Description:</strong> ${flag.Description}</div>` : ''}
                                        <div class="text-muted small">
                                            <strong>Reported by:</strong> ${flag.Reporter_Name || 'Anonymous'}
                                        </div>
                                    </div>
                                `;
                            });
                            flagsHtml += '</div>';
                        }

                        // Show flags with action buttons
                        Swal.fire({
                            title: `Flag Reports for ${userName}`,
                            html: flagsHtml,
                            width: '600px',
                            showCancelButton: true,
                            showConfirmButton: true,
                            confirmButtonText: '<i class="fas fa-user-slash"></i> Suspend User',
                            confirmButtonColor: '#dc3545',
                            cancelButtonText: 'Close',
                            cancelButtonColor: '#6c757d',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Call suspend function
                                confirmSuspend(userId, userName);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to load flag reports: ' + (data.message || 'Unknown error'),
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load flag reports. Please try again.',
                        icon: 'error'
                    });
                });
        }
    </script>
</body>
</html>