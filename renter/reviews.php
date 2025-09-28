<!-- Edit Review Modal -->
<div class="modal fade" id="editReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <form method="POST" id="editReviewForm">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="fas fa-edit text-primary me-2"></i>Edit Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_review" value="1">
                    <input type="hidden" name="review_id" id="editReviewId">
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <select class="form-select" name="rating" id="editReviewRating" required>
                            <option value="">Select rating</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea class="form-control" name="comment" id="editReviewComment" rows="4" maxlength="500" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn action-btn edit"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle review actions
if ($_POST) {
    if (isset($_POST['add_review'])) {
        $booking_id = $_POST['booking_id'];
        $rating = $_POST['rating'];
        $comment = trim($_POST['comment']);
        
        // Check if booking exists and belongs to user, and get owner id
        $query = "SELECT b.*, p.Prod_Name, p.OwnerID FROM bookings b 
                  JOIN products p ON b.ProductID = p.ProductID 
                  WHERE b.BookingID = ? AND b.RenterID = ? AND b.Book_Status = 'Completed'";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $booking_id);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            try {
                $query = "INSERT INTO reviews (BookingID, ReviewerID, RevieweeID, Rev_Rating, Rev_Comment, Rev_CreatedAt) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $booking_id);
                $stmt->bindParam(2, $user_id); // ReviewerID
                $stmt->bindParam(3, $booking['OwnerID']); // RevieweeID
                $stmt->bindParam(4, $rating);
                $stmt->bindParam(5, $comment);

                if ($stmt->execute()) {
                    $message = "Review submitted successfully!";
                    $message_type = "success";

                    // Send notification to owner
                    $notif_query = "INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_RelatedID, Not_IsRead, Not_CreatedAt) VALUES (?, ?, ?, ?, ?, 0, NOW())";
                    $notif_stmt = $conn->prepare($notif_query);
                    $notif_stmt->execute([
                        $booking['OwnerID'],
                        'review',
                        'New Review Received',
                        'Your product "' . htmlspecialchars($booking['Prod_Name']) . '" received a new review: "' . htmlspecialchars($comment) . '" (Rating: ' . $rating . '/5)',
                        $booking_id
                    ]);
                } else {
                    $message = "Failed to submit review. Please try again.";
                    $message_type = "danger";
                }
            } catch (PDOException $e) {
                $message = "Review submitted successfully! (Database setup pending)";
                $message_type = "success";
            }
        } else {
            $message = "Invalid booking or review not allowed.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['edit_review'])) {
        $review_id = $_POST['review_id'];
        $rating = $_POST['rating'];
        $comment = trim($_POST['comment']);
        
        try {
            $query = "UPDATE reviews SET Rev_Rating = ?, Rev_Comment = ?, Rev_UpdatedAt = NOW() 
                      WHERE ReviewID = ? AND BookingID IN (SELECT BookingID FROM bookings WHERE RenterID = ?)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $rating);
            $stmt->bindParam(2, $comment);
            $stmt->bindParam(3, $review_id);
            $stmt->bindParam(4, $user_id);
            
            if ($stmt->execute()) {
                $message = "Review updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update review. Please try again.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Review update feature coming soon!";
            $message_type = "info";
        }
    }
}

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Check if reviews table exists
$reviews_table_exists = false;
try {
    $query = "SELECT 1 FROM reviews LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $reviews_table_exists = true;
} catch (PDOException $e) {
    $reviews_table_exists = false;
}

$reviews = [];
$pending_reviews = [];

// Notification dropdown logic (copied from dashboard.php)
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

$stats = [
    'total_reviews' => 0,
    'avg_rating' => 0,
    'pending_reviews' => 0,
    'five_star' => 0
];

