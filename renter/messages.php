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

// Notification dropdown logic (copied from dashboard.php)
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

// Conversations aggregated by user pair (single thread per user pair)
$conversations = [];
try {
    $baseQuery = "
        SELECT pairs.other_id AS Other_User_ID,
               u.User_Name AS Other_User_Name,
               u.User_Photo AS Other_User_Photo,
               (
                   SELECT m2.Msg_Content
                   FROM messages m2
                   JOIN conversations c2 ON c2.ConversationID = m2.ConversationID
                   WHERE ((c2.User1ID = :uid AND c2.User2ID = pairs.other_id) OR (c2.User1ID = pairs.other_id AND c2.User2ID = :uid))
                   ORDER BY m2.Msg_CreatedAt DESC
                   LIMIT 1
               ) AS last_message,
               (
                   SELECT m3.Msg_CreatedAt
                   FROM messages m3
                   JOIN conversations c3 ON c3.ConversationID = m3.ConversationID
                   WHERE ((c3.User1ID = :uid AND c3.User2ID = pairs.other_id) OR (c3.User1ID = pairs.other_id AND c3.User2ID = :uid))
                   ORDER BY m3.Msg_CreatedAt DESC
                   LIMIT 1
               ) AS last_message_time,
               (
                   SELECT COUNT(*) FROM messages m4
                   JOIN conversations c4 ON c4.ConversationID = m4.ConversationID
                   WHERE ((c4.User1ID = :uid AND c4.User2ID = pairs.other_id) OR (c4.User1ID = pairs.other_id AND c4.User2ID = :uid))
                     AND m4.SenderID != :uid AND m4.Msg_IsRead = 0
               ) AS unread_count,
               (
                   SELECT c6.ConversationID
                   FROM conversations c6
                   WHERE ((c6.User1ID = :uid AND c6.User2ID = pairs.other_id) OR (c6.User1ID = pairs.other_id AND c6.User2ID = :uid))
                   ORDER BY c6.Conv_LastMessageAt DESC
                   LIMIT 1
               ) AS Latest_ConversationID
        FROM (
            SELECT DISTINCT CASE WHEN c.User1ID = :uid THEN c.User2ID ELSE c.User1ID END AS other_id
            FROM conversations c
            WHERE (c.User1ID = :uid OR c.User2ID = :uid)
        ) pairs
        JOIN user_accounts u ON u.UserID = pairs.other_id
        ORDER BY COALESCE(last_message_time, '1970-01-01 00:00:00') DESC, Latest_ConversationID DESC
    ";
    $stmt = $conn->prepare($baseQuery);
    $stmt->execute([':uid' => $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $conversations = [];
}

// Handle user_id parameter - ensure single thread by user pair (ignore product)
if (isset($_GET['user_id']) && !isset($_GET['conversation'])) {
    $target_user_id = (int)$_GET['user_id'];
    // Find latest conversation with this user regardless of product
    $existing_conv_stmt = $conn->prepare("SELECT ConversationID FROM conversations
        WHERE (User1ID = ? AND User2ID = ?) OR (User1ID = ? AND User2ID = ?)
        ORDER BY Conv_LastMessageAt DESC LIMIT 1");
    $existing_conv_stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    $existing_conversation = $existing_conv_stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing_conversation) {
        header("Location: messages.php?conversation=" . $existing_conversation['ConversationID']);
        exit;
    } else {
        // Create new conversation record without product context
        $create_conv_stmt = $conn->prepare("INSERT INTO conversations (User1ID, User2ID, Conv_CreatedAt, Conv_LastMessageAt) VALUES (?, ?, NOW(), NOW())");
        $create_conv_stmt->execute([$user_id, $target_user_id]);
        $new_conversation_id = $conn->lastInsertId();
        header("Location: messages.php?conversation=" . $new_conversation_id);
        exit;
    }
}

// Determine active conversation (use latest per pair if none specified)
$conversation_id = isset($_GET['conversation']) ? (int)$_GET['conversation'] : 0;
if (!$conversation_id && !empty($conversations)) {
    $conversation_id = (int)($conversations[0]['Latest_ConversationID'] ?? 0);
}

// Fetch messages for the active user pair conversation (aggregate across all convs between the pair)
$messages = [];
$other_user_name = '';
$other_user_photo = '';
$product_name = '';
$send_conversation_id = $conversation_id; // default to current

if ($conversation_id) {
    // Get conversation details and other user info for this conversation id
    $query = "SELECT c.*, 
                     CASE WHEN c.User1ID = ? THEN u2.User_Name ELSE u1.User_Name END AS Other_User_Name,
                     CASE WHEN c.User1ID = ? THEN u2.User_Photo ELSE u1.User_Photo END AS Other_User_Photo,
                     p.Prod_Name
              FROM conversations c
              LEFT JOIN user_accounts u1 ON c.User1ID = u1.UserID
              LEFT JOIN user_accounts u2 ON c.User2ID = u2.UserID
              LEFT JOIN products p ON c.ProductID = p.ProductID
              WHERE c.ConversationID = ? AND (c.User1ID = ? OR c.User2ID = ?)";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $user_id, $conversation_id, $user_id, $user_id]);
    $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current_conversation) {
        $other_user_name = $current_conversation['Other_User_Name'] ?? '';
        $other_user_photo = $current_conversation['Other_User_Photo'] ?? '';
        $product_name = $current_conversation['Prod_Name'] ?? '';

        $other_user_id = ($current_conversation['User1ID'] == $user_id) ? $current_conversation['User2ID'] : $current_conversation['User1ID'];

        // Fetch all messages across all conversations between the pair
        $msgQuery = "SELECT m.*, u.User_Name as sender_name
                     FROM messages m
                     JOIN conversations c ON c.ConversationID = m.ConversationID
                     JOIN user_accounts u ON u.UserID = m.SenderID
                     WHERE ((c.User1ID = ? AND c.User2ID = ?) OR (c.User1ID = ? AND c.User2ID = ?))
                     ORDER BY m.Msg_CreatedAt ASC";
        $stmt = $conn->prepare($msgQuery);
        $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark all messages from other user as read across the pair
        $markQuery = "UPDATE messages m
                      JOIN conversations c ON c.ConversationID = m.ConversationID
                      SET m.Msg_IsRead = 1
                      WHERE ((c.User1ID = ? AND c.User2ID = ?) OR (c.User1ID = ? AND c.User2ID = ?))
                        AND m.SenderID != ?";
        $stmt = $conn->prepare($markQuery);
        $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id, $user_id]);

        // Determine latest conversation id between pair for sending new messages
        $latestStmt = $conn->prepare("SELECT ConversationID FROM conversations
                                      WHERE ((User1ID = ? AND User2ID = ?) OR (User1ID = ? AND User2ID = ?))
                                      ORDER BY Conv_LastMessageAt DESC LIMIT 1");
        $latestStmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
        $send_conversation_id = (int)($latestStmt->fetch(PDO::FETCH_ASSOC)['ConversationID'] ?? $conversation_id);
    } else {
        // Access denied or not found; reset selection
        $messages = [];
        $conversation_id = 0;
    }
}

