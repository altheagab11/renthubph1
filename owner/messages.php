<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Both Renter/Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle message actions
if ($_POST) {
    if (isset($_POST['send_message'])) {
        $conversation_id = $_POST['conversation_id'];
        $message_content = trim($_POST['message_content']);
        
        if (!empty($message_content)) {
            $query = "INSERT INTO messages (ConversationID, SenderID, Msg_Content) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $conversation_id);
            $stmt->bindParam(2, $user_id);
            $stmt->bindParam(3, $message_content);
            
            if ($stmt->execute()) {
                // Update conversation last message
                $query = "UPDATE conversations SET Conv_LastMessageAt = NOW() WHERE ConversationID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $conversation_id);
                $stmt->execute();
                
                $message = "Message sent successfully!";
                $message_type = "success";
            }
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get conversations
$conditions = ["(c.User1ID = ? OR c.User2ID = ?)"];
$params = [$user_id, $user_id];

if ($search) {
    $conditions[] = "(u.User_Name LIKE ? OR p.Prod_Name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter == 'unread') {
    $conditions[] = "EXISTS (SELECT 1 FROM messages m WHERE m.ConversationID = c.ConversationID AND m.SenderID != ? AND m.Msg_IsRead = 0)";
    $params[] = $user_id;
}

$query = "SELECT c.*, 
          CASE 
            WHEN c.User1ID = ? THEN u2.User_Name 
            ELSE u1.User_Name 
          END as Other_User_Name,
          CASE 
            WHEN c.User1ID = ? THEN c.User2ID 
            ELSE c.User1ID 
          END as Other_User_ID,
          p.Prod_Name, pi.PI_ImagePath,
          (SELECT COUNT(*) FROM messages WHERE ConversationID = c.ConversationID AND SenderID != ? AND Msg_IsRead = 0) as unread_count,
          (SELECT Msg_Content FROM messages WHERE ConversationID = c.ConversationID ORDER BY Msg_CreatedAt DESC LIMIT 1) as last_message,
          (SELECT Msg_CreatedAt FROM messages WHERE ConversationID = c.ConversationID ORDER BY Msg_CreatedAt DESC LIMIT 1) as last_message_time
          FROM conversations c
          LEFT JOIN user_accounts u1 ON c.User1ID = u1.UserID
          LEFT JOIN user_accounts u2 ON c.User2ID = u2.UserID
          LEFT JOIN products p ON c.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY c.Conv_LastMessageAt DESC";

array_unshift($params, $user_id, $user_id, $user_id);
$stmt = $conn->prepare($query);
$stmt->execute($params);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current conversation details if viewing one
$current_conversation = null;
$conversation_messages = [];
$conversation_id = isset($_GET['conversation']) ? $_GET['conversation'] : '';

if ($conversation_id) {
    // Get conversation details
    $query = "SELECT c.*, 
              CASE 
                WHEN c.User1ID = ? THEN u2.User_Name 
                ELSE u1.User_Name 
              END as Other_User_Name,
              CASE 
                WHEN c.User1ID = ? THEN u2.User_Email 
                ELSE u1.User_Email 
              END as Other_User_Email,
              p.Prod_Name, pi.PI_ImagePath
              FROM conversations c
              LEFT JOIN user_accounts u1 ON c.User1ID = u1.UserID
              LEFT JOIN user_accounts u2 ON c.User2ID = u2.UserID
              LEFT JOIN products p ON c.ProductID = p.ProductID
              LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
              WHERE c.ConversationID = ? AND (c.User1ID = ? OR c.User2ID = ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->bindParam(2, $user_id);
    $stmt->bindParam(3, $conversation_id);
    $stmt->bindParam(4, $user_id);
    $stmt->bindParam(5, $user_id);
    $stmt->execute();
    $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_conversation) {
        // Get messages
        $query = "SELECT m.*, u.User_Name as Sender_Name
                  FROM messages m
                  JOIN user_accounts u ON m.SenderID = u.UserID
                  WHERE m.ConversationID = ?
                  ORDER BY m.Msg_CreatedAt ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $conversation_id);
        $stmt->execute();
        $conversation_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $query = "UPDATE messages SET Msg_IsRead = 1 WHERE ConversationID = ? AND SenderID != ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $conversation_id);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();
    }
}

// Get statistics
$stats = [];

// Total conversations
$stats['total_conversations'] = count($conversations);

// Unread messages count
$query = "SELECT COUNT(DISTINCT c.ConversationID) as total 
          FROM conversations c 
          WHERE (c.User1ID = ? OR c.User2ID = ?) 
          AND EXISTS (SELECT 1 FROM messages m WHERE m.ConversationID = c.ConversationID AND m.SenderID != ? AND m.Msg_IsRead = 0)";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $user_id);
$stmt->bindParam(3, $user_id);
$stmt->execute();
$stats['unread_conversations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Response rate (messages sent vs received)
$query = "SELECT 
          (SELECT COUNT(*) FROM messages m JOIN conversations c ON m.ConversationID = c.ConversationID WHERE m.SenderID = ? AND (c.User1ID = ? OR c.User2ID = ?)) as sent,
          (SELECT COUNT(*) FROM messages m JOIN conversations c ON m.ConversationID = c.ConversationID WHERE m.SenderID != ? AND (c.User1ID = ? OR c.User2ID = ?)) as received";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $user_id);
$stmt->bindParam(3, $user_id);
$stmt->bindParam(4, $user_id);
$stmt->bindParam(5, $user_id);
$stmt->bindParam(6, $user_id);
$stmt->execute();
$response_data = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['response_rate'] = $response_data['received'] > 0 ? round(($response_data['sent'] / $response_data['received']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - RentHub PH</title>
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
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.conversations { background: var(--primary-gradient); color: white; }
        .stat-card.unread { background: var(--accent-gradient); color: white; }
        .stat-card.response { background: var(--info-gradient); color: white; }
        
        .messages-container {
            display: flex;
            height: calc(100vh - 200px);
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
        }
        
        .conversations-list {
            width: 400px;
            border-right: 1px solid #e9ecef;
            background: #f8f9fa;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #e9ecef;
        }
        
        .conversation-item.active {
            background: var(--primary-gradient);
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
        
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .message-bubble {
            max-width: 70%;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 20px;
            position: relative;
        }
        
        .message-bubble.sent {
            margin-left: auto;
            background: var(--primary-gradient);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message-bubble.received {
            margin-right: auto;
            background: #e9ecef;
            color: #333;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        
        .chat-input {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        .chat-input .form-control {
            border-radius: 25px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            resize: none;
        }
        
        .chat-input .form-control:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
        }
        
        .send-btn {
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
        
        .send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            display: inline-block;
            margin-left: 0.5rem;
        }
        
        .product-info {
            background: rgba(17, 153, 142, 0.1);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #11998e;
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
            
            .messages-container {
                flex-direction: column;
                height: auto;
            }
            
            .conversations-list {
                width: 100%;
                max-height: 300px;
            }
            
            .search-filters {
                padding: 1rem;
            }
        }
        
        /* Custom scrollbar */
        .conversations-list::-webkit-scrollbar,
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .conversations-list::-webkit-scrollbar-track,
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .conversations-list::-webkit-scrollbar-thumb,
        .chat-messages::-webkit-scrollbar-thumb {
            background: #11998e;
            border-radius: 3px;
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
                    <a class="nav-link" href="add-product.php">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Booking Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="earnings.php">
                        <i class="fas fa-chart-line me-2"></i> Earnings
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
                    <a class="nav-link" href="subscription.php">
                        <i class="fas fa-crown me-2"></i> Subscription
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
                        <i class="fas fa-comments text-info me-2"></i>Messages
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $stats['unread_conversations']; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar-check text-success me-2"></i>New booking request</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-message text-primary me-2"></i>New message received</a></li>
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
                <div class="col-xl-4 col-md-6 mb-4">
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

                <div class="col-xl-4 col-md-6 mb-4">
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

                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card response">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Response Rate
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['response_rate']; ?>%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-reply fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search Conversations
                        </label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by user name or product...">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Filter
                        </label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Messages</option>
                            <option value="unread" <?php echo $status_filter == 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                    
                    <?php if($conversation_id): ?>
                    <input type="hidden" name="conversation" value="<?php echo $conversation_id; ?>">
                    <?php endif; ?>
                </form>
            </div>

            <!-- Messages Interface -->
            <div class="messages-container">
                <!-- Conversations List -->
                <div class="conversations-list">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0">
                            <i class="fas fa-inbox me-2"></i>Conversations
                            <?php if($stats['unread_conversations'] > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $stats['unread_conversations']; ?></span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    
                    <?php if(empty($conversations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h6 class="text-muted">No conversations yet</h6>
                            <p class="text-muted small">Messages from renters will appear here</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($conversations as $conv): ?>
                        <div class="conversation-item <?php echo $conv['ConversationID'] == $conversation_id ? 'active' : ''; ?>" 
                             onclick="selectConversation(<?php echo $conv['ConversationID']; ?>)">
                            
                            <?php if($conv['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if($conv['PI_ImagePath']): ?>
                                        <img src="<?php echo htmlspecialchars($conv['PI_ImagePath']); ?>" 
                                             class="rounded" style="width: 50px; height: 50px; object-fit: cover;" 
                                             alt="Product">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-white" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($conv['Other_User_Name']); ?></h6>
                                    <p class="text-muted small mb-1"><?php echo htmlspecialchars($conv['Prod_Name']); ?></p>
                                    <p class="text-muted small mb-0" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 50)); ?>
                                        <?php echo strlen($conv['last_message'] ?? '') > 50 ? '...' : ''; ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo $conv['last_message_time'] ? date('M j, g:i A', strtotime($conv['last_message_time'])) : ''; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if($current_conversation): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if($current_conversation['PI_ImagePath']): ?>
                                        <img src="<?php echo htmlspecialchars($current_conversation['PI_ImagePath']); ?>" 
                                             class="rounded" style="width: 60px; height: 60px; object-fit: cover;" 
                                             alt="Product">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-white" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($current_conversation['Other_User_Name']); ?>
                                        <span class="online-indicator"></span>
                                    </h5>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($current_conversation['Other_User_Email']); ?></p>
                                </div>
                                
                                <div class="ms-auto">
                                    <button class="btn btn-outline-primary btn-sm me-2">
                                        <i class="fas fa-phone"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if($current_conversation['Prod_Name']): ?>
                            <div class="product-info mt-3">
                                <h6 class="mb-1">
                                    <i class="fas fa-box me-2"></i>About: <?php echo htmlspecialchars($current_conversation['Prod_Name']); ?>
                                </h6>
                                <p class="text-muted small mb-0">This conversation is about your rental product</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Chat Messages -->
                        <div class="chat-messages" id="chatMessages">
                            <?php if(empty($conversation_messages)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <h6 class="text-muted">Start a conversation</h6>
                                    <p class="text-muted small">Send a message to begin chatting</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($conversation_messages as $msg): ?>
                                <div class="message-bubble <?php echo $msg['SenderID'] == $user_id ? 'sent' : 'received'; ?>">
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($msg['Msg_Content'])); ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo date('M j, Y g:i A', strtotime($msg['Msg_CreatedAt'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Chat Input -->
                        <div class="chat-input">
                            <form method="POST" class="d-flex align-items-end gap-3">
                                <input type="hidden" name="conversation_id" value="<?php echo $current_conversation['ConversationID']; ?>">
                                
                                <div class="flex-grow-1">
                                    <textarea class="form-control" name="message_content" rows="2" placeholder="Type your message..." required></textarea>
                                </div>
                                
                                <button type="submit" name="send_message" class="btn send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <h4 class="text-muted">Select a conversation</h4>
                                <p class="text-muted">Choose a conversation from the left to start messaging</p>
                            </div>
                        </div>
                    <?php endif; ?>
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

        // Select conversation
        function selectConversation(conversationId) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('conversation', conversationId);
            
            // Preserve other filters
            const search = urlParams.get('search');
            const status = urlParams.get('status');
            
            let newUrl = 'messages.php?conversation=' + conversationId;
            if (search) newUrl += '&search=' + encodeURIComponent(search);
            if (status) newUrl += '&status=' + encodeURIComponent(status);
            
            window.location.href = newUrl;
        }

        // Auto-scroll to bottom of chat messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Auto-expand textarea
        document.querySelector('textarea[name="message_content"]')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Send message on Enter (but not Shift+Enter)
        document.querySelector('textarea[name="message_content"]')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
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
        }, 3000);

        // Refresh page every 30 seconds to check for new messages
        <?php if($conversation_id): ?>
        setInterval(() => {
            // Only refresh if user hasn't typed anything recently
            const textarea = document.querySelector('textarea[name="message_content"]');
            if (!textarea || textarea.value.trim() === '') {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>