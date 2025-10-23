<?php
require_once '../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
        throw new Exception("Product ID is required");
    }
    
    $product_id = $_GET['product_id'];
    
    // Get all booked dates for this product (excluding cancelled bookings)
    $query = "SELECT Book_StartDate, Book_EndDate, Book_Status 
              FROM bookings 
              WHERE ProductID = ? 
              AND Book_Status NOT IN ('Cancelled', 'Completed')
              ORDER BY Book_StartDate";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$product_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $booked_dates = [];
    
    foreach ($bookings as $booking) {
        $start = new DateTime($booking['Book_StartDate']);
        $end = new DateTime($booking['Book_EndDate']);
        
        // Include all dates from start to end
        $current = clone $start;
        while ($current <= $end) {
            $booked_dates[] = [
                'date' => $current->format('Y-m-d'),
                'status' => $booking['Book_Status']
            ];
            $current->add(new DateInterval('P1D'));
        }
    }
    
    // Remove duplicates and sort
    $unique_dates = array_unique(array_column($booked_dates, 'date'));
    sort($unique_dates);
    
    echo json_encode([
        'success' => true,
        'booked_dates' => $unique_dates,
        'detailed_bookings' => $booked_dates
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>