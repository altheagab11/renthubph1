<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle refund actions (only for owner's products and when owner account is not suspended)
if ($_POST) {
    if (isset($_POST['process_refund'])) {
        $refund_id = $_POST['refund_id'];
        $refund_method = $_POST['refund_method'];
        $transaction_id = $_POST['transaction_id'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        try {
            $conn->beginTransaction();
            
            // Verify this refund belongs to the owner and owner is not suspended
            $verify_query = "SELECT r.*, b.RenterID, b.Book_TotalAmount, p.Prod_Name, p.OwnerID, ua.User_Status
                           FROM refunds r 
                           JOIN bookings b ON r.BookingID = b.BookingID 
                           JOIN products p ON b.ProductID = p.ProductID
                           LEFT JOIN user_accounts ua ON p.OwnerID = ua.UserID
                           WHERE r.RefundID = ? AND p.OwnerID = ? AND r.Refund_Status = 'Pending'";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->execute([$refund_id, $user_id]);
            $refund_info = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$refund_info) {
                throw new Exception("Refund not found or not authorized");
            }
            
            // Check if owner account is suspended
            if ($refund_info['User_Status'] === 'Suspended') {
                throw new Exception("Cannot process refunds while account is suspended. Please contact admin.");
            }
            
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
            $stmt->execute([$refund_method, $transaction_id, $user_id, $notes, $refund_id]);
            
            // Create notification for renter
            $notification_msg = "Your refund of ₱" . number_format($refund_info['Refund_Amount'], 2) . " for booking '{$refund_info['Prod_Name']}' has been processed by the owner via {$refund_method}.";
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
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["p.OwnerID = ?"];
$params = [$user_id];

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

// Get refunds for owner's products only
$query = "SELECT r.*, b.BookingID, b.RenterID, b.Book_TotalAmount, b.Book_StartDate, b.Book_EndDate,
          p.Prod_Name, renter.User_Name as Renter_Name, renter.User_Email as Renter_Email,
          processor.User_Name as Processed_By_Name, ua.User_Status as Owner_Status
          FROM refunds r
          JOIN bookings b ON r.BookingID = b.BookingID
          JOIN products p ON b.ProductID = p.ProductID
          JOIN user_accounts renter ON b.RenterID = renter.UserID
          LEFT JOIN user_accounts processor ON r.Refund_ProcessedBy = processor.UserID
          LEFT JOIN user_accounts ua ON p.OwnerID = ua.UserID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for this owner
$stats_query = "SELECT 
                COUNT(*) as total_refunds,
                SUM(CASE WHEN r.Refund_Status = 'Pending' THEN 1 ELSE 0 END) as pending_refunds,
                SUM(CASE WHEN r.Refund_Status = 'Completed' THEN 1 ELSE 0 END) as completed_refunds,
                SUM(CASE WHEN r.Refund_Status = 'Completed' THEN r.Refund_Amount ELSE 0 END) as total_refunded
                FROM refunds r
                JOIN bookings b ON r.BookingID = b.BookingID
                JOIN products p ON b.ProductID = p.ProductID
                WHERE p.OwnerID = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Check if owner account is suspended
$owner_status_query = "SELECT User_Status FROM user_accounts WHERE UserID = ?";
$owner_status_stmt = $conn->prepare($owner_status_query);
$owner_status_stmt->execute([$user_id]);
$owner_status = $owner_status_stmt->fetch(PDO::FETCH_ASSOC)['User_Status'] ?? 'Active';

// Get unread notifications for navbar
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count_query = "SELECT COUNT(*) as cnt FROM notifications WHERE UserID = ? AND Not_IsRead = 0";
$notif_count_stmt = $conn->prepare($notif_count_query);
$notif_count_stmt->execute([$user_id]);
$notif_count = $notif_count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refunds Management - RentHub PH Owner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--primary-gradient);
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e9ecef;
        }
        
        .stats-card {
            background: var(--primary-gradient);
            border: none;
            border-radius: 15px;
            color: white;
        }
        
        .refund-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .refund-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d1edff; color: #0c5460; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-1">
                <i class="fas fa-home"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Owner Dashboard</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box me-2"></i> My Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add-product.php">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Booking Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="earnings.php">
                        <i class="fas fa-chart-line me-2"></i> Earnings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="refunds.php">
                        <i class="fas fa-undo me-2"></i> Refunds
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="fas fa-comments me-2"></i> Messages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reviews.php">
                        <i class="fas fa-star me-2"></i> Reviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="subscription.php">
                        <i class="fas fa-crown me-2"></i> Subscription
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user me-2"></i> Profile Settings
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <hr class="text-white-50">
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../renter/dashboard.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-search me-2"></i> Switch to Renter
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light sticky-top">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-3" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0">Refunds Management</h5>
                </div>
                
                <div class="d-flex align-items-center">
                    <!-- Notifications -->
                    <div class="dropdown me-3">
                        <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if($notif_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifCount">
                                    <?php echo $notif_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="width: 300px; max-height: 400px; overflow-y: auto;">
                            <?php if(empty($unread_notifications)): ?>
                                <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
                            <?php else: ?>
                                <?php foreach($unread_notifications as $notif): ?>
                                    <li>
                                        <div class="dropdown-item">
                                            <small class="text-muted"><?php echo date('M j, g:i A', strtotime($notif['Not_CreatedAt'])); ?></small>
                                            <div class="fw-bold"><?php echo htmlspecialchars($notif['Not_Title']); ?></div>
                                            <div class="small"><?php echo htmlspecialchars($notif['Not_Message']); ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid p-4">
            <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if($owner_status === 'Suspended'): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Account Suspended:</strong> Your account is currently suspended. You cannot process refunds. All refunds for your products will be handled by the admin. Please contact support for assistance.
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-undo fa-2x mb-2"></i>
                            <h4><?php echo $stats['total_refunds']; ?></h4>
                            <p class="mb-0">Total Refunds</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <h4><?php echo $stats['pending_refunds']; ?></h4>
                            <p class="mb-0">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <h4><?php echo $stats['completed_refunds']; ?></h4>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-peso-sign fa-2x mb-2"></i>
                            <h4>₱<?php echo number_format($stats['total_refunded'], 2); ?></h4>
                            <p class="mb-0">Total Refunded</p>
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
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount (High to Low)</option>
                                <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount (Low to High)</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="refunds.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Refunds List -->
            <?php if (empty($refunds)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-undo fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No refunds found</h5>
                        <p class="text-muted">No refund requests have been made for your products yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($refunds as $refund): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card refund-card h-100">
                                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Refund #<?php echo $refund['RefundID']; ?></h6>
                                    <span class="status-badge status-<?php echo strtolower($refund['Refund_Status']); ?>">
                                        <?php echo $refund['Refund_Status']; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title text-primary">₱<?php echo number_format($refund['Refund_Amount'], 2); ?></h5>
                                    
                                    <div class="mb-3">
                                        <strong>Product:</strong> <?php echo htmlspecialchars($refund['Prod_Name']); ?><br>
                                        <strong>Renter:</strong> <?php echo htmlspecialchars($refund['Renter_Name']); ?><br>
                                        <strong>Booking Date:</strong> <?php echo date('M j, Y', strtotime($refund['Book_StartDate'])); ?> - <?php echo date('M j, Y', strtotime($refund['Book_EndDate'])); ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Reason:</strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($refund['Refund_Reason']); ?></small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($refund['Refund_CreatedAt'])); ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($refund['Refund_Status'] == 'Completed'): ?>
                                        <div class="mb-3">
                                            <strong>Method:</strong> <?php echo htmlspecialchars($refund['Refund_Method']); ?><br>
                                            <?php if ($refund['Refund_TransactionID']): ?>
                                                <strong>Transaction ID:</strong> <?php echo htmlspecialchars($refund['Refund_TransactionID']); ?><br>
                                            <?php endif; ?>
                                            <strong>Processed by:</strong> <?php echo htmlspecialchars($refund['Processed_By_Name']); ?><br>
                                            <small class="text-muted">on <?php echo date('M j, Y g:i A', strtotime($refund['Refund_ProcessedAt'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($refund['Refund_Status'] == 'Pending' && $owner_status !== 'Suspended'): ?>
                                    <div class="card-footer bg-transparent border-0">
                                        <button type="button" class="btn btn-success btn-sm me-2" 
                                                onclick="showProcessRefundModal(<?php echo htmlspecialchars(json_encode($refund)); ?>)">
                                            <i class="fas fa-check me-1"></i> Process Refund
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Process Refund Modal -->
    <div class="modal fade" id="processRefundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Process Refund</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="processRefundId">
                        <input type="hidden" name="process_refund" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Refund Amount</label>
                            <input type="text" class="form-control" id="processRefundAmount" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Renter</label>
                            <input type="text" class="form-control" id="processRefundRenter" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="processRefundProduct" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Refund Method *</label>
                            <select name="refund_method" class="form-select" required>
                                <option value="">Select Method</option>
                                <option value="GCash">GCash</option>
                                <option value="Maya">Maya</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Transaction ID (Optional)</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="Enter transaction reference number">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about the refund process"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Process Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Process refund modal
        function showProcessRefundModal(refund) {
            document.getElementById('processRefundId').value = refund.RefundID;
            document.getElementById('processRefundAmount').value = '₱' + parseFloat(refund.Refund_Amount).toLocaleString();
            document.getElementById('processRefundRenter').value = refund.Renter_Name;
            document.getElementById('processRefundProduct').value = refund.Prod_Name;
            
            const modal = new bootstrap.Modal(document.getElementById('processRefundModal'));
            modal.show();
        }

        // Auto-hide success alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 3000);
        
        // Mark notifications as read when dropdown opens
        document.addEventListener('DOMContentLoaded', function() {
            const notifDropdown = document.querySelector('.nav-link.dropdown-toggle[role="button"]');
            notifDropdown?.addEventListener('show.bs.dropdown', function() {
                fetch('../api/mark-notifications-read.php', { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            document.getElementById('notifCount').textContent = '0';
                        }
                    });
            });
        });
    </script>
</body>
</html>