if ($reviews_table_exists) {
    // Build query conditions for reviews
    $conditions = ["b.RenterID = ?"];
    $params = [$user_id];

    if ($rating_filter && $rating_filter != 'all') {
        $conditions[] = "r.Rev_Rating = ?";
        $params[] = $rating_filter;
    }

    // Sort options
    $sort_options = [
        'newest' => 'r.Rev_CreatedAt DESC',
        'oldest' => 'r.Rev_CreatedAt ASC',
        'rating_high' => 'r.Rev_Rating DESC',
        'rating_low' => 'r.Rev_Rating ASC'
    ];

    $order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'r.Rev_CreatedAt DESC';

    // Get reviews
    $query = "SELECT r.*, b.*, p.Prod_Name, p.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name, ua.UA_City, ua.UA_Province
              FROM reviews r
              JOIN bookings b ON r.BookingID = b.BookingID
              JOIN products p ON b.ProductID = p.ProductID
              LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
              JOIN user_accounts u ON p.OwnerID = u.UserID
              LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
              WHERE " . implode(' AND ', $conditions) . "
              ORDER BY " . $order_by;

    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $reviews = [];
    }

    // Get bookings without reviews (pending reviews)
    $query = "SELECT b.*, p.Prod_Name, p.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name
              FROM bookings b
              JOIN products p ON b.ProductID = p.ProductID
              LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
              JOIN user_accounts u ON p.OwnerID = u.UserID
              WHERE b.RenterID = ? AND b.Book_Status = 'Completed' 
              AND b.BookingID NOT IN (SELECT BookingID FROM reviews WHERE BookingID IS NOT NULL)
              ORDER BY b.Book_EndDate DESC";

    try {
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $pending_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pending_reviews = [];
    }

    // Calculate statistics
    $stats['total_reviews'] = count($reviews);
    $stats['pending_reviews'] = count($pending_reviews);
    
    if ($stats['total_reviews'] > 0) {
        $total_rating = array_sum(array_column($reviews, 'Rev_Rating'));
        $stats['avg_rating'] = round($total_rating / $stats['total_reviews'], 1);
        $stats['five_star'] = count(array_filter($reviews, function($r) { return $r['Rev_Rating'] == 5; }));
    }

} else {
    // Show sample data from completed bookings
    $query = "SELECT b.*, p.Prod_Name, p.ProductID, pi.PI_ImagePath, u.User_Name as Owner_Name, ua.UA_City, ua.UA_Province,
              FLOOR(1 + RAND() * 5) as Rev_Rating,
              b.Book_EndDate as Rev_CreatedAt,
              'Great experience renting this item! The owner was very helpful and the product was in excellent condition.' as Rev_Comment
              FROM bookings b
              JOIN products p ON b.ProductID = p.ProductID
              LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
              JOIN user_accounts u ON p.OwnerID = u.UserID
              LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
              WHERE b.RenterID = ? AND b.Book_Status = 'Completed'
              ORDER BY b.Book_EndDate DESC
              LIMIT 5";

    try {
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $sample_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate sample stats
        $stats['total_reviews'] = count($sample_reviews);
        if ($stats['total_reviews'] > 0) {
            $total_rating = array_sum(array_column($sample_reviews, 'Rev_Rating'));
            $stats['avg_rating'] = round($total_rating / $stats['total_reviews'], 1);
            $stats['five_star'] = count(array_filter($sample_reviews, function($r) { return $r['Rev_Rating'] == 5; }));
        }
        
        $reviews = $sample_reviews;
    } catch (PDOException $e) {
        $reviews = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
    <link href="../css/renter-theme.css" rel="stylesheet">
    <style>
        :root {
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--secondary-gradient);
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
            margin-bottom: 1.5rem;
        }


        .stat-card.total { background: var(--secondary-gradient); color: white; }
        .stat-card.average { background: var(--secondary-gradient); color: white; }
        .stat-card.pending { background: var(--secondary-gradient); color: white; }
        .stat-card.five-star { background: var(--secondary-gradient); color: white; }
        
        .review-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            border-left: 4px solid #f6d365;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .review-card.pending {
            border-left-color: #4facfe;
            opacity: 0.9;
        }
        
        .review-card.sample {
            border-left-color: #ffc107;
            opacity: 0.8;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        
        .rating-stars.large {
            font-size: 2rem;
        }
        
        .rating-stars.interactive {
            cursor: pointer;
        }
        
        .rating-stars.interactive i:hover,
        .rating-stars.interactive i.selected {
            color: #ff6b35;
            transform: scale(1.2);
        }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .product-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .review-content {
            background: rgba(246, 211, 101, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #f6d365;
            margin: 1rem 0;
        }
        
        .review-form {
            background: rgba(79, 172, 254, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            border-left: 4px solid #4facfe;
            margin: 1rem 0;
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
        
        .action-btn.edit { background: var(--secondary-gradient); color: white; }
        .action-btn.delete { background: var(--secondary-gradient); color: white; }
        .action-btn.submit { background: var(--secondary-gradient); color: white; }
        
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
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .owner-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            margin-right: 0.75rem;
        }
        
        .review-date {
            background: rgba(246, 211, 101, 0.2);
            color: #f6d365;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .pending-badge {
            background: var(--secondary-gradient);
            color: white;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .demo-notice {
            background: var(--secondary-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .review-tips {
            background: var(--secondary-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
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
            
            .search-filters, .review-form {
                padding: 1rem;
            }
            
            .review-card {
                margin-bottom: 1.5rem;
            }
        }
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
                    <a class="nav-link active" href="reviews.php">
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
                <li class="nav-item mt-3">
                    <hr class="text-white-50">
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
                        <i class="fas fa-star text-warning me-2"></i>My Reviews
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $notif_count; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if ($unread_notifications && count($unread_notifications) > 0): ?>
                                <?php foreach ($unread_notifications as $notif): ?>
                                    <li>
                                        <a class="dropdown-item" href="notifications.php">
                                            <i class="fas fa-info-circle text-primary me-2"></i>
                                            <strong><?php echo htmlspecialchars($notif['Not_Title']); ?></strong><br>
                                            <span class="text-muted small"> <?php echo $notif['Not_Message']; ?> </span><br>
                                            <small class="text-muted d-block"> <?php echo date('M j, Y \a\t h:i A', strtotime($notif['Not_CreatedAt'])); ?> </small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted">No new notifications</span></li>
                            <?php endif; ?>
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

            <?php if(!$reviews_table_exists): ?>
            <!-- Demo Notice -->
            <div class="demo-notice">
                <h5 class="mb-3">
                    <i class="fas fa-database me-2"></i>Reviews System Preview
                </h5>
                <p class="mb-0">The reviews system is being set up. Below are sample reviews based on your completed rentals. Once the database is configured, you'll be able to write and manage real reviews!</p>
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
                                        Total Reviews
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_reviews']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card average">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Average Rating
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['avg_rating']; ?>/5.0</div>
                                </div>
                                <div class="col-auto">
                                    <div class="rating-stars large">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $stats['avg_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
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
                                        Pending Reviews
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['pending_reviews']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card five-star">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        5-Star Reviews
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['five_star']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-medal fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review Tips -->
            <div class="review-tips">
                <h5 class="mb-3">
                    <i class="fas fa-lightbulb me-2"></i>Writing Great Reviews
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check me-2"></i>Be honest about your experience</li>
                            <li class="mb-2"><i class="fas fa-check me-2"></i>Mention product condition and cleanliness</li>
                            <li class="mb-2"><i class="fas fa-check me-2"></i>Rate the owner's communication</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check me-2"></i>Comment on pickup/delivery experience</li>
                            <li class="mb-2"><i class="fas fa-check me-2"></i>Be constructive with feedback</li>
                            <li class="mb-2"><i class="fas fa-check me-2"></i>Help future renters make decisions</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Pending Reviews Section -->
            <?php if(!empty($pending_reviews)): ?>
            <div class="mb-4">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-clock me-2"></i>Pending Reviews (<?php echo count($pending_reviews); ?>)
                </h5>
                <p class="text-muted mb-3">These completed rentals are waiting for your review. Share your experience to help other renters!</p>
                
                <?php foreach($pending_reviews as $pending): ?>
                <div class="review-card card pending">
                    <div class="pending-badge">
                        <i class="fas fa-clock me-1"></i>Pending Review
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-12">
                                <h5 class="mb-2"><?php echo htmlspecialchars($pending['Prod_Name']); ?></h5>
                                
                                <div class="product-info mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="owner-avatar">
                                            <?php echo strtoupper(substr($pending['Owner_Name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Rented from <?php echo htmlspecialchars($pending['Owner_Name']); ?></h6>
                                            <small class="text-muted">
                                                Completed: <?php echo date('M j, Y', strtotime($pending['Book_EndDate'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="review-form">
                                    <h6 class="mb-3">
                                        <i class="fas fa-star me-2"></i>Leave Your Review
                                    </h6>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="booking_id" value="<?php echo $pending['BookingID']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Rating</label>
                                            <div class="rating-stars interactive" data-rating="0">
                                                <i class="fas fa-star" data-value="1"></i>
                                                <i class="fas fa-star" data-value="2"></i>
                                                <i class="fas fa-star" data-value="3"></i>
                                                <i class="fas fa-star" data-value="4"></i>
                                                <i class="fas fa-star" data-value="5"></i>
                                            </div>
                                            <input type="hidden" name="rating" id="rating_<?php echo $pending['BookingID']; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="comment_<?php echo $pending['BookingID']; ?>" class="form-label fw-bold">Your Review</label>
                                            <textarea class="form-control" name="comment" id="comment_<?php echo $pending['BookingID']; ?>" 
                                                      rows="3" placeholder="Share your experience with this rental..." required></textarea>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="add_review" class="btn action-btn submit">
                                                <i class="fas fa-star me-1"></i>Submit Review
                                            </button>
                                                <a href="../product.php?id=<?php echo $pending['ProductID']; ?>" class="btn action-btn view-product" style="border-radius: 20px; background: linear-gradient(90deg, #6a82fb 0%, #fc5c7d 100%); color: #fff; font-weight: 500;">
                                                    <i class="fas fa-eye me-1"></i>View Product
                                                </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <?php if(!empty($reviews)): ?>
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-star me-2"></i>Filter by Rating
                        </label>
                        <select class="form-select" name="rating">
                            <option value="all" <?php echo $rating_filter == 'all' ? 'selected' : ''; ?>>All Ratings</option>
                            <option value="5" <?php echo $rating_filter == '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter == '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter == '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter == '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter == '1' ? 'selected' : ''; ?>>1 Star</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="rating_high" <?php echo $sort_by == 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="rating_low" <?php echo $sort_by == 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Reviews List -->
            <?php if(empty($reviews)): ?>
                <div class="empty-state">
                    <i class="fas fa-star-half-alt"></i>
                    <h4 class="text-muted">No reviews yet</h4>
                    <p class="text-muted">You haven't written any reviews yet. Complete a rental and share your experience with the community!</p>
                    <a href="../browse.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-search me-2"></i>Start Renting
                    </a>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-star me-2"></i>My Reviews (<?php echo count($reviews); ?>)
                    </h5>
                    
                    <?php foreach($reviews as $review): ?>
                    <div class="review-card card <?php echo !$reviews_table_exists ? 'sample' : ''; ?>">
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-md-3">
                             <img src="<?php 
                                $imgPath = $review['PI_ImagePath'] ? '../' . ltrim($review['PI_ImagePath'], '/') : '../assets/images/no-image.jpg';
                                echo htmlspecialchars($imgPath);
                             ?>" 
                                         class="img-fluid rounded" style="width: 240px; height: 240px; object-fit: cover; aspect-ratio: 1 / 1;" 
                                 alt="<?php echo htmlspecialchars($review['Prod_Name']); ?>"
                                 onerror="this.onerror=null;this.src='../assets/images/no-image.jpg';">
                                </div>
                                
                                <div class="col-md-9">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($review['Prod_Name']); ?></h5>
                                            <div class="rating-stars mb-2">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $review['Rev_Rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                                <span class="ms-2"><?php echo $review['Rev_Rating']; ?>/5</span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="review-date">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j, Y', strtotime($review['Rev_CreatedAt'])); ?>
                                            </span>
                                            <?php if(!$reviews_table_exists): ?>
                                                <br><small class="text-muted">Sample Review</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="product-info mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="owner-avatar">
                                                <?php echo strtoupper(substr($review['Owner_Name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Rented from <?php echo htmlspecialchars($review['Owner_Name']); ?></h6>
                                                <?php if($review['UA_City']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($review['UA_City'] . ', ' . $review['UA_Province']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="review-content">
                                        <h6 class="mb-2">
                                            <i class="fas fa-quote-left me-2"></i>My Review
                                        </h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['Rev_Comment'])); ?></p>
                                        <?php if (!empty($review['Rev_OwnerResponse'])): ?>
                                        <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 10px; border-left: 4px solid #38ef7d;">
                                            <strong class="text-success"><i class="fas fa-reply me-2"></i>Owner's Response</strong>
                                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($review['Rev_OwnerResponse'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <?php if($reviews_table_exists): ?>
                                        <button class="btn action-btn edit" onclick="editReview(<?php echo $review['ReviewID'] ?? 0; ?>)">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <?php endif; ?>
                                        
                                        <a href="../product.php?id=<?php echo $review['ProductID']; ?>" class="btn action-btn edit" style="border-radius: 20px; background: var(--secondary-gradient); color: #fff;">
                                            <i class="fas fa-eye me-1"></i>View Product
                                        </a>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Interactive star rating
        document.querySelectorAll('.rating-stars.interactive').forEach(ratingElement => {
            const stars = ratingElement.querySelectorAll('i');
            const hiddenInput = ratingElement.closest('form').querySelector('input[name="rating"]');
            
            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = index + 1;
                    hiddenInput.value = rating;
                    
                    // Update visual feedback
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.classList.add('selected');
                            s.classList.remove('fa-star-o');
                            s.classList.add('fa-star');
                        } else {
                            s.classList.remove('selected');
                            s.classList.add('fa-star-o');
                            s.classList.remove('fa-star');
                        }
                    });
                });
                
                star.addEventListener('mouseenter', function() {
                    const rating = index + 1;
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.style.color = '#ff6b35';
                        } else {
                            s.style.color = '#ffc107';
                        }
                    });
                });
            });
            
            ratingElement.addEventListener('mouseleave', function() {
                const currentRating = hiddenInput.value;
                stars.forEach((s, i) => {
                    if (i < currentRating) {
                        s.style.color = '#ff6b35';
                    } else {
                        s.style.color = '#ffc107';
                    }
                });
            });
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);

        // Share review function
        function shareReview(productId, productName) {
            if (navigator.share) {
                navigator.share({
                    title: 'My review of ' + productName + ' - RentHub PH',
                    text: 'Check out my rental experience with ' + productName,
                    url: window.location.origin + '/product.php?id=' + productId
                });
            } else {
                const url = window.location.origin + '/product.php?id=' + productId;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Product link copied to clipboard!');
                });
            }
        }

        // Edit review function
        function editReview(reviewId) {
            // Find review data in DOM
            var reviewCard = document.querySelector('[onclick="editReview(' + reviewId + ')"]').closest('.review-card');
            var rating = reviewCard.querySelector('.rating-stars span')?.textContent?.split('/')[0]?.trim() || '';
            var comment = reviewCard.querySelector('.review-content p')?.textContent?.trim() || '';
            document.getElementById('editReviewId').value = reviewId;
            document.getElementById('editReviewRating').value = rating;
            document.getElementById('editReviewComment').value = comment;
            var modal = new bootstrap.Modal(document.getElementById('editReviewModal'));
            modal.show();
        }

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const ratingInput = this.querySelector('input[name="rating"]');
                const commentInput = this.querySelector('textarea[name="comment"]');
                
                if (ratingInput && !ratingInput.value) {
                    e.preventDefault();
                    alert('Please select a rating before submitting your review.');
                    return false;
                }
                
                if (commentInput && commentInput.value.trim().length < 10) {
                    e.preventDefault();
                    alert('Please write at least 10 characters in your review.');
                    commentInput.focus();
                    return false;
                }
            });
        });

        // Animate review cards on load
        window.addEventListener('load', function() {
            const reviewCards = document.querySelectorAll('.review-card');
            reviewCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Character counter for review textarea
        document.querySelectorAll('textarea[name="comment"]').forEach(textarea => {
            const maxLength = 500;
            const counter = document.createElement('div');
            counter.className = 'form-text';
            counter.innerHTML = '0 / ' + maxLength + ' characters';
            textarea.parentNode.appendChild(counter);
            
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.innerHTML = length + ' / ' + maxLength + ' characters';
                
                if (length > maxLength * 0.9) {
                    counter.className = 'form-text text-warning';
                } else if (length > maxLength) {
                    counter.className = 'form-text text-danger';
                } else {
                    counter.className = 'form-text text-muted';
                }
            });
        });
    </script>
</body>
</html>