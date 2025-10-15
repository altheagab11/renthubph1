<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner role only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Fetch notifications for the owner
$stmt = $conn->prepare("SELECT * FROM notifications WHERE UserID = ? ORDER BY Not_CreatedAt DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - RentHub PH</title>
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
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .list-group-item {
            border: none;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        .list-group-item:last-child {
            border-bottom: none;
        }
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
                    <h5 class="mb-0">
                        <i class="fas fa-bell text-warning me-2"></i>Notifications
                    </h5>
                </div>
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item">
                        <a href="dashboard.php" class="btn btn-outline-primary" style="border-radius: 25px;">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Notifications Content -->
        <div class="container-fluid p-4">
            <div class="card mb-4">
                <div class="card-body">
                    <?php if ($notifications): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($notifications as $notif): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notif['Not_Title']); ?></strong><br>
                                        <span class="text-muted small"><?php echo htmlspecialchars($notif['Not_Message']); ?></span>
                                    </div>
                                    <span class="badge bg-secondary"><?php echo date('M d, Y H:i', strtotime($notif['Not_CreatedAt'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell fa-4x text-warning mb-3"></i>
                            <h6 class="text-muted">No notifications found.</h6>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay d-md-none" id="sidebarOverlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        });
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.remove('show');
            this.classList.remove('show');
        });
    </script>
</body>
</html>