// Stats using aggregated conversations
$stats = [];
$stats['total_conversations'] = count($conversations);
$stats['unread_conversations'] = 0;
foreach ($conversations as $conv) {
    if (!empty($conv['unread_count'])) { $stats['unread_conversations']++; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="user-id" content="<?php echo $user_id; ?>">
    <title>Messages - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
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
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { background: var(--secondary-gradient); color: white; }
        .stat-card.unread { background: var(--secondary-gradient); color: white; }
        .stat-card.conversations { background: var(--secondary-gradient); color: white; }
        .stat-card.response { background: var(--secondary-gradient); color: white; }
        
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
            position: relative;
        }
        
        .conversation-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
            color: inherit;
            text-decoration: none;
        }
        
        .conversation-item.active {
            background: var(--secondary-gradient);
            color: white;
        }
        
        .conversation-item.active .text-muted {
            color: rgba(255,255,255,0.7) !important;
        }
        
        .conversation-item .unread-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
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
        
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 600px;
        }
        
        .chat-header {
            background: var(--secondary-gradient);
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
            background: var(--secondary-gradient);
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
        
        .message-status {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }
        
        .message-bubble.received .message-status {
            color: #6c757d;
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
            background: var(--secondary-gradient);
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
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .typing-indicator.show {
            display: block;
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
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
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="browse.php"><i class="fas fa-search me-2"></i> Browse Items</a></li>
                <li class="nav-item"><a class="nav-link" href="bookings.php"><i class="fas fa-calendar-check me-2"></i> My Bookings</a></li>
                <li class="nav-item"><a class="nav-link" href="favorites.php"><i class="fas fa-heart me-2"></i> Favorites</a></li>
                <li class="nav-item"><a class="nav-link active" href="messages.php"><i class="fas fa-comments me-2"></i> Messages</a></li>
                <li class="nav-item"><a class="nav-link" href="reviews.php"><i class="fas fa-star me-2"></i> Reviews</a></li>
                <li class="nav-item"><a class="nav-link" href="payment-history.php"><i class="fas fa-money-bill me-2"></i> Payment History</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i> Profile Settings</a></li>
                <li class="nav-item mt-3">
                    <hr class="text-white-50">
                </li>
                <?php if($_SESSION['user_role'] == 3): ?>
                    <li class="nav-item mt-3"><a class="nav-link" href="../owner/dashboard.php" style="background-color: rgba(255,255,255,0.1);"><i class="fas fa-store me-2"></i> Switch to Owner</a></li>
                <?php else: ?>
                    <li class="nav-item mt-3"><a class="nav-link" href="upgrade.php" style="background-color: rgba(255,255,255,0.1);"><i class="fas fa-crown me-2"></i> Become an Owner</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card stat-card conversations">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Conversations
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_conversations']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card stat-card unread">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Unread Messages
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['unread_conversations']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-envelope fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="messages-container">
                <div class="row g-0">
                    <!-- Conversations Sidebar -->
                    <div class="col-md-4 conversations-sidebar">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0 text-dark">
                                <i class="fas fa-users me-2"></i>Conversations (<?php echo count($conversations); ?>)
                                <?php if($stats['unread_conversations'] > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $stats['unread_conversations']; ?></span>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <?php if(empty($conversations)): ?>
                            <div class="text-center p-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No conversations yet</h6>
                                <p class="text-muted small">Start by making a booking or contacting an owner!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($conversations as $conv): ?>
                            <a href="?conversation=<?php echo $conv['Latest_ConversationID']; ?>" 
                               class="conversation-item <?php echo ($conversation_id == (int)$conv['Latest_ConversationID']) ? 'active' : ''; ?>">
                                <?php if((int)$conv['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?php echo (int)$conv['unread_count']; ?></div>
                                <?php endif; ?>
                                
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <?php if(!empty($conv['Other_User_Photo'])): ?>
                                            <img src="../uploads/users/<?php echo htmlspecialchars($conv['Other_User_Photo']); ?>" 
                                                 class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;" 
                                                 alt="User Profile">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($conv['Other_User_Name']); ?></h6>
                                            <small class="text-muted">
                                                <?php 
                                                echo !empty($conv['last_message_time'])
                                                    ? date('M j', strtotime($conv['last_message_time'])) 
                                                    : '';
                                                ?>
                                            </small>
                                        </div>
                                        <p class="mb-0 small text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 40)); ?>
                                            <?php echo strlen($conv['last_message'] ?? '') > 40 ? '...' : ''; ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Area -->
                    <div class="col-md-8">
                        <?php if($conversation_id): ?>
                        <div class="chat-area">
                            <!-- Chat Header -->
                            <div class="chat-header">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <?php if(!empty($other_user_photo)): ?>
                                            <img src="../uploads/users/<?php echo htmlspecialchars($other_user_photo); ?>" 
                                                 class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover;" 
                                                 alt="User Profile">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" 
                                                 style="width: 60px; height: 60px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($other_user_name); ?></h6>
                                        <small class="opacity-75">
                                            <i class="fas fa-calendar text-warning me-1"></i>
                                            <?php echo htmlspecialchars($product_name); ?>
                                        </small>
                                    </div>
                                    <div class="ms-auto">
                                        <button class="btn btn-outline-light btn-sm me-2">
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
                                <?php if(empty($messages)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-comment-dots fa-3x mb-3"></i>
                                        <h6>No messages yet</h6>
                                        <p>Say hello to start the conversation!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($messages as $msg): ?>
                                    <div class="message-bubble <?php echo $msg['SenderID'] == $user_id ? 'sent' : 'received'; ?>" data-msg-id="<?php echo (int)$msg['MessageID']; ?>" data-time="<?php echo htmlspecialchars($msg['Msg_CreatedAt']); ?>">
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($msg['Msg_Content'])); ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('M j, g:i A', strtotime($msg['Msg_CreatedAt'])); ?>
                                            <?php if($msg['SenderID'] == $user_id): ?>
                                                <div class="message-status">
                                                    <i class="fas fa-check"></i> Sent
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Typing Indicator -->
                            <div class="typing-indicator" id="typingIndicator">
                                <i class="fas fa-ellipsis-h"></i> <?php echo htmlspecialchars($other_user_name); ?> is typing...
                            </div>
                            
                            <!-- Chat Input -->
                            <div class="chat-input">
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="conversation_id" value="<?php echo (int)$send_conversation_id; ?>">
                                    <textarea class="form-control" name="message_content" id="messageInput"
                                            rows="2" placeholder="Type your message..." required></textarea>
                                    <button type="submit" name="send_message" class="btn-send">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="chat-area d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No conversation selected</h5>
                                <p class="text-muted">Choose a conversation from the sidebar to start messaging</p>
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

        // Auto-expand textarea
        document.querySelector('textarea[name="message_content"]')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
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
        }, 3000);
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
    
    <!-- Real-time Messaging Script -->
    <script src="../js/realtime-messaging.js"></script>
</body>
</html>