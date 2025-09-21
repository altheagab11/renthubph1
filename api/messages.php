<?php
// api/messages.php - API endpoint for real-time message updates
// Suppress error display to prevent HTML output in JSON responses
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Buffer output to allow headers after potential early output
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Handle different API actions
$action = $_GET['action'] ?? '';
$conversation_id = $_GET['conversation_id'] ?? '';

switch ($action) {
    case 'check_new_messages':
        checkNewMessages($conn, $user_id, $conversation_id);
        break;
    
    case 'get_messages':
        getMessages($conn, $conversation_id, $user_id);
        break;
    
    case 'send_message':
        sendMessage($conn, $user_id);
        break;
    
    case 'mark_read':
        markMessagesRead($conn, $conversation_id, $user_id);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

// Flush buffer at end
ob_end_flush();

function checkNewMessages($conn, $user_id, $conversation_id) {
    $last_check = $_GET['last_check'] ?? '1970-01-01 00:00:00';
    
    // Check for new messages in current conversation
    $new_messages = [];
    if ($conversation_id) {
        $stmt = $conn->prepare("
            SELECT m.MessageID AS Msg_ID, m.ConversationID, m.SenderID, 
                   m.Msg_Content, m.Msg_CreatedAt, m.Msg_IsRead,
                   u.User_Name as Sender_Name
            FROM messages m
            JOIN user_accounts u ON m.SenderID = u.UserID
            WHERE m.ConversationID = ? AND m.Msg_CreatedAt > ?
            ORDER BY m.Msg_CreatedAt ASC
        ");
        $stmt->execute([$conversation_id, $last_check]);
        $new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark new messages as read if they're not from the current user
        if (!empty($new_messages)) {
            $stmt = $conn->prepare("
                UPDATE messages 
                SET Msg_IsRead = 1 
                WHERE ConversationID = ? AND SenderID != ? AND Msg_CreatedAt > ?
            ");
            $stmt->execute([$conversation_id, $user_id, $last_check]);
        }
    }
    
    // Get unread count for all conversations
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT c.ConversationID) as total 
        FROM conversations c 
        WHERE (c.User1ID = ? OR c.User2ID = ?) 
        AND EXISTS (
            SELECT 1 FROM messages m 
            WHERE m.ConversationID = c.ConversationID 
            AND m.SenderID != ? 
            AND m.Msg_IsRead = 0
        )
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'new_messages' => $new_messages,
        'unread_count' => $unread_count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getMessages($conn, $conversation_id, $user_id) {
    if (!$conversation_id) {
        echo json_encode(['error' => 'No conversation ID provided']);
        return;
    }
    
    // Verify user has access to this conversation
    $stmt = $conn->prepare("
        SELECT 1 FROM conversations 
        WHERE ConversationID = ? AND (User1ID = ? OR User2ID = ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT m.MessageID AS Msg_ID, m.ConversationID, m.SenderID, 
               m.Msg_Content, m.Msg_CreatedAt, m.Msg_IsRead,
               u.User_Name as Sender_Name
        FROM messages m
        JOIN user_accounts u ON m.SenderID = u.UserID
        WHERE m.ConversationID = ?
        ORDER BY m.Msg_CreatedAt ASC
    ");
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['messages' => $messages]);
}

function sendMessage($conn, $user_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = $input['conversation_id'] ?? '';
    $message_content = trim($input['message_content'] ?? '');
    
    if (!$conversation_id || !$message_content) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // Verify user has access to this conversation
    $stmt = $conn->prepare("
        SELECT 1 FROM conversations 
        WHERE ConversationID = ? AND (User1ID = ? OR User2ID = ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (ConversationID, SenderID, Msg_Content, Msg_CreatedAt) 
        VALUES (?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$conversation_id, $user_id, $message_content])) {
        // Get the newly inserted message ID
        $msg_id = $conn->lastInsertId();
        
        // Fetch the full new message (including sender_name)
        $fetch_stmt = $conn->prepare("
            SELECT m.MessageID AS Msg_ID, m.ConversationID, m.SenderID, 
                   m.Msg_Content, m.Msg_CreatedAt, m.Msg_IsRead,
                   u.User_Name as Sender_Name
            FROM messages m
            JOIN user_accounts u ON m.SenderID = u.UserID
            WHERE m.MessageID = ?
        ");
        $fetch_stmt->execute([$msg_id]);
        $new_message = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update conversation last message time
        $conn->prepare("
            UPDATE conversations 
            SET Conv_LastMessageAt = NOW() 
            WHERE ConversationID = ?
        ")->execute([$conversation_id]);
        
        echo json_encode(['success' => true, 'new_message' => $new_message]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send message']);
    }
}

function markMessagesRead($conn, $conversation_id, $user_id) {
    if (!$conversation_id) {
        echo json_encode(['error' => 'No conversation ID provided']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE messages 
        SET Msg_IsRead = 1 
        WHERE ConversationID = ? AND SenderID != ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
    
    echo json_encode(['success' => true]);
}