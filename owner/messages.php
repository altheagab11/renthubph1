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

// Get unread notifications for the owner
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 10";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get conversations aggregated by user pair (single thread per renter/owner pair)
$conversations = [];
try {
        $search_user = "%$search%";
        $search_prod = "%$search%";

        // Build base pairs (distinct other user for current user)
            $baseQuery = "
                SELECT pairs.other_id AS Other_User_ID,
                             u.User_Name AS Other_User_Name,
                             u.User_Photo AS Other_User_Photo,
                             -- Latest message content across all conversations with this user
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
                             -- Unread across all conversations with this user
                             (
                                 SELECT COUNT(*) FROM messages m4
                                 JOIN conversations c4 ON c4.ConversationID = m4.ConversationID
                                 WHERE ((c4.User1ID = :uid AND c4.User2ID = pairs.other_id) OR (c4.User1ID = pairs.other_id AND c4.User2ID = :uid))
                                     AND m4.SenderID != :uid AND m4.Msg_IsRead = 0
                             ) AS unread_count,
                                             -- Latest product name, prefer latest booking's product if any, fallback to latest conversation product
                                             COALESCE(
                                                 (
                                                     SELECT pB.Prod_Name
                                                     FROM bookings bB
                                                     JOIN products pB ON pB.ProductID = bB.ProductID
                                                     WHERE ((bB.OwnerID = :uid AND bB.RenterID = pairs.other_id) OR (bB.OwnerID = pairs.other_id AND bB.RenterID = :uid))
                                                     ORDER BY bB.Book_CreatedAt DESC
                                                     LIMIT 1
                                                 ),
                                                 (
                                                     SELECT p.Prod_Name
                                                     FROM conversations c5
                                                     LEFT JOIN products p ON c5.ProductID = p.ProductID
                                                     WHERE ((c5.User1ID = :uid AND c5.User2ID = pairs.other_id) OR (c5.User1ID = pairs.other_id AND c5.User2ID = :uid))
                                                     ORDER BY c5.Conv_LastMessageAt DESC
                                                     LIMIT 1
                                                 )
                                             ) AS Prod_Name,
                                 -- Latest conversation id for linking
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
        ";

        $whereClauses = [];
        $params = [':uid' => $user_id];

        if ($search) {
                // Search by user name OR any related product name
                $whereClauses[] = "(u.User_Name LIKE :search_user OR EXISTS (
                        SELECT 1 FROM conversations cs
                        LEFT JOIN products ps ON cs.ProductID = ps.ProductID
                        WHERE ((cs.User1ID = :uid AND cs.User2ID = pairs.other_id) OR (cs.User1ID = pairs.other_id AND cs.User2ID = :uid))
                            AND ps.Prod_Name LIKE :search_prod
                ))";
                $params[':search_user'] = $search_user;
                $params[':search_prod'] = $search_prod;
        }

        if ($status_filter === 'unread') {
                $whereClauses[] = "((
                        SELECT COUNT(*) FROM messages mu
                        JOIN conversations cu ON cu.ConversationID = mu.ConversationID
                        WHERE ((cu.User1ID = :uid AND cu.User2ID = pairs.other_id) OR (cu.User1ID = pairs.other_id AND cu.User2ID = :uid))
                            AND mu.SenderID != :uid AND mu.Msg_IsRead = 0
                ) > 0)";
        }

        $finalQuery = $baseQuery;
        if (!empty($whereClauses)) {
                $finalQuery .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $finalQuery .= " ORDER BY COALESCE(last_message_time, '1970-01-01 00:00:00') DESC";

        $stmt = $conn->prepare($finalQuery);
        $stmt->execute($params);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
        $conversations = [];
}

