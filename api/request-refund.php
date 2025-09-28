<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireRole([2]); // Renter only
$database = new Database();
$conn = $database->getConnection();


$user_id = $_SESSION['user_id'];
$payment_id = $_POST['payment_id'] ?? null;
$product_name = $_POST['product_name'] ?? '';

// Get renter's name
$renter_stmt = $conn->prepare('SELECT User_Name FROM user_accounts WHERE UserID = ?');
$renter_stmt->execute([$user_id]);
$renter = $renter_stmt->fetch(PDO::FETCH_ASSOC);
$renter_name = $renter ? $renter['User_Name'] : 'Unknown Renter';

if (!$payment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing payment ID.']);
    exit;
}

// Get owner ID and product name
$stmt = $conn->prepare('SELECT b.OwnerID, p.Prod_Name FROM payments ph JOIN bookings b ON ph.BookingID = b.BookingID JOIN products p ON b.ProductID = p.ProductID WHERE ph.PaymentID = ?');
$stmt->execute([$payment_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Payment not found.']);
    exit;
}
$owner_id = $row['OwnerID'];
$product_name = $row['Prod_Name'];


// Insert notification for owner, including renter's name
$notif_stmt = $conn->prepare('INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_RelatedID, Not_IsRead) VALUES (?, ?, ?, ?, ?, 0)');
$notif_stmt->execute([
    $owner_id,
    'Refund Request',
    'Refund Requested',
    $renter_name . ' has requested a refund for product: ' . $product_name,
    $payment_id
]);

echo json_encode(['success' => true, 'message' => 'Refund request sent and owner notified.']);
