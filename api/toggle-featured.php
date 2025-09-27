<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $action = $_POST['action'] ?? null;
    if (!$product_id || !$action) {
        $response['message'] = 'Missing product_id or action.';
        echo json_encode($response);
        exit;
    }
    if ($action === 'feature') {
        $featured_until = date('Y-m-d H:i:s', strtotime('+30 days'));
        $sql = "UPDATE products SET Prod_IsFeatured = 1, Prod_FeaturedUntil = ? WHERE ProductID = ? AND OwnerID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$featured_until, $product_id, $user_id]);
        $response['success'] = true;
    } elseif ($action === 'unfeature') {
        $sql = "UPDATE products SET Prod_IsFeatured = 0, Prod_FeaturedUntil = NULL WHERE ProductID = ? AND OwnerID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$product_id, $user_id]);
        $response['success'] = true;
    }
}
header('Content-Type: application/json');
echo json_encode($response);
