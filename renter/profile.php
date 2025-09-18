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

// Check which columns exist in user_accounts table
$user_columns = [];
try {
    $query = "DESCRIBE user_accounts";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($columns as $column) {
        $user_columns[] = $column['Field'];
    }
} catch (PDOException $e) {
    $user_columns = ['UserID', 'User_Name', 'User_Email', 'User_Password', 'User_CreatedAt'];
}

// Check if user_addresses table exists and what columns it has
$address_table_exists = false;
$address_columns = [];
try {
    $query = "DESCRIBE user_addresses";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($columns as $column) {
        $address_columns[] = $column['Field'];
    }
    $address_table_exists = true;
} catch (PDOException $e) {
    $address_table_exists = false;
}

// Handle profile updates
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        $user_name = trim($_POST['user_name'] ?? '');
        $user_email = trim($_POST['user_email'] ?? '');
        $user_phone = trim($_POST['user_phone'] ?? '');
        $user_birthdate = $_POST['user_birthdate'] ?? '';
        $user_gender = $_POST['user_gender'] ?? '';
        $bio = trim($_POST['bio'] ?? '');
        
        // Basic validation
        if (!empty($user_name) && !empty($user_email)) {
            // Build dynamic query based on available columns
            $update_fields = ['User_Name = ?', 'User_Email = ?'];
            $params = [$user_name, $user_email];
            
            if (in_array('User_Phone', $user_columns)) {
                $update_fields[] = 'User_Phone = ?';
                $params[] = $user_phone;
            }
            if (in_array('User_Birthdate', $user_columns)) {
                $update_fields[] = 'User_Birthdate = ?';
                $params[] = $user_birthdate ?: null;
            }
            if (in_array('User_Gender', $user_columns)) {
                $update_fields[] = 'User_Gender = ?';
                $params[] = $user_gender;
            }
            if (in_array('User_Bio', $user_columns)) {
                $update_fields[] = 'User_Bio = ?';
                $params[] = $bio;
            }
            
            $params[] = $user_id;
            
            $query = "UPDATE user_accounts SET " . implode(', ', $update_fields) . " WHERE UserID = ?";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute($params)) {
                $_SESSION['user_name'] = $user_name;
                $message = "Profile updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update profile. Please try again.";
                $message_type = "danger";
            }
        } else {
            $message = "Please fill in all required fields.";
            $message_type = "warning";
        }
    }
    
    if (isset($_POST['update_address']) && $address_table_exists) {
        $address_line = trim($_POST['address_line'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = $_POST['province'] ?? '';
        $postal_code = trim($_POST['postal_code'] ?? '');
        
        if (!empty($address_line) && !empty($city) && !empty($province)) {
            // Check if address exists
            $query = "SELECT * FROM user_addresses WHERE UserID = ?";
            if (in_array('UA_IsDefault', $address_columns)) {
                $query .= " AND UA_IsDefault = 1";
            }
            $query .= " LIMIT 1";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $existing_address = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Build dynamic query based on available columns
            $address_fields = [];
            $params = [];
            
            if (in_array('UA_AddressLine', $address_columns)) {
                $address_fields[] = 'UA_AddressLine';
                $params[] = $address_line;
            }
            if (in_array('UA_City', $address_columns)) {
                $address_fields[] = 'UA_City';
                $params[] = $city;
            }
            if (in_array('UA_Province', $address_columns)) {
                $address_fields[] = 'UA_Province';
                $params[] = $province;
            }
            if (in_array('UA_PostalCode', $address_columns)) {
                $address_fields[] = 'UA_PostalCode';
                $params[] = $postal_code;
            }
            
            if ($existing_address) {
                // Update existing address
                $update_fields = array_map(function($field) { return $field . ' = ?'; }, $address_fields);
                $params[] = $existing_address['AddressID'] ?? $user_id;
                
                $query = "UPDATE user_addresses SET " . implode(', ', $update_fields) . " WHERE ";
                $query .= isset($existing_address['AddressID']) ? "AddressID = ?" : "UserID = ?";
                
                $stmt = $conn->prepare($query);
            } else {
                // Insert new address
                $all_fields = ['UserID'];
                $all_params = [$user_id];
                
                $all_fields = array_merge($all_fields, $address_fields);
                $all_params = array_merge($all_params, $params);
                
                if (in_array('UA_IsDefault', $address_columns)) {
                    $all_fields[] = 'UA_IsDefault';
                    $all_params[] = 1;
                }
                
                $placeholders = str_repeat('?,', count($all_fields));
                $placeholders = rtrim($placeholders, ',');
                
                $query = "INSERT INTO user_addresses (" . implode(', ', $all_fields) . ") VALUES (" . $placeholders . ")";
                $stmt = $conn->prepare($query);
                $params = $all_params;
            }
            
            if ($stmt->execute($params)) {
                $message = "Address updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update address. Please try again.";
                $message_type = "danger";
            }
        } else {
            $message = "Please fill in all required address fields.";
            $message_type = "warning";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password === $confirm_password) {
                // Verify current password
                $query = "SELECT User_Password FROM user_accounts WHERE UserID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['User_Password'])) {
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
                    $message = "Current password is incorrect.";
                    $message_type = "danger";
                }
            } else {
                $message = "New passwords do not match.";
                $message_type = "danger";
            }
        } else {
            $message = "Please fill in all password fields.";
            $message_type = "warning";
        }
    }
}

