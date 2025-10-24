<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Debug output
error_reporting(E_ALL);
ini_set('display_errors', 1);

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo '<div class="alert alert-danger">Invalid user ID provided</div>';
    exit;
}

$user_id = intval($_GET['user_id']);

// Debug: Output user ID being requested
echo "<!-- Debug: Requesting user ID: $user_id -->";

try {
    // Get user details
    $query = "SELECT ua.*, 
              (SELECT COUNT(*) FROM products WHERE OwnerID = ua.UserID) as total_products,
              (SELECT COUNT(*) FROM bookings WHERE RenterID = ua.UserID) as total_bookings_as_renter,
              (SELECT COUNT(*) FROM bookings WHERE OwnerID = ua.UserID) as total_bookings_as_owner,
              (SELECT COUNT(*) FROM flag_reports WHERE (OwnerID = ua.UserID OR RenterID = ua.UserID)) as total_flags,
              (SELECT SUM(Pay_Amount) FROM payments p 
               JOIN bookings b ON p.BookingID = b.BookingID 
               WHERE b.OwnerID = ua.UserID AND p.Pay_Status = 'Completed') as total_earnings,
              (SELECT SUM(Pay_Amount) FROM payments p 
               JOIN bookings b ON p.BookingID = b.BookingID 
               WHERE b.RenterID = ua.UserID AND p.Pay_Status = 'Completed') as total_spent
              FROM user_accounts ua 
              WHERE ua.UserID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo '<div class="alert alert-danger">User not found</div>';
        exit;
    }
    
    // Get recent bookings
    $recent_bookings_query = "SELECT b.*, p.Prod_Name, pay.Pay_Amount, pay.Pay_Status 
                              FROM bookings b 
                              LEFT JOIN products p ON b.ProductID = p.ProductID 
                              LEFT JOIN payments pay ON b.BookingID = pay.BookingID
                              WHERE b.RenterID = ? OR b.OwnerID = ?
                              ORDER BY b.Book_CreatedAt DESC 
                              LIMIT 5";
    $stmt = $conn->prepare($recent_bookings_query);
    $stmt->execute([$user_id, $user_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent flag reports
    $flag_reports_query = "SELECT fr.*, ua_reporter.User_Name as Reporter_Name 
                           FROM flag_reports fr 
                           LEFT JOIN user_accounts ua_reporter ON fr.ReporterID = ua_reporter.UserID
                           WHERE fr.OwnerID = ? OR fr.RenterID = ?
                           ORDER BY fr.FlagDate DESC 
                           LIMIT 3";
    $stmt = $conn->prepare($flag_reports_query);
    $stmt->execute([$user_id, $user_id]);
    $flag_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    function getRoleName($role_id) {
        switch($role_id) {
            case 1: return 'Admin';
            case 2: return 'Renter';
            case 3: return 'Owner';
            default: return 'Unknown';
        }
    }
    
    function formatCurrency($amount) {
        return $amount ? '₱' . number_format($amount, 2) : '₱0.00';
    }
    
    ?>
    
    <div class="container-fluid">
        <!-- User Basic Info -->
        <div class="row mb-4">
            <div class="col-md-4 text-center">
                <div class="user-avatar-large mx-auto mb-3" style="width: 80px; height: 80px; background: #007bff; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.5rem;">
                    <?php echo strtoupper(substr($user['User_Name'], 0, 2)); ?>
                </div>
                <h5 class="mb-1"><?php echo htmlspecialchars($user['User_Name']); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user['User_Email']); ?></p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-<?php 
                        echo $user['User_Status'] == 'Active' ? 'success' : 
                            ($user['User_Status'] == 'Suspended' ? 'danger' : 'secondary'); 
                    ?>">
                        <?php echo $user['User_Status']; ?>
                    </span>
                    <span class="badge bg-primary"><?php echo getRoleName($user['User_Role']); ?></span>
                    <?php if($user['User_IsVerified']): ?>
                        <span class="badge bg-success">Verified</span>
                    <?php else: ?>
                        <span class="badge bg-warning">Unverified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Personal Information</strong>
                        <ul class="list-unstyled mt-2">
                            <li><i class="fas fa-phone text-muted me-2"></i><strong>Phone:</strong> <?php echo $user['User_Phone'] ?: 'Not provided'; ?></li>
                            <li><i class="fas fa-venus-mars text-muted me-2"></i><strong>Gender:</strong> <?php echo $user['User_Gender'] ?: 'Not specified'; ?></li>
                            <li><i class="fas fa-birthday-cake text-muted me-2"></i><strong>Birth Date:</strong> <?php echo $user['User_Birthdate'] ? date('M d, Y', strtotime($user['User_Birthdate'])) : 'Not provided'; ?></li>
                            <li><i class="fas fa-map-marker-alt text-muted me-2"></i><strong>Address:</strong> <?php echo $user['User_Address'] ?: 'Not provided'; ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <strong>Account Information</strong>
                        <ul class="list-unstyled mt-2">
                            <li><i class="fas fa-calendar text-muted me-2"></i><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($user['User_CreatedAt'])); ?></li>
                            <li><i class="fas fa-clock text-muted me-2"></i><strong>Last Updated:</strong> <?php echo $user['User_UpdatedAt'] ? date('M d, Y', strtotime($user['User_UpdatedAt'])) : 'Never'; ?></li>
                            <li><i class="fas fa-key text-muted me-2"></i><strong>Password:</strong> Encrypted</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title"><?php echo $user['total_products'] ?: 0; ?></h5>
                                <p class="card-text">Products Listed</p>
                            </div>
                            <i class="fas fa-box fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title"><?php echo ($user['total_bookings_as_renter'] ?: 0) + ($user['total_bookings_as_owner'] ?: 0); ?></h5>
                                <p class="card-text">Total Bookings</p>
                            </div>
                            <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title"><?php echo formatCurrency($user['total_earnings']); ?></h5>
                                <p class="card-text">Total Earnings</p>
                            </div>
                            <i class="fas fa-money-bill fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-<?php echo ($user['total_flags'] ?: 0) > 0 ? 'danger' : 'secondary'; ?> text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title"><?php echo $user['total_flags'] ?: 0; ?></h5>
                                <p class="card-text">Flag Reports</p>
                            </div>
                            <i class="fas fa-flag fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Bookings</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_bookings)): ?>
                            <p class="text-muted text-center py-3">No recent bookings found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_bookings as $booking): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($booking['Prod_Name'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $booking['RenterID'] == $user_id ? 'info' : 'warning'; ?>">
                                                        <?php echo $booking['RenterID'] == $user_id ? 'Renter' : 'Owner'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $booking['Book_Status'] == 'Completed' ? 'success' : 
                                                            ($booking['Book_Status'] == 'Cancelled' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $booking['Book_Status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($booking['Pay_Amount']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($booking['Book_CreatedAt'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Flag Reports</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($flag_reports)): ?>
                            <p class="text-muted text-center py-3">No flag reports found.</p>
                        <?php else: ?>
                            <?php foreach($flag_reports as $flag): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="small">
                                        <strong>Type:</strong> <?php echo ucfirst($flag['FlagType']); ?><br>
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($flag['FlagReason']); ?><br>
                                        <strong>Reported by:</strong> <?php echo htmlspecialchars($flag['Reporter_Name'] ?: 'Unknown'); ?><br>
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($flag['FlagDate'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading user details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>