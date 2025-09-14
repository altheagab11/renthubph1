<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle parent category actions
if ($_POST) {
    if (isset($_POST['create_parent_category'])) {
        $parent_name = trim($_POST['parent_name']);
        $parent_description = trim($_POST['parent_description']);
        $parent_icon = $_POST['parent_icon'] ?? 'fas fa-folder';
        $parent_color = $_POST['parent_color'] ?? '#007bff';
        
        try {
            $query = "INSERT INTO parent_categories (Parent_Name, Parent_Description, Parent_Icon, Parent_Color, Parent_CreatedAt) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $parent_name);
            $stmt->bindParam(2, $parent_description);
            $stmt->bindParam(3, $parent_icon);
            $stmt->bindParam(4, $parent_color);
            
            if ($stmt->execute()) {
                $message = "Parent category created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create parent category.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error creating parent category: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['create_category'])) {
        $cat_name = trim($_POST['cat_name']);
        $cat_description = trim($_POST['cat_description']);
        $parent_category_id = $_POST['parent_category_id'];
        
        try {
            $query = "INSERT INTO categories (Cat_Name, Cat_Description, ParentCategoryID, Cat_CreatedAt) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $cat_name);
            $stmt->bindParam(2, $cat_description);
            $stmt->bindParam(3, $parent_category_id);
            
            if ($stmt->execute()) {
                $message = "Category created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create category.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error creating category: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['update_parent_category'])) {
        $parent_id = $_POST['parent_id'];
        $parent_name = trim($_POST['parent_name']);
        $parent_description = trim($_POST['parent_description']);
        $parent_icon = $_POST['parent_icon'];
        $parent_color = $_POST['parent_color'];
        $parent_active = isset($_POST['parent_active']) ? 1 : 0;
        
        try {
            $query = "UPDATE parent_categories SET Parent_Name = ?, Parent_Description = ?, Parent_Icon = ?, Parent_Color = ?, Parent_IsActive = ?, Parent_UpdatedAt = NOW() WHERE ParentCategoryID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $parent_name);
            $stmt->bindParam(2, $parent_description);
            $stmt->bindParam(3, $parent_icon);
            $stmt->bindParam(4, $parent_color);
            $stmt->bindParam(5, $parent_active);
            $stmt->bindParam(6, $parent_id);
            
            if ($stmt->execute()) {
                $message = "Parent category updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update parent category.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error updating parent category: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['update_category'])) {
        $category_id = $_POST['category_id'];
        $cat_name = trim($_POST['cat_name']);
        $cat_description = trim($_POST['cat_description']);
        $parent_category_id = $_POST['parent_category_id'];
        
        try {
            $query = "UPDATE categories SET Cat_Name = ?, Cat_Description = ?, ParentCategoryID = ? WHERE CategoryID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $cat_name);
            $stmt->bindParam(2, $cat_description);
            $stmt->bindParam(3, $parent_category_id);
            $stmt->bindParam(4, $category_id);
            
            if ($stmt->execute()) {
                $message = "Category updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update category.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Error updating category: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['delete_parent_category'])) {
        $parent_id = $_POST['parent_id'];
        
        try {
            // Check if parent category has subcategories
            $query = "SELECT COUNT(*) as count FROM categories WHERE ParentCategoryID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $parent_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $message = "Cannot delete parent category. It has " . $result['count'] . " subcategories. Please reassign or delete subcategories first.";
                $message_type = "warning";
            } else {
                $query = "DELETE FROM parent_categories WHERE ParentCategoryID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $parent_id);
                
                if ($stmt->execute()) {
                    $message = "Parent category deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to delete parent category.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Error deleting parent category: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'];
        
        try {
            // Check if category has products
            try {
                $query = "SELECT COUNT(*) as count FROM products WHERE CategoryID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $category_id);
                $stmt->execute();
                $product_result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product_result['count'] > 0) {
                    $message = "Cannot delete category. It has " . $product_result['count'] . " product(s) associated with it.";
                    $message_type = "warning";
                } else {
                    $query = "DELETE FROM categories WHERE CategoryID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(1, $category_id);
                    
                    if ($stmt->execute()) {
                        $message = "Category deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Failed to delete category.";
                        $message_type = "danger";
                    }
                }
            } catch (PDOException $e) {
                // If products table doesn't exist, just delete the category
                $query = "DELETE FROM categories WHERE CategoryID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $category_id);
                
                if ($stmt->execute()) {
                    $message = "Category deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to delete category.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Error deleting category: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Check if tables exist
$parent_categories_table_exists = false;
$categories_table_exists = false;

try {
    $query = "SELECT 1 FROM parent_categories LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $parent_categories_table_exists = true;
} catch (PDOException $e) {
    $parent_categories_table_exists = false;
}

try {
    $query = "SELECT 1 FROM categories LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories_table_exists = true;
} catch (PDOException $e) {
    $categories_table_exists = false;
}

// Get all parent categories with subcategory count
$parent_categories = [];
if ($parent_categories_table_exists) {
    try {
        $query = "SELECT pc.*, 
                         (SELECT COUNT(*) FROM categories WHERE ParentCategoryID = pc.ParentCategoryID) as subcategory_count
                  FROM parent_categories pc 
                  ORDER BY pc.Parent_Name ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $parent_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $parent_categories = [];
    }
}

// Get all categories with parent information
$categories = [];
if ($categories_table_exists && $parent_categories_table_exists) {
    try {
        $query = "SELECT c.*, pc.Parent_Name, pc.Parent_Icon, pc.Parent_Color
                  FROM categories c 
                  JOIN parent_categories pc ON c.ParentCategoryID = pc.ParentCategoryID 
                  ORDER BY pc.Parent_Name ASC, c.Cat_Name ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $categories = [];
    }
}

// Calculate statistics
$stats = [
    'total_parent_categories' => count($parent_categories),
    'total_categories' => count($categories),
    'active_parents' => count(array_filter($parent_categories, function($cat) { return $cat['Parent_IsActive'] == 1; })),
    'empty_categories' => 0
];

// Try to get categories with no products
try {
    $query = "SELECT COUNT(DISTINCT c.CategoryID) as count 
              FROM categories c 
              LEFT JOIN products p ON c.CategoryID = p.CategoryID 
              WHERE p.CategoryID IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['empty_categories'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    $stats['empty_categories'] = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - RentHub PH Admin</title>
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
        }
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
        .stat-card.parent { border-left-color: #28a745; }
        .stat-card.sub { border-left-color: #ffc107; }
        .stat-card.empty { border-left-color: #dc3545; }
        
        .category-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .category-card.parent-category {
            border-left: 4px solid #007bff;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .category-card.parent-category:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            border-left-color: #0056b3;
        }
        .category-card.sub-category {
            border-left: 4px solid #28a745;
            margin-left: 2rem;
            background: white;
        }
        
        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        .category-icon.parent { 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .category-icon.sub { 
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }
        
        /* Fixed Subcategory Indicator Styling */
        .subcategory-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            min-height: 60px;
        }

        .subcategory-indicator .badge {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
            color: white;
            border-radius: 20px !important;
            padding: 8px 16px !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            letter-spacing: 0.5px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
            transition: all 0.3s ease;
            min-width: 140px;
            text-align: center;
            white-space: nowrap;
        }

        .subcategory-indicator .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(23, 162, 184, 0.4);
            background: linear-gradient(135deg, #138496 0%, #0f6674 100%) !important;
        }

        .subcategory-indicator .badge i {
            margin-right: 6px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Empty State for No Subcategories */
        .no-subcategories-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 60px;
        }

        .no-subcategories-state .badge {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
            color: white;
            border-radius: 20px !important;
            padding: 6px 12px !important;
            font-size: 0.8rem !important;
            margin-bottom: 8px;
            opacity: 0.8;
        }

        .no-subcategories-state .btn {
            padding: 4px 12px;
            font-size: 0.75rem;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .no-subcategories-state .btn:hover {
            transform: translateY(-1px);
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

        .btn-outline-primary.btn-action:hover {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border-color: #007bff;
            color: white;
        }

        .btn-outline-danger.btn-action:hover {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-color: #dc3545;
            color: white;
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
        
        .table-responsive {
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
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
                <a class="nav-link active" href="categories.php">
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
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <h5 class="mb-0">Categories Management</h5>
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
                                        Parent Categories
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_parent_categories']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-layer-group fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card parent">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Subcategories
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_categories']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tags fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card sub">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Active Parents
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['active_parents']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card empty">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Empty Categories
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['empty_categories']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-inbox fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions and Categories List -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tags me-2"></i>Categories Hierarchy
                            </h5>
                            <div>
                                <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addParentCategoryModal">
                                    <i class="fas fa-plus me-1"></i>Add Parent
                                </button>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-tag me-1"></i>Add Category
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($parent_categories)): ?>
                                <?php foreach($parent_categories as $parent): ?>
                                <!-- Parent Category with Fixed Indicator -->
                                <div class="category-card parent-category">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-7">
                                                <div class="d-flex align-items-center">
                                                    <div class="category-icon parent" style="background: linear-gradient(135deg, <?php echo $parent['Parent_Color']; ?> 0%, <?php echo $parent['Parent_Color']; ?>cc 100%);">
                                                        <i class="<?php echo $parent['Parent_Icon']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($parent['Parent_Name']); ?></h6>
                                                        <p class="text-muted mb-1 small">
                                                            <?php echo htmlspecialchars($parent['Parent_Description']); ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            ID: <?php echo $parent['ParentCategoryID']; ?> | 
                                                            Status: <span class="badge bg-<?php echo $parent['Parent_IsActive'] ? 'success' : 'danger'; ?> rounded-pill"><?php echo $parent['Parent_IsActive'] ? 'Active' : 'Inactive'; ?></span>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <!-- Fixed Subcategory Indicator -->
                                                <?php if($parent['subcategory_count'] > 0): ?>
                                                    <div class="subcategory-indicator">
                                                        <span class="badge">
                                                            <i class="fas fa-layer-group"></i><?php echo $parent['subcategory_count']; ?> 
                                                            <?php echo $parent['subcategory_count'] == 1 ? 'subcategory' : 'subcategories'; ?>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="no-subcategories-state">
                                                        <span class="badge">
                                                            <i class="fas fa-inbox"></i>No subcategories
                                                        </span>
                                                        <button class="btn btn-outline-success btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#addCategoryModal" 
                                                                onclick="setParentForNewCategory(<?php echo $parent['ParentCategoryID']; ?>)"
                                                                title="Add first subcategory">
                                                            <i class="fas fa-plus"></i> Add First
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-outline-primary btn-action" 
                                                            onclick="editParentCategory(<?php echo $parent['ParentCategoryID']; ?>, '<?php echo addslashes($parent['Parent_Name']); ?>', '<?php echo addslashes($parent['Parent_Description']); ?>', '<?php echo $parent['Parent_Icon']; ?>', '<?php echo $parent['Parent_Color']; ?>', <?php echo $parent['Parent_IsActive']; ?>)"
                                                            title="Edit Parent Category">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this parent category and all its subcategories?')">
                                                        <input type="hidden" name="parent_id" value="<?php echo $parent['ParentCategoryID']; ?>">
                                                        <button type="submit" name="delete_parent_category" class="btn btn-outline-danger btn-action" title="Delete Parent Category">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Subcategories for this parent -->
                                <?php
                                $parent_subcategories = array_filter($categories, function($cat) use ($parent) {
                                    return $cat['ParentCategoryID'] == $parent['ParentCategoryID'];
                                });
                                ?>

                                <?php foreach($parent_subcategories as $child): ?>
                                <div class="category-card sub-category">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center">
                                                    <div class="category-icon sub">
                                                        <i class="fas fa-tag"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($child['Cat_Name']); ?></h6>
                                                        <p class="text-muted mb-1 small">
                                                            <?php echo htmlspecialchars($child['Cat_Description']); ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            ID: <?php echo $child['CategoryID']; ?> | 
                                                            Parent: <?php echo htmlspecialchars($child['Parent_Name']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <small class="text-muted">Subcategory</small>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-outline-primary btn-action" 
                                                            onclick="editCategory(<?php echo $child['CategoryID']; ?>, '<?php echo addslashes($child['Cat_Name']); ?>', '<?php echo addslashes($child['Cat_Description']); ?>', <?php echo $child['ParentCategoryID']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this subcategory?')">
                                                        <input type="hidden" name="category_id" value="<?php echo $child['CategoryID']; ?>">
                                                        <button type="submit" name="delete_category" class="btn btn-outline-danger btn-action">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-tags"></i>
                                    <h5 class="text-muted">No Categories Found</h5>
                                    <p class="text-muted">Start organizing your RentHub PH products by creating categories.</p>
                                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addParentCategoryModal">
                                        <i class="fas fa-plus me-2"></i>Create Parent Category
                                    </button>
                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                        <i class="fas fa-tag me-2"></i>Create First Category
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParentCategoryModal">
                                    <i class="fas fa-layer-group"></i> Add Parent Category
                                </button>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus"></i> Add New Category
                                </button>
                                <button class="btn btn-info" onclick="exportCategories()">
                                    <i class="fas fa-download"></i> Export Categories
                                </button>
                                <a href="products.php" class="btn btn-warning">
                                    <i class="fas fa-box"></i> Manage Products
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-success">
                                <small>
                                    <strong>Database Status:</strong><br>
                                    Parent Categories: <span class="badge bg-<?php echo $parent_categories_table_exists ? 'success' : 'danger'; ?>">
                                        <?php echo $parent_categories_table_exists ? 'Ready' : 'Not Found'; ?>
                                    </span><br>
                                    Categories: <span class="badge bg-<?php echo $categories_table_exists ? 'success' : 'danger'; ?>">
                                        <?php echo $categories_table_exists ? 'Ready' : 'Not Found'; ?>
                                    </span>
                                </small>
                            </div>
                            <div class="alert alert-info">
                                <small>
                                    <strong>Best Practices:</strong><br>
                                    • Use clear, descriptive names<br>
                                    • Organize with parent-child relationships<br>
                                    • Keep hierarchy simple<br>
                                    • Delete empty categories periodically
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Parent Category Modal -->
    <div class="modal fade" id="addParentCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-layer-group me-2"></i>Add Parent Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Parent Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="parent_name" required placeholder="e.g. Electronics, Furniture">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Color</label>
                                <input type="color" class="form-control" name="parent_color" value="#007bff" style="height: 38px;">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="parent_description" rows="3" placeholder="Brief description"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <select class="form-select" name="parent_icon">
                                <option value="fas fa-laptop">💻 Electronics</option>
                                <option value="fas fa-couch">🛋️ Furniture</option>
                                <option value="fas fa-car">🚗 Vehicles</option>
                                <option value="fas fa-football-ball">⚽ Sports</option>
                                <option value="fas fa-tools">🔧 Tools</option>
                                <option value="fas fa-home">🏠 Home</option>
                                <option value="fas fa-camera">📷 Photography</option>
                                <option value="fas fa-music">🎵 Audio</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_parent_category" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Parent Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tag me-2"></i>Add New Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cat_name" required placeholder="Enter category name">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="cat_description" rows="3" placeholder="Brief description of this category"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Parent Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="parent_category_id" required>
                                <option value="">Select Parent Category</option>
                                <?php foreach($parent_categories as $parent): ?>
                                <option value="<?php echo $parent['ParentCategoryID']; ?>">
                                    <?php echo htmlspecialchars($parent['Parent_Name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_category" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Create Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Parent Category Modal -->
    <div class="modal fade" id="editParentCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Parent Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" id="edit_parent_id" name="parent_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Parent Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_parent_name" name="parent_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Color</label>
                                <input type="color" class="form-control" id="edit_parent_color" name="parent_color" style="height: 38px;">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="edit_parent_description" name="parent_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <select class="form-select" id="edit_parent_icon" name="parent_icon">
                                <option value="fas fa-laptop">💻 Electronics</option>
                                <option value="fas fa-couch">🛋️ Furniture</option>
                                <option value="fas fa-car">🚗 Vehicles</option>
                                <option value="fas fa-football-ball">⚽ Sports</option>
                                <option value="fas fa-tools">🔧 Tools</option>
                                <option value="fas fa-home">🏠 Home</option>
                                <option value="fas fa-camera">📷 Photography</option>
                                <option value="fas fa-music">🎵 Audio</option>
                            </select>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="edit_parent_active" name="parent_active">
                            <label class="form-check-label" for="edit_parent_active">
                                Active (visible to users)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_parent_category" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Parent Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_cat_name" name="cat_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="edit_cat_description" name="cat_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Parent Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_parent_category_id" name="parent_category_id" required>
                                <option value="">Select Parent Category</option>
                                <?php foreach($parent_categories as $parent): ?>
                                <option value="<?php echo $parent['ParentCategoryID']; ?>">
                                    <?php echo htmlspecialchars($parent['Parent_Name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_category" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pre-select parent category when adding subcategory
        function setParentForNewCategory(parentId) {
            // Wait for modal to open, then set the parent
            setTimeout(() => {
                const parentSelect = document.querySelector('select[name="parent_category_id"]');
                if (parentSelect) {
                    parentSelect.value = parentId;
                }
            }, 100);
        }

        // Edit parent category function
        function editParentCategory(id, name, description, icon, color, active) {
            document.getElementById('edit_parent_id').value = id;
            document.getElementById('edit_parent_name').value = name;
            document.getElementById('edit_parent_description').value = description;
            document.getElementById('edit_parent_icon').value = icon;
            document.getElementById('edit_parent_color').value = color;
            document.getElementById('edit_parent_active').checked = active == 1;
            
            new bootstrap.Modal(document.getElementById('editParentCategoryModal')).show();
        }

        // Edit category function
        function editCategory(id, name, description, parentId) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_cat_name').value = name;
            document.getElementById('edit_cat_description').value = description;
            document.getElementById('edit_parent_category_id').value = parentId;
            
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }

        // Export categories function
        function exportCategories() {
            alert('Export categories functionality will be implemented.');
        }

        // Auto-hide alerts
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
            const cards = document.querySelectorAll('.category-card, .stat-card');
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
    </script>
</body>
</html>