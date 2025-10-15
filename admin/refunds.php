<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle refund actions
if ($_POST) {
    if (isset($_POST['process_refund'])) {
        $refund_id = $_POST['refund_id'];
        $refund_method = $_POST['refund_method'];
        $transaction_id = $_POST['transaction_id'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        try {
            $conn->beginTransaction();
            
            // Update refund status
            $query = "UPDATE refunds SET 
                     Refund_Status = 'Completed', 
                     Refund_Method = ?, 
                     Refund_TransactionID = ?, 
                     Refund_ProcessedBy = ?, 
                     Refund_ProcessedAt = NOW(),
                     Refund_Notes = ?
                     WHERE RefundID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$refund_method, $transaction_id, $_SESSION['user_id'], $notes, $refund_id]);
            
            // Get refund details for notification
            $refund_query = "SELECT r.*, b.RenterID, b.Book_TotalAmount, p.Prod_Name 
                           FROM refunds r 
                           JOIN bookings b ON r.BookingID = b.BookingID 
                           JOIN products p ON b.ProductID = p.ProductID 
                           WHERE r.RefundID = ?";
            $refund_stmt = $conn->prepare($refund_query);
            $refund_stmt->execute([$refund_id]);
            $refund_info = $refund_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification for renter
            $notification_msg = "Your refund of ₱" . number_format($refund_info['Refund_Amount'], 2) . " for booking '{$refund_info['Prod_Name']}' has been processed via {$refund_method}.";
            if ($transaction_id) {
                $notification_msg .= " Transaction ID: {$transaction_id}";
            }
            
            $notif_query = "INSERT INTO notifications (UserID, Not_Title, Not_Message, Not_Type, Not_CreatedAt) 
                          VALUES (?, ?, ?, 'refund', NOW())";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_stmt->execute([
                $refund_info['RenterID'], 
                'Refund Processed',
                $notification_msg
            ]);
            
            $conn->commit();
            $message = "Refund processed successfully!";
            $message_type = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to process refund: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['reject_refund'])) {
        $refund_id = $_POST['refund_id'];
        $rejection_reason = $_POST['rejection_reason'];
        
        try {
            $query = "UPDATE refunds SET 
                     Refund_Status = 'Rejected', 
                     Refund_ProcessedBy = ?, 
                     Refund_ProcessedAt = NOW(),
                     Refund_Notes = ?
                     WHERE RefundID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $rejection_reason, $refund_id]);
            
            $message = "Refund rejected.";
            $message_type = "warning";
            
        } catch (Exception $e) {
            $message = "Failed to reject refund: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["1=1"];
$params = [];

if ($status_filter && $status_filter != 'all') {
    $conditions[] = "r.Refund_Status = ?";
    $params[] = ucfirst($status_filter);
}

// Sort options
$sort_options = [
    'newest' => 'r.Refund_CreatedAt DESC',
    'oldest' => 'r.Refund_CreatedAt ASC',
    'amount_high' => 'r.Refund_Amount DESC',
    'amount_low' => 'r.Refund_Amount ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'r.Refund_CreatedAt DESC';

// Get refunds
$query = "SELECT r.*, b.BookingID, b.RenterID, b.Book_TotalAmount, b.Book_StartDate, b.Book_EndDate,
          p.Prod_Name, renter.User_Name as Renter_Name, renter.User_Email as Renter_Email,
          owner.User_Name as Owner_Name, processor.User_Name as Processed_By_Name
          FROM refunds r
          JOIN bookings b ON r.BookingID = b.BookingID
          JOIN products p ON b.ProductID = p.ProductID
          JOIN user_accounts renter ON b.RenterID = renter.UserID
          JOIN user_accounts owner ON p.OwnerID = owner.UserID
          LEFT JOIN user_accounts processor ON r.Refund_ProcessedBy = processor.UserID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_refunds,
                SUM(CASE WHEN Refund_Status = 'Pending' THEN 1 ELSE 0 END) as pending_refunds,
                SUM(CASE WHEN Refund_Status = 'Completed' THEN 1 ELSE 0 END) as completed_refunds,
                SUM(CASE WHEN Refund_Status = 'Completed' THEN Refund_Amount ELSE 0 END) as total_refunded
                FROM refunds";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - RentHub PH Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d1edff; color: #0c5460; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">RentHub PH</h4>
                        <small class="text-white-50">Admin Panel</small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-calendar me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="refunds.php">
                                <i class="fas fa-money-bill-wave me-2"></i>Refunds
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Refund Management</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Refunds</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_refunds']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Refunds</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['pending_refunds']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Completed Refunds</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['completed_refunds']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Refunded</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            ₱<?php echo number_format($stats['total_refunded'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-peso-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" name="sort">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount: High to Low</option>
                                    <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount: Low to High</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Refunds Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Refund Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($refunds)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No refund requests found</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Refund ID</th>
                                            <th>Renter</th>
                                            <th>Product</th>
                                            <th>Amount</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($refunds as $refund): ?>
                                        <tr>
                                            <td>#<?php echo $refund['RefundID']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($refund['Renter_Name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($refund['Renter_Email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($refund['Prod_Name']); ?></td>
                                            <td><strong>₱<?php echo number_format($refund['Refund_Amount'], 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars($refund['Refund_Reason']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($refund['Refund_Status']); ?>">
                                                    <?php echo $refund['Refund_Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($refund['Refund_CreatedAt'])); ?></td>
                                            <td>
                                                <?php if ($refund['Refund_Status'] == 'Pending'): ?>
                                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processModal<?php echo $refund['RefundID']; ?>">
                                                        <i class="fas fa-check"></i> Process
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $refund['RefundID']; ?>">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">Processed</span>
                                                    <?php if ($refund['Processed_By_Name']): ?>
                                                        <br><small>by <?php echo htmlspecialchars($refund['Processed_By_Name']); ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Process Refund Modal -->
                                        <div class="modal fade" id="processModal<?php echo $refund['RefundID']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Process Refund #<?php echo $refund['RefundID']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="refund_id" value="<?php echo $refund['RefundID']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Refund Method</label>
                                                                <select class="form-select" name="refund_method" required>
                                                                    <option value="">Select method...</option>
                                                                    <option value="GCash">GCash</option>
                                                                    <option value="Maya">Maya</option>
                                                                    <option value="Bank Transfer">Bank Transfer</option>
                                                                    <option value="Cash">Cash</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Transaction ID (if applicable)</label>
                                                                <input type="text" class="form-control" name="transaction_id" placeholder="Enter transaction reference">
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Notes</label>
                                                                <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about the refund process"></textarea>
                                                            </div>
                                                            
                                                            <div class="alert alert-info">
                                                                <strong>Refund Amount:</strong> ₱<?php echo number_format($refund['Refund_Amount'], 2); ?><br>
                                                                <strong>Renter:</strong> <?php echo htmlspecialchars($refund['Renter_Name']); ?><br>
                                                                <strong>Product:</strong> <?php echo htmlspecialchars($refund['Prod_Name']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="process_refund" class="btn btn-success">Process Refund</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Refund Modal -->
                                        <div class="modal fade" id="rejectModal<?php echo $refund['RefundID']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Refund #<?php echo $refund['RefundID']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="refund_id" value="<?php echo $refund['RefundID']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for Rejection</label>
                                                                <textarea class="form-control" name="rejection_reason" rows="4" required placeholder="Explain why this refund request is being rejected"></textarea>
                                                            </div>
                                                            
                                                            <div class="alert alert-warning">
                                                                <strong>Refund Amount:</strong> ₱<?php echo number_format($refund['Refund_Amount'], 2); ?><br>
                                                                <strong>Renter:</strong> <?php echo htmlspecialchars($refund['Renter_Name']); ?><br>
                                                                <strong>Reason:</strong> <?php echo htmlspecialchars($refund['Refund_Reason']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="reject_refund" class="btn btn-danger">Reject Refund</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>