// Handle user_id parameter - find or create conversation with specific user about specific product
if (isset($_GET['user_id']) && !isset($_GET['conversation'])) {
    // Always unify by user pair; ignore product to keep a single thread
    $target_user_id = $_GET['user_id'];

    // Find the most recent conversation with this user regardless of product
    $existing_conv_query = "SELECT ConversationID FROM conversations 
                           WHERE (User1ID = ? AND User2ID = ?) OR (User1ID = ? AND User2ID = ?)
                           ORDER BY Conv_LastMessageAt DESC LIMIT 1";
    $existing_conv_stmt = $conn->prepare($existing_conv_query);
    $existing_conv_stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    $existing_conversation = $existing_conv_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_conversation) {
        header("Location: messages.php?conversation=" . $existing_conversation['ConversationID']);
        exit;
    } else {
        // Create new conversation without product context
        $create_conv_query = "INSERT INTO conversations (User1ID, User2ID, Conv_CreatedAt, Conv_LastMessageAt) VALUES (?, ?, NOW(), NOW())";
        $create_conv_stmt = $conn->prepare($create_conv_query);
        $create_conv_stmt->execute([$user_id, $target_user_id]);
        $new_conversation_id = $conn->lastInsertId();
        header("Location: messages.php?conversation=" . $new_conversation_id);
        exit;
    }
}

// Get current conversation details if viewing one
$current_conversation = null;
$conversation_messages = [];
$conversation_id = isset($_GET['conversation']) ? $_GET['conversation'] : '';

