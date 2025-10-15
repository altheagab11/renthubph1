<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([2]); // Only renters can upgrade

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle upgrade request
if ($_POST && isset($_POST['upgrade_to_owner'])) {
    try {
        // Update user role from 2 (Renter) to 3 (Both - Renter + Owner)
        $query = "UPDATE user_accounts SET User_Role = 3, User_UpdatedAt = NOW() WHERE UserID = ? AND User_Role = 2";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            // Update session role
            $_SESSION['user_role'] = 3;
            
            // Create notification for successful upgrade
            $notif_query = "INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_IsRead, Not_CreatedAt) 
                           VALUES (?, 'upgrade', 'Account Upgraded Successfully!', 'Congratulations! You can now rent and list products on RentHub PH.', 0, NOW())";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_stmt->execute([$user_id]);
            
            // Redirect to owner dashboard after successful upgrade
            header("Location: ../owner/dashboard.php?upgraded=1");
            exit();
        } else {
            $message = "Failed to upgrade account. Please try again.";
            $message_type = "danger";
        }
    } catch (PDOException $e) {
        $message = "An error occurred during upgrade. Please contact support.";
        $message_type = "danger";
        error_log("Upgrade error: " . $e->getMessage());
    }
}

// Get user information
$query = "SELECT * FROM user_accounts WHERE UserID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user stats
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN Book_Status = 'Completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN Book_Status = 'Completed' THEN Book_TotalAmount ELSE 0 END) as total_spent
    FROM bookings WHERE RenterID = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unread notifications
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE UserID = ? AND Not_IsRead = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$notif_count = $notif_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become an Owner - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--primary-gradient);
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
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .upgrade-hero {
            background: var(--primary-gradient);
            color: white;
            padding: 3rem 0 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .upgrade-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s linear infinite;
        }
        
        .upgrade-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .upgrade-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .upgrade-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 2.5rem;
            margin-top: -2rem;
            position: relative;
            z-index: 2;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: auto;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .upgrade-btn {
            background: var(--secondary-gradient);
            border: none;
            border-radius: 25px;
            padding: 1rem 3rem;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(245, 87, 108, 0.3);
        }
        
        .upgrade-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(245, 87, 108, 0.4);
            color: white;
        }
        
        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .benefits-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .benefits-list li:last-child {
            border-bottom: none;
        }
        
        .benefits-list i {
            margin-right: 0.75rem;
            font-size: 1rem;
            width: 16px;
            text-align: center;
        }
        
        .benefits-list .fa-check-circle {
            color: #28a745;
        }
        
        .benefits-list .text-info {
            color: #17a2b8 !important;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-0">
                <i class="fas fa-home me-2"></i>RentHub PH
            </h4>
            <small class="text-white-50">Renter Dashboard</small>
        </div>
        
        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
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
                        <i class="fas fa-receipt me-2"></i> Payment History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user me-2"></i> Profile Settings
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link active" href="upgrade.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-crown me-2"></i> Become an Owner
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
        <!-- Hero Section -->
        <div class="upgrade-hero">
            <div class="container">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="fas fa-crown me-3"></i>Become an Owner
                </h1>
                <p class="lead mb-0">Start earning by renting out your items to the RentHub PH community</p>
            </div>
        </div>

        <div class="container py-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Upgrade Card -->
                <div class="col-lg-8">
                    <div class="upgrade-card">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary mb-2">Ready to Start Earning?</h2>
                            <p class="text-muted mb-0">Join thousands of owners already earning on RentHub PH</p>
                        </div>

                        <!-- Current User Stats -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $user_stats['total_bookings']; ?></div>
                                    <div class="text-muted small">Total Bookings</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $user_stats['completed_bookings']; ?></div>
                                    <div class="text-muted small">Completed</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <div class="stats-number">₱<?php echo number_format($user_stats['total_spent'], 0); ?></div>
                                    <div class="text-muted small">Total Spent</div>
                                </div>
                            </div>
                        </div>

                        <!-- Benefits -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <h5 class="fw-bold mb-3 text-success">
                                    <i class="fas fa-gift me-2"></i>What You'll Get:
                                </h5>
                                <ul class="benefits-list">
                                    <li><i class="fas fa-check-circle"></i> List unlimited products</li>
                                    <li><i class="fas fa-check-circle"></i> Earn from rentals</li>
                                    <li><i class="fas fa-check-circle"></i> Access to owner dashboard</li>
                                    <li><i class="fas fa-check-circle"></i> Booking management tools</li>
                                    <li><i class="fas fa-check-circle"></i> Revenue analytics</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5 class="fw-bold mb-3 text-info">
                                    <i class="fas fa-clipboard-list me-2"></i>Requirements:
                                </h5>
                                <ul class="benefits-list">
                                    <li><i class="fas fa-check-circle text-info"></i> Valid RentHub PH account</li>
                                    <li><i class="fas fa-check-circle text-info"></i> Active renter status</li>
                                    <li><i class="fas fa-check-circle text-info"></i> Agree to owner terms</li>
                                    <li><i class="fas fa-check-circle text-info"></i> Provide accurate information</li>
                                    <li><i class="fas fa-check-circle text-info"></i> Free upgrade - no fees!</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Upgrade Button -->
                        <div class="text-center pt-3 border-top">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="upgrade_to_owner" class="upgrade-btn mb-3" 
                                        onclick="return confirm('Are you sure you want to upgrade to become an owner? This will give you access to list and rent out products.')">
                                    <i class="fas fa-arrow-up me-2"></i>Upgrade to Owner Account
                                </button>
                            </form>
                            <div class="mb-3">
                                <a href="dashboard.php" class="btn btn-outline-secondary" style="border-radius: 20px; padding: 0.5rem 2rem;">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-shield-alt me-1"></i>
                                100% Free • Instant Activation • Keep All Renter Features
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Features -->
                <div class="col-lg-4">
                    <div class="sticky-top" style="top: 2rem;">
                        <div class="feature-card mb-3">
                            <div class="feature-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <h5 class="fw-bold">Start Earning</h5>
                            <p class="text-muted mb-0">Rent out your items and earn money from things you already own.</p>
                        </div>

                        <div class="feature-card mb-3">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h5 class="fw-bold">Track Performance</h5>
                            <p class="text-muted mb-0">Monitor your earnings, bookings, and performance with detailed analytics.</p>
                        </div>

                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="fw-bold">Trusted Community</h5>
                            <p class="text-muted mb-0">Join a community of verified renters and owners with secure transactions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>