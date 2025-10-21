<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$product_id = (int)$_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // First, get basic product details
    $query = "SELECT * FROM products WHERE ProductID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $product_id);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Try to get owner details
    try {
        $owner_query = "SELECT User_Name as owner_name FROM users WHERE UserID = ?";
        $owner_stmt = $conn->prepare($owner_query);
        $owner_stmt->bindParam(1, $product['OwnerID']);
        $owner_stmt->execute();
        $owner = $owner_stmt->fetch(PDO::FETCH_ASSOC);
        if ($owner) {
            $product['owner_name'] = $owner['owner_name'];
        } else {
            $product['owner_name'] = 'Unknown Owner';
        }
    } catch (PDOException $e) {
        $product['owner_name'] = 'Unknown Owner';
    }
    
    // Try to get owner address (city)
    try {
        $address_query = "SELECT UA_City as owner_city FROM user_addresses WHERE UserID = ? AND UA_IsDefault = 1 LIMIT 1";
        $address_stmt = $conn->prepare($address_query);
        $address_stmt->bindParam(1, $product['OwnerID']);
        $address_stmt->execute();
        $address = $address_stmt->fetch(PDO::FETCH_ASSOC);
        if ($address) {
            $product['owner_city'] = $address['owner_city'];
        } else {
            $product['owner_city'] = 'Not specified';
        }
    } catch (PDOException $e) {
        $product['owner_city'] = 'Not specified';
    }
    
    // Try to get category name
    try {
        $category_query = "SELECT Cat_Name as category_name FROM categories WHERE CategoryID = ?";
        $category_stmt = $conn->prepare($category_query);
        $category_stmt->bindParam(1, $product['CategoryID']);
        $category_stmt->execute();
        $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
        if ($category) {
            $product['category_name'] = $category['category_name'];
        } else {
            $product['category_name'] = 'Uncategorized';
        }
    } catch (PDOException $e) {
        $product['category_name'] = 'Uncategorized';
    }
    
    // Try to get product images
    try {
        $images_query = "SELECT * FROM product_images WHERE ProductID = ? ORDER BY PI_IsMain DESC, PI_CreatedAt ASC";
        $images_stmt = $conn->prepare($images_query);
        $images_stmt->bindParam(1, $product_id);
        $images_stmt->execute();
        $product_images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
        $product['images'] = $product_images;
    } catch (PDOException $e) {
        // If product_images table doesn't exist, set empty array
        $product['images'] = [];
    }
    
    // Try to get product location details
    try {
        $location_query = "SELECT * FROM product_locations WHERE ProductID = ?";
        $location_stmt = $conn->prepare($location_query);
        $location_stmt->bindParam(1, $product_id);
        $location_stmt->execute();
        $product_location = $location_stmt->fetch(PDO::FETCH_ASSOC);
        $product['location'] = $product_location;
    } catch (PDOException $e) {
        // If product_locations table doesn't exist, set default values
        $product['location'] = [
            'PL_PickupAvailable' => 1,
            'PL_DeliveryAvailable' => 0,
            'PL_DeliveryFee' => 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-product-details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in get-product-details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>