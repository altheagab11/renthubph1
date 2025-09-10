<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$price_min = isset($_GET['price_min']) ? $_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) ? $_GET['price_max'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["p.Prod_Status = 'Active'", "p.Prod_Availability = 1"];
$params = [];

if ($search) {
    $conditions[] = "(p.Prod_Name LIKE ? OR p.Prod_Description LIKE ? OR p.Prod_Brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $conditions[] = "p.CategoryID = ?";
    $params[] = $category_filter;
}

if ($location_filter) {
    $conditions[] = "ua.UA_City LIKE ?";
    $params[] = "%$location_filter%";
}

if ($price_min) {
    $conditions[] = "p.Prod_RentalPrice >= ?";
    $params[] = $price_min;
}

if ($price_max) {
    $conditions[] = "p.Prod_RentalPrice <= ?";
    $params[] = $price_max;
}

// Sort options
$sort_options = [
    'newest' => 'p.Prod_CreatedAt DESC',
    'oldest' => 'p.Prod_CreatedAt ASC',
    'price_low' => 'p.Prod_RentalPrice ASC',
    'price_high' => 'p.Prod_RentalPrice DESC',
    'name_asc' => 'p.Prod_Name ASC',
    'popular' => 'booking_count DESC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'p.Prod_CreatedAt DESC';

// Get products
$query = "SELECT p.*, pi.PI_ImagePath, u.User_Name as Owner_Name, ua.UA_City, ua.UA_Province,
          (SELECT COUNT(*) FROM bookings WHERE ProductID = p.ProductID) as booking_count,
          (SELECT AVG(Rev_Rating) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as avg_rating,
          (SELECT COUNT(*) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as review_count
          FROM products p
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON p.OwnerID = u.UserID
          LEFT JOIN user_addresses ua ON u.UserID = ua.UserID AND ua.UA_IsDefault = 1
          WHERE " . implode(' AND ', $conditions) . "
          ORDER BY " . $order_by . "
          LIMIT 24";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$query = "SELECT * FROM categories ORDER BY Cat_Name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products
$query = "SELECT p.*, pi.PI_ImagePath, u.User_Name as Owner_Name
          FROM products p
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          JOIN user_accounts u ON p.OwnerID = u.UserID
          WHERE p.Prod_Status = 'Active' AND p.Prod_Availability = 1 
          AND p.Prod_IsFeatured = 1 AND p.Prod_FeaturedUntil > NOW()
          ORDER BY p.Prod_CreatedAt DESC
          LIMIT 6";

$stmt = $conn->prepare($query);
$stmt->execute();
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get popular categories
$query = "SELECT c.*, COUNT(p.ProductID) as product_count
          FROM categories c
          LEFT JOIN products p ON c.CategoryID = p.CategoryID AND p.Prod_Status = 'Active'
          GROUP BY c.CategoryID
          ORDER BY product_count DESC, c.Cat_Name
          LIMIT 8";
$stmt = $conn->prepare($query);
$stmt->execute();
$popular_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Products - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        }
        
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .browse-header {
            background: var(--primary-gradient);
            color: white;
            padding: 4rem 0 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .browse-header::before {
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
        
        .search-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .filter-sidebar {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            position: sticky;
            top: 100px;
        }
        
        .filter-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .filter-section h6 {
            color: #11998e;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .product-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .product-card .card-img-top {
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .card-img-top {
            transform: scale(1.1);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--warning-gradient);
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .price-tag {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .owner-info {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .owner-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            margin-right: 0.5rem;
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
        
        .btn-search {
            background: var(--primary-gradient);
            border: none;
            border-radius: 15px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .btn-filter {
            background: var(--secondary-gradient);
            border: none;
            border-radius: 15px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            margin: 0.25rem;
        }
        
        .btn-filter:hover, .btn-filter.active {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .featured-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 2rem;
            margin: 3rem 0;
        }
        
        .category-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            border: 2px solid transparent;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            border-color: #11998e;
        }
        
        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        
        .breadcrumb {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .breadcrumb-item a {
            color: #11998e;
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-item.active {
            color: #6c757d;
        }
        
        .results-info {
            background: rgba(17, 153, 142, 0.1);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            border-left: 4px solid #11998e;
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
        
        .pagination {
            justify-content: center;
            margin-top: 3rem;
        }
        
        .page-link {
            border-radius: 10px;
            border: none;
            margin: 0 0.25rem;
            color: #11998e;
            font-weight: 500;
        }
        
        .page-link:hover, .page-item.active .page-link {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .browse-header {
                padding: 2rem 0 1rem;
            }
            
            .search-section {
                margin-top: -2rem;
                padding: 1.5rem;
            }
            
            .filter-sidebar {
                position: static;
                margin-bottom: 2rem;
                padding: 1.5rem;
            }
            
            .product-card .card-img-top {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-home text-success me-2"></i>RentHub PH
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="browse.php">Browse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="renter/dashboard.php">
                                    <i class="fas fa-search me-2"></i>Renter Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="owner/dashboard.php">
                                    <i class="fas fa-home me-2"></i>Owner Dashboard
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2" style="border-radius: 20px;">Login</a>
                        <a href="register.php" class="btn btn-primary" style="border-radius: 20px;">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Browse Header -->
    <div class="browse-header mt-5">
        <div class="container">
            <div class="row align-items-center" style="position: relative; z-index: 2;">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-3">Find Your Perfect Rental</h1>
                    <p class="lead opacity-90 mb-0">
                        Discover thousands of quality products available for rent from trusted owners across the Philippines.
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column align-items-end">
                        <h3 class="mb-1"><?php echo count($products); ?></h3>
                        <span class="opacity-75">Products Available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="container">
        <div class="search-section">
            <form method="GET" action="browse.php">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search Products
                        </label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="What are you looking for?">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tags me-2"></i>Category
                        </label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['CategoryID']; ?>" 
                                        <?php echo $category_filter == $category['CategoryID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-map-marker-alt me-2"></i>Location
                        </label>
                        <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($location_filter); ?>" 
                               placeholder="City">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-peso-sign me-2"></i>Price Range
                        </label>
                        <div class="d-flex">
                            <input type="number" class="form-control me-1" name="price_min" value="<?php echo htmlspecialchars($price_min); ?>" 
                                   placeholder="Min" style="border-radius: 15px 0 0 15px;">
                            <input type="number" class="form-control ms-1" name="price_max" value="<?php echo htmlspecialchars($price_max); ?>" 
                                   placeholder="Max" style="border-radius: 0 15px 15px 0;">
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn btn-search w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Featured Products -->
    <?php if(!empty($featured_products) && empty($search) && empty($category_filter)): ?>
    <div class="container">
        <div class="featured-section">
            <h3 class="text-center mb-4">
                <i class="fas fa-star text-warning me-2"></i>Featured Products
            </h3>
            <div class="row">
                <?php foreach(array_slice($featured_products, 0, 6) as $product): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card product-card h-100">
                        <div class="position-relative">
                            <img src="<?php echo $product['PI_ImagePath'] ? htmlspecialchars($product['PI_ImagePath']) : 'assets/images/no-image.jpg'; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>">
                            <div class="product-badge">Featured</div>
                        </div>
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2"><?php echo htmlspecialchars(substr($product['Prod_Name'], 0, 30)); ?>...</h6>
                            <div class="price-tag mb-2">
                                ₱<?php echo number_format($product['Prod_RentalPrice'], 0); ?>
                                <small>/<?php echo htmlspecialchars($product['Prod_PriceType']); ?></small>
                            </div>
                            <div class="owner-info">
                                <div class="owner-avatar">
                                    <?php echo strtoupper(substr($product['Owner_Name'], 0, 1)); ?>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($product['Owner_Name']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Browse Products</li>
                <?php if($category_filter): ?>
                    <li class="breadcrumb-item active">
                        <?php 
                        foreach($categories as $cat) {
                            if($cat['CategoryID'] == $category_filter) {
                                echo htmlspecialchars($cat['Cat_Name']);
                                break;
                            }
                        }
                        ?>
                    </li>
                <?php endif; ?>
            </ol>
        </nav>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="filter-sidebar">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-filter me-2"></i>Filters
                    </h5>
                    
                    <!-- Quick Filters -->
                    <div class="filter-section">
                        <h6>Quick Filters</h6>
                        <div class="d-flex flex-wrap">
                            <a href="browse.php?sort=newest" class="btn btn-filter btn-sm <?php echo $sort_by == 'newest' ? 'active' : ''; ?>">
                                Newest
                            </a>
                            <a href="browse.php?sort=price_low" class="btn btn-filter btn-sm <?php echo $sort_by == 'price_low' ? 'active' : ''; ?>">
                                Price Low
                            </a>
                            <a href="browse.php?sort=popular" class="btn btn-filter btn-sm <?php echo $sort_by == 'popular' ? 'active' : ''; ?>">
                                Popular
                            </a>
                        </div>
                    </div>
                    
                    <!-- Categories -->
                    <div class="filter-section">
                        <h6>Categories</h6>
                        <?php foreach(array_slice($popular_categories, 0, 6) as $category): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <a href="browse.php?category=<?php echo $category['CategoryID']; ?>" 
                               class="text-decoration-none <?php echo $category_filter == $category['CategoryID'] ? 'text-primary fw-bold' : 'text-muted'; ?>">
                                <?php echo htmlspecialchars($category['Cat_Name']); ?>
                            </a>
                            <span class="badge bg-light text-muted"><?php echo $category['product_count']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Clear Filters -->
                    <?php if($search || $category_filter || $location_filter || $price_min || $price_max): ?>
                    <div class="text-center">
                        <a href="browse.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Clear All Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <!-- Results Info -->
                <div class="results-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">
                                <i class="fas fa-search me-2"></i>Search Results
                            </h6>
                            <p class="mb-0 text-muted">
                                Found <?php echo count($products); ?> products
                                <?php if($search): ?>
                                    for "<?php echo htmlspecialchars($search); ?>"
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <form method="GET" class="d-flex align-items-center">
                                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                <?php if($category_filter): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>"><?php endif; ?>
                                <?php if($location_filter): ?><input type="hidden" name="location" value="<?php echo htmlspecialchars($location_filter); ?>"><?php endif; ?>
                                <?php if($price_min): ?><input type="hidden" name="price_min" value="<?php echo htmlspecialchars($price_min); ?>"><?php endif; ?>
                                <?php if($price_max): ?><input type="hidden" name="price_max" value="<?php echo htmlspecialchars($price_max); ?>"><?php endif; ?>
                                
                                <label class="form-label me-2 mb-0">Sort:</label>
                                <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()" style="width: auto;">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                    <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if(empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h4 class="text-muted">No products found</h4>
                        <p class="text-muted">Try adjusting your search criteria or browse all available products.</p>
                        <a href="browse.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
                            <i class="fas fa-eye me-2"></i>Browse All Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($products as $product): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card product-card h-100">
                                <div class="position-relative">
                                    <img src="<?php echo $product['PI_ImagePath'] ? htmlspecialchars($product['PI_ImagePath']) : 'assets/images/no-image.jpg'; ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>">
                                    <?php if($product['Prod_IsFeatured'] && $product['Prod_FeaturedUntil'] > date('Y-m-d H:i:s')): ?>
                                        <div class="product-badge">Featured</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['Prod_Name']); ?></h5>
                                    <p class="card-text text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars($product['Prod_Description']); ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="price-tag">
                                            ₱<?php echo number_format($product['Prod_RentalPrice'], 0); ?>
                                            <small>/<?php echo htmlspecialchars($product['Prod_PriceType']); ?></small>
                                        </div>
                                        <?php if($product['avg_rating']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="rating-stars me-1">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $product['avg_rating'] ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted">(<?php echo $product['review_count']; ?>)</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="owner-info mb-3">
                                        <div class="owner-avatar">
                                            <?php echo strtoupper(substr($product['Owner_Name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <small class="fw-bold"><?php echo htmlspecialchars($product['Owner_Name']); ?></small>
                                            <?php if($product['UA_City']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($product['UA_City'] . ', ' . $product['UA_Province']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <a href="product.php?id=<?php echo $product['ProductID']; ?>" 
                                           class="btn btn-primary" style="border-radius: 15px;">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Products pagination">
                        <ul class="pagination">
                            <li class="page-item">
                                <a class="page-link" href="#" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-home text-success me-2"></i>RentHub PH
                    </h5>
                    <p class="text-light">Your trusted platform for renting quality products across the Philippines.</p>
                </div>
                <div class="col-md-2 mb-4">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="browse.php" class="text-light">Browse</a></li>
                        <li><a href="categories.php" class="text-light">Categories</a></li>
                        <li><a href="about.php" class="text-light">About</a></li>
                        <li><a href="contact.php" class="text-light">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">For Owners</h6>
                    <ul class="list-unstyled">
                        <li><a href="owner/dashboard.php" class="text-light">Owner Dashboard</a></li>
                        <li><a href="owner/add-product.php" class="text-light">List Your Product</a></li>
                        <li><a href="owner/earnings.php" class="text-light">Earnings</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">For Renters</h6>
                    <ul class="list-unstyled">
                        <li><a href="renter/dashboard.php" class="text-light">Renter Dashboard</a></li>
                        <li><a href="renter/bookings.php" class="text-light">My Bookings</a></li>
                        <li><a href="renter/favorites.php" class="text-light">Favorites</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 RentHub PH. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-light me-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-light"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit search form on enter
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading state to search button
        document.querySelector('.btn-search').addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Searching...';
            this.disabled = true;
        });
    </script>
</body>
</html>