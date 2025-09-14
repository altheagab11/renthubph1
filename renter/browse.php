<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([2, 3]); // Renter or Both Renter/Owner

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$price_min = isset($_GET['price_min']) ? $_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) ? $_GET['price_max'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build the WHERE clause for filters
$where_conditions = ["p.Prod_Availability = 'Available'", "p.Prod_Status = 'Active'"];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "p.CategoryID = ?";
    $params[] = $category_filter;
}

if (!empty($search_filter)) {
    $where_conditions[] = "(p.Prod_Name LIKE ? OR p.Prod_Description LIKE ? OR p.Prod_Brand LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($price_min)) {
    $where_conditions[] = "p.Prod_RentalPrice >= ?";
    $params[] = $price_min;
}

if (!empty($price_max)) {
    $where_conditions[] = "p.Prod_RentalPrice <= ?";
    $params[] = $price_max;
}

// Build ORDER BY clause
$order_by = "p.Prod_CreatedAt DESC"; // default newest first
switch ($sort_by) {
    case 'price_low':
        $order_by = "p.Prod_RentalPrice ASC";
        break;
    case 'price_high':
        $order_by = "p.Prod_RentalPrice DESC";
        break;
    case 'name':
        $order_by = "p.Prod_Name ASC";
        break;
    case 'newest':
    default:
        $order_by = "p.Prod_CreatedAt DESC";
        break;
}

// Get products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Count total products for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM products p 
                JOIN categories c ON p.CategoryID = c.CategoryID 
                JOIN user_accounts u ON p.OwnerID = u.UserID 
                WHERE " . implode(' AND ', $where_conditions);
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $items_per_page);

// Get products - FIXED: Changed Category_Name to Cat_Name
$query = "SELECT p.*, c.Cat_Name, u.User_Name as Owner_Name, 
                 pi.PI_ImagePath,
                 (SELECT COUNT(*) FROM favorites f WHERE f.ProductID = p.ProductID AND f.UserID = ?) as is_favorited
          FROM products p
          JOIN categories c ON p.CategoryID = c.CategoryID
          JOIN user_accounts u ON p.OwnerID = u.UserID
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          WHERE " . implode(' AND ', $where_conditions) . "
          ORDER BY $order_by
          LIMIT $items_per_page OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->execute(array_merge([$user_id], $params));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter - FIXED: Changed Category_Name to Cat_Name
$cat_query = "SELECT * FROM categories ORDER BY Cat_Name";
$cat_stmt = $conn->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
        }
        .main-content {
            margin-left: 250px;
        }
        
        .product-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-weight: bold;
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .favorite-btn.favorited {
            color: #dc3545;
        }
        
        .filter-card {
            background: #f8f9fa;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .book-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 20px;
            padding: 0.5rem 1.5rem;
            color: white;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            color: white;
        }
        
        .product-meta {
            font-size: 0.875rem;
            color: #6c757d;
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
            <p class="text-white-50 small">Renter Dashboard</p>
        </div>
        
        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="browse.php">
                        <i class="fas fa-search"></i> Browse Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check"></i> My Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="favorites.php">
                        <i class="fas fa-heart"></i> Favorites
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="fas fa-comments"></i> Messages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reviews.php">
                        <i class="fas fa-star"></i> Reviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payment-history.php">
                        <i class="fas fa-money-bill"></i> Payment History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile Settings
                    </a>
                </li>
                <?php if($_SESSION['user_role'] == 3): ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="../owner/dashboard.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-store"></i> Switch to Owner
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="upgrade.php" style="background-color: rgba(255,255,255,0.1);">
                        <i class="fas fa-crown"></i> Become an Owner
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
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
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <h5 class="mb-0">Browse Available Items</h5>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger badge-sm">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">Booking confirmed</a></li>
                            <li><a class="dropdown-item" href="#">New message received</a></li>
                            <li><a class="dropdown-item" href="#">Payment processed</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="notifications.php">View all</a></li>
                        </ul>
                    </div>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Browse Content -->
        <div class="container-fluid p-4">
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card filter-card">
                        <div class="card-body">
                            <form method="GET" action="browse.php" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Search items...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['CategoryID']; ?>" <?php echo $category_filter == $category['CategoryID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Min Price</label>
                                    <input type="number" class="form-control" name="price_min" value="<?php echo htmlspecialchars($price_min); ?>" placeholder="₱0" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Max Price</label>
                                    <input type="number" class="form-control" name="price_max" value="<?php echo htmlspecialchars($price_max); ?>" placeholder="₱10000" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Sort By</label>
                                    <select class="form-select" name="sort">
                                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> items</h6>
                        <?php if($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">Previous</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="row">
                <?php if(empty($products)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No items found</h5>
                        <p class="text-muted">Try adjusting your search filters or browse all categories.</p>
                        <a href="browse.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card product-card">
                            <div class="position-relative">
                                <img src="<?php echo $product['PI_ImagePath'] ? htmlspecialchars($product['PI_ImagePath']) : '../assets/images/no-image.jpg'; ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>">
                                <button class="favorite-btn <?php echo $product['is_favorited'] > 0 ? 'favorited' : ''; ?>" 
                                        onclick="toggleFavorite(<?php echo $product['ProductID']; ?>, this)">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title mb-2"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                                <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars(substr($product['Prod_Description'], 0, 80)) . (strlen($product['Prod_Description']) > 80 ? '...' : ''); ?></p>
                                
                                <div class="product-meta mb-2">
                                    <div><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['Cat_Name']); ?></div>
                                    <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($product['Owner_Name']); ?></div>
                                    <div><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($product['Prod_Condition']); ?></div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price-badge">₱<?php echo number_format($product['Prod_RentalPrice'], 2); ?>/<?php echo $product['Prod_PriceType']; ?></span>
                                    <button class="btn book-btn btn-sm" onclick="bookProduct(<?php echo $product['ProductID']; ?>)">
                                        <i class="fas fa-calendar-plus"></i> Book
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingModalLabel">Book Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bookingModalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFavorite(productId, button) {
            fetch('../api/toggle-favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.classList.toggle('favorited');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function bookProduct(productId) {
            // Load booking form in modal
            fetch(`book-product.php?product_id=${productId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('bookingModalContent').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>