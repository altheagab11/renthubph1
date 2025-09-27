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
    $product_id = $_POST['ProductID'] ?? null;
    $imgPath = $_POST['PI_ImagePath'] ?? null;
    if (!$product_id || !$imgPath) {
        $response['message'] = 'Missing ProductID or PI_ImagePath.';
        echo json_encode($response);
        exit;
    }

    // Delete image record
    $del_stmt = $conn->prepare("DELETE FROM product_images WHERE ProductID = ? AND PI_ImagePath = ?");
    $del_stmt->execute([$product_id, $imgPath]);

    // Delete file from uploads folder
    $file = __DIR__ . '/../' . $imgPath;
    if (file_exists($file)) @unlink($file);

    // If deleted image was main, set another as main
    $mainCheck = $conn->prepare("SELECT COUNT(*) FROM product_images WHERE ProductID = ? AND PI_IsMain = 1");
    $mainCheck->execute([$product_id]);
    if ($mainCheck->fetchColumn() == 0) {
        $setMain = $conn->prepare("UPDATE product_images SET PI_IsMain = 1 WHERE ProductID = ? ORDER BY PI_UploadedAt ASC LIMIT 1");
        $setMain->execute([$product_id]);
    }

    $response['success'] = true;
}
header('Content-Type: application/json');
echo json_encode($response);