// Handle direct user_id parameter (from booking messages)
$target_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';
if ($target_user_id && !$conversation_id) {
    // Find latest existing conversation with this user
    $find_conv_query = "SELECT ConversationID FROM conversations 
                        WHERE (User1ID = ? AND User2ID = ?) OR (User1ID = ? AND User2ID = ?)
                        ORDER BY Conv_LastMessageAt DESC LIMIT 1";
    $find_conv_stmt = $conn->prepare($find_conv_query);
    $find_conv_stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    $existing_conv = $find_conv_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_conv) {
        $conversation_id = $existing_conv['ConversationID'];
    } else {
        // Create new conversation if none exists (no product context)
        $create_conv_query = "INSERT INTO conversations (User1ID, User2ID, Conv_CreatedAt, Conv_LastMessageAt) VALUES (?, ?, NOW(), NOW())";
        $create_conv_stmt = $conn->prepare($create_conv_query);
        $create_conv_stmt->execute([$user_id, $target_user_id]);
        $conversation_id = $conn->lastInsertId();
    }
}

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
              CASE 
                WHEN c.User1ID = ? THEN u2.User_Photo 
                ELSE u1.User_Photo 
              END as Other_User_Photo,
              p.Prod_Name
              FROM conversations c
              LEFT JOIN user_accounts u1 ON c.User1ID = u1.UserID
              LEFT JOIN user_accounts u2 ON c.User2ID = u2.UserID
              LEFT JOIN products p ON c.ProductID = p.ProductID
              WHERE c.ConversationID = ? AND (c.User1ID = ? OR c.User2ID = ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->bindParam(2, $user_id);
    $stmt->bindParam(3, $user_id);
    $stmt->bindParam(4, $conversation_id);
    $stmt->bindParam(5, $user_id);
    $stmt->bindParam(6, $user_id);
    $stmt->execute();
    $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_conversation) {
        // Determine other user id for this conversation
        $other_user_id = ($current_conversation['User1ID'] == $user_id) ? $current_conversation['User2ID'] : $current_conversation['User1ID'];

        // Get all messages across all conversations between this user pair
        $query = "SELECT m.*, u.User_Name as Sender_Name
                  FROM messages m
                  JOIN conversations c ON c.ConversationID = m.ConversationID
                  JOIN user_accounts u ON m.SenderID = u.UserID
                  WHERE ((c.User1ID = ? AND c.User2ID = ?) OR (c.User1ID = ? AND c.User2ID = ?))
                  ORDER BY m.Msg_CreatedAt ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
        $conversation_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark messages as read across all conversations with this user pair
        $query = "UPDATE messages m 
                  JOIN conversations c ON c.ConversationID = m.ConversationID
                  SET m.Msg_IsRead = 1
                  WHERE ((c.User1ID = ? AND c.User2ID = ?) OR (c.User1ID = ? AND c.User2ID = ?))
                    AND m.SenderID != ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id, $user_id]);

        // Determine latest conversation id between the pair for sending new messages
        $send_conversation_id = null;
        $latest_stmt = $conn->prepare("SELECT ConversationID FROM conversations 
                                       WHERE ((User1ID = ? AND User2ID = ?) OR (User1ID = ? AND User2ID = ?))
                                       ORDER BY Conv_LastMessageAt DESC LIMIT 1");
        $latest_stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
        $send_conversation_id = ($latest_stmt->fetch(PDO::FETCH_ASSOC)['ConversationID'] ?? $conversation_id);

        // Get the latest booking between the pair for system message rendering
        $latest_booking = null;
        try {
            $book_stmt = $conn->prepare("SELECT b.BookingID, b.ProductID, p.Prod_Name, b.Book_Status,
                                                b.Book_TotalAmount, b.Book_StartDate, b.Book_EndDate, b.Book_PickupType,
                                                b.Book_CreatedAt, b.Book_UpdatedAt
                                         FROM bookings b
                                         JOIN products p ON p.ProductID = b.ProductID
                                         WHERE ((b.OwnerID = ? AND b.RenterID = ?) OR (b.OwnerID = ? AND b.RenterID = ?))
                                         ORDER BY b.Book_UpdatedAt DESC, b.Book_CreatedAt DESC
                                         LIMIT 1");
            $book_stmt->execute([$user_id, $other_user_id, $user_id, $other_user_id]);
            $latest_booking = $book_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            $latest_booking = null;
        }

        // If a latest booking exists, inject a system message representing its status
        if ($latest_booking) {
            $status = $latest_booking['Book_Status'];
            $statusIcon = 'info-circle';
            $statusText = 'Booking update';
            $statusColorClass = 'received'; // neutral gray bubble style
            if (in_array($status, ['Confirmed','Active','In Progress','Completed'])) {
                $statusIcon = 'check-circle';
                $statusText = ($status === 'Confirmed' ? 'Booking accepted' : ($status === 'In Progress' ? 'Rental started' : ($status === 'Completed' ? 'Booking completed' : 'Booking active')));
            } elseif (in_array($status, ['Cancelled','Rejected','Declined'])) {
                $statusIcon = 'times-circle';
                $statusText = ($status === 'Cancelled' ? 'Booking cancelled' : 'Booking rejected');
            }

            // Compose details text
            $details = [];
            if (!empty($latest_booking['Prod_Name'])) $details[] = 'Product: ' . $latest_booking['Prod_Name'];
            if (!empty($latest_booking['Book_TotalAmount'])) $details[] = 'Amount: ₱' . number_format((float)$latest_booking['Book_TotalAmount'], 2);
            if (!empty($latest_booking['Book_StartDate'])) $details[] = 'Start: ' . date('M j, Y g:i A', strtotime($latest_booking['Book_StartDate']));
            if (!empty($latest_booking['Book_EndDate'])) $details[] = 'End: ' . date('M j, Y g:i A', strtotime($latest_booking['Book_EndDate']));
            if (!empty($latest_booking['Book_PickupType'])) $details[] = 'Pickup: ' . $latest_booking['Book_PickupType'];

            $system_text = $statusText . "\n" . implode("\n", $details);

            // Create a synthetic message object using the last update time
            $system_msg_time = $latest_booking['Book_UpdatedAt'] ?? $latest_booking['Book_CreatedAt'];
            $system_message = [
                'MessageID' => 'sys_' . ($latest_booking['BookingID'] ?? '0'),
                'ConversationID' => $conversation_id,
                'SenderID' => 0, // system
                'Msg_Content' => $system_text,
                'Msg_CreatedAt' => $system_msg_time,
                'Msg_IsRead' => 1,
                'Sender_Name' => 'System'
            ];

            // Insert system message into the correct chronological place, but visible distinctly at the end
            $conversation_messages[] = $system_message;
            usort($conversation_messages, function($a, $b) {
                return strtotime($a['Msg_CreatedAt']) <=> strtotime($b['Msg_CreatedAt']);
            });
        }
    }
}

