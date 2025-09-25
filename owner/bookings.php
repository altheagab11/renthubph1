<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Both Renter/Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle booking actions
if ($_POST) {
    if (isset($_POST['action'])) {
        $booking_id = $_POST['booking_id'];
        $action = $_POST['action'];
        $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
        
        // Get current booking status
        $query = "SELECT Book_Status FROM bookings WHERE BookingID = ? AND OwnerID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $booking_id);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();
        $current_booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_booking) {
            $old_status = $current_booking['Book_Status'];
            $new_status = '';
            
            switch ($action) {
                case 'accept':
                    $new_status = 'Confirmed';
                    break;
                case 'reject':
                    $new_status = 'Cancelled';
                    break;
                case 'start':
                    $new_status = 'In Progress';
                    break;
                case 'complete':
                    $new_status = 'Completed';
                    break;
            }
            
            if ($new_status) {
                // Update booking status
                $query = "UPDATE bookings SET Book_Status = ?, Book_UpdatedAt = NOW() WHERE BookingID = ? AND OwnerID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $new_status);
                $stmt->bindParam(2, $booking_id);
                $stmt->bindParam(3, $user_id);
                
                if ($stmt->execute()) {
                    // Log status change
                    $query = "INSERT INTO booking_status_history (BookingID, BSH_OldStatus, BSH_NewStatus, BSH_ChangedBy, BSH_ChangeReason) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $booking_id);
                    $stmt->bindParam(2, $old_status);
                    $stmt->bindParam(3, $new_status);
                    $stmt->bindParam(4, $user_id);
                    $stmt->bindParam(5, $reason);
                    $stmt->execute();
                    
                    $message = "Booking status updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to update booking status.";
                    $message_type = "danger";
                }
            }
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["b.OwnerID = ?"];
$params = [$user_id];

if ($status_filter && $status_filter != 'All') {
    $conditions[] = "b.Book_Status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $conditions[] = "DATE(b.Book_CreatedAt) = ?";
    $params[] = $date_filter;
}

if ($search) {
    $conditions[] = "(p.Prod_Name LIKE ? OR u.User_Name LIKE ? OR b.BookingID LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Sort options
$sort_options = [
    'newest' => 'b.Book_CreatedAt DESC',
    'oldest' => 'b.Book_CreatedAt ASC',
    'start_date' => 'b.Book_StartDate ASC',
    'amount_high' => 'b.Book_TotalAmount DESC',
    'amount_low' => 'b.Book_TotalAmount ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'b.Book_CreatedAt DESC';

// Get bookings
$query = "SELECT b.*, p.Prod_Name, p.Prod_RentalPrice, p.Prod_PriceType, 
          pi.PI_ImagePath, u.User_Name as Renter_Name, u.User_Phone as Renter_Phone,
          u.User_Email as Renter_Email
          FROM bookings b
          JOIN products p ON b.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON b.RenterID = u.UserID
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$active_bookings = [];
$history_bookings = [];
foreach ($bookings as $b) {
    if (in_array($b['Book_Status'], ['Pending', 'Confirmed', 'In Progress'])) {
        $active_bookings[] = $b;
    } else {
        $history_bookings[] = $b;
    }
}

// Get statistics
$stats = [];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending bookings
$query = "SELECT COUNT(*) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ? AND b.Book_Status = 'Pending'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['pending_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active bookings
$query = "SELECT COUNT(*) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ? AND b.Book_Status IN ('Confirmed', 'In Progress')";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$query = "SELECT SUM(b.Book_TotalAmount) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ? AND b.Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Requests - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { background: var(--primary-gradient); color: white; }
        .stat-card.pending { background: var(--primary-gradient); color: white; }
        .stat-card.active { background: var(--primary-gradient); color: white; }
        .stat-card.revenue { background: var(--primary-gradient); color: white; }
        
        .booking-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .booking-status {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 2;
        }
        
        .status-badge {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .status-badge.pending { background: #ffc107; color: #000; }
        .status-badge.confirmed { background: #198754; }
        .status-badge.in-progress { background: #0d6efd; }
        .status-badge.completed { background: #6c757d; }
        .status-badge.cancelled { background: #dc3545; }
        .status-badge.disputed { background: #fd7e14; }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .action-btn {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            margin: 0.25rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .action-btn.accept { background: #28a745; color: white; }
        .action-btn.reject { background: #dc3545; color: white; }
        .action-btn.start { background: #007bff; color: white; }
        .action-btn.complete { background: #6c757d; color: white; }
        .action-btn.message { background: #17a2b8; color: white; }
        
        .navbar {
            border-bottom: 1px solid #e9ecef;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
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
        
        .renter-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .booking-timeline {
            border-left: 3px solid #11998e;
            padding-left: 1rem;
            margin-left: 0.5rem;
        }
        
        .timeline-item {
            margin-bottom: 1rem;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #11998e;
        }
        
        /* Mobile Responsiveness */
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
            
            .search-filters {
                padding: 1rem;
            }
            
            .booking-card .row {
                flex-direction: column;
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
                    <a class="nav-link active" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Booking Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="earnings.php">
                        <i class="fas fa-chart-line me-2"></i> Earnings
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
                    <a class="nav-link" href="../index.php">
                        <i class="fas fa-arrow-left me-2"></i> Back to Site
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
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check text-primary me-2"></i>Booking Requests
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $stats['pending_bookings']; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar-check text-success me-2"></i>New booking request</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-money-bill text-primary me-2"></i>Payment received</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="notifications.php">View all notifications</a></li>
                        </ul>
                    </div>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container-fluid p-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 15px;">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card total">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card pending">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Pending Requests
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['pending_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card active">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Active Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-play-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card revenue">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Revenue
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search Bookings
                        </label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by product, renter, or booking ID">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="All" <?php echo $status_filter == 'All' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Confirmed" <?php echo $status_filter == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Date
                        </label>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="start_date" <?php echo $sort_by == 'start_date' ? 'selected' : ''; ?>>Start Date</option>
                            <option value="amount_high" <?php echo $sort_by == 'amount_high' ? 'selected' : ''; ?>>Amount (High-Low)</option>
                            <option value="amount_low" <?php echo $sort_by == 'amount_low' ? 'selected' : ''; ?>>Amount (Low-High)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn" style="background: var(--primary-gradient); color: white;">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>


            <!-- Active Bookings List -->
            <div class="card shadow mb-5" style="border-radius: 25px;">
                <div class="card-header text-white" style="background: var(--primary-gradient); color: white; border-radius: 25px 25px 0 0;">
                    <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Active Bookings</h4>
                </div>
                <div class="card-body" style="background: #f8f9fa; border-radius: 0 0 25px 25px;">
                    <?php if(empty($active_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4 class="text-muted">No active bookings found</h4>
                            <p class="text-muted">Booking requests for your products will appear here.</p>
                            <a href="products.php" class="btn btn-lg" style="background: var(--primary-gradient); color: white; border-radius: 25px;">
                                <i class="fas fa-box me-2"></i>Manage Products
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach($active_bookings as $booking): ?>
                            <?php /* ...existing booking card code... */ ?>
                            <div class="booking-card card mb-4">
                                <div class="booking-status">
                                    <span class="badge status-badge <?php echo strtolower(str_replace(' ', '-', $booking['Book_Status'])); ?>">
                                        <?php echo htmlspecialchars($booking['Book_Status']); ?>
                                    </span>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <img
                                                src="<?php 
                                                    $imgPath = $booking['PI_ImagePath'] ? '../' . ltrim($booking['PI_ImagePath'], '/') : '../assets/images/no-image.jpg';
                                                    echo htmlspecialchars($imgPath); 
                                                ?>"
                                                class="img-fluid rounded" style="width: 240px; height: 240px; object-fit: cover; aspect-ratio: 1 / 1;"
                                                alt="<?php echo htmlspecialchars($booking['Prod_Name']); ?>"
                                                onerror="this.onerror=null;this.src='../assets/images/no-image.jpg';">
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="mb-2"><?php echo htmlspecialchars($booking['Prod_Name']); ?></h5>
                                            <p class="text-muted mb-2">Booking #<?php echo $booking['BookingID']; ?></p>
                                            <div class="renter-info">
                                                <h6 class="mb-2">
                                                    <i class="fas fa-user me-2"></i>Renter Information
                                                </h6>
                                                <p class="mb-1"><strong><?php echo htmlspecialchars($booking['Renter_Name']); ?></strong></p>
                                                <p class="mb-1 small">
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($booking['Renter_Email']); ?>
                                                </p>
                                                <?php if($booking['Renter_Phone']): ?>
                                                <p class="mb-0 small">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($booking['Renter_Phone']); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="booking-timeline">
                                                <div class="timeline-item">
                                                    <strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['Book_StartDate'])); ?>
                                                </div>
                                                <div class="timeline-item">
                                                    <strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['Book_EndDate'])); ?>
                                                </div>
                                                <div class="timeline-item">
                                                    <strong>Pickup:</strong> <?php echo htmlspecialchars($booking['Book_PickupType']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-end">
                                                <h4 class="text-success mb-2">₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?></h4>
                                                <?php if($booking['Book_SecurityDeposit'] > 0): ?>
                                                <p class="text-muted small mb-1">Security: ₱<?php echo number_format($booking['Book_SecurityDeposit'], 2); ?></p>
                                                <?php endif; ?>
                                                <?php if($booking['Book_DeliveryFee'] > 0): ?>
                                                <p class="text-muted small mb-2">Delivery: ₱<?php echo number_format($booking['Book_DeliveryFee'], 2); ?></p>
                                                <?php endif; ?>
                                                <div class="d-flex flex-column gap-2">
                                                    <?php if($booking['Book_Status'] == 'Pending'): ?>
                                                        <button class="btn action-btn accept" data-bs-toggle="modal" data-bs-target="#actionModal" 
                                                                data-action="accept" data-booking-id="<?php echo $booking['BookingID']; ?>" 
                                                                data-booking-name="<?php echo htmlspecialchars($booking['Prod_Name']); ?>">
                                                            <i class="fas fa-check me-2"></i>Accept
                                                        </button>
                                                        <button class="btn action-btn reject" data-bs-toggle="modal" data-bs-target="#actionModal" 
                                                                data-action="reject" data-booking-id="<?php echo $booking['BookingID']; ?>" 
                                                                data-booking-name="<?php echo htmlspecialchars($booking['Prod_Name']); ?>">
                                                            <i class="fas fa-times me-2"></i>Reject
                                                        </button>
                                                    <?php elseif($booking['Book_Status'] == 'Confirmed'): ?>
                                                        <button class="btn action-btn start" data-bs-toggle="modal" data-bs-target="#actionModal" 
                                                                data-action="start" data-booking-id="<?php echo $booking['BookingID']; ?>" 
                                                                data-booking-name="<?php echo htmlspecialchars($booking['Prod_Name']); ?>">
                                                            <i class="fas fa-play me-2"></i>Start Rental
                                                        </button>
                                                    <?php elseif($booking['Book_Status'] == 'In Progress'): ?>
                                                        <button class="btn action-btn complete" data-bs-toggle="modal" data-bs-target="#actionModal" 
                                                                data-action="complete" data-booking-id="<?php echo $booking['BookingID']; ?>" 
                                                                data-booking-name="<?php echo htmlspecialchars($booking['Prod_Name']); ?>">
                                                            <i class="fas fa-check-circle me-2"></i>Complete
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn action-btn message">
                                                        <i class="fas fa-comment me-2"></i>Message
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="text-end mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Requested <?php echo date('M j, Y', strtotime($booking['Book_CreatedAt'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if($booking['Book_Notes']): ?>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="alert alert-info" style="border-radius: 15px;">
                                                <h6><i class="fas fa-sticky-note me-2"></i>Renter Notes:</h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($booking['Book_Notes'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Booking History List -->
            <div class="card shadow mb-5" style="border-radius: 25px;">
                <div class="card-header bg-secondary text-white" style="border-radius: 25px 25px 0 0;">
                    <h4 class="mb-0"><i class="fas fa-history me-2"></i>Booking History</h4>
                </div>
                <div class="card-body" style="background: #f8f9fa; border-radius: 0 0 25px 25px;">
                    <?php if(empty($history_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-minus"></i>
                            <h4 class="text-muted">No booking history found</h4>
                            <p class="text-muted">Completed, cancelled, or past bookings will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($history_bookings as $booking): ?>
                            <div class="booking-card card bg-light mb-4">
                                <div class="booking-status">
                                    <span class="badge status-badge <?php echo strtolower(str_replace(' ', '-', $booking['Book_Status'])); ?>">
                                        <?php echo htmlspecialchars($booking['Book_Status']); ?>
                                    </span>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <img src="<?php echo $booking['PI_ImagePath'] ? htmlspecialchars($booking['PI_ImagePath']) : '../assets/images/no-image.jpg'; ?>" 
                                                 class="img-fluid rounded" style="height: 120px; width: 100%; object-fit: cover;" 
                                                 alt="<?php echo htmlspecialchars($booking['Prod_Name']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="mb-2"><?php echo htmlspecialchars($booking['Prod_Name']); ?></h5>
                                            <p class="text-muted mb-2">Booking #<?php echo $booking['BookingID']; ?></p>
                                            <div class="renter-info">
                                                <h6 class="mb-2">
                                                    <i class="fas fa-user me-2"></i>Renter Information
                                                </h6>
                                                <p class="mb-1"><strong><?php echo htmlspecialchars($booking['Renter_Name']); ?></strong></p>
                                                <p class="mb-1 small">
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($booking['Renter_Email']); ?>
                                                </p>
                                                <?php if($booking['Renter_Phone']): ?>
                                                <p class="mb-0 small">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($booking['Renter_Phone']); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="booking-timeline">
                                                <div class="timeline-item">
                                                    <strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['Book_StartDate'])); ?>
                                                </div>
                                                <div class="timeline-item">
                                                    <strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['Book_EndDate'])); ?>
                                                </div>
                                                <div class="timeline-item">
                                                    <strong>Pickup:</strong> <?php echo htmlspecialchars($booking['Book_PickupType']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-end">
                                                <h4 class="text-success mb-2">₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?></h4>
                                                <?php if($booking['Book_SecurityDeposit'] > 0): ?>
                                                <p class="text-muted small mb-1">Security: ₱<?php echo number_format($booking['Book_SecurityDeposit'], 2); ?></p>
                                                <?php endif; ?>
                                                <?php if($booking['Book_DeliveryFee'] > 0): ?>
                                                <p class="text-muted small mb-2">Delivery: ₱<?php echo number_format($booking['Book_DeliveryFee'], 2); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Requested <?php echo date('M j, Y', strtotime($booking['Book_CreatedAt'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if($booking['Book_Notes']): ?>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="alert alert-info" style="border-radius: 15px;">
                                                <h6><i class="fas fa-sticky-note me-2"></i>Renter Notes:</h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($booking['Book_Notes'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="actionModalTitle">
                        <i class="fas fa-question-circle text-primary me-2"></i>Confirm Action
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction">
                        <input type="hidden" name="booking_id" id="modalBookingId">
                        
                        <div class="text-center mb-4">
                            <h5 id="modalBookingName"></h5>
                            <p class="text-muted" id="modalActionDescription"></p>
                        </div>
                        
                        <div class="mb-3" id="reasonSection" style="display: none;">
                            <label for="reason" class="form-label">Reason (Optional)</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Provide a reason for this action..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="modalConfirmBtn">Confirm</button>
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

        // Action modal
        document.querySelectorAll('[data-bs-target="#actionModal"]').forEach(button => {
            button.addEventListener('click', function() {
                const action = this.dataset.action;
                const bookingId = this.dataset.bookingId;
                const bookingName = this.dataset.bookingName;
                
                document.getElementById('modalAction').value = action;
                document.getElementById('modalBookingId').value = bookingId;
                document.getElementById('modalBookingName').textContent = bookingName;
                
                const modalTitle = document.getElementById('actionModalTitle');
                const modalDescription = document.getElementById('modalActionDescription');
                const modalConfirmBtn = document.getElementById('modalConfirmBtn');
                const reasonSection = document.getElementById('reasonSection');
                
                switch(action) {
                    case 'accept':
                        modalTitle.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Accept Booking';
                        modalDescription.textContent = 'Are you sure you want to accept this booking request?';
                        modalConfirmBtn.className = 'btn btn-success';
                        modalConfirmBtn.innerHTML = '<i class="fas fa-check me-2"></i>Accept Booking';
                        reasonSection.style.display = 'none';
                        break;
                    case 'reject':
                        modalTitle.innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Reject Booking';
                        modalDescription.textContent = 'Are you sure you want to reject this booking request?';
                        modalConfirmBtn.className = 'btn btn-danger';
                        modalConfirmBtn.innerHTML = '<i class="fas fa-times me-2"></i>Reject Booking';
                        reasonSection.style.display = 'block';
                        break;
                    case 'start':
                        modalTitle.innerHTML = '<i class="fas fa-play-circle text-primary me-2"></i>Start Rental';
                        modalDescription.textContent = 'Mark this booking as in progress?';
                        modalConfirmBtn.className = 'btn btn-primary';
                        modalConfirmBtn.innerHTML = '<i class="fas fa-play me-2"></i>Start Rental';
                        reasonSection.style.display = 'none';
                        break;
                    case 'complete':
                        modalTitle.innerHTML = '<i class="fas fa-check-circle text-secondary me-2"></i>Complete Rental';
                        modalDescription.textContent = 'Mark this rental as completed?';
                        modalConfirmBtn.className = 'btn btn-secondary';
                        modalConfirmBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Complete Rental';
                        reasonSection.style.display = 'none';
                        break;
                }
            });
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>