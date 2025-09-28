<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireRole([2]); // Renter only
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$payment_id = isset($_GET['payment_id']) ? $_GET['payment_id'] : null;
if (!$payment_id) {
    echo "<h3>Invalid receipt request.</h3>";
    exit;
}
$query = "SELECT ph.*, b.BookingID, b.Book_StartDate, b.Book_EndDate, p.Prod_Name, p.Prod_Brand, p.Prod_Model FROM payments ph JOIN bookings b ON ph.BookingID = b.BookingID JOIN products p ON b.ProductID = p.ProductID WHERE ph.PaymentID = ? AND b.RenterID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$payment_id, $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "<h3>Receipt not found.</h3>";
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .receipt-box { max-width: 500px; margin: auto; border: 1px solid #eee; padding: 30px; border-radius: 12px; background: #f9f9f9; }
        h2 { text-align: center; margin-bottom: 24px; }
        .row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 140px; }
        .value { display: inline-block; }
        .print-btn { display: block; margin: 20px auto 0 auto; padding: 8px 24px; background: #4f6ef7; color: #fff; border: none; border-radius: 20px; font-size: 16px; cursor: pointer; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <div class="receipt-box">
        <h2>Payment Receipt</h2>
        <div class="row"><span class="label">Receipt #:</span> <span class="value"><?php echo $row['PaymentID']; ?></span></div>
        <div class="row"><span class="label">Product:</span> <span class="value"><?php echo htmlspecialchars($row['Prod_Name']); ?></span></div>
        <div class="row"><span class="label">Brand:</span> <span class="value"><?php echo htmlspecialchars($row['Prod_Brand']); ?></span></div>
        <div class="row"><span class="label">Model:</span> <span class="value"><?php echo htmlspecialchars($row['Prod_Model']); ?></span></div>
    <div class="row"><span class="label">Transaction ID:</span> <span class="value"><?php echo $row['Pay_TransactionID']; ?></span></div>
    <div class="row"><span class="label">Booking Period:</span> <span class="value"><?php echo $row['Book_StartDate'] . ' to ' . $row['Book_EndDate']; ?></span></div>
        <div class="row"><span class="label">Amount Paid:</span> <span class="value">₱<?php echo number_format($row['Pay_Amount'],2); ?></span></div>
        <div class="row"><span class="label">Payment Method:</span> <span class="value"><?php echo htmlspecialchars($row['Pay_Method']); ?></span></div>
        <div class="row"><span class="label">Status:</span> <span class="value"><?php echo htmlspecialchars($row['Pay_Status']); ?></span></div>
        <div class="row"><span class="label">Date Paid:</span> <span class="value"><?php echo $row['Pay_CreatedAt']; ?></span></div>
        <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    </div>
</body>
</html>
