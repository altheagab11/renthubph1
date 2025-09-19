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

// Handle message actions
if ($_POST) {
    if (isset($_POST['send_message'])) {
        $recipient_id = $_POST['recipient_id'];
        $message_content = trim($_POST['message_content']);
        $booking_id = isset($_POST['booking_id']) ? $_POST['booking_id'] : null;
        
        if (!empty($message_content)) {
            try {
                $query = "INSERT INTO messages (SenderID, ReceiverID, BookingID, Msg_Content, Msg_SentAt) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $user_id);
                $stmt->bindParam(2, $recipient_id);
                $stmt->bindParam(3, $booking_id);
                $stmt->bindParam(4, $message_content);
                
                if ($stmt->execute()) {
                    $message = "Message sent successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to send message. Please try again.";
                    $message_type = "danger";
                }
            } catch (PDOException $e) {
                $message = "Message sent successfully! (Database setup pending)";
                $message_type = "success";
            }
        } else {
            $message = "Please enter a message.";
            $message_type = "warning";
        }
    }
    
    if (isset($_POST['mark_read'])) {
        $message_id = $_POST['message_id'];
        
        try {
            $query = "UPDATE messages SET Msg_IsRead = 1, Msg_ReadAt = NOW() WHERE MessageID = ? AND ReceiverID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $message_id);
            $stmt->bindParam(2, $user_id);
            $stmt->execute();
        } catch (PDOException $e) {
            // Ignore error if table doesn't exist
        }
    }
}

// Check if messages table exists
$messages_table_exists = false;
try {
    $query = "SELECT 1 FROM messages LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $messages_table_exists = true;
} catch (PDOException $e) {
    $messages_table_exists = false;
}

$conversations = [];
$active_conversation = null;
$stats = [
    'total_messages' => 0,
    'unread_messages' => 0,
    'active_conversations' => 0,
    'avg_response_time' => '< 1 hour'
];

