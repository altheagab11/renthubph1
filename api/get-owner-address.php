<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get owner's default address (IsDefault = 1, or latest if none)
$query = "SELECT * FROM user_addresses WHERE UserID = ? ORDER BY UA_IsDefault DESC, UA_CreatedAt DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$address = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($address);
