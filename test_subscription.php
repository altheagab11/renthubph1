<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Test user ID (you can change this to test different users)
$test_user_id = 1; // Change this to test different users

echo "<h2>Subscription Check Test</h2>";
echo "<p>Testing user ID: $test_user_id</p>";

// Check subscription status
$sub_check_query = "SELECT us.Sub_Status, us.Sub_EndDate, sp.Plan_Name, us.UserID
                    FROM user_subscriptions us 
                    LEFT JOIN subscription_plans sp ON us.PlanID = sp.PlanID 
                    WHERE us.UserID = ? AND us.Sub_Status = 'Active' AND us.Sub_EndDate >= NOW()";
$sub_check_stmt = $conn->prepare($sub_check_query);
$sub_check_stmt->execute([$test_user_id]);
$active_subscription = $sub_check_stmt->fetch(PDO::FETCH_ASSOC);

if ($active_subscription) {
    echo "<div style='color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ User has ACTIVE subscription!</strong><br>";
    echo "Plan: " . htmlspecialchars($active_subscription['Plan_Name']) . "<br>";
    echo "Status: " . htmlspecialchars($active_subscription['Sub_Status']) . "<br>";
    echo "End Date: " . htmlspecialchars($active_subscription['Sub_EndDate']) . "<br>";
    echo "</div>";
    echo "<p><strong>Result:</strong> User CAN accept bookings</p>";
} else {
    echo "<div style='color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ User has NO active subscription!</strong>";
    echo "</div>";
    echo "<p><strong>Result:</strong> User CANNOT accept bookings</p>";
}

// Show all subscriptions for this user
echo "<h3>All subscriptions for user $test_user_id:</h3>";
$all_subs_query = "SELECT us.*, sp.Plan_Name 
                   FROM user_subscriptions us 
                   LEFT JOIN subscription_plans sp ON us.PlanID = sp.PlanID 
                   WHERE us.UserID = ? 
                   ORDER BY us.Sub_CreatedAt DESC";
$all_subs_stmt = $conn->prepare($all_subs_query);
$all_subs_stmt->execute([$test_user_id]);
$all_subscriptions = $all_subs_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($all_subscriptions) {
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr><th>Plan Name</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Created At</th></tr>";
    foreach ($all_subscriptions as $sub) {
        $row_color = ($sub['Sub_Status'] === 'Active' && $sub['Sub_EndDate'] >= date('Y-m-d H:i:s')) ? '#d4edda' : '#f8d7da';
        echo "<tr style='background-color: $row_color;'>";
        echo "<td>" . htmlspecialchars($sub['Plan_Name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($sub['Sub_Status']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['Sub_StartDate']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['Sub_EndDate']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['Sub_CreatedAt']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No subscriptions found for this user.</p>";
}

// Test with other user IDs
echo "<h3>Quick test for other users:</h3>";
for ($i = 1; $i <= 5; $i++) {
    $check_stmt = $conn->prepare($sub_check_query);
    $check_stmt->execute([$i]);
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $status = $result ? "✅ Active" : "❌ No Active Sub";
    echo "<p>User $i: $status</p>";
}
?>