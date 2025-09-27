<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireRole([2]); // Renter only
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$query = "SELECT ph.PaymentID, ph.Pay_Amount, ph.Pay_Method, ph.Pay_Status, ph.Pay_CreatedAt, b.BookingID, p.Prod_Name FROM payments ph JOIN bookings b ON ph.BookingID = b.BookingID JOIN products p ON b.ProductID = p.ProductID WHERE b.RenterID = ? ORDER BY ph.Pay_CreatedAt DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History PDF Export</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #f8f8f8; }
        h2 { margin-bottom: 16px; }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <h2>Payment History</h2>
    <button onclick="window.print()">Print or Save as PDF</button>
    <table>
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Booking ID</th>
                <th>Product Name</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo $row['PaymentID']; ?></td>
                <td><?php echo $row['BookingID']; ?></td>
                <td><?php echo htmlspecialchars($row['Prod_Name']); ?></td>
                <td><?php echo $row['Pay_Amount']; ?></td>
                <td><?php echo htmlspecialchars($row['Pay_Method']); ?></td>
                <td><?php echo htmlspecialchars($row['Pay_Status']); ?></td>
                <td><?php echo $row['Pay_CreatedAt']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
