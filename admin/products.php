<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle product actions
if ($_POST) {
    if (isset($_POST['update_product_status'])) {
        $product_id = $_POST['product_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $query = "UPDATE products SET Prod_Status = ?, Prod_UpdatedAt = NOW() WHERE ProductID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $new_status);
            $stmt->bindParam(2, $product_id);
            
            if ($stmt->execute()) {
                $message = "Product status updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update product status.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error updating product status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['delete_product'])) {
        $product_id = $_POST['product_id'];
        
        try {
            $query = "SELECT COUNT(*) as count FROM bookings WHERE ProductID = ? AND Book_Status IN ('Active', 'Pending')";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $product_id);
            $stmt->execute();
            $booking_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking_result['count'] > 0) {
                $message = "Cannot delete product. It has " . $booking_result['count'] . " active booking(s).";
                $message_type = "warning";
            } else {
                $query = "UPDATE products SET Prod_Status = 'Deleted', Prod_UpdatedAt = NOW() WHERE ProductID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $product_id);
                
                if ($stmt->execute()) {
                    $message = "Product deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to delete product.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Error deleting product: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['feature_product'])) {
        $product_id = $_POST['product_id'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        try {
            $query = "UPDATE products SET Prod_IsFeatured = ?, Prod_UpdatedAt = NOW() WHERE ProductID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $is_featured);
            $stmt->bindParam(2, $product_id);
            
            if ($stmt->execute()) {
                $message = $is_featured ? "Product featured successfully!" : "Product unfeatured successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update product feature status.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error updating product feature status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$owner_filter = isset($_GET['owner']) ? $_GET['owner'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["p.Prod_Status != 'Deleted'"];
$params = [];

if ($category_filter && $category_filter != 'all') {
    $conditions[] = "p.CategoryID = ?";
    $params[] = $category_filter;
}

if ($status_filter && $status_filter != 'all') {
    $conditions[] = "p.Prod_Status = ?";
    $params[] = ucfirst($status_filter);
}

if ($owner_filter && $owner_filter != 'all') {
    $conditions[] = "p.OwnerID = ?";
    $params[] = $owner_filter;
}

if ($search_term) {
    $conditions[] = "(p.Prod_Name LIKE ? OR p.Prod_Description LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Sort options
$sort_options = [
    'newest' => 'p.Prod_CreatedAt DESC',
    'oldest' => 'p.Prod_CreatedAt ASC',
    'name_asc' => 'p.Prod_Name ASC',
    'name_desc' => 'p.Prod_Name DESC',
    'price_low' => 'p.Prod_RentalPrice ASC',
    'price_high' => 'p.Prod_RentalPrice DESC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'p.Prod_CreatedAt DESC';

// Check if tables exist
$products_table_exists = false;
$categories_table_exists = false;

try {
    $query = "SELECT 1 FROM products LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $products_table_exists = true;
} catch (PDOException $e) {
    $products_table_exists = false;
}

try {
    $query = "SELECT 1 FROM categories LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories_table_exists = true;
} catch (PDOException $e) {
    $categories_table_exists = false;
}

// Get products with owner and category information
$products = [];
if ($products_table_exists) {
    try {
    $base_query = "SELECT p.*, 
                u.User_Name as Owner_Name, u.User_Email as Owner_Email,
                " . ($categories_table_exists ? "c.Cat_Name as Category_Name," : "'Unknown' as Category_Name,") . "
                (SELECT COUNT(*) FROM bookings WHERE ProductID = p.ProductID) as total_bookings,
                (SELECT COUNT(*) FROM bookings WHERE ProductID = p.ProductID AND Book_Status = 'Active') as active_bookings,
                (SELECT SUM(Book_TotalAmount) FROM bookings WHERE ProductID = p.ProductID AND Book_Status IN ('Active', 'Completed')) as total_revenue,
                (SELECT PI_ImagePath FROM product_images WHERE ProductID = p.ProductID AND PI_IsMain = 1 LIMIT 1) as Main_Image
            FROM products p 
            LEFT JOIN user_accounts u ON p.OwnerID = u.UserID";
        
        if ($categories_table_exists) {
            $base_query .= " LEFT JOIN categories c ON p.CategoryID = c.CategoryID";
        }
        
        $base_query .= " WHERE " . implode(' AND ', $conditions) . " ORDER BY " . $order_by;

        $stmt = $conn->prepare($base_query);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $products = [];
    }
}

// Get statistics
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'pending_products' => 0,
    'featured_products' => 0,
    'total_revenue' => 0
];

if ($products_table_exists) {
    try {
        $query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status != 'Deleted'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Active'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Pending'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['pending_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $query = "SELECT COUNT(*) as total FROM products WHERE Prod_IsFeatured = 1 AND Prod_Status = 'Active'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats['featured_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        try {
            $query = "SELECT SUM(Book_TotalAmount) as total FROM bookings WHERE Book_Status IN ('Active', 'Completed')";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            $stats['total_revenue'] = 0;
        }
    } catch (PDOException $e) {
        // Keep default stats if queries fail
    }
}

// Get categories for filter
$categories = [];
if ($categories_table_exists) {
    try {
        $query = "SELECT c.*, pc.Parent_Name 
                FROM categories c 
                LEFT JOIN parent_categories pc ON c.ParentCategoryID = pc.ParentCategoryID
                ORDER BY pc.Parent_Name ASC, c.Cat_Name ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $categories = [];
    }
}

// Get owners for filter
$owners = [];
try {
    $query = "SELECT DISTINCT u.UserID, u.User_Name, u.User_Email
            FROM user_accounts u
            WHERE u.User_Role = 3 AND u.User_Status = 'Active'
            ORDER BY u.User_Name ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $owners = [];
}

// Recent product activities
$recent_activities = [];
if ($products_table_exists) {
    try {
        $query = "SELECT 'Product Listed' as activity, 
                        CONCAT(p.Prod_Name, ' by ', u.User_Name) as details, 
                        p.Prod_CreatedAt as created_at 
                FROM products p
                LEFT JOIN user_accounts u ON p.OwnerID = u.UserID
                WHERE p.Prod_Status != 'Deleted'
                ORDER BY p.Prod_CreatedAt DESC 
                LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_activities = [];
    }
}

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'active': return 'success';
        case 'pending': return 'warning';
        case 'inactive': return 'secondary';
        case 'suspended': return 'danger';
        default: return 'secondary';
    }
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - RentHub PH Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Fix dropdown menu being cut off */
        .dropdown-menu {
            z-index: 2000 !important;
            max-height: 400px; /* Optional: Limit height with scroll if too many items */
            overflow-y: auto; /* Add scroll if needed */
        }
        .card, .card-body {
            overflow: visible !important;
        }
        .product-card {
            overflow: visible !important; /* Ensure dropdown is not clipped by card */
        }

        /* Sidebar Styling */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #343a40;
            transition: all 0.3s;
            z-index: 1000;
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

        /* Main Content Styling */
        .main-content {
            margin-left: 250px;
            padding-top: 70px; /* Space for top navbar */
            min-height: 100vh;
            transition: all 0.3s;
        }

        /* Top Navbar Styling */
        .navbar {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 1000;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Stat and Product Cards */
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .stat-card.total { border-left-color: #007bff; }
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.featured { border-left-color: #dc3545; }
        
        .product-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: visible !important; /* Ensure dropdown is not clipped */
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-image-placeholder {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 2rem;
        }
        
        .owner-avatar {
            width: 35px;
            height: 35px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #333;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(255, 215, 0, 0.3);
        }
        
        .product-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 1rem;
        }
        
        .product-stat-item {
            text-align: center;
            padding: 0.25rem;
        }
        
        .product-stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: #007bff;
        }
        
        .product-stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-action {
            padding: 0.4rem 0.8rem;
            margin: 0 0.1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .category-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .price-tag {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .main-content {
                margin-left: 0;
                padding-top: 60px; /* Adjust for smaller screens */
            }
            .navbar {
                left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a class="nav-link active" href="products.php">
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
                <a class="nav-link" href="settings.php">
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
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h5 class="mb-0">Product Management</h5>
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

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card total">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Products
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card active">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Products
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['active_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Approval
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['pending_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card featured">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Featured Products
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['featured_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-search me-1"></i>Search Products</label>
                        <input type="text" class="form-control" name="search" 
                            value="<?php echo htmlspecialchars($search_term); ?>" 
                            placeholder="Search by name or description...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-tags me-1"></i>Category</label>
                        <select class="form-select" name="category">
                            <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['CategoryID']; ?>" <?php echo $category_filter == $category['CategoryID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                <?php if($category['Parent_Name']): ?>
                                    (<?php echo htmlspecialchars($category['Parent_Name']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-flag me-1"></i>Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-user me-1"></i>Owner</label>
                        <select class="form-select" name="owner">
                            <option value="all" <?php echo $owner_filter == 'all' ? 'selected' : ''; ?>>All Owners</option>
                            <?php foreach($owners as $owner): ?>
                            <option value="<?php echo $owner['UserID']; ?>" <?php echo $owner_filter == $owner['UserID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($owner['User_Name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-sort me-1"></i>Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Products List and Actions -->
            <div class="row">
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-box me-2"></i>Products Management
                                <span class="badge bg-primary ms-2"><?php echo count($products); ?> Found</span>
                            </h5>
                            <div>
                                <button class="btn btn-success btn-sm" onclick="exportProducts()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                                <button class="btn btn-info btn-sm" onclick="bulkActions()">
                                    <i class="fas fa-layer-group me-1"></i>Bulk Actions
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($products)): ?>
                                <?php foreach($products as $product): ?>
                                <div class="product-card position-relative">
                                    <?php if($product['Prod_IsFeatured']): ?>
                                    <div class="featured-badge">
                                        <i class="fas fa-star me-1"></i>FEATURED
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <?php 
                                                    $imgPath = $product['Main_Image'] ?? '';
                                                    if (!empty($imgPath)) {
                                                        // Always use relative path from admin to uploads
                                                        if (strpos($imgPath, 'uploads/products/') === 0) {
                                                            $imgPath = '../' . $imgPath;
                                                        }
                                                    }
                                                ?>
                                                <?php if(!empty($imgPath) && file_exists(__DIR__ . '/../' . $product['Main_Image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($imgPath); ?>" 
                                                        alt="Product Image" style="width: 130px; height: 130px; object-fit: cover; border-radius: 12px; background: #f8f9fa; display: block;" loading="lazy">
                                                <?php else: ?>
                                                    <div class="product-image-placeholder" style="width: 130px; height: 130px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 12px;">
                                                        <i class="fas fa-image fa-4x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-5">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($product['Prod_Status']); ?> status-badge me-2">
                                                        <?php echo $product['Prod_Status']; ?>
                                                    </span>
                                                    <?php if($product['Category_Name']): ?>
                                                    <span class="category-badge">
                                                        <?php echo htmlspecialchars($product['Category_Name']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <h6 class="mb-2"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                                                <p class="text-muted mb-2 small">
                                                    <?php echo strlen($product['Prod_Description']) > 100 ? 
                                                        htmlspecialchars(substr($product['Prod_Description'], 0, 100)) . '...' : 
                                                        htmlspecialchars($product['Prod_Description']); ?>
                                                </p>
                                                
                                                <div class="d-flex align-items-center">
                                                    <div class="owner-avatar">
                                                        <?php echo strtoupper(substr($product['Owner_Name'] ?? 'UN', 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <small class="fw-bold"><?php echo htmlspecialchars($product['Owner_Name'] ?? 'Unknown Owner'); ?></small><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($product['Owner_Email'] ?? ''); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <div class="price-tag">
                                                    <?php echo formatCurrency($product['Prod_RentalPrice']); ?>
                                                    <small class="d-block" style="font-size: 0.75rem; opacity: 0.9;">per day</small>
                                                </div>
                                            
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">Product ID:</small><br>
                                                    <small class="fw-bold">#<?php echo str_pad($product['ProductID'], 6, '0', STR_PAD_LEFT); ?></small>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <div class="product-stats">
                                                    <div class="row">
                                                        <div class="col-4 product-stat-item">
                                                            <div class="product-stat-value"><?php echo number_format($product['total_bookings'] ?? 0); ?></div>
                                                            <div class="product-stat-label">Bookings</div>
                                                        </div>
                                                        <div class="col-4 product-stat-item">
                                                            <div class="product-stat-value"><?php echo number_format($product['active_bookings'] ?? 0); ?></div>
                                                            <div class="product-stat-label">Active</div>
                                                        </div>
                                                        <div class="col-4 product-stat-item">
                                                            <div class="product-stat-value">₱<?php echo number_format($product['total_revenue'] ?? 0, 0); ?></div>
                                                            <div class="product-stat-label">Revenue</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex flex-wrap gap-1 mt-3">
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <input type="hidden" name="new_status" value="Active">
                                                            <button type="submit" name="update_product_status" class="btn btn-outline-success btn-sm" title="Approve"><i class="fas fa-check"></i></button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <input type="hidden" name="new_status" value="Suspended">
                                                            <button type="submit" name="update_product_status" class="btn btn-outline-danger btn-sm" title="Reject"><i class="fas fa-ban"></i></button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <input type="hidden" name="new_status" value="Inactive">
                                                            <button type="submit" name="update_product_status" class="btn btn-outline-secondary btn-sm" title="Deactivate"><i class="fas fa-pause"></i></button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <input type="hidden" name="new_status" value="Suspended">
                                                            <button type="submit" name="update_product_status" class="btn btn-outline-warning btn-sm" title="Suspend"><i class="fas fa-exclamation-triangle"></i></button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <input type="hidden" name="new_status" value="Active">
                                                            <button type="submit" name="update_product_status" class="btn btn-outline-success btn-sm" title="Activate"><i class="fas fa-play"></i></button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <?php if($product['Prod_IsFeatured']): ?>
                                                                <button type="submit" name="feature_product" class="btn btn-outline-info btn-sm" title="Remove Featured"><i class="fas fa-star-half-alt"></i></button>
                                                            <?php else: ?>
                                                                <input type="hidden" name="is_featured" value="1">
                                                                <button type="submit" name="feature_product" class="btn btn-outline-warning btn-sm" title="Make Featured"><i class="fas fa-star"></i></button>
                                                            <?php endif; ?>
                                                        </form>
                                                        <button class="btn btn-outline-primary btn-sm" title="View Details" onclick="viewProductDetails(<?php echo $product['ProductID']; ?>)"><i class="fas fa-eye"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm" title="Edit Product" onclick="editProduct(<?php echo $product['ProductID']; ?>)"><i class="fas fa-edit"></i></button>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                                                            <button type="submit" name="delete_product" class="btn btn-outline-danger btn-sm" title="Delete Product" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="text-muted small">
                                                        <i class="fas fa-calendar-plus me-1"></i>
                                                        Listed: <?php echo date('M j, Y', strtotime($product['Prod_CreatedAt'])); ?>
                                                        <?php if($product['Prod_UpdatedAt'] && $product['Prod_UpdatedAt'] != $product['Prod_CreatedAt']): ?>
                                                        | <i class="fas fa-edit me-1"></i>
                                                        Updated: <?php echo date('M j, Y', strtotime($product['Prod_UpdatedAt'])); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php if(isset($product['Prod_Location']) && !empty($product['Prod_Location'])): ?>
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($product['Prod_Location']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if(count($products) > 10): ?>
                                    <nav aria-label="Products pagination" class="mt-4">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item disabled">
                                                <span class="page-link">Previous</span>
                                            </li>
                                            <li class="page-item active">
                                                <span class="page-link">1</span>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="#">2</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="#">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-box-open"></i>
                                        <h5 class="text-muted">No Products Found</h5>
                                        <p class="text-muted">
                                            <?php if($search_term || $category_filter != 'all' || $status_filter != 'all' || $owner_filter != 'all'): ?>
                                                No products match your current filters. Try adjusting your search criteria.
                                            <?php else: ?>
                                                No products have been listed yet. Products will appear here when owners start listing items for rent.
                                            <?php endif; ?>
                                        </p>
                                        <?php if($search_term || $category_filter != 'all' || $status_filter != 'all' || $owner_filter != 'all'): ?>
                                        <a href="products.php" class="btn btn-primary">
                                            <i class="fas fa-times me-2"></i>Clear Filters
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success" onclick="exportProducts()">
                                        <i class="fas fa-download"></i> Export Products
                                    </button>
                                    <button class="btn btn-warning" onclick="bulkApprove()">
                                        <i class="fas fa-check-double"></i> Bulk Approve
                                    </button>
                                    <a href="categories.php" class="btn btn-info">
                                        <i class="fas fa-tags"></i> Manage Categories
                                    </a>
                                    <a href="users.php" class="btn btn-primary">
                                        <i class="fas fa-users"></i> View Owners
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <?php if(!empty($recent_activities)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach($recent_activities as $activity): ?>
                                        <div class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong class="small"><?php echo htmlspecialchars($activity['activity']); ?>:</strong>
                                                    <p class="mb-0 small text-muted">
                                                        <?php echo htmlspecialchars($activity['details']); ?>
                                                    </p>
                                                </div>
                                                <small class="text-muted"><?php echo date('M j', strtotime($activity['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0 small">No recent activities</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Products Table</span>
                                    <span class="badge bg-<?php echo $products_table_exists ? 'success' : 'danger'; ?>">
                                        <?php echo $products_table_exists ? 'Ready' : 'Not Found'; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Categories Table</span>
                                    <span class="badge bg-<?php echo $categories_table_exists ? 'success' : 'warning'; ?>">
                                        <?php echo $categories_table_exists ? 'Ready' : 'Optional'; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small">Total Revenue</span>
                                    <strong class="text-success"><?php echo formatCurrency($stats['total_revenue']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offcanvas Sidebar (for mobile) -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="sidebarLabel">RentHub PH Admin</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
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
                        <a class="nav-link active" href="products.php">
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
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-money-bill"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
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
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Export products function
            function exportProducts() {
                alert('Export products functionality will be implemented. This will generate a CSV/Excel file with all product data.');
            }

            // Bulk actions function
            function bulkActions() {
                alert('Bulk actions functionality will be implemented. This will allow selecting multiple products for batch operations.');
            }

            // Bulk approve function
            function bulkApprove() {
                if (confirm('Approve all pending products? This will make them visible to renters.')) {
                    alert('Bulk approve functionality will be implemented.');
                }
            }

            // View product details
            function viewProductDetails(productId) {
                alert('Product details view will be implemented. Product ID: ' + productId);
            }

            // Edit product
            function editProduct(productId) {
                alert('Product editing functionality will be implemented. Product ID: ' + productId);
            }

            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-success')) {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                });
            }, 5000);

            // Animate cards on load
            window.addEventListener('load', function() {
                const cards = document.querySelectorAll('.product-card, .stat-card');
                cards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            });

            // Initialize tooltips if any
            document.addEventListener('DOMContentLoaded', function() {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>
    </body>
</html>