// Get statistics
$stats = [];

// Total conversations (distinct user pairs)
$stats['total_conversations'] = count($conversations);

// Unread messages count (distinct user pairs with any unread) using aggregated list
$stats['unread_conversations'] = 0;
foreach ($conversations as $conv) {
        if (!empty($conv['unread_count'])) {
                $stats['unread_conversations']++;
        }
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
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
        .stat-card.unread { background: var(--primary-gradient); color: white; }
        .stat-card.response { background: var(--primary-gradient); color: white; }
        
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
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .conversation-item:hover {
            background: #e9ecef;
            text-decoration: none;
            color: inherit;
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
        .message-bubble.system {
            margin: 1rem auto;
            background: #f1f3f5;
            color: #495057;
            border-radius: 10px;
            border: 1px dashed #ced4da;
            max-width: 90%;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
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
        
    /* Legacy booking chip removed; booking statuses now appear as system messages in thread */
        .booking-chip {
            background: rgba(17, 153, 142, 0.12);
            border-radius: 14px;
            padding: 0.75rem 1rem;
            margin-top: 0.75rem;
            border-left: 4px solid #11998e;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .booking-chip .status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            color: #0f7a70;
        }
        .booking-chip .product-name { font-weight: 600; }
        
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
                                            <span class="text-muted small"> <?php echo htmlspecialchars($notif['Not_Message']); ?> </span>
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
                        <button type="submit" class="btn w-100" style="background: var(--primary-gradient); color: white; border-radius: 15px;">
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
                                <a href="?conversation=<?php echo $conv['Latest_ConversationID']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?>"
                                    class="conversation-item <?php echo ($conv['Latest_ConversationID'] == $conversation_id) ? 'active' : ''; ?>">
                            
                            <?php if($conv['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if($conv['Other_User_Photo']): ?>
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
                                    <h6 class="mb-1"><?php echo htmlspecialchars($conv['Other_User_Name']); ?></h6>
                                    <p class="text-muted small mb-0" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 50)); ?>
                                        <?php echo strlen($conv['last_message'] ?? '') > 50 ? '...' : ''; ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo $conv['last_message_time'] ? date('M j, g:i A', strtotime($conv['last_message_time'])) : ''; ?>
                                    </small>
                                </div>
                            </div>
                        </a>
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
                                    <?php if($current_conversation['Other_User_Photo']): ?>
                                        <img src="../uploads/users/<?php echo htmlspecialchars($current_conversation['Other_User_Photo']); ?>" 
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
                            
                            <!-- No booking chip here; booking will be shown as system message in the thread below -->
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
                                <?php $bubbleClass = ($msg['SenderID'] == 0) ? 'system' : (($msg['SenderID'] == $user_id) ? 'sent' : 'received'); ?>
                                <div class="message-bubble <?php echo $bubbleClass; ?>" data-msg-id="<?php echo htmlspecialchars($msg['MessageID']); ?>" data-time="<?php echo htmlspecialchars($msg['Msg_CreatedAt']); ?>">
                                    <div class="message-content">
                                        <?php if($msg['SenderID'] == 0): ?>
                                            <i class="fas fa-info-circle me-1"></i>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($msg['Msg_Content'])); ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo date('M j, Y g:i A', strtotime($msg['Msg_CreatedAt'])); ?>
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
                            <i class="fas fa-ellipsis-h"></i> Someone is typing...
                        </div>

                        <!-- Chat Input -->
                        <div class="chat-input">
                            <form method="POST" class="d-flex align-items-end gap-3">
                                <input type="hidden" name="conversation_id" value="<?php echo isset($send_conversation_id) ? $send_conversation_id : $current_conversation['ConversationID']; ?>">
                                
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