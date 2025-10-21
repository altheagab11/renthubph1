<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$payment_id = isset($_GET['payment_id']) ? $_GET['payment_id'] : null;

if (!$payment_id) {
    echo "<h3>Invalid receipt request.</h3>";
    exit;
}

// Check if payments table exists
$payments_table_exists = false;
try {
    $query = "SELECT 1 FROM payments LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $payments_table_exists = true;
} catch (PDOException $e) {
    $payments_table_exists = false;
}

$row = null;

if ($payments_table_exists) {
    // Try to get payment data from payments table
    $query = "SELECT p.*, b.BookingID, b.Book_StartDate, b.Book_EndDate, b.Book_TotalAmount,
              prod.Prod_Name, prod.Prod_Description, u.User_Name as Owner_Name
              FROM payments p
              JOIN bookings b ON p.BookingID = b.BookingID
              JOIN products prod ON b.ProductID = prod.ProductID
              JOIN user_accounts u ON prod.OwnerID = u.UserID
              WHERE p.PaymentID = ? AND b.RenterID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$payment_id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    // Fallback: Try to get booking data using payment_id as booking_id
    $query = "SELECT b.*, b.BookingID as PaymentID, b.Book_TotalAmount as Pay_Amount,
              'Completed' as Pay_Status, 'GCash' as Pay_Method, b.Book_CreatedAt as Pay_CreatedAt,
              CONCAT('TXN', LPAD(b.BookingID, 6, '0')) as Pay_TransactionID,
              prod.Prod_Name, prod.Prod_Description, u.User_Name as Owner_Name
              FROM bookings b
              JOIN products prod ON b.ProductID = prod.ProductID
              JOIN user_accounts u ON prod.OwnerID = u.UserID
              WHERE b.BookingID = ? AND b.RenterID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$payment_id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    echo "<h3>Receipt not found or access denied.</h3>";
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
        <h2>🧾 Payment Receipt</h2>
        <div style="text-align: center; margin-bottom: 20px; color: #666;">
            <strong>RentHub PH</strong><br>
            <small>Your Trusted Rental Platform</small>
        </div>
        
        <div class="row"><span class="label">Receipt #:</span> <span class="value"><?php echo $row['PaymentID'] ?? $row['BookingID']; ?></span></div>
        <div class="row"><span class="label">Product:</span> <span class="value"><?php echo htmlspecialchars($row['Prod_Name']); ?></span></div>
        
        <?php if (isset($row['Prod_Description'])): ?>
        <div class="row"><span class="label">Description:</span> <span class="value"><?php echo htmlspecialchars($row['Prod_Description']); ?></span></div>
        <?php endif; ?>
        
        <?php if (isset($row['Owner_Name'])): ?>
        <div class="row"><span class="label">Owner:</span> <span class="value"><?php echo htmlspecialchars($row['Owner_Name']); ?></span></div>
        <?php endif; ?>
        
        <div class="row"><span class="label">Transaction ID:</span> <span class="value"><?php echo $row['Pay_TransactionID'] ?? 'TXN' . str_pad($row['BookingID'], 6, '0', STR_PAD_LEFT); ?></span></div>
        
        <?php if (isset($row['Book_StartDate']) && isset($row['Book_EndDate'])): ?>
        <div class="row"><span class="label">Rental Period:</span> <span class="value"><?php echo date('M j, Y', strtotime($row['Book_StartDate'])) . ' to ' . date('M j, Y', strtotime($row['Book_EndDate'])); ?></span></div>
        <?php endif; ?>
        
        <div class="row"><span class="label">Amount Paid:</span> <span class="value"><strong>₱<?php echo number_format($row['Pay_Amount'], 2); ?></strong></span></div>
        <div class="row"><span class="label">Payment Method:</span> <span class="value"><?php echo htmlspecialchars($row['Pay_Method']); ?></span></div>
        <div class="row"><span class="label">Status:</span> <span class="value"><span style="color: #28a745; font-weight: bold;"><?php echo htmlspecialchars($row['Pay_Status']); ?></span></span></div>
        <div class="row"><span class="label">Date Paid:</span> <span class="value"><?php echo date('M j, Y - g:i A', strtotime($row['Pay_CreatedAt'])); ?></span></div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666;">
            <small>Thank you for using RentHub PH!</small><br>
            <small>For questions or support, please contact our customer service.</small>
        </div>
        
        <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>
</body>
</html>
