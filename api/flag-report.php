<?php
header('Content-Type: application/json');
// Suppress error output to avoid breaking JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL); // Still log errors if needed
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both

session_start();
$reporter_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reporter_id) {
    $product_id = !empty($_POST['product_id']) && is_numeric($_POST['product_id']) ? $_POST['product_id'] : null;
    $owner_id = !empty($_POST['owner_id']) && is_numeric($_POST['owner_id']) ? $_POST['owner_id'] : null;
    $flag_type = $_POST['flag_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!in_array($flag_type, ['product', 'owner']) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("INSERT INTO flag_reports (ReporterID, ProductID, OwnerID, FlagType, Reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $reporter_id,
            $product_id,
            $owner_id,
            $flag_type,
            $reason
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Output error directly for debugging
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
}

?>