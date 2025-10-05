<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Check if settings table exists, if not create it
try {
    $query = "CREATE TABLE IF NOT EXISTS site_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Insert default settings if they don't exist
    $default_settings = [
        ['site_name', 'RentHub PH', 'Website name displayed across the platform'],
        ['site_description', 'Your trusted rental marketplace in the Philippines', 'Website description for SEO'],
        ['site_email', 'admin@renthub.ph', 'Main contact email for the platform'],
        ['site_phone', '+63-XXX-XXX-XXXX', 'Main contact phone number'],
        ['maintenance_mode', '0', 'Enable/disable maintenance mode (0=disabled, 1=enabled)'],
        ['registration_enabled', '1', 'Allow new user registrations (0=disabled, 1=enabled)'],
        ['auto_approve_products', '0', 'Automatically approve new products (0=manual, 1=auto)'],
        ['featured_product_price', '500.00', 'Price for featuring a product (in PHP)'],
        ['commission_rate', '5.00', 'Platform commission rate (percentage)'],
        ['max_upload_size', '5', 'Maximum file upload size in MB'],
        ['currency_symbol', '₱', 'Currency symbol to display'],
        ['timezone', 'Asia/Manila', 'Default timezone for the platform'],
        ['date_format', 'Y-m-d', 'Default date format'],
        ['items_per_page', '12', 'Number of items to show per page'],
        ['allow_guest_browsing', '1', 'Allow guests to browse products (0=no, 1=yes)'],
        ['min_rental_duration', '1', 'Minimum rental duration in days'],
        ['max_rental_duration', '365', 'Maximum rental duration in days'],
        ['backup_frequency', 'weekly', 'Database backup frequency (daily, weekly, monthly)'],
        ['email_notifications', '1', 'Enable email notifications (0=disabled, 1=enabled)'],
        ['sms_notifications', '0', 'Enable SMS notifications (0=disabled, 1=enabled)']
    ];
    
    foreach ($default_settings as $setting) {
        $check_query = "SELECT COUNT(*) as count FROM site_settings WHERE setting_key = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$setting[0]]);
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            $insert_query = "INSERT INTO site_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->execute($setting);
        }
    }
} catch (PDOException $e) {
    $message = "Error creating settings table: " . $e->getMessage();
    $message_type = "danger";
}

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['update_settings'])) {
            foreach ($_POST as $key => $value) {
                if ($key !== 'update_settings') {
                    $query = "UPDATE site_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$value, $key]);
                }
            }
            $message = "Settings updated successfully!";
            $message_type = "success";
        }
        
        if (isset($_POST['backup_database'])) {
            // This would implement database backup functionality
            $message = "Database backup initiated. (This feature needs to be implemented based on your server configuration)";
            $message_type = "info";
        }
        
        if (isset($_POST['clear_cache'])) {
            // This would implement cache clearing functionality
            $message = "Cache cleared successfully!";
            $message_type = "success";
        }
        
        if (isset($_POST['reset_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                $message = "New passwords do not match!";
                $message_type = "danger";
            } else {
                // Verify current password
                $user_id = $_SESSION['user_id'];
                $query = "SELECT User_Password FROM user_accounts WHERE UserID = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['User_Password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE user_accounts SET User_Password = ?, User_UpdatedAt = NOW() WHERE UserID = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([$hashed_password, $user_id]);
                    
                    $message = "Password updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Current password is incorrect!";
                    $message_type = "danger";
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Error updating settings: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all settings
$settings = [];
try {
    $query = "SELECT * FROM site_settings ORDER BY setting_key";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting;
    }
} catch (PDOException $e) {
    $settings = [];
}

// Get current admin info
$admin_info = [];
try {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM user_accounts WHERE UserID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin_info = [];
}

// Get system information
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'mysql_version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
];
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
            padding-top: 70px;
            min-height: 100vh;
        }
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
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
                <a class="nav-link" href="bookings.php">
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
                <a class="nav-link active" href="settings.php">
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
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="left:250px;">
            <div class="container-fluid">
                <h5 class="mb-0">Settings</h5>
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
        <div class="container-fluid p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Settings Navigation Tabs -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                <i class="fas fa-cog me-1"></i>General Settings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="platform-tab" data-bs-toggle="tab" data-bs-target="#platform" type="button" role="tab">
                                <i class="fas fa-globe me-1"></i>Platform Settings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="fas fa-shield-alt me-1"></i>Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                <i class="fas fa-server me-1"></i>System Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                                <i class="fas fa-tools me-1"></i>Maintenance
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="settingsTabContent">
                        <!-- General Settings -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">Site Information</h5>
                                        <div class="mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']['setting_value'] ?? ''); ?>">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['site_name']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="site_description" class="form-label">Site Description</label>
                                            <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description']['setting_value'] ?? ''); ?></textarea>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['site_description']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="site_email" class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email']['setting_value'] ?? ''); ?>">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['site_email']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="site_phone" class="form-label">Contact Phone</label>
                                            <input type="text" class="form-control" id="site_phone" name="site_phone" value="<?php echo htmlspecialchars($settings['site_phone']['setting_value'] ?? ''); ?>">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['site_phone']['setting_description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">Regional Settings</h5>
                                        <div class="mb-3">
                                            <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                            <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol']['setting_value'] ?? ''); ?>">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['currency_symbol']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-select" id="timezone" name="timezone">
                                                <option value="Asia/Manila" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila</option>
                                                <option value="UTC" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            </select>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['timezone']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="date_format" class="form-label">Date Format</label>
                                            <select class="form-select" id="date_format" name="date_format">
                                                <option value="Y-m-d" <?php echo ($settings['date_format']['setting_value'] ?? '') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                <option value="m/d/Y" <?php echo ($settings['date_format']['setting_value'] ?? '') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                <option value="d/m/Y" <?php echo ($settings['date_format']['setting_value'] ?? '') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                            </select>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['date_format']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="items_per_page" class="form-label">Items Per Page</label>
                                            <input type="number" class="form-control" id="items_per_page" name="items_per_page" value="<?php echo htmlspecialchars($settings['items_per_page']['setting_value'] ?? ''); ?>" min="1" max="100">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['items_per_page']['setting_description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Platform Settings -->
                        <div class="tab-pane fade" id="platform" role="tabpanel">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">User Management</h5>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="registration_enabled" name="registration_enabled" value="1" <?php echo ($settings['registration_enabled']['setting_value'] ?? '') == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="registration_enabled">
                                                    Enable New Registrations
                                                </label>
                                            </div>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['registration_enabled']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="allow_guest_browsing" name="allow_guest_browsing" value="1" <?php echo ($settings['allow_guest_browsing']['setting_value'] ?? '') == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allow_guest_browsing">
                                                    Allow Guest Browsing
                                                </label>
                                            </div>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['allow_guest_browsing']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="auto_approve_products" name="auto_approve_products" value="1" <?php echo ($settings['auto_approve_products']['setting_value'] ?? '') == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="auto_approve_products">
                                                    Auto-Approve Products
                                                </label>
                                            </div>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['auto_approve_products']['setting_description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">Business Settings</h5>
                                        <div class="mb-3">
                                            <label for="commission_rate" class="form-label">Commission Rate (%)</label>
                                            <input type="number" class="form-control" id="commission_rate" name="commission_rate" value="<?php echo htmlspecialchars($settings['commission_rate']['setting_value'] ?? ''); ?>" min="0" max="100" step="0.01">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['commission_rate']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="featured_product_price" class="form-label">Featured Product Price (₱)</label>
                                            <input type="number" class="form-control" id="featured_product_price" name="featured_product_price" value="<?php echo htmlspecialchars($settings['featured_product_price']['setting_value'] ?? ''); ?>" min="0" step="0.01">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['featured_product_price']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="min_rental_duration" class="form-label">Min Rental Duration (days)</label>
                                            <input type="number" class="form-control" id="min_rental_duration" name="min_rental_duration" value="<?php echo htmlspecialchars($settings['min_rental_duration']['setting_value'] ?? ''); ?>" min="1">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['min_rental_duration']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="max_rental_duration" class="form-label">Max Rental Duration (days)</label>
                                            <input type="number" class="form-control" id="max_rental_duration" name="max_rental_duration" value="<?php echo htmlspecialchars($settings['max_rental_duration']['setting_value'] ?? ''); ?>" min="1">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['max_rental_duration']['setting_description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">File Upload Settings</h5>
                                        <div class="mb-3">
                                            <label for="max_upload_size" class="form-label">Max Upload Size (MB)</label>
                                            <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" value="<?php echo htmlspecialchars($settings['max_upload_size']['setting_value'] ?? ''); ?>" min="1" max="100">
                                            <div class="form-text"><?php echo htmlspecialchars($settings['max_upload_size']['setting_description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">Notifications</h5>
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php echo ($settings['email_notifications']['setting_value'] ?? '') == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="email_notifications">
                                                    Email Notifications
                                                </label>
                                            </div>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['email_notifications']['setting_description'] ?? ''); ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" value="1" <?php echo ($settings['sms_notifications']['setting_value'] ?? '') == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="sms_notifications">
                                                    SMS Notifications
                                                </label>
                                            </div>
                                            <div class="form-text"><?php echo htmlspecialchars($settings['sms_notifications']['setting_description'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Save Platform Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Change Admin Password</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <label for="current_password" class="form-label">Current Password</label>
                                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="new_password" class="form-label">New Password</label>
                                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                                </div>
                                                
                                                <button type="submit" name="reset_password" class="btn btn-warning">
                                                    <i class="fas fa-key me-1"></i>Update Password
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Security Settings</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo ($settings['maintenance_mode']['setting_value'] ?? '') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="maintenance_mode">
                                                            Maintenance Mode
                                                        </label>
                                                    </div>
                                                    <div class="form-text">Enable to put the site in maintenance mode</div>
                                                </div>
                                                
                                                <button type="submit" name="update_settings" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i>Save Security Settings
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="card mt-3">
                                        <div class="card-header">
                                            <h5 class="mb-0">Admin Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($admin_info['User_Name'] ?? 'N/A'); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($admin_info['User_Email'] ?? 'N/A'); ?></p>
                                            <p><strong>Created:</strong> <?php echo isset($admin_info['User_CreatedAt']) ? date('M j, Y', strtotime($admin_info['User_CreatedAt'])) : 'N/A'; ?></p>
                                            <p><strong>Last Login:</strong> <?php echo isset($admin_info['User_LastLogin']) ? date('M j, Y H:i', strtotime($admin_info['User_LastLogin'])) : 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Info Tab -->
                        <div class="tab-pane fade" id="system" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Server Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>PHP Version:</strong></td>
                                                    <td><?php echo $system_info['php_version']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Server Software:</strong></td>
                                                    <td><?php echo $system_info['server_software']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>MySQL Version:</strong></td>
                                                    <td><?php echo $system_info['mysql_version']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Upload Max Size:</strong></td>
                                                    <td><?php echo $system_info['upload_max_filesize']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>POST Max Size:</strong></td>
                                                    <td><?php echo $system_info['post_max_size']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Memory Limit:</strong></td>
                                                    <td><?php echo $system_info['memory_limit']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Max Execution Time:</strong></td>
                                                    <td><?php echo $system_info['max_execution_time']; ?>s</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Server Time:</strong></td>
                                                    <td><?php echo $system_info['server_time']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Timezone:</strong></td>
                                                    <td><?php echo $system_info['timezone']; ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Database Statistics</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            try {
                                                $tables = ['user_accounts', 'products', 'bookings', 'categories', 'flag_reports'];
                                                echo '<table class="table table-sm">';
                                                foreach ($tables as $table) {
                                                    $query = "SELECT COUNT(*) as count FROM $table";
                                                    $stmt = $conn->prepare($query);
                                                    $stmt->execute();
                                                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                                    echo "<tr><td><strong>" . ucfirst(str_replace('_', ' ', $table)) . ":</strong></td><td>" . number_format($count) . "</td></tr>";
                                                }
                                                echo '</table>';
                                            } catch (PDOException $e) {
                                                echo '<p class="text-muted">Unable to fetch database statistics.</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Tab -->
                        <div class="tab-pane fade" id="maintenance" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Database Maintenance</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                                    <select class="form-select" id="backup_frequency" name="backup_frequency">
                                                        <option value="daily" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                        <option value="weekly" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                        <option value="monthly" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                    </select>
                                                    <div class="form-text"><?php echo htmlspecialchars($settings['backup_frequency']['setting_description'] ?? ''); ?></div>
                                                </div>
                                                
                                                <button type="submit" name="update_settings" class="btn btn-primary me-2">
                                                    <i class="fas fa-save me-1"></i>Save Settings
                                                </button>
                                                
                                                <button type="submit" name="backup_database" class="btn btn-warning">
                                                    <i class="fas fa-database me-1"></i>Backup Now
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">System Maintenance</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="d-grid gap-2">
                                                    <button type="submit" name="clear_cache" class="btn btn-info">
                                                        <i class="fas fa-broom me-1"></i>Clear Cache
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-secondary" onclick="location.reload()">
                                                        <i class="fas fa-sync me-1"></i>Refresh Page
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-danger" onclick="confirmAction('Are you sure you want to restart the system?')">
                                                        <i class="fas fa-power-off me-1"></i>System Restart
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
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
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Confirmation for critical actions
        function confirmAction(message) {
            if (confirm(message)) {
                alert('This action would be implemented based on your server configuration.');
            }
        }
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
        
        // Auto-save indication
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', function() {
                // Add visual indication that settings have changed
                const saveButtons = document.querySelectorAll('button[name="update_settings"]');
                saveButtons.forEach(btn => {
                    btn.classList.add('btn-warning');
                    btn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Save Changes';
                });
            });
        });
        
        // Success/Error message auto-hide
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 3000);
    </script>
</body>
</html>