// Get user information with dynamic query
$user_select_fields = ['u.UserID', 'u.User_Name', 'u.User_Email', 'u.User_CreatedAt'];

// Add optional fields if they exist
$optional_user_fields = ['User_Phone', 'User_Birthdate', 'User_Gender', 'User_Bio'];
foreach($optional_user_fields as $field) {
    if (in_array($field, $user_columns)) {
        $user_select_fields[] = 'u.' . $field;
    }
}

// Build address fields if table exists
$address_select_fields = [];
if ($address_table_exists) {
    $optional_address_fields = ['UA_AddressLine', 'UA_City', 'UA_Province', 'UA_PostalCode'];
    foreach($optional_address_fields as $field) {
        if (in_array($field, $address_columns)) {
            $address_select_fields[] = 'ua.' . $field;
        }
    }
}

$query = "SELECT " . implode(', ', $user_select_fields);
if (!empty($address_select_fields)) {
    $query .= ", " . implode(', ', $address_select_fields);
}
$query .= " FROM user_accounts u";

if ($address_table_exists && !empty($address_select_fields)) {
    $query .= " LEFT JOIN user_addresses ua ON u.UserID = ua.UserID";
    if (in_array('UA_IsDefault', $address_columns)) {
        $query .= " AND ua.UA_IsDefault = 1";
    }
}

$query .= " WHERE u.UserID = ?";

$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$stats = [];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE RenterID = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total spent
$query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE RenterID = ? AND Book_Status IN ('Active', 'Completed')";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$stats['total_spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Reviews written
try {
    $query = "SELECT COUNT(*) as total FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.RenterID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['reviews_written'] = $result ? $result['total'] : 0;
} catch (PDOException $e) {
    $stats['reviews_written'] = 0;
}

// Account age
$stats['member_since'] = date('M Y', strtotime($user_info['User_CreatedAt']));

