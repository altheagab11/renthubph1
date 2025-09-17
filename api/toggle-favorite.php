<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both Renter/Owner

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['product_id'])) {
        throw new Exception('Product ID is required');
    }
    
    $product_id = $input['product_id'];
    
    // Check if product exists
    $product_check = "SELECT ProductID FROM products WHERE ProductID = ?";
    $product_stmt = $conn->prepare($product_check);
    $product_stmt->execute([$product_id]);
    
    if (!$product_stmt->fetch()) {
        throw new Exception('Product not found');
    }
    
    // Check if already favorited
    $check_query = "SELECT FavoriteID FROM favorites WHERE UserID = ? AND ProductID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute([$user_id, $product_id]);
    $existing_favorite = $check_stmt->fetch();
    
    if ($existing_favorite) {
        // Remove from favorites
        $delete_query = "DELETE FROM favorites WHERE UserID = ? AND ProductID = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->execute([$user_id, $product_id]);
        
        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'message' => 'Product removed from favorites'
        ]);
    } else {
        // Add to favorites
        $insert_query = "INSERT INTO favorites (UserID, ProductID, Fav_AddedAt) VALUES (?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->execute([$user_id, $product_id]);
        
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'message' => 'Product added to favorites'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
