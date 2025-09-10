<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();
$database = new Database();
$conn = $database->getConnection();

// Get featured products
$query = "SELECT p.*, pi.PI_ImagePath, c.Cat_Name, u.User_Name 
          FROM products p 
          LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
          LEFT JOIN categories c ON p.CategoryID = c.CategoryID
          LEFT JOIN user_accounts u ON p.OwnerID = u.UserID
          WHERE p.Prod_Status = 'Active' AND p.Prod_Availability = 1
          ORDER BY p.Prod_CreatedAt DESC LIMIT 8";
$stmt = $conn->prepare($query);
$stmt->execute();
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$query = "SELECT * FROM categories WHERE Cat_ParentID IS NULL ORDER BY Cat_Name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentHub PH - Rent Anything, Anytime</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .category-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .category-card:hover {
            transform: translateY(-5px);
        }
        .product-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .search-section {
            background-color: #f8f9fa;
            padding: 60px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand text-primary" href="index.php">
                <i class="fas fa-home"></i> RentHub PH
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="browse.php">Browse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="how-it-works.php">How it Works</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if($auth->isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if($_SESSION['user_role'] == 1): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">Admin Dashboard</a></li>
                                <?php elseif($_SESSION['user_role'] == 2): ?>
                                    <li><a class="dropdown-item" href="renter/dashboard.php">My Dashboard</a></li>
                                <?php elseif($_SESSION['user_role'] == 3): ?>
                                    <li><a class="dropdown-item" href="owner/dashboard.php">Owner Dashboard</a></li>
                                    <li><a class="dropdown-item" href="renter/dashboard.php">Renter Dashboard</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Rent Anything, Anytime</h1>
            <p class="lead mb-5">Discover thousands of items available for rent in your area. From tools to party equipment, find what you need when you need it.</p>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <form action="search.php" method="GET" class="d-flex">
                        <input type="text" name="q" class="form-control form-control-lg me-2" placeholder="What are you looking for?" required>
                        <button type="submit" class="btn btn-warning btn-lg px-4">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-4">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <h5>Find & Browse</h5>
                    <p>Search thousands of items available for rent</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-calendar-check fa-3x mb-3"></i>
                    <h5>Book & Pay</h5>
                    <p>Secure booking with flexible payment options</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-handshake fa-3x mb-3"></i>
                    <h5>Enjoy & Return</h5>
                    <p>Use the item and return it safely</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Popular Categories</h2>
            <div class="row">
                <?php foreach($categories as $category): ?>
                <div class="col-md-3 mb-4">
                    <div class="card category-card h-100 text-center">
                        <div class="card-body">
                            <i class="fas fa-tools fa-3x text-primary mb-3"></i>
                            <h5 class="card-title"><?php echo htmlspecialchars($category['Cat_Name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($category['Cat_Description']); ?></p>
                            <a href="browse.php?category=<?php echo $category['CategoryID']; ?>" class="btn btn-outline-primary">Browse</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Featured Items</h2>
            <div class="row">
                <?php foreach($featured_products as $product): ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card h-100">
                        <img src="<?php echo $product['PI_ImagePath'] ? htmlspecialchars($product['PI_ImagePath']) : 'assets/images/no-image.jpg'; ?>" 
                             class="card-img-top" style="height: 200px; object-fit: cover;" 
                             alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($product['Prod_Name']); ?></h6>
                            <p class="text-muted small"><?php echo htmlspecialchars($product['Cat_Name']); ?></p>
                            <p class="card-text text-primary fw-bold">
                                ₱<?php echo number_format($product['Prod_RentalPrice'], 2); ?> 
                                <small>/<?php echo htmlspecialchars($product['Prod_PriceType']); ?></small>
                            </p>
                            <p class="text-muted small">by <?php echo htmlspecialchars($product['User_Name']); ?></p>
                            <a href="product.php?id=<?php echo $product['ProductID']; ?>" class="btn btn-primary btn-sm">View Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="browse.php" class="btn btn-outline-primary btn-lg">View All Items</a>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-4">Start Earning with Your Items</h2>
            <p class="lead mb-4">Have items sitting unused? Turn them into income by renting them out to others.</p>
            <?php if(!$auth->isLoggedIn()): ?>
                <a href="register.php?type=owner" class="btn btn-warning btn-lg me-3">Become an Owner</a>
                <a href="register.php" class="btn btn-outline-light btn-lg">Sign Up as Renter</a>
            <?php else: ?>
                <?php if($_SESSION['user_role'] == 2): ?>
                    <a href="owner/upgrade.php" class="btn btn-warning btn-lg">Become an Owner</a>
                <?php else: ?>
                    <a href="owner/dashboard.php" class="btn btn-warning btn-lg">Go to Owner Dashboard</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>RentHub PH</h5>
                    <p>The Philippines' premier rental marketplace. Rent anything, anytime, anywhere.</p>
                </div>
                <div class="col-md-2">
                    <h6>Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="about.php" class="text-white-50">About Us</a></li>
                        <li><a href="contact.php" class="text-white-50">Contact</a></li>
                        <li><a href="careers.php" class="text-white-50">Careers</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6>Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="help.php" class="text-white-50">Help Center</a></li>
                        <li><a href="safety.php" class="text-white-50">Safety</a></li>
                        <li><a href="terms.php" class="text-white-50">Terms</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6>Community</h6>
                    <ul class="list-unstyled">
                        <li><a href="blog.php" class="text-white-50">Blog</a></li>
                        <li><a href="forum.php" class="text-white-50">Forum</a></li>
                        <li><a href="events.php" class="text-white-50">Events</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6>Follow Us</h6>
                    <div class="d-flex">
                        <a href="#" class="text-white-50 me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white-50 me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-twitter fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2025 RentHub PH. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>