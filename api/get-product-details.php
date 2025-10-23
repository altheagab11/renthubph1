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
    
    // Start with basic product query and build up
    $query = "SELECT p.*,
                     COALESCE(c.Cat_Name, 'Uncategorized') as Cat_Name,
                     COALESCE(u.User_Name, 'Unknown Owner') as Owner_Name
              FROM products p
              LEFT JOIN categories c ON p.CategoryID = c.CategoryID
              LEFT JOIN user_accounts u ON p.OwnerID = u.UserID
              WHERE p.ProductID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$product_id]);
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Try to get product images
    $images = [];
    try {
        $images_query = "SELECT * FROM product_images WHERE ProductID = ? ORDER BY PI_IsMain DESC, PI_CreatedAt ASC";
        $images_stmt = $conn->prepare($images_query);
        $images_stmt->execute([$product_id]);
        $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist, continue with empty array
        $images = [];
    }
    
    // Try to get location data
    $location = null;
    try {
        $location_query = "SELECT pl.*, ua.UA_Street, ua.UA_Barangay, ua.UA_City, ua.UA_Province, ua.UA_ZipCode
                          FROM product_locations pl
                          LEFT JOIN user_addresses ua ON pl.AddressID = ua.AddressID
                          WHERE pl.ProductID = ?";
        $location_stmt = $conn->prepare($location_query);
        $location_stmt->execute([$product_id]);
        $location = $location_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tables might not exist, set default values
        $location = [
            'PL_PickupAvailable' => 1,
            'PL_DeliveryAvailable' => 0,
            'PL_DeliveryFee' => 0,
            'PL_DeliveryRadius' => 0
        ];
    }
    
    // Try to get availability data
    $availability = null;
    try {
        $availability_query = "SELECT pa.*,
                                     CASE
                                         WHEN pa.PA_IsAvailable = 1 AND CURDATE() BETWEEN pa.PA_DateFrom AND pa.PA_DateTo THEN 'Available'
                                         WHEN pa.PA_IsAvailable = 0 AND CURDATE() BETWEEN pa.PA_DateFrom AND pa.PA_DateTo THEN 'Unavailable'
                                         WHEN pa.PA_DateTo < CURDATE() THEN 'Expired'
                                         WHEN pa.PA_DateFrom > CURDATE() THEN 'Scheduled'
                                         ELSE 'No Schedule'
                                     END as AvailabilityStatus
                              FROM product_availability pa
                              WHERE pa.ProductID = ?
                              ORDER BY pa.PA_CreatedAt DESC
                              LIMIT 1";
        $availability_stmt = $conn->prepare($availability_query);
        $availability_stmt->execute([$product_id]);
        $availability = $availability_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist
        $availability = [
            'AvailabilityStatus' => 'Available',
            'PA_Reason' => null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product,
        'images' => $images,
        'location' => $location,
        'availability' => $availability
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-product-details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in get-product-details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>