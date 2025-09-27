<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireRole([2]); // Renter only
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payment_history.csv"');
header('Pragma: no-cache');
header('Expires: 0');
$query = "SELECT ph.PaymentID, ph.Pay_Amount, ph.Pay_Method, ph.Pay_Status, ph.Pay_CreatedAt, b.BookingID, p.Prod_Name FROM payments ph JOIN bookings b ON ph.BookingID = b.BookingID JOIN products p ON b.ProductID = p.ProductID WHERE b.RenterID = ? ORDER BY ph.Pay_CreatedAt DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Payment ID,Booking ID,Product Name,Amount,Method,Status,Date\n";
foreach ($rows as $row) {
    echo '"' . $row['PaymentID'] . '","' . $row['BookingID'] . '","' . $row['Prod_Name'] . '","' . $row['Pay_Amount'] . '","' . $row['Pay_Method'] . '","' . $row['Pay_Status'] . '","' . $row['Pay_CreatedAt'] . "\n";
}
