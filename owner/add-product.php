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

// Check subscription status and limits
$query = "SELECT us.*, sp.Plan_Name, sp.Plan_MaxListings, sp.Plan_FeaturedListings 
          FROM user_subscriptions us 
          JOIN subscription_plans sp ON us.PlanID = sp.PlanID 
          WHERE us.UserID = ? AND us.Sub_Status = 'Active' AND us.Sub_EndDate > NOW()
          ORDER BY us.Sub_EndDate DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current product count
$query = "SELECT COUNT(*) as total FROM products WHERE OwnerID = ? AND Prod_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$current_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Check if user can add more products - user needs active subscription AND must be within limits
$can_add_product = $subscription && $current_products < $subscription['Plan_MaxListings'];

// Handle form submission
if ($_POST && isset($_POST['submit_product'])) {
    if (!$subscription) {
        $message = "You need an active subscription to add products. Please choose a subscription plan first.";
        $message_type = "warning";
    } elseif (!$can_add_product) {
        $message = "You have reached your subscription limit of " . $subscription['Plan_MaxListings'] . " products. Please upgrade your plan to add more products.";
        $message_type = "warning";
    } else {
        // Validate and process form data
        $prod_name = trim($_POST['prod_name']);
        $category_id = $_POST['category_id'];
        $prod_description = trim($_POST['prod_description']);
        $prod_brand = trim($_POST['prod_brand']);
        $prod_model = trim($_POST['prod_model']);
        $prod_condition = $_POST['prod_condition'];
        $prod_rental_price = $_POST['prod_rental_price'];
        $prod_price_type = $_POST['prod_price_type'];
        $prod_security_deposit = $_POST['prod_security_deposit'];
        $prod_min_duration = $_POST['prod_min_duration'];
        $prod_max_duration = $_POST['prod_max_duration'];
        $pickup_available = isset($_POST['pickup_available']) ? 1 : 0;
        $delivery_available = isset($_POST['delivery_available']) ? 1 : 0;
        $delivery_radius = $_POST['delivery_radius'] ?? 0;
        $delivery_fee = $_POST['delivery_fee'] ?? 0;
        
        // Insert product
        $query = "INSERT INTO products (OwnerID, CategoryID, Prod_Name, Prod_Description, Prod_Brand, Prod_Model, Prod_Condition, Prod_RentalPrice, Prod_PriceType, Prod_SecurityDeposit, Prod_MinRentalDuration, Prod_MaxRentalDuration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $category_id);
        $stmt->bindParam(3, $prod_name);
        $stmt->bindParam(4, $prod_description);
        $stmt->bindParam(5, $prod_brand);
        $stmt->bindParam(6, $prod_model);
        $stmt->bindParam(7, $prod_condition);
        $stmt->bindParam(8, $prod_rental_price);
        $stmt->bindParam(9, $prod_price_type);
        $stmt->bindParam(10, $prod_security_deposit);
        $stmt->bindParam(11, $prod_min_duration);
        $stmt->bindParam(12, $prod_max_duration);
        
        if ($stmt->execute()) {
            $product_id = $conn->lastInsertId();
            
            // Get user's default address for product location
            $query = "SELECT AddressID FROM user_addresses WHERE UserID = ? AND UA_IsDefault = 1 LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
            $address = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($address) {
                // Insert product location
                $query = "INSERT INTO product_locations (ProductID, AddressID, PL_PickupAvailable, PL_DeliveryAvailable, PL_DeliveryRadius, PL_DeliveryFee) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $product_id);
                $stmt->bindParam(2, $address['AddressID']);
                $stmt->bindParam(3, $pickup_available);
                $stmt->bindParam(4, $delivery_available);
                $stmt->bindParam(5, $delivery_radius);
                $stmt->bindParam(6, $delivery_fee);
                $stmt->execute();
            }
            
            $message = "Product added successfully! You can now upload images and manage your listing.";
            $message_type = "success";
            
            // Redirect to edit product page to add images
            header("Location: edit-product.php?id=" . $product_id . "&new=1");
            exit();
        } else {
            $message = "Failed to add product. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get categories
$query = "SELECT * FROM categories ORDER BY Cat_Name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        
        .btn-draft {
            background: var(--secondary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-draft:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
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
        
        /* Custom file upload styling */
        .file-upload-zone {
            border: 2px dashed #11998e;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-zone:hover {
            border-color: #0d6846;
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        }
        
        .file-upload-zone i {
            font-size: 3rem;
            color: #11998e;
            margin-bottom: 1rem;
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
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 15px;">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'info-circle')); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Subscription Status -->
            <?php if(!$subscription): ?>
            <div class="subscription-warning">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>Subscription Required
                        </h5>
                        <p class="mb-0 opacity-90">You need an active subscription to add products. Choose a plan to start listing your items.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="subscription.php" class="btn btn-light" style="border-radius: 25px;">
                            <i class="fas fa-crown me-2"></i>Choose Plan
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif(!$can_add_product): ?>
            <div class="subscription-warning">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2">
                            <i class="fas fa-limit me-2"></i>Product Limit Reached
                        </h5>
                        <p class="mb-0 opacity-90">You've reached your plan limit of <?php echo $subscription['Plan_MaxListings']; ?> products. Upgrade to add more.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="subscription.php" class="btn btn-light" style="border-radius: 25px;">
                            <i class="fas fa-arrow-up me-2"></i>Upgrade Plan
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-step active">
                    <div class="step-number">1</div>
                    <div>
                        <strong>Product Details</strong>
                        <div class="text-muted small">Basic information about your product</div>
                    </div>
                </div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div>
                        <strong>Upload Images</strong>
                        <div class="text-muted small">Add photos after creating the product</div>
                    </div>
                </div>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <div>
                        <strong>Publish</strong>
                        <div class="text-muted small">Make your product available for rent</div>
                    </div>
                </div>
            </div>

            <!-- Subscription Info -->
            <div class="pricing-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-1">
                            <i class="fas fa-crown me-2"></i><?php echo htmlspecialchars($subscription['Plan_Name']); ?> Plan
                        </h6>
                        <p class="mb-0 opacity-90">
                            <?php echo $current_products; ?>/<?php echo $subscription['Plan_MaxListings']; ?> products used • 
                            <?php echo $subscription['Plan_FeaturedListings']; ?> featured listings available
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="text-white">
                            <strong><?php echo $subscription['Plan_MaxListings'] - $current_products; ?></strong> slots remaining
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Product Form -->
            <div class="row">
                <div class="col-lg-8">
                    <form method="POST" id="addProductForm">
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
                                                   placeholder="Enter a descriptive product name">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="category_id" class="form-label">
                                                Category <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach($categories as $category): ?>
                                                    <option value="<?php echo $category['CategoryID']; ?>">
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
                                                  placeholder="Describe your product in detail. Include features, condition, and any special instructions."></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_brand" class="form-label">Brand</label>
                                            <input type="text" class="form-control" id="prod_brand" name="prod_brand" 
                                                   placeholder="e.g., Canon, Apple, Nike">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_model" class="form-label">Model</label>
                                            <input type="text" class="form-control" id="prod_model" name="prod_model" 
                                                   placeholder="e.g., iPhone 13, PowerShot">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_condition" class="form-label">
                                                Condition <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="prod_condition" name="prod_condition" required>
                                                <option value="">Select Condition</option>
                                                <option value="New">New</option>
                                                <option value="Like New">Like New</option>
                                                <option value="Good">Good</option>
                                                <option value="Fair">Fair</option>
                                            </select>
                                        </div>
                                    </div>
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
                                                       min="0" step="0.01" required placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="prod_price_type" class="form-label">
                                                Price Type <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="prod_price_type" name="prod_price_type" required>
                                                <option value="">Select Price Type</option>
                                                <option value="Per Hour">Per Hour</option>
                                                <option value="Per Day">Per Day</option>
                                                <option value="Per Week">Per Week</option>
                                                <option value="Per Month">Per Month</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_security_deposit" class="form-label">Security Deposit</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="prod_security_deposit" name="prod_security_deposit" 
                                                       min="0" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_min_duration" class="form-label">Min Duration</label>
                                            <input type="number" class="form-control" id="prod_min_duration" name="prod_min_duration" 
                                                   min="1" placeholder="1">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="prod_max_duration" class="form-label">Max Duration</label>
                                            <input type="number" class="form-control" id="prod_max_duration" name="prod_max_duration" 
                                                   min="1" placeholder="30">
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Options -->
                                <div class="form-section">
                                    <h5><i class="fas fa-truck me-2"></i>Delivery & Pickup Options</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="pickup_available" name="pickup_available" checked>
                                                <label class="form-check-label" for="pickup_available">
                                                    <strong>Pickup Available</strong>
                                                    <div class="text-muted small">Customers can pick up the item from your location</div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="delivery_available" name="delivery_available">
                                                <label class="form-check-label" for="delivery_available">
                                                    <strong>Delivery Available</strong>
                                                    <div class="text-muted small">You can deliver the item to customers</div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row" id="delivery_options" style="display: none;">
                                        <div class="col-md-6 mb-3">
                                            <label for="delivery_radius" class="form-label">Delivery Radius (km)</label>
                                            <input type="number" class="form-control" id="delivery_radius" name="delivery_radius" 
                                                   min="0" step="0.1" placeholder="0">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="delivery_fee" class="form-label">Delivery Fee</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="delivery_fee" name="delivery_fee" 
                                                       min="0" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="products.php" class="btn btn-outline-secondary" style="border-radius: 25px;">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <div>
                                        <button type="submit" name="submit_product" class="btn btn-submit">
                                            <i class="fas fa-plus me-2"></i>Add Product
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
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
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
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

        // Form validation
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const minDuration = parseInt(document.getElementById('prod_min_duration').value) || 0;
            const maxDuration = parseInt(document.getElementById('prod_max_duration').value) || 0;
            
            if (maxDuration > 0 && minDuration > maxDuration) {
                e.preventDefault();
                alert('Maximum duration cannot be less than minimum duration.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[name="submit_product"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding Product...';
            submitBtn.disabled = true;
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

        // Real-time character count for description
        const descriptionField = document.getElementById('prod_description');
        const maxLength = 1000;
        
        // Create character counter
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
    </script>
</body>
</html>