<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

$product_id = intval($_GET['product_id']);

try {
    // Get flag reports for this product
    $query = "SELECT fr.*, 
              ua_reporter.User_Name as Reporter_Name
              FROM flag_reports fr 
              LEFT JOIN user_accounts ua_reporter ON fr.ReporterID = ua_reporter.UserID
              WHERE fr.ProductID = ? AND fr.FlagType = 'product'
              ORDER BY fr.FlagID DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$product_id]);
    $flags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up the data for JSON response
    foreach ($flags as &$flag) {
        // Ensure all text fields are properly escaped for HTML
        $flag['Reason'] = htmlspecialchars($flag['Reason']);
        $flag['Description'] = htmlspecialchars($flag['Description'] ?? '');
        $flag['Reporter_Name'] = htmlspecialchars($flag['Reporter_Name'] ?? '');
    }
    
    echo json_encode([
        'success' => true,
        'flags' => $flags,
        'count' => count($flags)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>