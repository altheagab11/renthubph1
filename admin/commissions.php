<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle commission actions
if ($_POST) {
    if (isset($_POST['update_commission_status'])) {
        $commission_id = $_POST['commission_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $query = "UPDATE commission_payments SET Comm_Status = ?, Comm_PayoutDate = NOW() WHERE CommissionID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$new_status, $commission_id]);
            
            $message = "Commission status updated successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Failed to update commission status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$owner_filter = isset($_GET['owner']) ? $_GET['owner'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["1=1"];
$params = [];

if ($status_filter && $status_filter != 'all') {
    $conditions[] = "cp.Comm_Status = ?";
    $params[] = ucfirst($status_filter);
}

if ($owner_filter && $owner_filter != 'all') {
    $conditions[] = "cp.OwnerID = ?";
    $params[] = $owner_filter;
}

// Sort options
$sort_options = [
    'newest' => 'cp.Comm_CreatedAt DESC',
    'oldest' => 'cp.Comm_CreatedAt ASC',
    'amount_high' => 'cp.Comm_Amount DESC',
    'amount_low' => 'cp.Comm_Amount ASC',
    'rate_high' => 'cp.Comm_Rate DESC',
    'rate_low' => 'cp.Comm_Rate ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'cp.Comm_CreatedAt DESC';

// Get commission payments
$query = "SELECT cp.*, b.Book_StartDate, b.Book_EndDate, b.Book_TotalAmount,
          p.Prod_Name, owner.User_Name as Owner_Name, owner.User_Email as Owner_Email,
          renter.User_Name as Renter_Name, sp.Plan_Name
          FROM commission_payments cp
          JOIN bookings b ON cp.BookingID = b.BookingID
          JOIN products p ON b.ProductID = p.ProductID
          JOIN user_accounts owner ON cp.OwnerID = owner.UserID
          JOIN user_accounts renter ON b.RenterID = renter.UserID
          LEFT JOIN user_subscriptions us ON owner.UserID = us.UserID 
              AND us.Sub_Status = 'Active' 
              AND us.Sub_EndDate >= NOW()
          LEFT JOIN subscription_plans sp ON us.PlanID = sp.PlanID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get owners for filter
$owners_query = "SELECT DISTINCT u.UserID, u.User_Name 
                FROM user_accounts u 
                JOIN commission_payments cp ON u.UserID = cp.OwnerID 
                ORDER BY u.User_Name";
$owners_stmt = $conn->prepare($owners_query);
$owners_stmt->execute();
$owners = $owners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_commissions,
                SUM(CASE WHEN Comm_Status = 'Completed' THEN 1 ELSE 0 END) as completed_commissions,
                SUM(CASE WHEN Comm_Status = 'Pending' THEN 1 ELSE 0 END) as pending_commissions,
                SUM(CASE WHEN Comm_Status = 'Completed' THEN Comm_Amount ELSE 0 END) as total_commission_amount,
                AVG(Comm_Rate) as average_commission_rate
                FROM commission_payments";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Management - RentHub PH Admin</title>
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
        .status-completed { background-color: #d1edff; color: #0c5460; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-paid { background-color: #d4edda; color: #155724; }
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
                            <a class="nav-link active" href="commissions.php">
                                <i class="fas fa-percentage me-2"></i>Commissions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="refunds.php">
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
                    <h1 class="h2">Commission Management</h1>
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
                                            Total Commissions</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_commissions']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
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
                                            Completed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['completed_commissions']); ?>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['pending_commissions']); ?>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Amount</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            ₱<?php echo number_format($stats['total_commission_amount'], 2); ?>
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
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Owner</label>
                                <select class="form-select" name="owner">
                                    <option value="all" <?php echo $owner_filter == 'all' ? 'selected' : ''; ?>>All Owners</option>
                                    <?php foreach($owners as $owner): ?>
                                        <option value="<?php echo $owner['UserID']; ?>" <?php echo $owner_filter == $owner['UserID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($owner['User_Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" name="sort">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount: High to Low</option>
                                    <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount: Low to High</option>
                                    <option value="rate_high" <?php echo $sort_by == 'rate_high' ? 'selected' : ''; ?>>Rate: High to Low</option>
                                    <option value="rate_low" <?php echo $sort_by == 'rate_low' ? 'selected' : ''; ?>>Rate: Low to High</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Commissions Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Commission Records</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($commissions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-percentage fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No commission records found</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Commission ID</th>
                                            <th>Owner</th>
                                            <th>Product</th>
                                            <th>Booking Period</th>
                                            <th>Gross Amount</th>
                                            <th>Rate</th>
                                            <th>Commission</th>
                                            <th>Net Amount</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($commissions as $commission): ?>
                                        <tr>
                                            <td>#<?php echo $commission['CommissionID']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($commission['Owner_Name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($commission['Owner_Email']); ?></small>
                                                <?php if($commission['Plan_Name']): ?>
                                                    <br><span class="badge bg-secondary"><?php echo htmlspecialchars($commission['Plan_Name']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($commission['Prod_Name']); ?><br>
                                                <small class="text-muted">Rented by: <?php echo htmlspecialchars($commission['Renter_Name']); ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M j, Y', strtotime($commission['Book_StartDate'])); ?><br>
                                                    to <?php echo date('M j, Y', strtotime($commission['Book_EndDate'])); ?>
                                                </small>
                                            </td>
                                            <td><strong>₱<?php echo number_format($commission['Comm_GrossAmount'], 2); ?></strong></td>
                                            <td><?php echo number_format($commission['Comm_Rate'], 1); ?>%</td>
                                            <td><strong class="text-danger">₱<?php echo number_format($commission['Comm_Amount'], 2); ?></strong></td>
                                            <td><strong class="text-success">₱<?php echo number_format($commission['Comm_NetAmount'], 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($commission['Comm_Status']); ?>">
                                                    <?php echo $commission['Comm_Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($commission['Comm_CreatedAt'])); ?></td>
                                            <td>
                                                <?php if ($commission['Comm_Status'] == 'Completed'): ?>
                                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $commission['CommissionID']; ?>">
                                                        <i class="fas fa-money-bill-wave"></i> Mark Paid
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">No actions</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Status Update Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $commission['CommissionID']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Commission Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="commission_id" value="<?php echo $commission['CommissionID']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">New Status</label>
                                                                <select class="form-select" name="new_status" required>
                                                                    <option value="Pending">Pending</option>
                                                                    <option value="Completed">Completed</option>
                                                                    <option value="Paid">Paid</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="alert alert-info">
                                                                <strong>Commission Details:</strong><br>
                                                                Owner: <?php echo htmlspecialchars($commission['Owner_Name']); ?><br>
                                                                Amount: ₱<?php echo number_format($commission['Comm_Amount'], 2); ?><br>
                                                                Product: <?php echo htmlspecialchars($commission['Prod_Name']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_commission_status" class="btn btn-primary">Update Status</button>
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