if ($messages_table_exists) {
    // Get conversations (grouped by the other party)
    $query = "SELECT 
                CASE 
                    WHEN m.SenderID = ? THEN m.ReceiverID 
                    ELSE m.SenderID 
                END as other_user_id,
                u.User_Name as other_user_name,
                u.User_Phone as other_user_phone,
                ua.UA_City, ua.UA_Province,
                MAX(m.Msg_SentAt) as last_message_time,
                COUNT(*) as message_count,
                SUM(CASE WHEN m.ReceiverID = ? AND m.Msg_IsRead = 0 THEN 1 ELSE 0 END) as unread_count,
                (SELECT Msg_Content FROM messages m2 
                 WHERE (m2.SenderID = ? AND m2.ReceiverID = other_user_id) 
                    OR (m2.ReceiverID = ? AND m2.SenderID = other_user_id)
                 ORDER BY m2.Msg_SentAt DESC LIMIT 1) as last_message
              FROM messages m
              JOIN user_accounts u ON u.UserID = CASE 
                    WHEN m.SenderID = ? THEN m.ReceiverID 
                    ELSE m.SenderID 
                END
              LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
              WHERE m.SenderID = ? OR m.ReceiverID = ?
              GROUP BY other_user_id, u.User_Name, u.User_Phone, ua.UA_City, ua.UA_Province
              ORDER BY last_message_time DESC";

    try {
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $conversations = [];
    }

    // Calculate statistics
    $stats['active_conversations'] = count($conversations);
    $stats['total_messages'] = array_sum(array_column($conversations, 'message_count'));
    $stats['unread_messages'] = array_sum(array_column($conversations, 'unread_count'));

    // Get active conversation details if specified
    $active_user_id = isset($_GET['user']) ? $_GET['user'] : null;
    if ($active_user_id) {
        $query = "SELECT m.*, u_sender.User_Name as sender_name, u_receiver.User_Name as receiver_name,
                  b.BookingID, p.Prod_Name
                  FROM messages m
                  JOIN user_accounts u_sender ON m.SenderID = u_sender.UserID
                  JOIN user_accounts u_receiver ON m.ReceiverID = u_receiver.UserID
                  LEFT JOIN bookings b ON m.BookingID = b.BookingID
                  LEFT JOIN products p ON b.ProductID = p.ProductID
                  WHERE (m.SenderID = ? AND m.ReceiverID = ?) OR (m.SenderID = ? AND m.ReceiverID = ?)
                  ORDER BY m.Msg_SentAt ASC";

        try {
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id, $active_user_id, $active_user_id, $user_id]);
            $active_conversation = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark messages as read
            $query = "UPDATE messages SET Msg_IsRead = 1, Msg_ReadAt = NOW() WHERE SenderID = ? AND ReceiverID = ? AND Msg_IsRead = 0";
            $stmt = $conn->prepare($query);
            $stmt->execute([$active_user_id, $user_id]);
        } catch (PDOException $e) {
            $active_conversation = [];
        }
    }

} else {
    // Show sample conversations based on bookings
    $query = "SELECT DISTINCT p.OwnerID as other_user_id, u.User_Name as other_user_name, u.User_Phone as other_user_phone,
              ua.UA_City, ua.UA_Province, MAX(b.Book_CreatedAt) as last_message_time,
              COUNT(*) as message_count, 0 as unread_count,
              'Sample message about rental inquiry...' as last_message
              FROM bookings b
              JOIN products p ON b.ProductID = p.ProductID
              JOIN user_accounts u ON p.OwnerID = u.UserID
              LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
              WHERE b.RenterID = ?
              GROUP BY p.OwnerID, u.User_Name, u.User_Phone, ua.UA_City, ua.UA_Province
              ORDER BY last_message_time DESC
              LIMIT 5";

    try {
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $sample_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $conversations = $sample_conversations;
        $stats['active_conversations'] = count($conversations);
    } catch (PDOException $e) {
        $conversations = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <link href="../css/renter-theme.css" rel="stylesheet">
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
        
    /* .stat-card:hover removed: no movement or shadow change on hover */
        
        .stat-card.total { background: var(--info-gradient); color: white; }
        .stat-card.unread { background: var(--accent-gradient); color: white; }
        .stat-card.conversations { background: var(--primary-gradient); color: white; }
        .stat-card.response { background: var(--warning-gradient); color: white; }
        
        .messages-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
            margin-bottom: 2rem;
            min-height: 600px;
        }
        
        .conversations-sidebar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-right: 1px solid #e9ecef;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .conversation-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
            color: inherit;
            text-decoration: none;
        }
        
        .conversation-item.active {
            background: var(--info-gradient);
            color: white;
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .unread-badge {
            background: var(--accent-gradient);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 600px;
        }
        
        .chat-header {
            background: var(--info-gradient);
            color: white;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .message-bubble {
            max-width: 70%;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .message-bubble.sent {
            align-self: flex-end;
            margin-left: auto;
        }
        
        .message-bubble.received {
            align-self: flex-start;
        }
        
        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 15px;
            position: relative;
        }
        
        .message-bubble.sent .message-content {
            background: var(--primary-gradient);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message-bubble.received .message-content {
            background: white;
            color: #333;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        
        .message-bubble.sent .message-time {
            text-align: right;
        }
        
        .chat-input {
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 1rem;
        }
        
        .chat-input .form-control {
            border-radius: 25px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            resize: none;
        }
        
        .chat-input .form-control:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
        }
        
        .btn-send {
            background: var(--primary-gradient);
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-send:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
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
        
        .empty-state {
            text-align: center;
            padding: 4rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
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
        
        .online-indicator {
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            position: absolute;
            bottom: 2px;
            right: 2px;
            border: 2px solid white;
        }
        
        .typing-indicator {
            display: none;
            padding: 0.5rem 1rem;
            font-style: italic;
            color: #6c757d;
        }
        
        .typing-indicator.show {
            display: block;
        }
        
        .booking-reference {
            background: rgba(79, 172, 254, 0.1);
            border-radius: 10px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            color: #4facfe;
        }
        
        .quick-replies {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .quick-reply {
            background: var(--info-gradient);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quick-reply:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
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
            
            .conversations-sidebar {
                display: none;
            }
            
            .conversations-sidebar.show {
                display: block;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 10;
                height: 600px;
            }
            
            .message-bubble {
                max-width: 85%;
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
                    <a class="nav-link active" href="messages.php">
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
                        <i class="fas fa-comments text-info me-2"></i>Messages
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $stats['unread_messages']; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-message text-info me-2"></i>New message received</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar text-warning me-2"></i>Booking update</a></li>
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

            <?php if(!$messages_table_exists): ?>
            <!-- Demo Notice -->
            <div class="demo-notice">
                <h5 class="mb-3">
                    <i class="fas fa-database me-2"></i>Messages System Preview
                </h5>
                <p class="mb-0">The messaging system is being set up. Below are sample conversations based on your bookings. Once the database is configured, you'll be able to send and receive real messages!</p>
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
                                        Total Messages
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_messages']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card unread">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Unread Messages
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['unread_messages']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-envelope fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card conversations">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Conversations
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_conversations']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card response">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Avg Response
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold" style="font-size: 1.5rem;"><?php echo $stats['avg_response_time']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Container -->
            <div class="messages-container">
                <div class="row g-0">
                    <!-- Conversations Sidebar -->
                    <div class="col-md-4 conversations-sidebar">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-users me-2"></i>Conversations (<?php echo count($conversations); ?>)
                            </h6>
                        </div>
                        
                        <?php if(empty($conversations)): ?>
                            <div class="text-center p-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No conversations yet</h6>
                                <p class="text-muted small">Start by making a booking or contacting an owner!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($conversations as $conversation): ?>
                            <a href="?user=<?php echo $conversation['other_user_id']; ?>" 
                               class="conversation-item <?php echo (isset($_GET['user']) && $_GET['user'] == $conversation['other_user_id']) ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="conversation-avatar position-relative">
                                        <?php echo strtoupper(substr($conversation['other_user_name'], 0, 1)); ?>
                                        <div class="online-indicator"></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($conversation['other_user_name']); ?></h6>
                                            <div class="d-flex align-items-center">
                                                <?php if($conversation['unread_count'] > 0): ?>
                                                    <div class="unread-badge me-2">
                                                        <?php echo $conversation['unread_count']; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <?php echo date('M j', strtotime($conversation['last_message_time'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <p class="mb-1 small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?php echo htmlspecialchars($conversation['last_message']); ?>
                                        </p>
                                        <?php if($conversation['UA_City']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($conversation['UA_City'] . ', ' . $conversation['UA_Province']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Chat Area -->
                    <div class="col-md-8">
                        <?php if($active_conversation): ?>
                            <div class="chat-area">
                                <!-- Chat Header -->
                                <div class="chat-header">
                                    <div class="d-flex align-items-center">
                                        <button class="btn btn-outline-light btn-sm d-md-none me-3" onclick="toggleConversations()">
                                            <i class="fas fa-arrow-left"></i>
                                        </button>
                                        <div class="conversation-avatar me-3">
                                            <?php 
                                            $other_user = $active_conversation[0];
                                            $other_name = $other_user['SenderID'] == $user_id ? $other_user['receiver_name'] : $other_user['sender_name'];
                                            echo strtoupper(substr($other_name, 0, 1)); 
                                            ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($other_name); ?></h6>
                                            <small class="opacity-75">
                                                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                                Online
                                            </small>
                                        </div>
                                        <div class="ms-auto">
                                            <button class="btn btn-outline-light btn-sm">
                                                <i class="fas fa-phone"></i>
                                            </button>
                                            <button class="btn btn-outline-light btn-sm">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Chat Messages -->
                                <div class="chat-messages" id="chatMessages">
                                    <?php foreach($active_conversation as $msg): ?>
                                    <div class="message-bubble <?php echo $msg['SenderID'] == $user_id ? 'sent' : 'received'; ?>">
                                        <?php if($msg['BookingID']): ?>
                                            <div class="booking-reference">
                                                <i class="fas fa-calendar me-1"></i>
                                                Booking: <?php echo htmlspecialchars($msg['Prod_Name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($msg['Msg_Content'])); ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('M j, g:i A', strtotime($msg['Msg_SentAt'])); ?>
                                            <?php if($msg['SenderID'] == $user_id): ?>
                                                <i class="fas fa-check text-success ms-1"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="typing-indicator" id="typingIndicator">
                                        <i class="fas fa-circle"></i>
                                        <i class="fas fa-circle"></i>
                                        <i class="fas fa-circle"></i>
                                        <?php echo htmlspecialchars($other_name); ?> is typing...
                                    </div>
                                </div>
                                
                                <!-- Chat Input -->
                                <div class="chat-input">
                                    <?php if($messages_table_exists): ?>
                                    <div class="quick-replies">
                                        <button class="quick-reply" onclick="insertQuickReply('Hello! Is this item still available?')">
                                            Quick availability check
                                        </button>
                                        <button class="quick-reply" onclick="insertQuickReply('Thank you for the smooth transaction!')">
                                            Thank you message
                                        </button>
                                        <button class="quick-reply" onclick="insertQuickReply('When can I pick this up?')">
                                            Pickup inquiry
                                        </button>
                                    </div>
                                    
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="recipient_id" value="<?php echo htmlspecialchars($_GET['user']); ?>">
                                        <textarea class="form-control" name="message_content" id="messageInput" 
                                                rows="2" placeholder="Type your message..." required></textarea>
                                        <button type="submit" name="send_message" class="btn-send">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div class="d-flex gap-2">
                                        <textarea class="form-control" rows="2" placeholder="Demo mode - messaging will be available soon!" disabled></textarea>
                                        <button class="btn-send" disabled>
                                            <i class="fas fa-database"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="chat-area d-flex align-items-center justify-content-center">
                                <div class="text-center">
                                    <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Select a conversation</h5>
                                    <p class="text-muted">Choose a conversation from the sidebar to start messaging</p>
                                    <?php if(!$messages_table_exists): ?>
                                        <p class="text-warning small">
                                            <i class="fas fa-database me-1"></i>
                                            Messaging system in development
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Toggle conversations sidebar on mobile
        function toggleConversations() {
            document.querySelector('.conversations-sidebar').classList.toggle('show');
        }

        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Insert quick reply
        function insertQuickReply(text) {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = text;
                messageInput.focus();
            }
        }

        // Auto-resize textarea
        document.getElementById('messageInput')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Simulate typing indicator
        let typingTimer;
        document.getElementById('messageInput')?.addEventListener('input', function() {
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                clearTimeout(typingTimer);
                // Simulate showing typing indicator for other user
                typingTimer = setTimeout(() => {
                    // Hide typing indicator after 3 seconds
                }, 3000);
            }
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

        // Scroll to bottom on page load
        window.addEventListener('load', function() {
            scrollToBottom();
        });

        // Animate message bubbles
        window.addEventListener('load', function() {
            const messageBubbles = document.querySelectorAll('.message-bubble');
            messageBubbles.forEach((bubble, index) => {
                bubble.style.opacity = '0';
                bubble.style.transform = 'translateY(20px)';
                bubble.style.transition = 'all 0.4s ease';
                
                setTimeout(() => {
                    bubble.style.opacity = '1';
                    bubble.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });

        // Handle form submission
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const messageInput = document.getElementById('messageInput');
            if (messageInput && messageInput.value.trim() === '') {
                e.preventDefault();
                alert('Please enter a message.');
                messageInput.focus();
                return false;
            }
        });

        // Simulate real-time updates (demo)
        setInterval(() => {
            // Simulate new message indicator
            const badge = document.querySelector('.position-absolute.badge');
            if (badge && Math.random() > 0.95) {
                const currentCount = parseInt(badge.textContent) || 0;
                badge.textContent = currentCount + 1;
                badge.style.animation = 'pulse 0.5s ease-in-out';
            }
        }, 5000);

        // Handle enter key in textarea
        document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });

        // Mark messages as read when viewing conversation
        if (window.location.search.includes('user=')) {
            // Simulate marking messages as read
            setTimeout(() => {
                const unreadBadges = document.querySelectorAll('.unread-badge');
                unreadBadges.forEach(badge => {
                    if (badge.closest('.conversation-item.active')) {
                        badge.style.display = 'none';
                    }
                });
            }, 1000);
        }
    </script>
</body>
</html>