// Helper function to get field value safely
function getFieldValue($user_info, $field, $default = '') {
    return isset($user_info[$field]) ? $user_info[$field] : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sidebar-scrollbar.css" rel="stylesheet">
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
        
        .profile-header {
            background: var(--secondary-gradient);
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
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 1rem;
            border: 4px solid rgba(255,255,255,0.3);
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
        
        .stat-card.bookings { background: var(--primary-gradient); color: white; }
        .stat-card.spent { background: var(--warning-gradient); color: white; }
        .stat-card.reviews { background: var(--accent-gradient); color: white; }
        .stat-card.member { background: var(--info-gradient); color: white; }
        
        .profile-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-left: 4px solid #667eea;
        }
        
        .profile-section h5 {
            color: #667eea;
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
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
        
        .btn-secondary-custom {
            background: var(--secondary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .navbar {
            border-bottom: 1px solid #e9ecef;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
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
            background: var(--secondary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .verification-badge {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 1rem;
        }
        
        .info-card {
            background: rgba(79, 172, 254, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #4facfe;
            margin-bottom: 1rem;
        }
        
        .demo-notice {
            background: var(--warning-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #fd7e14; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
        
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
            
            .profile-section {
                padding: 1.5rem;
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
                    <a class="nav-link active" href="profile.php">
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
                        <i class="fas fa-user text-secondary me-2"></i>Profile Settings
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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

            <?php if(!$address_table_exists): ?>
            <!-- Demo Notice -->
            <div class="demo-notice">
                <h5 class="mb-3">
                    <i class="fas fa-database me-2"></i>Profile System Setup
                </h5>
                <p class="mb-0">Some profile features are being configured. Address management will be available once the database tables are properly set up!</p>
            </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user_info['User_Name'], 0, 2)); ?>
                        </div>
                        <button class="btn btn-outline-light btn-sm" onclick="uploadPhoto()">
                            <i class="fas fa-camera me-2"></i>Change Photo
                        </button>
                    </div>
                    <div class="col-md-9" style="position: relative; z-index: 2;">
                        <h2 class="mb-2"><?php echo htmlspecialchars($user_info['User_Name']); ?>
                            <span class="verification-badge">
                                <i class="fas fa-check-circle me-1"></i>Verified
                            </span>
                        </h2>
                        <p class="mb-2 opacity-90">
                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user_info['User_Email']); ?>
                        </p>
                        <p class="mb-3 opacity-90">
                            <i class="fas fa-calendar me-2"></i>Member since <?php echo $stats['member_since']; ?>
                        </p>
                        <?php if(getFieldValue($user_info, 'User_Bio')): ?>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-quote-left me-2"></i><?php echo htmlspecialchars(getFieldValue($user_info, 'User_Bio')); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bookings">
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
                    <div class="card stat-card spent">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Spent
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold">₱<?php echo number_format($stats['total_spent'], 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card reviews">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Reviews Written
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['reviews_written']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card member">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Account Status
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">Active</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shield-check fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <button class="tab-btn active" onclick="showTab('personal')" id="personalTab">
                    <i class="fas fa-user me-2"></i>Personal Info
                </button>
                <?php if($address_table_exists): ?>
                <button class="tab-btn" onclick="showTab('address')" id="addressTab">
                    <i class="fas fa-map-marker-alt me-2"></i>Address
                </button>
                <?php endif; ?>
                <button class="tab-btn" onclick="showTab('security')" id="securityTab">
                    <i class="fas fa-shield-alt me-2"></i>Security
                </button>
            </div>

            <!-- Personal Information Tab -->
            <div id="personalContent" class="tab-content">
                <div class="profile-section">
                    <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Profile Information</h6>
                        <p class="mb-0 small">Keep your personal information up to date for better rental experiences and communication.</p>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="user_name" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="user_name" name="user_name" 
                                       value="<?php echo htmlspecialchars($user_info['User_Name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="user_email" class="form-label">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="user_email" name="user_email" 
                                       value="<?php echo htmlspecialchars($user_info['User_Email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <?php if(in_array('User_Phone', $user_columns)): ?>
                            <div class="col-md-6 mb-3">
                                <label for="user_phone" class="form-label">
                                    Phone Number
                                </label>
                                <input type="tel" class="form-control" id="user_phone" name="user_phone" 
                                       value="<?php echo htmlspecialchars(getFieldValue($user_info, 'User_Phone')); ?>" 
                                       placeholder="+63 9XX XXX XXXX">
                            </div>
                            <?php endif; ?>
                            
                            <?php if(in_array('User_Birthdate', $user_columns)): ?>
                            <div class="col-md-6 mb-3">
                                <label for="user_birthdate" class="form-label">
                                    Birth Date
                                </label>
                                <input type="date" class="form-control" id="user_birthdate" name="user_birthdate" 
                                       value="<?php echo getFieldValue($user_info, 'User_Birthdate'); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(in_array('User_Gender', $user_columns)): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="user_gender" class="form-label">
                                    Gender
                                </label>
                                <select class="form-select" id="user_gender" name="user_gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo getFieldValue($user_info, 'User_Gender') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo getFieldValue($user_info, 'User_Gender') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo getFieldValue($user_info, 'User_Gender') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="Prefer not to say" <?php echo getFieldValue($user_info, 'User_Gender') == 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(in_array('User_Bio', $user_columns)): ?>
                        <div class="mb-3">
                            <label for="bio" class="form-label">
                                Bio/About Yourself
                            </label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Tell other users about yourself..."><?php echo htmlspecialchars(getFieldValue($user_info, 'User_Bio')); ?></textarea>
                            <div class="form-text">Maximum 500 characters</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary-custom" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" name="update_profile" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Address Tab -->
            <?php if($address_table_exists): ?>
            <div id="addressContent" class="tab-content" style="display: none;">
                <div class="profile-section">
                    <h5><i class="fas fa-map-marker-alt me-2"></i>Address Information</h5>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Location Details</h6>
                        <p class="mb-0 small">Your address helps owners determine delivery options and rental availability in your area.</p>
                    </div>
                    
                    <form method="POST">
                        <?php if(in_array('UA_AddressLine', $address_columns)): ?>
                        <div class="mb-3">
                            <label for="address_line" class="form-label">
                                Street Address <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="address_line" name="address_line" 
                                   value="<?php echo htmlspecialchars(getFieldValue($user_info, 'UA_AddressLine')); ?>" 
                                   placeholder="House/Unit number, Street name" required>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php if(in_array('UA_City', $address_columns)): ?>
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">
                                    City <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo htmlspecialchars(getFieldValue($user_info, 'UA_City')); ?>" required>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(in_array('UA_Province', $address_columns)): ?>
                            <div class="col-md-6 mb-3">
                                <label for="province" class="form-label">
                                    Province <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="province" name="province" required>
                                    <option value="">Select Province</option>
                                    <option value="Metro Manila" <?php echo getFieldValue($user_info, 'UA_Province') == 'Metro Manila' ? 'selected' : ''; ?>>Metro Manila</option>
                                    <option value="Cavite" <?php echo getFieldValue($user_info, 'UA_Province') == 'Cavite' ? 'selected' : ''; ?>>Cavite</option>
                                    <option value="Laguna" <?php echo getFieldValue($user_info, 'UA_Province') == 'Laguna' ? 'selected' : ''; ?>>Laguna</option>
                                    <option value="Batangas" <?php echo getFieldValue($user_info, 'UA_Province') == 'Batangas' ? 'selected' : ''; ?>>Batangas</option>
                                    <option value="Rizal" <?php echo getFieldValue($user_info, 'UA_Province') == 'Rizal' ? 'selected' : ''; ?>>Rizal</option>
                                    <option value="Bulacan" <?php echo getFieldValue($user_info, 'UA_Province') == 'Bulacan' ? 'selected' : ''; ?>>Bulacan</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(in_array('UA_PostalCode', $address_columns)): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="postal_code" class="form-label">
                                    Postal Code
                                </label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars(getFieldValue($user_info, 'UA_PostalCode')); ?>" 
                                       placeholder="e.g. 1000">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary-custom" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" name="update_address" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Address
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Security Tab -->
            <div id="securityContent" class="tab-content" style="display: none;">
                <div class="profile-section">
                    <h5><i class="fas fa-shield-alt me-2"></i>Account Security</h5>
                    
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2"></i>Password Security</h6>
                        <p class="mb-0 small">Keep your account secure by using a strong password.</p>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">
                                Current Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                New Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="form-text" id="strengthText">Password strength will appear here</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                Confirm New Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary-custom" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" name="change_password" class="btn btn-save">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
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

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let text = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            switch (strength) {
                case 0:
                case 1:
                    strengthBar.classList.add('strength-weak');
                    text = 'Weak password';
                    break;
                case 2:
                    strengthBar.classList.add('strength-fair');
                    text = 'Fair password';
                    break;
                case 3:
                    strengthBar.classList.add('strength-good');
                    text = 'Good password';
                    break;
                case 4:
                case 5:
                    strengthBar.classList.add('strength-strong');
                    text = 'Strong password';
                    break;
            }
            
            strengthText.textContent = text;
        });

        // Upload photo function
        function uploadPhoto() {
            alert('Photo upload feature will be implemented with proper file handling.');
        }

        // Reset form function
        function resetForm() {
            if (confirm('Reset all changes? This will restore the original values.')) {
                location.reload();
            }
        }

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

        // Character counter for bio
        document.getElementById('bio')?.addEventListener('input', function() {
            const maxLength = 500;
            const currentLength = this.value.length;
            
            let counter = this.parentNode.querySelector('.char-counter');
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'form-text char-counter';
                this.parentNode.appendChild(counter);
            }
            
            counter.textContent = currentLength + ' / ' + maxLength + ' characters';
            
            if (currentLength > maxLength * 0.9) {
                counter.className = 'form-text char-counter text-warning';
            } else if (currentLength > maxLength) {
                counter.className = 'form-text char-counter text-danger';
            } else {
                counter.className = 'form-text char-counter text-muted';
            }
        });

        // Phone number formatting
        document.getElementById('user_phone')?.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.startsWith('63')) {
                value = '+' + value;
            } else if (value.startsWith('9') && value.length === 10) {
                value = '+63' + value;
            }
            this.value = value;
        });
    </script>
</body>
</html>