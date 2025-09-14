<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner role only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Check for success parameter from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Product added successfully!";
    $message_type = 'success';
}

// Check database connection
if (!$conn) {
    die("Database connection failed!");
}

// Get subscription info (simplified)
try {
    $query = "SELECT us.*, sp.Plan_Name, sp.Plan_MaxListings, sp.Plan_FeaturedListings 
              FROM user_subscriptions us 
              JOIN subscription_plans sp ON us.PlanID = sp.PlanID 
              WHERE us.UserID = ? AND us.Sub_Status = 'Active' AND us.Sub_EndDate > NOW()
              ORDER BY us.Sub_EndDate DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $subscription = false;
}

// Get current product count
try {
    $query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ? AND Prod_Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $current_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $current_products = 0;
}

// Check if user can add more products based on subscription
$can_add_product = false;
$max_listings = 0;

if ($subscription) {
    $max_listings = (int)$subscription['Plan_MaxListings'];
    
    // Check if user has unlimited listings (-1) or still has slots available
    if ($max_listings == -1 || $current_products < $max_listings) {
        $can_add_product = true;
    }
} else {
    // No active subscription - check if they have a free plan allowance
    $max_listings = 5; // Default free plan limit
    if ($current_products < $max_listings) {
        $can_add_product = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['prod_name'])) {
    
    // Check if user can still add products before processing
    if (!$can_add_product) {
        $message = "❌ You have reached your maximum listing limit of $max_listings products. Please upgrade your subscription to add more products.";
        $message_type = "danger";
    } else {
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Validate required fields
        $required_fields = ['prod_name', 'category_id', 'prod_description', 'prod_condition', 'prod_rental_price', 'prod_price_type', 'pa_date_from', 'pa_date_to', 'pa_is_available'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Process form data with exact database field names
        $prod_name = trim($_POST['prod_name']);
        $category_id = (int)$_POST['category_id'];
        $prod_description = trim($_POST['prod_description']);
        $prod_brand = !empty($_POST['prod_brand']) ? trim($_POST['prod_brand']) : null;
        $prod_model = !empty($_POST['prod_model']) ? trim($_POST['prod_model']) : null;
        $prod_condition = $_POST['prod_condition'];
        $prod_rental_price = (float)$_POST['prod_rental_price'];
        $prod_price_type = $_POST['prod_price_type'];
        $prod_security_deposit = !empty($_POST['prod_security_deposit']) ? (float)$_POST['prod_security_deposit'] : 0.00;
        $prod_min_duration = !empty($_POST['prod_min_duration']) ? (int)$_POST['prod_min_duration'] : 1;
        $prod_max_duration = !empty($_POST['prod_max_duration']) ? (int)$_POST['prod_max_duration'] : 30;
        
        // Set default values
        $prod_availability = 1;
        $prod_status = 'Active';
        $prod_is_featured = 0;
        $current_timestamp = date('Y-m-d H:i:s');
        
        // 1. INSERT INTO PRODUCTS TABLE
        $insert_product_query = "INSERT INTO products (
            OwnerID, CategoryID, Prod_Name, Prod_Description, Prod_Brand, Prod_Model, 
            Prod_Condition, Prod_RentalPrice, Prod_PriceType, Prod_SecurityDeposit, 
            Prod_MinRentalDuration, Prod_MaxRentalDuration, Prod_Availability, 
            Prod_CreatedAt, Prod_UpdatedAt, Prod_Status, Prod_IsFeatured
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_product_query);
        $product_result = $stmt->execute([
            $user_id,                // OwnerID
            $category_id,            // CategoryID
            $prod_name,              // Prod_Name
            $prod_description,       // Prod_Description
            $prod_brand,             // Prod_Brand
            $prod_model,             // Prod_Model
            $prod_condition,         // Prod_Condition
            $prod_rental_price,      // Prod_RentalPrice
            $prod_price_type,        // Prod_PriceType
            $prod_security_deposit,  // Prod_SecurityDeposit
            $prod_min_duration,      // Prod_MinRentalDuration
            $prod_max_duration,      // Prod_MaxRentalDuration
            $prod_availability,      // Prod_Availability
            $current_timestamp,      // Prod_CreatedAt
            $current_timestamp,      // Prod_UpdatedAt
            $prod_status,            // Prod_Status
            $prod_is_featured        // Prod_IsFeatured
        ]);
        
        if (!$product_result) {
            throw new Exception("Failed to insert product: " . implode(" ", $stmt->errorInfo()));
        }
        
        $product_id = $conn->lastInsertId();
        
        if (!$product_id) {
            throw new Exception("Failed to get product ID");
        }
        
        // 2. INSERT INTO PRODUCT_AVAILABILITY TABLE
        $pa_date_from = $_POST['pa_date_from'];
        $pa_date_to = $_POST['pa_date_to'];
        $pa_is_available = (int)$_POST['pa_is_available'];
        $pa_reason = !empty($_POST['pa_reason']) ? trim($_POST['pa_reason']) : null;
        
        $insert_availability_query = "INSERT INTO product_availability (
            ProductID, PA_DateFrom, PA_DateTo, PA_IsAvailable, PA_Reason, PA_CreatedAt
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_availability_query);
        $availability_result = $stmt->execute([
            $product_id,            // ProductID
            $pa_date_from,          // PA_DateFrom
            $pa_date_to,            // PA_DateTo
            $pa_is_available,       // PA_IsAvailable
            $pa_reason,             // PA_Reason
            $current_timestamp      // PA_CreatedAt
        ]);
        
        if (!$availability_result) {
            throw new Exception("Failed to insert product availability: " . implode(" ", $stmt->errorInfo()));
        }
        
        // 3. HANDLE IMAGE UPLOADS AND INSERT INTO PRODUCT_IMAGES TABLE
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/products/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            foreach ($_FILES['product_images']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['product_images']['error'][$key] === 0) {
                    $file_name = $_FILES['product_images']['name'][$key];
                    $file_size = $_FILES['product_images']['size'][$key];
                    $file_type = $_FILES['product_images']['type'][$key];
                    
                    // Validate file type
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception("Invalid file type for image: $file_name. Only JPG, PNG, and GIF are allowed.");
                    }
                    
                    // Validate file size
                    if ($file_size > $max_file_size) {
                        throw new Exception("File too large: $file_name. Maximum size is 5MB.");
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $unique_filename = 'product_' . $product_id . '_' . ($key + 1) . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_filename;
                    $relative_path = 'uploads/products/' . $unique_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Insert into product_images table
                        $insert_image_query = "INSERT INTO product_images (
                            ProductID, PI_ImagePath, PI_ImageOrder, PI_IsMain, PI_UploadedAt
                        ) VALUES (?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($insert_image_query);
                        $image_result = $stmt->execute([
                            $product_id,                    // ProductID
                            $relative_path,                 // PI_ImagePath
                            $key + 1,                       // PI_ImageOrder (1, 2, 3, etc.)
                            $key === 0 ? 1 : 0,           // PI_IsMain (first image is main)
                            $current_timestamp              // PI_UploadedAt
                        ]);
                        
                        if (!$image_result) {
                            throw new Exception("Failed to insert image record for: $file_name");
                        }
                    } else {
                        throw new Exception("Failed to upload image: $file_name");
                    }
                }
            }
        }
        
        // 4. INSERT ADDRESS INTO USER_ADDRESSES TABLE AND GET ADDRESS ID
        $address_id = null;
        if (!empty($_POST['location_street']) || !empty($_POST['location_city'])) {
            // First, check if this address already exists for this user
            $check_address_query = "SELECT AddressID FROM user_addresses 
                                   WHERE UserID = ? AND UA_Street = ? AND UA_Barangay = ? AND UA_City = ? AND UA_Province = ?";
            $stmt = $conn->prepare($check_address_query);
            $stmt->execute([
                $user_id,
                $_POST['location_street'] ?? '',
                $_POST['location_barangay'] ?? '',
                $_POST['location_city'] ?? '',
                $_POST['location_province'] ?? ''
            ]);
            
            $existing_address = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_address) {
                // Use existing address
                $address_id = $existing_address['AddressID'];
            } else {
                // Create new address in user_addresses table
                $insert_address_query = "INSERT INTO user_addresses (
                    UserID, UA_Street, UA_Barangay, UA_City, UA_Province, UA_CreatedAt
                ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_address_query);
                $address_result = $stmt->execute([
                    $user_id,                                   // UserID
                    $_POST['location_street'] ?? '',           // UA_Street
                    $_POST['location_barangay'] ?? '',         // UA_Barangay
                    $_POST['location_city'] ?? '',             // UA_City
                    $_POST['location_province'] ?? '',         // UA_Province
                    $current_timestamp                          // UA_CreatedAt
                ]);
                
                if (!$address_result) {
                    throw new Exception("Failed to insert user address: " . implode(" ", $stmt->errorInfo()));
                }
                
                $address_id = $conn->lastInsertId();
            }
        }
        
        // 5. INSERT INTO PRODUCT_LOCATIONS TABLE (DELIVERY/PICKUP OPTIONS)
        $insert_location_query = "INSERT INTO product_locations (
            ProductID, AddressID, PL_PickupAvailable, PL_DeliveryAvailable, PL_DeliveryRadius, PL_DeliveryFee
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_location_query);
        $location_result = $stmt->execute([
            $product_id,                                                    // ProductID
            $address_id,                                                    // AddressID (from user_addresses)
            isset($_POST['pickup_available']) ? 1 : 0,                     // PL_PickupAvailable
            isset($_POST['delivery_available']) ? 1 : 0,                   // PL_DeliveryAvailable
            isset($_POST['delivery_radius']) ? $_POST['delivery_radius'] : null,   // PL_DeliveryRadius
            isset($_POST['delivery_fee']) ? $_POST['delivery_fee'] : null          // PL_DeliveryFee
        ]);
        
        if (!$location_result) {
            throw new Exception("Failed to insert product location: " . implode(" ", $stmt->errorInfo()));
        }
        
        // Commit all changes
        $conn->commit();
        
        // Clear form data and redirect with success parameter
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
        
    } catch (PDOException $e) {
        $conn->rollback();
        $error_msg = "Database error: " . $e->getMessage();
        $message = $error_msg;
        $message_type = "danger";
        error_log("PDO Error: " . $e->getMessage());
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error: " . $e->getMessage();
        $message = $error_msg;
        $message_type = "danger";
        error_log("General Error: " . $e->getMessage());
    }
    } // Close the else statement
}

