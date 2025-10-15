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
    <style>
        /* Fix dropdown menu being cut off */
        .dropdown-menu {
            z-index: 2000 !important;
            max-height: 400px;
            overflow-y: auto;
        }
        .card, .card-body {
            overflow: visible !important;
        }

        /* Sidebar Styling */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #343a40;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
        }

        /* Main Content Styling */
        .main-content {
            margin-left: 250px;
            padding-top: 70px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        /* Top Navbar Styling */
        .navbar {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 1000;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Stat Cards */
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .stat-card.total { border-left-color: #007bff; }
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.completed { border-left-color: #28a745; }
        .stat-card.refunded { border-left-color: #17a2b8; }
        
        /* Custom column class for 4 equal columns */
        .col-xl-2-4 {
            flex: 0 0 25%;
            max-width: 25%;
        }
        
        @media (max-width: 1199.98px) {
            .col-xl-2-4 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }
        
        .refund-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: visible !important;
        }
        .refund-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 0.4rem;
            border-radius: 50%;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .status-badge:hover {
            transform: scale(1.1);
        }
        
        .status-pending { 
            background: rgba(255, 193, 7, 0.9); 
            color: #000;
        }
        .status-completed { 
            background: rgba(40, 167, 69, 0.9); 
            color: white;
        }
        .status-rejected { 
            background: rgba(220, 53, 69, 0.9); 
            color: white;
        }
        
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .btn-action {
            padding: 0.4rem 0.8rem;
            margin: 0 0.1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            .navbar {
                left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed top-0 start-0" style="width: 250px; z-index: 1000;">
        <div class="p-3">
            <h4 class="text-white">
                <i class="fas fa-home"></i> RentHub PH
            </h4>
            <p class="text-muted small">Admin Panel</p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i> Users Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php">
                    <i class="fas fa-box"></i> Products Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="bookings.php">
                    <i class="fas fa-calendar-check"></i> Bookings Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="refunds.php">
                    <i class="fas fa-undo"></i> Refunds Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions.php">
                    <i class="fas fa-credit-card"></i> Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h5 class="mb-0">Refund Management</h5>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-2-4 col-md-6 mb-4">
                    <div class="card stat-card total">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Refunds
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_refunds']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-undo fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2-4 col-md-6 mb-4">
                    <div class="card stat-card pending">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Refunds
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['pending_refunds']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2-4 col-md-6 mb-4">
                    <div class="card stat-card completed">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Completed Refunds
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['completed_refunds']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2-4 col-md-6 mb-4">
                    <div class="card stat-card refunded">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Refunded
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_refunded'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="filter-card">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Status
                        </label>
                        <select class="form-select" name="status" onchange="window.location.href='?status='+this.value+'&sort=<?php echo $sort_by; ?>'">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort" onchange="window.location.href='?status=<?php echo $status_filter; ?>&sort='+this.value">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount (High to Low)</option>
                            <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount (Low to High)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search
                        </label>
                        <input type="text" class="form-control" placeholder="Search refunds..." id="searchInput">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="window.location.reload()">
                            <i class="fas fa-sync me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>
            <!-- Refunds Management Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Refund Requests</h5>
                        <small class="text-muted"><?php echo count($refunds); ?> Found</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                            <i class="fas fa-sync me-1"></i>Export
                        </button>
                        <div class="btn-group">
                            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-cog me-1"></i>Bulk Actions
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-check me-2"></i>Mark as Processed</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-times me-2"></i>Reject Selected</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($refunds)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-money-bill-wave fa-4x text-muted mb-4"></i>
                            <h5 class="text-muted">No refund requests found</h5>
                            <p class="text-muted">Refund requests will appear here when customers cancel bookings with payments.</p>
                        </div>
                    <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                                </div>
                                            </th>
                                            <th>Refund ID</th>
                                            <th>Renter</th>
                                            <th>Product</th>
                                            <th>Amount</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($refunds as $refund): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $refund['RefundID']; ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-primary">#<?php echo $refund['RefundID']; ?></strong>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary text-white me-3">
                                                        <?php echo strtoupper(substr($refund['Renter_Name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($refund['Renter_Name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($refund['Renter_Email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($refund['Prod_Name']); ?></strong><br>
                                                <small class="text-muted">Booking #<?php echo $refund['BookingID']; ?></small>
                                            </td>
                                            <td>
                                                <strong class="text-success">₱<?php echo number_format($refund['Refund_Amount'], 2); ?></strong><br>
                                                <small class="text-muted">of ₱<?php echo number_format($refund['Book_TotalAmount'], 2); ?></small>
                                            </td>
                                            <td>
                                                <span class="text-muted" style="max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($refund['Refund_Reason']); ?>">
                                                    <?php echo htmlspecialchars($refund['Refund_Reason']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo strtolower($refund['Refund_Status']); ?>">
                                                    <?php 
                                                    $status_icons = [
                                                        'Pending' => 'fas fa-clock',
                                                        'Completed' => 'fas fa-check-circle',
                                                        'Rejected' => 'fas fa-times-circle'
                                                    ];
                                                    $icon = $status_icons[$refund['Refund_Status']] ?? 'fas fa-question';
                                                    ?>
                                                    <i class="<?php echo $icon; ?> me-1"></i><?php echo $refund['Refund_Status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo date('M j, Y', strtotime($refund['Refund_CreatedAt'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($refund['Refund_CreatedAt'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($refund['Refund_Status'] == 'Pending'): ?>
                                                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processModal<?php echo $refund['RefundID']; ?>" title="Process Refund">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $refund['RefundID']; ?>" title="Reject Refund">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-check me-1"></i>Processed
                                                        </span>
                                                        <?php if ($refund['Processed_By_Name']): ?>
                                                            <br><small class="text-muted">by <?php echo htmlspecialchars($refund['Processed_By_Name']); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
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
        </div>
    </div>

    <!-- Bootstrap and FontAwesome -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            var input = this.value.toLowerCase();
            var rows = document.querySelectorAll('.card-body .table tbody tr');
            
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.table tbody input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    </script>
</body>
</html>