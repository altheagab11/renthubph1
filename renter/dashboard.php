<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both Renter/Owner

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get renter statistics
$stats = [];

// Active bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE RenterID = ? AND Book_Status IN ('Pending', 'Confirmed', 'In Progress')";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE RenterID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total spent
$query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE RenterID = ? AND Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Favorites count
$query = "SELECT COUNT(*) as total FROM favorites WHERE UserID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['favorites_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent bookings
$query = "SELECT b.*, p.Prod_Name, p.Prod_RentalPrice, p.Prod_PriceType, pi.PI_ImagePath, u.User_Name as Owner_Name
          FROM bookings b
          JOIN products p ON b.ProductID = p.ProductID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON b.OwnerID = u.UserID
          WHERE b.RenterID = ?
          ORDER BY b.Book_CreatedAt DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renter Dashboard - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        .stat-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.bookings { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.total { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-card.spent { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .stat-card.favorites { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        
        .booking-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .booking-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge.pending { background: #ffc107; }
        .status-badge.confirmed { background: #198754; }
        .status-badge.in-progress { background: #0d6efd; }
        .status-badge.completed { background: #6c757d; }
        .status-badge.cancelled { background: #dc3545; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-1">
                <i class="fas fa-search"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Renter Dashboard</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="browse.php">
                        <i class="fas fa-search me-2"></i> Browse Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> My Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="favorites.php">
                        <i class="fas fa-heart me-2"></i> Favorites
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
                    <a class="nav-link" href="payment-history.php">
                        <i class="fas fa-money-bill me-2"></i> Payment History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user me-2"></i> Profile Settings
                    </a>
                </li>
                <?php if($_SESSION['user_role'] == 3): ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="../owner/dashboard.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-store me-2"></i> Switch to Owner
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="upgrade.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-crown me-2"></i> Become an Owner
                    </a>
                </li>
                <?php endif; ?>
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
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-3" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h5>
                </div>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger badge-sm">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">Booking confirmed</a></li>
                            <li><a class="dropdown-item" href="#">New message received</a></li>
                            <li><a class="dropdown-item" href="#">Payment processed</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="notifications.php">View all</a></li>
                        </ul>
                    </div>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bookings">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Active Bookings
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_bookings']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                                    <i class="fas fa-list fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card spent">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Spent
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_spent'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card favorites">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Saved Items
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['favorites_count']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-heart fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Quick Actions</h5>
                            <div class="row">
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="../browse.php" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i><br>
                                        <small>Browse Items</small>
                                    </a>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="bookings.php" class="btn btn-success w-100">
                                        <i class="fas fa-calendar-check"></i><br>
                                        <small>My Bookings</small>
                                    </a>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="favorites.php" class="btn btn-danger w-100">
                                        <i class="fas fa-heart"></i><br>
                                        <small>Favorites</small>
                                    </a>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="messages.php" class="btn btn-info w-100">
                                        <i class="fas fa-comments"></i><br>
                                        <small>Messages</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings and Recommendations -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Recent Bookings</h5>
                            <a href="bookings.php" class="btn btn-outline-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if(empty($recent_bookings)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">No bookings yet</h6>
                                    <p class="text-muted">Start exploring items to rent!</p>
                                    <a href="../browse.php" class="btn btn-primary">Browse Items</a>
                                </div>
                            <?php else: ?>
                                <?php foreach($recent_bookings as $booking): ?>
                                <div class="booking-card card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <img src="<?php echo $booking['PI_ImagePath'] ? htmlspecialchars($booking['PI_ImagePath']) : '../assets/images/no-image.jpg'; ?>" 
                                                     class="img-fluid rounded" style="height: 60px; width: 60px; object-fit: cover;" 
                                                     alt="<?php echo htmlspecialchars($booking['Prod_Name']); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['Prod_Name']); ?></h6>
                                                <p class="text-muted small mb-0">by <?php echo htmlspecialchars($booking['Owner_Name']); ?></p>
                                            </div>
                                            <div class="col-md-2">
                                                <strong>₱<?php echo number_format($booking['Book_TotalAmount'], 2); ?></strong>
                                            </div>
                                            <div class="col-md-2">
                                                <span class="badge status-badge <?php echo strtolower(str_replace(' ', '-', $booking['Book_Status'])); ?>">
                                                    <?php echo htmlspecialchars($booking['Book_Status']); ?>
                                                </span>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted"><?php echo date('M j, Y', strtotime($booking['Book_CreatedAt'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recommended for You</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-3">
                                <i class="fas fa-magic fa-2x text-muted mb-3"></i>
                                <h6 class="text-muted">Coming Soon!</h6>
                                <p class="text-muted small">We're working on personalized recommendations based on your rental history.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Rental Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-lightbulb text-warning me-2"></i>
                                    <small>Always read item descriptions carefully</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-shield-alt text-success me-2"></i>
                                    <small>Check owner ratings and reviews</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-camera text-info me-2"></i>
                                    <small>Take photos before and after use</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-clock text-primary me-2"></i>
                                    <small>Return items on time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>