// Get categories for dropdown
try {
    $categories_query = "SELECT CategoryID, Cat_Name FROM categories ORDER BY Cat_Name";
    $stmt = $conn->prepare($categories_query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - RentHub PH</title>
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
        
        .form-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
            background: white;
        }
        
        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #11998e;
        }
        
        .form-section h5 {
            color: #11998e;
            font-weight: 600;
            margin-bottom: 1rem;
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
        
        .required {
            color: #dc3545;
        }
        
        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .progress-indicator {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .progress-step {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .progress-step .step-number {
            background: var(--primary-gradient);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            font-size: 0.85rem;
        }
        
        .progress-step.completed .step-number {
            background: #28a745;
        }
        
        .progress-step.active .step-number {
            background: var(--warning-gradient);
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
        
        .pricing-card {
            background: var(--info-gradient);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .subscription-warning {
            background: var(--warning-gradient);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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
        
        .form-check-input:checked {
            background-color: #11998e;
            border-color: #11998e;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-family: monospace;
            font-size: 0.85rem;
        }
        
        #image_preview .card img {
            border-radius: 8px 8px 0 0;
        }
        
        #image_preview .card {
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        #image_preview .card:hover {
            border-color: #11998e;
            transform: translateY(-2px);
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
            
            .form-section {
                padding: 1rem;
            }
        }
        
        /* Success notification animation */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
            font-weight: 400;
            border-radius: 8px;
            position: relative;
            padding: 1rem 3rem 1rem 3rem;
        }
        
        .alert-success::before {
            content: '✓';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: #0f5132;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .alert-success .btn-close {
            filter: none;
            opacity: 0.8;
        }
        
        .alert-success .btn-close:hover {
            opacity: 1;
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
                    <a class="nav-link active" href="add-product.php">
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
                        <i class="fas fa-plus text-success me-2"></i>Add New Product
                    </h5>
                </div>
                
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <div class="nav-item me-3">
                        <a href="products.php" class="btn btn-outline-secondary" style="border-radius: 25px;">
                            <i class="fas fa-arrow-left me-2"></i>Back to Products
                        </a>
                    </div>
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
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 8px; animation: slideInDown 0.5s ease-out;">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-step active">
                    <div class="step-number">1</div>
                    <div>
                        <strong>Product Details</strong>
                        <div class="text-muted small">Fill in your product information</div>
                    </div>
                </div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div>
                        <strong>Database Tables</strong>
                        <div class="text-muted small">Save to products, product_locations, product_availability</div>
                    </div>
                </div>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <div>
                        <strong>Success</strong>
                        <div class="text-muted small">Product successfully added</div>
                    </div>
                </div>
            </div>

            <!-- Add Product Form -->
            <div class="row">
                <div class="col-lg-8">
                    <?php if (!$can_add_product): ?>
                    <div class="alert alert-warning" style="border-radius: 15px;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Listing Limit Reached!</strong><br>
                        You have reached your maximum listing limit of <strong><?php echo $max_listings; ?> products</strong>. 
                        Current listings: <strong><?php echo $current_products; ?></strong><br>
                        <small>Please upgrade your subscription to add more products or remove existing listings to make room for new ones.</small>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="addProductForm" enctype="multipart/form-data" <?php echo !$can_add_product ? 'style="pointer-events: none; opacity: 0.6;"' : ''; ?>>
                        <div class="form-card">
                            <div class="card-body p-4">
                                <!-- Basic Information -->
                                <div class="form-section">
                                    <h5><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label for="prod_name" class="form-label">
                                                Product Name <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="prod_name" name="prod_name" required 
                                                   placeholder="Enter a descriptive product name" 
                                                   value="<?php echo isset($_POST['prod_name']) ? htmlspecialchars($_POST['prod_name']) : ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="category_id" class="form-label">
                                                Category <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach($categories as $category): ?>
                                                    <option value="<?php echo $category['CategoryID']; ?>" 
                                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['CategoryID']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="prod_description" class="form-label">
                                            Description <span class="required">*</span>
                                        </label>
                                        <textarea class="form-control" id="prod_description" name="prod_description" rows="4" required
                                                  placeholder="Describe your product in detail. Include features, condition, and any special instructions."><?php echo isset($_POST['prod_description']) ? htmlspecialchars($_POST['prod_description']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_brand" class="form-label">Brand</label>
                                            <input type="text" class="form-control" id="prod_brand" name="prod_brand" 
                                                   placeholder="e.g., Canon, Apple, Nike" 
                                                   value="<?php echo isset($_POST['prod_brand']) ? htmlspecialchars($_POST['prod_brand']) : ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_model" class="form-label">Model</label>
                                            <input type="text" class="form-control" id="prod_model" name="prod_model" 
                                                   placeholder="e.g., iPhone 13, PowerShot" 
                                                   value="<?php echo isset($_POST['prod_model']) ? htmlspecialchars($_POST['prod_model']) : ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_condition" class="form-label">
                                                Condition <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="prod_condition" name="prod_condition" required>
                                                <option value="">Select Condition</option>
                                                <option value="New" <?php echo (isset($_POST['prod_condition']) && $_POST['prod_condition'] == 'New') ? 'selected' : ''; ?>>New</option>
                                                <option value="Like New" <?php echo (isset($_POST['prod_condition']) && $_POST['prod_condition'] == 'Like New') ? 'selected' : ''; ?>>Like New</option>
                                                <option value="Good" <?php echo (isset($_POST['prod_condition']) && $_POST['prod_condition'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                                                <option value="Fair" <?php echo (isset($_POST['prod_condition']) && $_POST['prod_condition'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Product Images -->
                                <div class="form-section">
                                    <h5><i class="fas fa-camera me-2"></i>Product Images</h5>
                                    
                                    <div class="mb-3">
                                        <label for="product_images" class="form-label">
                                            Upload Images (Optional for testing)
                                        </label>
                                        <input type="file" class="form-control" id="product_images" name="product_images[]" 
                                               multiple accept="image/*">
                                        <div class="form-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Select multiple images (JPG, PNG). First image will be the main image.
                                        </div>
                                    </div>
                                    
                                    <div id="image_preview" class="row"></div>
                                </div>

                                <!-- Pricing Information -->
                                <div class="form-section">
                                    <h5><i class="fas fa-peso-sign me-2"></i>Pricing & Duration</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="prod_rental_price" class="form-label">
                                                Rental Price <span class="required">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="prod_rental_price" name="prod_rental_price" 
                                                       min="0" step="0.01" required placeholder="0.00" 
                                                       value="<?php echo isset($_POST['prod_rental_price']) ? htmlspecialchars($_POST['prod_rental_price']) : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="prod_price_type" class="form-label">
                                                Price Type <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="prod_price_type" name="prod_price_type" required>
                                                <option value="">Select Price Type</option>
                                                <option value="Per Hour" <?php echo (isset($_POST['prod_price_type']) && $_POST['prod_price_type'] == 'Per Hour') ? 'selected' : ''; ?>>Per Hour</option>
                                                <option value="Per Day" <?php echo (isset($_POST['prod_price_type']) && $_POST['prod_price_type'] == 'Per Day') ? 'selected' : ''; ?>>Per Day</option>
                                                <option value="Per Week" <?php echo (isset($_POST['prod_price_type']) && $_POST['prod_price_type'] == 'Per Week') ? 'selected' : ''; ?>>Per Week</option>
                                                <option value="Per Month" <?php echo (isset($_POST['prod_price_type']) && $_POST['prod_price_type'] == 'Per Month') ? 'selected' : ''; ?>>Per Month</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_security_deposit" class="form-label">Security Deposit</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="prod_security_deposit" name="prod_security_deposit" 
                                                       min="0" step="0.01" placeholder="0.00" 
                                                       value="<?php echo isset($_POST['prod_security_deposit']) ? htmlspecialchars($_POST['prod_security_deposit']) : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_min_duration" class="form-label">Min Duration (days)</label>
                                            <input type="number" class="form-control" id="prod_min_duration" name="prod_min_duration" 
                                                   min="1" placeholder="1" 
                                                   value="<?php echo isset($_POST['prod_min_duration']) ? htmlspecialchars($_POST['prod_min_duration']) : ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_max_duration" class="form-label">Max Duration (days)</label>
                                            <input type="number" class="form-control" id="prod_max_duration" name="prod_max_duration" 
                                                   min="1" placeholder="30" 
                                                   value="<?php echo isset($_POST['prod_max_duration']) ? htmlspecialchars($_POST['prod_max_duration']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Options -->
                                <div class="form-section">
                                    <h5><i class="fas fa-truck me-2"></i>Delivery & Pickup Options</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="pickup_available" name="pickup_available" 
                                                       <?php echo (isset($_POST['pickup_available']) || (!isset($_POST['submit_product']) && $message_type != 'success')) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="pickup_available">
                                                    <strong>Pickup Available</strong>
                                                    <div class="text-muted small">Customers can pick up the item from your location</div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="delivery_available" name="delivery_available" 
                                                       <?php echo isset($_POST['delivery_available']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="delivery_available">
                                                    <strong>Delivery Available</strong>
                                                    <div class="text-muted small">You can deliver the item to customers</div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row" id="delivery_options" style="display: <?php echo isset($_POST['delivery_available']) ? 'block' : 'none'; ?>;">
                                        <div class="col-md-6 mb-3">
                                            <label for="delivery_radius" class="form-label">Delivery Radius (km)</label>
                                            <input type="number" class="form-control" id="delivery_radius" name="delivery_radius" 
                                                   min="0" step="0.1" placeholder="0" 
                                                   value="<?php echo isset($_POST['delivery_radius']) ? htmlspecialchars($_POST['delivery_radius']) : ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="delivery_fee" class="form-label">Delivery Fee</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="delivery_fee" name="delivery_fee" 
                                                       min="0" step="0.01" placeholder="0.00" 
                                                       value="<?php echo isset($_POST['delivery_fee']) ? htmlspecialchars($_POST['delivery_fee']) : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Availability Settings -->
                                <div class="form-section">
                                    <h5><i class="fas fa-calendar me-2"></i>Availability Settings</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="pa_date_from" class="form-label">Available From <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="pa_date_from" name="pa_date_from" required
                                                   value="<?php echo isset($_POST['pa_date_from']) ? $_POST['pa_date_from'] : date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="pa_date_to" class="form-label">Available Until <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="pa_date_to" name="pa_date_to" required
                                                   value="<?php echo isset($_POST['pa_date_to']) ? $_POST['pa_date_to'] : date('Y-m-d', strtotime('+1 year')); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="pa_is_available" class="form-label">Availability Status <span class="required">*</span></label>
                                            <select class="form-select" id="pa_is_available" name="pa_is_available" required>
                                                <option value="1" <?php echo (!isset($_POST['pa_is_available']) || $_POST['pa_is_available'] == '1') ? 'selected' : ''; ?>>Available</option>
                                                <option value="0" <?php echo (isset($_POST['pa_is_available']) && $_POST['pa_is_available'] == '0') ? 'selected' : ''; ?>>Not Available</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="pa_reason" class="form-label">Reason (if not available)</label>
                                            <input type="text" class="form-control" id="pa_reason" name="pa_reason" 
                                                   placeholder="e.g., Under maintenance, Booked, etc."
                                                   value="<?php echo isset($_POST['pa_reason']) ? htmlspecialchars($_POST['pa_reason']) : ''; ?>">
                                            <small class="text-muted">Only fill this if status is "Not Available"</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Location Settings -->
                                <div class="form-section">
                                    <h5><i class="fas fa-map-marker-alt me-2"></i>Location Settings</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="location_street" class="form-label">Street Address</label>
                                            <input type="text" class="form-control" id="location_street" name="location_street" 
                                                   placeholder="Street, Building, House No." 
                                                   value="<?php echo isset($_POST['location_street']) ? htmlspecialchars($_POST['location_street']) : ''; ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="location_barangay" class="form-label">Barangay</label>
                                            <input type="text" class="form-control" id="location_barangay" name="location_barangay" 
                                                   placeholder="Barangay" 
                                                   value="<?php echo isset($_POST['location_barangay']) ? htmlspecialchars($_POST['location_barangay']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="location_city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="location_city" name="location_city" 
                                                   placeholder="City" 
                                                   value="<?php echo isset($_POST['location_city']) ? htmlspecialchars($_POST['location_city']) : ''; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="location_province" class="form-label">Province</label>
                                            <input type="text" class="form-control" id="location_province" name="location_province" 
                                                   placeholder="Province" 
                                                   value="<?php echo isset($_POST['location_province']) ? htmlspecialchars($_POST['location_province']) : ''; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="location_zipcode" class="form-label">Zip Code</label>
                                            <input type="text" class="form-control" id="location_zipcode" name="location_zipcode" 
                                                   placeholder="Zip Code" 
                                                   value="<?php echo isset($_POST['location_zipcode']) ? htmlspecialchars($_POST['location_zipcode']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="products.php" class="btn btn-outline-secondary" style="border-radius: 25px;">
                                        <i class="fas fa-arrow-left me-2"></i>View Products
                                    </a>
                                    <div>
                                        <button type="submit" name="submit_product" class="btn btn-submit" <?php echo !$can_add_product ? 'disabled' : ''; ?>>
                                            <i class="fas fa-plus me-2"></i>
                                            <?php echo $can_add_product ? 'Add Product' : 'Limit Reached'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                                <div class="col-lg-4">

                    <!-- Database Tables Info -->
                    <div class="card mb-4">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-database text-info me-2"></i>Database Tables
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-table text-primary me-2"></i>
                                    <small><strong>products</strong> - Main product data</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-map-marker-alt text-success me-2"></i>
                                    <small><strong>product_locations</strong> - Delivery options</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-calendar text-warning me-2"></i>
                                    <small><strong>product_availability</strong> - Availability dates</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-home text-info me-2"></i>
                                    <small><strong>user_addresses</strong> - Default address</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tips Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-lightbulb text-warning me-2"></i>Tips for Better Listings
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Use clear, descriptive product names</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-camera text-primary me-2"></i>
                                    <small>Add multiple high-quality photos</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-edit text-info me-2"></i>
                                    <small>Write detailed descriptions</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-peso-sign text-warning me-2"></i>
                                    <small>Research competitive pricing</small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="fas fa-shield-alt text-danger me-2"></i>
                                    <small>Set appropriate security deposits</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Guidelines -->
                    <div class="card">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-line text-success me-2"></i>Pricing Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Security Deposit</strong>
                                <p class="text-muted small mb-2">Usually 10-50% of item value</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Rental Duration</strong>
                                <p class="text-muted small mb-2">Consider item type and demand</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Delivery Fee</strong>
                                <p class="text-muted small mb-0">Cover your transportation costs</p>
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

        // Show/hide reason field based on availability status
        document.getElementById('pa_is_available').addEventListener('change', function() {
            const reasonField = document.getElementById('pa_reason');
            const reasonLabel = reasonField.previousElementSibling;
            
            if (this.value == '0') { // Not Available
                reasonField.style.display = 'block';
                reasonLabel.style.display = 'block';
                reasonField.required = true;
            } else { // Available
                reasonField.style.display = 'block'; // Keep visible but not required
                reasonLabel.style.display = 'block';
                reasonField.required = false;
                reasonField.value = ''; // Clear the field
            }
        });

        // Show/hide delivery options
        document.getElementById('delivery_available').addEventListener('change', function() {
            const deliveryOptions = document.getElementById('delivery_options');
            if (this.checked) {
                deliveryOptions.style.display = 'block';
            } else {
                deliveryOptions.style.display = 'none';
                document.getElementById('delivery_radius').value = '';
                document.getElementById('delivery_fee').value = '';
            }
        });

        // Form validation and submission
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            console.log('Form submission started');
            
            // Validate required fields
            const requiredFields = ['prod_name', 'category_id', 'prod_description', 'prod_condition', 'prod_rental_price', 'prod_price_type', 'pa_date_from', 'pa_date_to', 'pa_is_available'];
            let hasErrors = false;
            
            requiredFields.forEach(field => {
                const element = document.getElementById(field);
                if (!element.value.trim()) {
                    element.style.borderColor = '#dc3545';
                    hasErrors = true;
                } else {
                    element.style.borderColor = '#11998e';
                }
            });
            
            // Validate images (optional for testing)
            const imageInput = document.getElementById('product_images');
            if (imageInput.files && imageInput.files.length > 0) {
                imageInput.style.borderColor = '#11998e';
                console.log('Images provided: ' + imageInput.files.length);
            } else {
                console.log('No images provided, but continuing');
            }
            
            if (hasErrors) {
                e.preventDefault();
                alert('Please fill in all required fields marked with * and upload at least one product image.');
                return false;
            }
            
            // Duration validation
            const minDuration = parseInt(document.getElementById('prod_min_duration').value) || 0;
            const maxDuration = parseInt(document.getElementById('prod_max_duration').value) || 0;
            
            if (maxDuration > 0 && minDuration > maxDuration) {
                e.preventDefault();
                alert('Maximum duration cannot be less than minimum duration.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[name="submit_product"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving Product...';
            submitBtn.disabled = true;
            
            console.log('Form validation passed, submitting...');
        });

        // Auto-hide success alerts after 4 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                alert.style.transition = 'all 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 4000);

        // Real-time character count for description
        const descriptionField = document.getElementById('prod_description');
        const maxLength = 1000;
        
        const counter = document.createElement('div');
        counter.className = 'text-muted small mt-1';
        counter.innerHTML = `0/${maxLength} characters`;
        descriptionField.parentNode.appendChild(counter);
        
        descriptionField.addEventListener('input', function() {
            const length = this.value.length;
            counter.innerHTML = `${length}/${maxLength} characters`;
            
            if (length > maxLength * 0.9) {
                counter.className = 'text-warning small mt-1';
            } else {
                counter.className = 'text-muted small mt-1';
            }
        });

        // Price type hints
        document.getElementById('prod_price_type').addEventListener('change', function() {
            const priceField = document.getElementById('prod_rental_price');
            const priceType = this.value;
            
            let placeholder = '0.00';
            switch(priceType) {
                case 'Per Hour':
                    placeholder = 'e.g., 50.00 (hourly rate)';
                    break;
                case 'Per Day':
                    placeholder = 'e.g., 500.00 (daily rate)';
                    break;
                case 'Per Week':
                    placeholder = 'e.g., 2500.00 (weekly rate)';
                    break;
                case 'Per Month':
                    placeholder = 'e.g., 8000.00 (monthly rate)';
                    break;
            }
            
            priceField.placeholder = placeholder;
        });

        // Form field highlighting on focus
        document.querySelectorAll('.form-control, .form-select').forEach(field => {
            field.addEventListener('focus', function() {
                this.style.borderColor = '#11998e';
            });
        });

        // Image preview functionality
        document.getElementById('product_images').addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.getElementById('image_preview');
            previewContainer.innerHTML = '';
            
            if (files.length > 0) {
                for (let i = 0; i < files.length && i < 5; i++) { // Limit to 5 images
                    const file = files[i];
                    
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const col = document.createElement('div');
                            col.className = 'col-md-2 mb-3';
                            
                            col.innerHTML = `
                                <div class="card">
                                    <img src="${e.target.result}" class="card-img-top" style="height: 100px; object-fit: cover;">
                                    <div class="card-body p-2">
                                        <small class="text-muted">${i === 0 ? 'Main Image' : 'Image ' + (i + 1)}</small>
                                    </div>
                                </div>
                            `;
                            
                            previewContainer.appendChild(col);
                        };
                        
                        reader.readAsDataURL(file);
                    }
                }
            }
        });

        // Date validation for availability
        document.getElementById('pa_date_from').addEventListener('change', function() {
            const fromDate = new Date(this.value);
            const toDateInput = document.getElementById('pa_date_to');
            const toDate = new Date(toDateInput.value);
            
            if (fromDate >= toDate) {
                const newToDate = new Date(fromDate);
                newToDate.setDate(newToDate.getDate() + 1);
                toDateInput.value = newToDate.toISOString().split('T')[0];
            }
        });

        document.getElementById('pa_date_to').addEventListener('change', function() {
            const toDate = new Date(this.value);
            const fromDateInput = document.getElementById('pa_date_from');
            const fromDate = new Date(fromDateInput.value);
            
            if (toDate <= fromDate) {
                const newFromDate = new Date(toDate);
                newFromDate.setDate(newFromDate.getDate() - 1);
                fromDateInput.value = newFromDate.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>