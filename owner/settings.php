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

// Handle settings updates
if ($_POST) {
    if (isset($_POST['update_notification_settings'])) {
        $email_bookings = isset($_POST['email_bookings']) ? 1 : 0;
        $email_messages = isset($_POST['email_messages']) ? 1 : 0;
        $email_reviews = isset($_POST['email_reviews']) ? 1 : 0;
        $email_marketing = isset($_POST['email_marketing']) ? 1 : 0;
        $sms_bookings = isset($_POST['sms_bookings']) ? 1 : 0;
        $sms_urgent = isset($_POST['sms_urgent']) ? 1 : 0;
        
        // Update or insert notification settings
        $query = "INSERT INTO user_settings (UserID, email_bookings, email_messages, email_reviews, email_marketing, sms_bookings, sms_urgent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  email_bookings = VALUES(email_bookings),
                  email_messages = VALUES(email_messages),
                  email_reviews = VALUES(email_reviews),
                  email_marketing = VALUES(email_marketing),
                  sms_bookings = VALUES(sms_bookings),
                  sms_urgent = VALUES(sms_urgent)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $email_bookings);
        $stmt->bindParam(3, $email_messages);
        $stmt->bindParam(4, $email_reviews);
        $stmt->bindParam(5, $email_marketing);
        $stmt->bindParam(6, $sms_bookings);
        $stmt->bindParam(7, $sms_urgent);
        
        if ($stmt->execute()) {
            $message = "Notification settings updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update notification settings.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['update_business_settings'])) {
        $auto_accept = isset($_POST['auto_accept']) ? 1 : 0;
        $instant_booking = isset($_POST['instant_booking']) ? 1 : 0;
        $require_approval = isset($_POST['require_approval']) ? 1 : 0;
        $min_advance_booking = $_POST['min_advance_booking'];
        $max_advance_booking = $_POST['max_advance_booking'];
        $default_rental_duration = $_POST['default_rental_duration'];
        
        $query = "INSERT INTO business_settings (UserID, auto_accept, instant_booking, require_approval, min_advance_booking, max_advance_booking, default_rental_duration) 
                  VALUES (?, ?, ?, ?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  auto_accept = VALUES(auto_accept),
                  instant_booking = VALUES(instant_booking),
                  require_approval = VALUES(require_approval),
                  min_advance_booking = VALUES(min_advance_booking),
                  max_advance_booking = VALUES(max_advance_booking),
                  default_rental_duration = VALUES(default_rental_duration)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $auto_accept);
        $stmt->bindParam(3, $instant_booking);
        $stmt->bindParam(4, $require_approval);
        $stmt->bindParam(5, $min_advance_booking);
        $stmt->bindParam(6, $max_advance_booking);
        $stmt->bindParam(7, $default_rental_duration);
        
        if ($stmt->execute()) {
            $message = "Business settings updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update business settings.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['update_privacy_settings'])) {
        $profile_visibility = $_POST['profile_visibility'];
        $show_contact = isset($_POST['show_contact']) ? 1 : 0;
        $show_location = isset($_POST['show_location']) ? 1 : 0;
        $allow_reviews = isset($_POST['allow_reviews']) ? 1 : 0;
        $data_sharing = isset($_POST['data_sharing']) ? 1 : 0;
        
        $query = "INSERT INTO privacy_settings (UserID, profile_visibility, show_contact, show_location, allow_reviews, data_sharing) 
                  VALUES (?, ?, ?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  profile_visibility = VALUES(profile_visibility),
                  show_contact = VALUES(show_contact),
                  show_location = VALUES(show_location),
                  allow_reviews = VALUES(allow_reviews),
                  data_sharing = VALUES(data_sharing)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $profile_visibility);
        $stmt->bindParam(3, $show_contact);
        $stmt->bindParam(4, $show_location);
        $stmt->bindParam(5, $allow_reviews);
        $stmt->bindParam(6, $data_sharing);
        
        if ($stmt->execute()) {
            $message = "Privacy settings updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update privacy settings.";
            $message_type = "danger";
        }
    }
}

// Get current settings (with defaults if not set)
$notification_settings = [
    'email_bookings' => 1,
    'email_messages' => 1,
    'email_reviews' => 1,
    'email_marketing' => 0,
    'sms_bookings' => 1,
    'sms_urgent' => 1
];

$business_settings = [
    'auto_accept' => 0,
    'instant_booking' => 0,
    'require_approval' => 1,
    'min_advance_booking' => 1,
    'max_advance_booking' => 30,
    'default_rental_duration' => 1
];

$privacy_settings = [
    'profile_visibility' => 'public',
    'show_contact' => 1,
    'show_location' => 1,
    'allow_reviews' => 1,
    'data_sharing' => 0
];

// Try to get actual settings from database
try {
    $query = "SELECT * FROM user_settings WHERE UserID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $db_notification_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($db_notification_settings) {
        $notification_settings = array_merge($notification_settings, $db_notification_settings);
    }
} catch (PDOException $e) {
    // Table doesn't exist, use defaults
}

