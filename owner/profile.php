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

// Get unread notifications for the owner
$notif_count = 0;
$unread_notifications = [];
$notif_query = "SELECT * FROM notifications WHERE UserID = ? AND Not_IsRead = 0 ORDER BY Not_CreatedAt DESC LIMIT 10";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$unread_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($unread_notifications);

// Handle form submissions
if ($_POST) {
    // Handle profile photo upload
    if (isset($_POST['change_photo']) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file = $_FILES['profile_photo'];
        if (!in_array($file['type'], $allowed_types)) {
            $message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            $message_type = "danger";
        } elseif ($file['size'] > $max_size) {
            $message = "File is too large. Maximum size is 2MB.";
            $message_type = "danger";
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = realpath(__DIR__ . '/../uploads/users');
            if (!$upload_dir) {
                $upload_dir = __DIR__ . '/../uploads/users';
            }
            $target_path = $upload_dir . DIRECTORY_SEPARATOR . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Remove old photo if exists and not empty
                if (!empty($user_profile['User_Photo']) && file_exists($upload_dir . DIRECTORY_SEPARATOR . $user_profile['User_Photo'])) {
                    @unlink($upload_dir . DIRECTORY_SEPARATOR . $user_profile['User_Photo']);
                }
                // Update database
                $query = "UPDATE user_accounts SET User_Photo = ? WHERE UserID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $new_filename);
                $stmt->bindParam(2, $user_id);
                if ($stmt->execute()) {
                    $message = "Profile photo updated successfully!";
                    $message_type = "success";
                    // Update $user_profile for immediate UI feedback
                    $user_profile['User_Photo'] = $new_filename;
                } else {
                    $message = "Failed to update profile photo. Please try again.";
                    $message_type = "danger";
                }
            } else {
                $message = "Failed to upload file. Please try again.";
                $message_type = "danger";
            }
        }
    }
    if (isset($_POST['update_profile'])) {
        $user_name = trim($_POST['user_name']);
        $user_email = trim($_POST['user_email']);
        $user_phone = trim($_POST['user_phone']);
        $user_bio = trim($_POST['user_bio']);
        
        // Update user profile
        $query = "UPDATE user_accounts SET User_Name = ?, User_Email = ?, User_Phone = ?, User_Bio = ? WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_name);
        $stmt->bindParam(2, $user_email);
        $stmt->bindParam(3, $user_phone);
        $stmt->bindParam(4, $user_bio);
        $stmt->bindParam(5, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $user_name;
            $message = "Profile updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update profile. Please try again.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $query = "SELECT User_Password FROM user_accounts WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($current_password, $user['User_Password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE user_accounts SET User_Password = ? WHERE UserID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $hashed_password);
                    $stmt->bindParam(2, $user_id);
                    
                    if ($stmt->execute()) {
                        $message = "Password changed successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Failed to change password. Please try again.";
                        $message_type = "danger";
                    }
                } else {
                    $message = "New password must be at least 6 characters long.";
                    $message_type = "danger";
                }
            } else {
                $message = "New passwords do not match.";
                $message_type = "danger";
            }
        } else {
            $message = "Current password is incorrect.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['update_address'])) {
    $street = trim($_POST['street']);
    $barangay = trim($_POST['barangay']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $postal_code = trim($_POST['postal_code']);
    $address_type = isset($_POST['address_type']) ? trim($_POST['address_type']) : null;
    $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if ($is_default) {
            // Remove default from other addresses
            $query = "UPDATE user_addresses SET UA_IsDefault = 0 WHERE UserID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
        }
        
        // Always insert new address (allow multiple addresses per user)
    $query = "INSERT INTO user_addresses (UserID, UA_Street, UA_Barangay, UA_City, UA_Province, UA_ZipCode, UA_AddressType, UA_IsDefault) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->bindParam(2, $street);
    $stmt->bindParam(3, $barangay);
    $stmt->bindParam(4, $city);
    $stmt->bindParam(5, $province);
    $stmt->bindParam(6, $postal_code);
    $stmt->bindParam(7, $address_type);
    $stmt->bindParam(8, $is_default);
        
        if ($stmt->execute()) {
            $message = "Address updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update address. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get user profile data
$query = "SELECT * FROM user_accounts WHERE UserID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$user_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user address
$query = "SELECT * FROM user_addresses WHERE UserID = ? ORDER BY UA_IsDefault DESC, AddressID DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$user_address = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$stats = [];

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ? AND Prod_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total earnings
$query = "SELECT SUM(b.Book_TotalAmount) as total FROM bookings b JOIN products p ON b.ProductID = p.ProductID WHERE p.OwnerID = ? AND b.Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_earnings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Member since
$stats['member_since'] = date('F Y', strtotime($user_profile['User_CreatedAt']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
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
        
        .profile-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(-15deg);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            border: 4px solid rgba(255,255,255,0.3);
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.products { background: var(--primary-gradient); color: white; }
        .stat-card.bookings { background: var(--secondary-gradient); color: white; }
        .stat-card.earnings { background: var(--info-gradient); color: white; }
        
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-left: 4px solid #11998e;
        }
        
        .form-section h5 {
            color: #11998e;
            font-weight: 600;
            margin-bottom: 1.5rem;
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
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .btn-update {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .btn-danger-custom {
            background: var(--accent-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-danger-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
            color: white;
        }
        
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
        
        .profile-tabs {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .tab-btn {
            background: transparent;
            border: none;
            border-radius: 15px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s ease;
            margin: 0.25rem;
        }
        
        .tab-btn.active {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
        }
        
        .verification-badge {
            background: #194bffff;
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .security-info {
            background: rgba(17, 153, 142, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #11998e;
            margin-bottom: 1rem;
        }
        
        .address-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border-left: 4px solid #11998e;
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
            
            .profile-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .form-section {
                padding: 1.5rem;
            }
            
            .profile-tabs {
                padding: 0.5rem;
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
                    <a class="nav-link active" href="profile.php">
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
                        <i class="fas fa-user text-primary me-2"></i>Profile Settings
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span id="notifCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
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
                                            <span class="text-muted small"> <?php echo htmlspecialchars($notif['Not_Message']); ?> </span>
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
                            <div class="profile-avatar mx-auto" id="profileAvatar">
                                <?php if (!empty($user_profile['User_Photo'])): ?>
                                    <img src="../uploads/users/<?php echo htmlspecialchars($user_profile['User_Photo']); ?>" alt="Profile Photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" style="display: none;" onchange="document.getElementById('photoUploadForm').submit();">
                            <button type="button" class="btn btn-light btn-sm" style="border-radius: 20px;" onclick="document.getElementById('profilePhotoInput').click();">
                                <i class="fas fa-camera me-2"></i>Change Photo
                            </button>
                            <input type="hidden" name="change_photo" value="1">
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-2"><?php echo htmlspecialchars($user_profile['User_Name']); ?>
                            <?php if (isset($user_profile['User_IsVerified']) && $user_profile['User_IsVerified'] == 1): ?>
                                <span class="verification-badge">
                                    <i class="fas fa-check-circle me-1"></i>Verified
                                </span>
                            <?php else: ?>
                                <span class="verification-badge bg-warning text-dark" style="border-radius: 8px; padding: 2px 8px; font-size: 1rem;">
                                    <i class="fas fa-hourglass-half me-1"></i>Waiting for Admin Verification
                                </span>
                            <?php endif; ?>
                        </h2>
                        <?php if (isset($user_profile['User_IsVerified']) && $user_profile['User_IsVerified'] == 0): ?>
                        <div class="alert alert-warning mt-2" style="border-radius: 10px;">
                            <i class="fas fa-info-circle me-2"></i>
                            Your account is not yet verified. Please wait for the admin to verify your account before you can access all features.
                        </div>
                        <?php endif; ?>
                        <p class="mb-3 opacity-90"><?php echo htmlspecialchars($user_profile['User_Email']); ?></p>
                        <div class="d-flex flex-wrap gap-3">
                            <div>
                                <i class="fas fa-calendar-alt me-2"></i>
                                <span>Member since <?php echo $stats['member_since']; ?></span>
                            </div>
                            <div>
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <span><?php echo $user_address ? htmlspecialchars($user_address['UA_City'] . ', ' . $user_address['UA_Province']) : 'No address set'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="row">
                            <div class="col-4 text-center">
                                <h4 class="mb-0"><?php echo $stats['total_products']; ?></h4>
                                <small class="opacity-75">Products</small>
                            </div>
                            <div class="col-4 text-center">
                                <h4 class="mb-0"><?php echo $stats['total_bookings']; ?></h4>
                                <small class="opacity-75">Bookings</small>
                            </div>
                            <div class="col-4 text-center">
                                <h4 class="mb-0">₱<?php echo number_format($stats['total_earnings'], 0); ?></h4>
                                <small class="opacity-75">Earned</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <button class="tab-btn active" onclick="showTab('profile')" id="profileTab">
                    <i class="fas fa-user me-2"></i>Profile Information
                </button>
                <button class="tab-btn" onclick="showTab('security')" id="securityTab">
                    <i class="fas fa-shield-alt me-2"></i>Security
                </button>
                <button class="tab-btn" onclick="showTab('address')" id="addressTab">
                    <i class="fas fa-map-marker-alt me-2"></i>Address
                </button>
            </div>

            <!-- Profile Information Tab -->
            <div id="profileContent" class="tab-content">
                <div class="form-section">
                    <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="user_name" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="user_name" name="user_name" 
                                       value="<?php echo htmlspecialchars($user_profile['User_Name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="user_email" class="form-label">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="user_email" name="user_email" 
                                       value="<?php echo htmlspecialchars($user_profile['User_Email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="user_phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="user_phone" name="user_phone" 
                                       value="<?php echo htmlspecialchars($user_profile['User_Phone'] ?? ''); ?>" 
                                       placeholder="+63 9XX XXX XXXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="user_role" class="form-label">Account Type</label>
                                <input type="text" class="form-control" value="Owner/Renter" readonly style="background-color: #f8f9fa;">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="user_bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="user_bio" name="user_bio" rows="4" 
                                      placeholder="Tell others about yourself and your rental business..."><?php echo htmlspecialchars($user_profile['User_Bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="update_profile" class="btn btn-update">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div id="securityContent" class="tab-content" style="display: none;">
                <div class="form-section">
                    <h5><i class="fas fa-shield-alt me-2"></i>Change Password</h5>
                    
                    <div class="security-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Password Security Tips</h6>
                        <ul class="mb-0 small">
                            <li>Use at least 8 characters with a mix of letters, numbers, and symbols</li>
                            <li>Don't use common words or personal information</li>
                            <li>Consider using a password manager</li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="current_password" class="form-label">
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="change_password" class="btn" style="background: var(--primary-gradient); color: white; border-radius: 25px; padding: 0.75rem 2rem; font-weight: 600;">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="form-section">
                    <h5><i class="fas fa-mobile-alt me-2"></i>Two-Factor Authentication</h5>
                    <p class="text-muted">Add an extra layer of security to your account.</p>
                    
                    <div class="d-flex justify-content-between align-items-center p-3" style="background: #f8f9fa; border-radius: 15px;">
                        <div>
                            <h6 class="mb-1">SMS Authentication</h6>
                            <small class="text-muted">Receive codes via text message</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="smsAuth" style="transform: scale(1.2);">
                            <label class="form-check-label" for="smsAuth"></label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Tab -->
            <div id="addressContent" class="tab-content" style="display: none;">
                <div class="form-section">
                    <h5><i class="fas fa-map-marker-alt me-2"></i>Address Information</h5>
                    <div class="alert alert-info mb-3" style="border-radius: 10px;">
                        <i class="fas fa-info-circle me-2"></i>
                        <b>Location Details</b><br>
                        Your address helps owners determine delivery options and rental availability in your area.
                    </div>
                    <div class="mb-2">
                        <h6 class="mb-2"><i class="fas fa-list me-2"></i>Your Addresses</h6>
                    </div>
                    <ul class="list-group mb-4">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE UserID = ? ORDER BY UA_IsDefault DESC, UA_CreatedAt DESC");
                    $stmt->execute([$user_id]);
                    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($addresses) {
                        foreach ($addresses as $address) {
                            $parts = [];
                            if (!empty($address['UA_Street'])) $parts[] = htmlspecialchars($address['UA_Street']);
                            if (!empty($address['UA_Barangay'])) $parts[] = htmlspecialchars($address['UA_Barangay']);
                            if (!empty($address['UA_City'])) $parts[] = htmlspecialchars($address['UA_City']);
                            if (!empty($address['UA_Province'])) $parts[] = htmlspecialchars($address['UA_Province']);
                            if (!empty($address['UA_ZipCode'])) $parts[] = htmlspecialchars($address['UA_ZipCode']);
                            if (!empty($address['UA_AddressType'])) $parts[] = '('.htmlspecialchars($address['UA_AddressType']).')';
                            $address_str = implode(', ', $parts);
                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                            echo $address_str;
                            if (!empty($address['UA_IsDefault'])) {
                                echo ' <span class="badge bg-primary ms-2">Default</span>';
                            }
                            echo '</li>';
                        }
                    } else {
                        echo '<li class="list-group-item">No addresses found.</li>';
                    }
                    ?>
                    </ul>
                    <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addAddressForm" aria-expanded="false" aria-controls="addAddressForm">
                        <i class="fas fa-plus me-1"></i> Add New Address
                    </button>
                    <div class="collapse" id="addAddressForm">
                    <form method="POST" style="margin-top:2rem;">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="street" class="form-label">Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="street" name="street" value="" placeholder="House/Unit No., Street Name" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="barangay" name="barangay" value="" placeholder="Barangay" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" value="" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                <select class="form-select" id="province" name="province" required>
                                    <option value="">Select Province</option>
                                    <?php
                                    $provinces = [
                                        'Abra','Agusan del Norte','Agusan del Sur','Aklan','Albay','Antique','Apayao','Aurora','Basilan','Bataan','Batanes','Batangas','Benguet','Biliran','Bohol','Bukidnon','Bulacan','Cagayan','Camarines Norte','Camarines Sur','Camiguin','Capiz','Catanduanes','Cavite','Cebu','Cotabato','Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental','Dinagat Islands','Eastern Samar','Guimaras','Ifugao','Ilocos Norte','Ilocos Sur','Iloilo','Isabela','Kalinga','La Union','Laguna','Lanao del Norte','Lanao del Sur','Leyte','Maguindanao','Marinduque','Masbate','Metro Manila','Misamis Occidental','Misamis Oriental','Mountain Province','Negros Occidental','Negros Oriental','Northern Samar','Nueva Ecija','Nueva Vizcaya','Occidental Mindoro','Oriental Mindoro','Palawan','Pampanga','Pangasinan','Quezon','Quirino','Rizal','Romblon','Samar','Sarangani','Siquijor','Sorsogon','South Cotabato','Southern Leyte','Sultan Kudarat','Sulu','Surigao del Norte','Surigao del Sur','Tarlac','Tawi-Tawi','Zambales','Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'
                                    ];
                                    foreach ($provinces as $province) {
                                        echo "<option value=\"$province\">$province</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="" placeholder="Postal Code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address_type" class="form-label">Address Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address_type" name="address_type" value="" placeholder="e.g. Home, Work" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                                <label class="form-check-label" for="is_default">Set as default address</label>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="update_address" class="btn btn-update">
                                <i class="fas fa-map-marker-alt me-2"></i>Save Address
                            </button>
                        </div>
                    </form>
                    <script>
                    document.getElementById('showAddAddressForm').addEventListener('click', function() {
                        var form = document.getElementById('addAddressForm');
                        form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
                        this.style.display = 'none';
                    });
                    </script>
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

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + 'Content').style.display = 'block';
            
            // Add active class to selected tab
            document.getElementById(tabName + 'Tab').classList.add('active');
        }

        // Form validation
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
            }
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

        // Phone number formatting
        document.getElementById('user_phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('63')) {
                value = value.substring(2);
            }
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = '+63 ' + value;
                } else if (value.length <= 6) {
                    value = '+63 ' + value.substring(0, 3) + ' ' + value.substring(3);
                } else {
                    value = '+63 ' + value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6, 10);
                }
            }
            e.target.value = value;
        });
    </script>

<script>
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