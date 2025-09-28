<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireRole([2,3]); // Renter or Owner
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE notifications SET Not_IsRead = 1 WHERE UserID = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['success' => false]);