try {
    $query = "SELECT * FROM business_settings WHERE UserID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $db_business_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($db_business_settings) {
        $business_settings = array_merge($business_settings, $db_business_settings);
    }
} catch (PDOException $e) {
    // Table doesn't exist, use defaults
}

try {
    $query = "SELECT * FROM privacy_settings WHERE UserID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $db_privacy_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($db_privacy_settings) {
        $privacy_settings = array_merge($privacy_settings, $db_privacy_settings);
    }
} catch (PDOException $e) {
    // Table doesn't exist, use defaults
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - RentHub PH</title>
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
        
        .settings-header {
            background: var(--info-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .settings-header::before {
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
        
        .settings-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-left: 4px solid #11998e;
            position: relative;
        }
        
        .settings-section h5 {
            color: #11998e;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .setting-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .setting-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left-color: #11998e;
        }
        
        .setting-item.active {
            border-left-color: #11998e;
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.05) 100%);
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
        
        .btn-save {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .btn-reset {
            background: var(--secondary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .form-switch .form-check-input {
            background-color: #dee2e6;
            border-color: #dee2e6;
            width: 3rem;
            height: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .form-switch .form-check-input:checked {
            background-color: #11998e;
            border-color: #11998e;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
        }
        
        .form-switch .form-check-input:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.25rem rgba(17, 153, 142, 0.25);
        }
        
        .settings-tabs {
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
        
        .info-card {
            background: rgba(79, 172, 254, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #4facfe;
            margin-bottom: 1rem;
        }
        
        .warning-card {
            background: rgba(246, 211, 101, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #f6d365;
            margin-bottom: 1rem;
        }
        
        .danger-zone {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            border-left: 4px solid #f093fb;
        }
        
        .input-group-text {
            border-radius: 15px 0 0 15px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
        }
        
        .input-group .form-control {
            border-radius: 0 15px 15px 0;
            border-left: none;
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
            
            .settings-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .settings-section {
                padding: 1.5rem;
            }
            
            .settings-tabs {
                padding: 0.5rem;
            }
            
            .setting-item {
                padding: 1rem;
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
                        <i class="fas fa-cog text-info me-2"></i>Settings
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                2
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-calendar-check text-success me-2"></i>New booking request</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-star text-warning me-2"></i>New review received</a></li>
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

            <!-- Settings Header -->
            <div class="settings-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2" style="position: relative; z-index: 2;">
                            <i class="fas fa-cog me-3"></i>Account Settings
                        </h2>
                        <p class="mb-0 opacity-90" style="position: relative; z-index: 2;">
                            Manage your account preferences, notifications, and business settings
                        </p>
                    </div>
                    <div class="col-md-4 text-end" style="position: relative; z-index: 2;">
                        <div class="d-flex flex-column align-items-end">
                            <div class="mb-2">
                                <i class="fas fa-shield-check me-2"></i>
                                <span>Account Secured</span>
                            </div>
                            <small class="opacity-75">Last updated: <?php echo date('M j, Y'); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-btn active" onclick="showTab('notifications')" id="notificationsTab">
                    <i class="fas fa-bell me-2"></i>Notifications
                </button>
                <button class="tab-btn" onclick="showTab('business')" id="businessTab">
                    <i class="fas fa-briefcase me-2"></i>Business
                </button>
                <button class="tab-btn" onclick="showTab('privacy')" id="privacyTab">
                    <i class="fas fa-shield-alt me-2"></i>Privacy
                </button>
                <button class="tab-btn" onclick="showTab('account')" id="accountTab">
                    <i class="fas fa-user-cog me-2"></i>Account
                </button>
            </div>

            <!-- Notifications Tab -->
            <div id="notificationsContent" class="tab-content">
                <div class="settings-section">
                    <h5><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Stay Connected</h6>
                        <p class="mb-0 small">Choose how you want to be notified about important activities on your account.</p>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Email Notifications</h6>
                                
                                <div class="setting-item <?php echo $notification_settings['email_bookings'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">New Bookings</div>
                                            <small class="text-muted">Get notified when someone books your product</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_bookings" 
                                                   id="email_bookings" <?php echo $notification_settings['email_bookings'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $notification_settings['email_messages'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">New Messages</div>
                                            <small class="text-muted">Get notified of new messages from renters</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_messages" 
                                                   id="email_messages" <?php echo $notification_settings['email_messages'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $notification_settings['email_reviews'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Reviews & Ratings</div>
                                            <small class="text-muted">Get notified when you receive new reviews</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_reviews" 
                                                   id="email_reviews" <?php echo $notification_settings['email_reviews'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $notification_settings['email_marketing'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Marketing Updates</div>
                                            <small class="text-muted">Receive tips, promotions, and platform updates</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_marketing" 
                                                   id="email_marketing" <?php echo $notification_settings['email_marketing'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3">SMS Notifications</h6>
                                
                                <div class="setting-item <?php echo $notification_settings['sms_bookings'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Booking Alerts</div>
                                            <small class="text-muted">SMS alerts for urgent booking requests</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="sms_bookings" 
                                                   id="sms_bookings" <?php echo $notification_settings['sms_bookings'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $notification_settings['sms_urgent'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Urgent Updates</div>
                                            <small class="text-muted">Critical account and security updates</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="sms_urgent" 
                                                   id="sms_urgent" <?php echo $notification_settings['sms_urgent'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="warning-card">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>SMS Charges Apply</h6>
                                    <p class="mb-0 small">Standard SMS rates may apply depending on your mobile plan.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-reset" onclick="resetNotifications()">
                                <i class="fas fa-undo me-2"></i>Reset to Default
                            </button>
                            <button type="submit" name="update_notification_settings" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Business Tab -->
            <div id="businessContent" class="tab-content" style="display: none;">
                <div class="settings-section">
                    <h5><i class="fas fa-briefcase me-2"></i>Business Settings</h5>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Optimize Your Business</h6>
                        <p class="mb-0 small">Configure how your rental business operates and how customers can book your products.</p>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Booking Settings</h6>
                                
                                <div class="setting-item <?php echo $business_settings['auto_accept'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Auto-Accept Bookings</div>
                                            <small class="text-muted">Automatically accept booking requests</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="auto_accept" 
                                                   id="auto_accept" <?php echo $business_settings['auto_accept'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $business_settings['instant_booking'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Instant Booking</div>
                                            <small class="text-muted">Allow immediate bookings without approval</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="instant_booking" 
                                                   id="instant_booking" <?php echo $business_settings['instant_booking'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $business_settings['require_approval'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Require Approval</div>
                                            <small class="text-muted">Manually review all booking requests</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="require_approval" 
                                                   id="require_approval" <?php echo $business_settings['require_approval'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3">Rental Parameters</h6>
                                
                                <div class="mb-3">
                                    <label for="min_advance_booking" class="form-label">Minimum Advance Booking</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="min_advance_booking" 
                                               id="min_advance_booking" value="<?php echo $business_settings['min_advance_booking']; ?>" min="0" max="30">
                                        <span class="input-group-text">days</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_advance_booking" class="form-label">Maximum Advance Booking</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="max_advance_booking" 
                                               id="max_advance_booking" value="<?php echo $business_settings['max_advance_booking']; ?>" min="1" max="365">
                                        <span class="input-group-text">days</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="default_rental_duration" class="form-label">Default Rental Duration</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="default_rental_duration" 
                                               id="default_rental_duration" value="<?php echo $business_settings['default_rental_duration']; ?>" min="1" max="30">
                                        <span class="input-group-text">days</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-reset" onclick="resetBusiness()">
                                <i class="fas fa-undo me-2"></i>Reset to Default
                            </button>
                            <button type="submit" name="update_business_settings" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Privacy Tab -->
            <div id="privacyContent" class="tab-content" style="display: none;">
                <div class="settings-section">
                    <h5><i class="fas fa-shield-alt me-2"></i>Privacy & Security</h5>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Control Your Privacy</h6>
                        <p class="mb-0 small">Manage what information is visible to other users and how your data is used.</p>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Profile Visibility</h6>
                                
                                <div class="mb-3">
                                    <label for="profile_visibility" class="form-label">Profile Visibility</label>
                                    <select class="form-select" name="profile_visibility" id="profile_visibility">
                                        <option value="public" <?php echo $privacy_settings['profile_visibility'] == 'public' ? 'selected' : ''; ?>>Public - Anyone can see your profile</option>
                                        <option value="registered" <?php echo $privacy_settings['profile_visibility'] == 'registered' ? 'selected' : ''; ?>>Registered Users Only</option>
                                        <option value="verified" <?php echo $privacy_settings['profile_visibility'] == 'verified' ? 'selected' : ''; ?>>Verified Users Only</option>
                                        <option value="private" <?php echo $privacy_settings['profile_visibility'] == 'private' ? 'selected' : ''; ?>>Private - Hidden from search</option>
                                    </select>
                                </div>
                                
                                <div class="setting-item <?php echo $privacy_settings['show_contact'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Show Contact Information</div>
                                            <small class="text-muted">Display phone number and email to renters</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="show_contact" 
                                                   id="show_contact" <?php echo $privacy_settings['show_contact'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $privacy_settings['show_location'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Show Location</div>
                                            <small class="text-muted">Display your city and province</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="show_location" 
                                                   id="show_location" <?php echo $privacy_settings['show_location'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3">Data & Reviews</h6>
                                
                                <div class="setting-item <?php echo $privacy_settings['allow_reviews'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Allow Reviews</div>
                                            <small class="text-muted">Let customers leave reviews on your profile</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="allow_reviews" 
                                                   id="allow_reviews" <?php echo $privacy_settings['allow_reviews'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="setting-item <?php echo $privacy_settings['data_sharing'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Analytics Data Sharing</div>
                                            <small class="text-muted">Share anonymized data to improve platform</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="data_sharing" 
                                                   id="data_sharing" <?php echo $privacy_settings['data_sharing'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="warning-card">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Privacy Notice</h6>
                                    <p class="mb-0 small">Restricting visibility may reduce your booking opportunities. We recommend keeping your profile public for better reach.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-reset" onclick="resetPrivacy()">
                                <i class="fas fa-undo me-2"></i>Reset to Default
                            </button>
                            <button type="submit" name="update_privacy_settings" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Tab -->
            <div id="accountContent" class="tab-content" style="display: none;">
                <div class="settings-section">
                    <h5><i class="fas fa-user-cog me-2"></i>Account Management</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Account Information</h6>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold">Account Status</div>
                                        <small class="text-success">
                                            <i class="fas fa-check-circle me-1"></i>Active & Verified
                                        </small>
                                    </div>
                                    <a href="profile.php" class="btn btn-outline-primary btn-sm" style="border-radius: 15px;">
                                        Edit Profile
                                    </a>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold">Two-Factor Authentication</div>
                                        <small class="text-muted">Extra security for your account</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" style="border-radius: 15px;">
                                        Setup
                                    </button>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold">Download Data</div>
                                        <small class="text-muted">Export your account data</small>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" style="border-radius: 15px;">
                                        <i class="fas fa-download me-1"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3">Subscription & Billing</h6>
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold">Current Plan</div>
                                        <small class="text-muted">Premium Owner Plan</small>
                                    </div>
                                    <a href="subscription.php" class="btn btn-outline-warning btn-sm" style="border-radius: 15px;">
                                        <i class="fas fa-crown me-1"></i>Manage
                                    </a>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold">Payment Methods</div>
                                        <small class="text-muted">Manage billing information</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" style="border-radius: 15px;">
                                        <i class="fas fa-credit-card me-1"></i>Manage
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h5 class="text-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-danger">Deactivate Account</h6>
                                <p class="small text-muted mb-3">Temporarily disable your account. You can reactivate it anytime.</p>
                                <button class="btn btn-outline-danger btn-sm" style="border-radius: 15px;">
                                    <i class="fas fa-pause me-2"></i>Deactivate Account
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-danger">Delete Account</h6>
                                <p class="small text-muted mb-3">Permanently delete your account and all data. This action cannot be undone.</p>
                                <button class="btn btn-danger btn-sm" style="border-radius: 15px;" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash me-2"></i>Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h5>Are you absolutely sure?</h5>
                        <p class="text-muted">This action will permanently delete your account, all your products, bookings, and earnings data. This cannot be undone.</p>
                    </div>
                    
                    <div class="alert alert-danger" style="border-radius: 15px;">
                        <strong>What will be deleted:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Your profile and account information</li>
                            <li>All your product listings</li>
                            <li>Booking history and earnings</li>
                            <li>Messages and reviews</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="confirmDelete" placeholder="Type DELETE here">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="fas fa-trash me-2"></i>Delete My Account
                    </button>
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

        // Reset functions
        function resetNotifications() {
            if (confirm('Reset all notification settings to default?')) {
                document.getElementById('email_bookings').checked = true;
                document.getElementById('email_messages').checked = true;
                document.getElementById('email_reviews').checked = true;
                document.getElementById('email_marketing').checked = false;
                document.getElementById('sms_bookings').checked = true;
                document.getElementById('sms_urgent').checked = true;
                updateSettingItems();
            }
        }

        function resetBusiness() {
            if (confirm('Reset all business settings to default?')) {
                document.getElementById('auto_accept').checked = false;
                document.getElementById('instant_booking').checked = false;
                document.getElementById('require_approval').checked = true;
                document.getElementById('min_advance_booking').value = 1;
                document.getElementById('max_advance_booking').value = 30;
                document.getElementById('default_rental_duration').value = 1;
                updateSettingItems();
            }
        }

        function resetPrivacy() {
            if (confirm('Reset all privacy settings to default?')) {
                document.getElementById('profile_visibility').value = 'public';
                document.getElementById('show_contact').checked = true;
                document.getElementById('show_location').checked = true;
                document.getElementById('allow_reviews').checked = true;
                document.getElementById('data_sharing').checked = false;
                updateSettingItems();
            }
        }

        // Update setting item visual states
        function updateSettingItems() {
            document.querySelectorAll('.setting-item').forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    if (checkbox.checked) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                }
            });
        }

        // Add event listeners to checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', updateSettingItems);
        });

        // Delete account confirmation
        document.getElementById('confirmDelete')?.addEventListener('input', function() {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            if (this.value === 'DELETE') {
                deleteBtn.disabled = false;
                deleteBtn.classList.remove('btn-danger');
                deleteBtn.classList.add('btn-danger');
            } else {
                deleteBtn.disabled = true;
            }
        });

        document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
            alert('Account deletion feature will be implemented with proper backend handling.');
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

        // Initialize setting item states
        updateSettingItems();
    </script>
</body>
</html>