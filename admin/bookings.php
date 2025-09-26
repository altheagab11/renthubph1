
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

// Fetch all bookings with product, renter, and owner info
// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$conditions = [];
$params = [];
if ($status_filter && $status_filter != 'all') {
    $conditions[] = "b.Book_Status = ?";
    $params[] = $status_filter;
}
if ($date_filter && $date_filter != 'all') {
    switch($date_filter) {
        case 'today':
            $conditions[] = "DATE(b.Book_CreatedAt) = CURDATE()";
            break;
        case 'week':
            $conditions[] = "b.Book_CreatedAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $conditions[] = "b.Book_CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $conditions[] = "b.Book_CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}
if ($search) {
    $conditions[] = "(
        p.Prod_Name LIKE ? OR
        u1.User_Name LIKE ? OR
        u2.User_Name LIKE ?
    )";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
$query = "SELECT b.*, p.Prod_Name, p.Prod_Description, u1.User_Name AS Renter_Name, u2.User_Name AS Owner_Name
          FROM bookings b
          JOIN products p ON b.ProductID = p.ProductID
          JOIN user_accounts u1 ON b.RenterID = u1.UserID
          JOIN user_accounts u2 ON p.OwnerID = u2.UserID
          $where
          ORDER BY b.Book_CreatedAt DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
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
        .main-content {
            margin-left: 250px;
        }
    </style>
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
                <a class="nav-link active" href="bookings.php">
                    <i class="fas fa-calendar-check"></i> Bookings Management
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
            <li class="nav-item mt-3">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-arrow-left"></i> Back to Site
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
                <h5 class="mb-0">Bookings Management</h5>
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

        <!-- Content -->
        <div class="container-fluid p-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-calendar-check me-2"></i>All Bookings
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all"<?= $status_filter=='all'? ' selected':''; ?>>All</option>
                                <option value="Pending"<?= $status_filter=='Pending'? ' selected':''; ?>>Pending</option>
                                <option value="Confirmed"<?= $status_filter=='Confirmed'? ' selected':''; ?>>Confirmed</option>
                                <option value="Active"<?= $status_filter=='Active'? ' selected':''; ?>>Active</option>
                                <option value="Completed"<?= $status_filter=='Completed'? ' selected':''; ?>>Completed</option>
                                <option value="Cancelled"<?= $status_filter=='Cancelled'? ' selected':''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" class="form-select">
                                <option value="all"<?= $date_filter=='all'? ' selected':''; ?>>All Time</option>
                                <option value="today"<?= $date_filter=='today'? ' selected':''; ?>>Today</option>
                                <option value="week"<?= $date_filter=='week'? ' selected':''; ?>>This Week</option>
                                <option value="month"<?= $date_filter=='month'? ' selected':''; ?>>This Month</option>
                                <option value="year"<?= $date_filter=='year'? ' selected':''; ?>>This Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Product, Renter, Owner" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr class="<?php echo isset($_SESSION['flagged_bookings'][$booking['BookingID']]) ? 'table-warning' : ''; ?>">
                                    <th>Booking ID</th>
                                    <th>Product</th>
                                    <th>Renter</th>
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Total Amount</th>
                                    <th>Created At</th>
                                       <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['BookingID']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['Prod_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['Renter_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['Owner_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['Book_Status']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['Book_StartDate']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['Book_EndDate']); ?></td>
                                    <td>₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($booking['Book_CreatedAt']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $booking['BookingID']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="flag_booking_id" value="<?php echo $booking['BookingID']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo isset($_SESSION['flagged_bookings'][$booking['BookingID']]) ? 'btn-warning' : 'btn-outline-warning'; ?> ms-1" title="Flag as suspicious">
                                                <i class="fas fa-flag"></i> <?php echo isset($_SESSION['flagged_bookings'][$booking['BookingID']]) ? 'Unflag' : 'Flag'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                                                    <!-- Booking Details Modal -->
                                                                    <div class="modal fade" id="detailsModal<?php echo $booking['BookingID']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $booking['BookingID']; ?>" aria-hidden="true">
                                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                                            <div class="modal-content">
                                                                                <div class="modal-header bg-primary text-white">
                                                                                    <h5 class="modal-title" id="detailsModalLabel<?php echo $booking['BookingID']; ?>">Booking Details (ID: <?php echo $booking['BookingID']; ?>)</h5>
                                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                                </div>
                                                                                <div class="modal-body">
                                                                                    <div class="row mb-2">
                                                                                        <div class="col-md-6">
                                                                                            <strong>Product:</strong> <?php echo htmlspecialchars($booking['Prod_Name']); ?><br>
                                                                                            <small><?php echo htmlspecialchars($booking['Prod_Description']); ?></small>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <strong>Owner:</strong> <?php echo htmlspecialchars($booking['Owner_Name']); ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="row mb-2">
                                                                                        <div class="col-md-6">
                                                                                            <strong>Renter:</strong> <?php echo htmlspecialchars($booking['Renter_Name']); ?>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <strong>Status:</strong> <?php echo htmlspecialchars($booking['Book_Status']); ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="row mb-2">
                                                                                        <div class="col-md-4">
                                                                                            <strong>Start Date:</strong> <?php echo htmlspecialchars($booking['Book_StartDate']); ?>
                                                                                        </div>
                                                                                        <div class="col-md-4">
                                                                                            <strong>End Date:</strong> <?php echo htmlspecialchars($booking['Book_EndDate']); ?>
                                                                                        </div>
                                                                                        <div class="col-md-4">
                                                                                            <strong>Created At:</strong> <?php echo htmlspecialchars($booking['Book_CreatedAt']); ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="row mb-2">
                                                                                        <div class="col-md-6">
                                                                                            <strong>Total Amount:</strong> ₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <strong>Payment Status:</strong> <?php echo isset($booking['Book_PaymentStatus']) ? htmlspecialchars($booking['Book_PaymentStatus']) : 'N/A'; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="row mb-2">
                                                                                        <div class="col-md-12">
                                                                                            <strong>Flagged as suspicious:</strong> <?php echo isset($_SESSION['flagged_bookings'][$booking['BookingID']]) ? '<span class=\"text-danger\">Yes</span>' : 'No'; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <!-- Status history and payment info can be added here if available -->
                                                                                </div>
                                                                                <div class="modal-footer">
                                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>