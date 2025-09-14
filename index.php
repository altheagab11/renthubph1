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

// Get parent categories with their subcategory count for homepage display
$query = "SELECT pc.*, 
                 (SELECT COUNT(*) FROM categories WHERE ParentCategoryID = pc.ParentCategoryID) as category_count
          FROM parent_categories pc 
          WHERE pc.Parent_IsActive = 1 
          ORDER BY pc.Parent_Name ASC 
          LIMIT 8";
$stmt = $conn->prepare($query);
$stmt->execute();
$parent_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get random subcategories for additional display
$query = "SELECT c.*, pc.Parent_Name, pc.Parent_Icon, pc.Parent_Color
          FROM categories c 
          JOIN parent_categories pc ON c.ParentCategoryID = pc.ParentCategoryID 
          WHERE pc.Parent_IsActive = 1
          ORDER BY RAND() 
          LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->execute();
$featured_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            --warning-gradient: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .hero-section {
            background: var(--secondary-gradient);
            color: white;
            padding: 120px 0;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="1000,100 1000,0 0,100"/></svg>') no-repeat;
            background-size: cover;
        }

        .category-card {
            transition: all 0.4s ease;
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            background: white;
        }
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--primary-gradient);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .category-card:hover::before {
            opacity: 0.1;
        }
        .category-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .category-card .card-body {
            position: relative;
            z-index: 2;
            padding: 2rem;
        }

        .category-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            background: var(--primary-gradient);
            color: white;
            transition: all 0.3s ease;
        }
        .category-card:hover .category-icon {
            transform: scale(1.1) rotate(10deg);
        }

        .product-card {
            transition: all 0.4s ease;
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            background: white;
        }
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .product-card .card-img-top {
            transition: all 0.3s ease;
        }
        .product-card:hover .card-img-top {
            transform: scale(1.05);
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.8rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .search-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 80px 0;
        }

        .search-form {
            background: white;
            border-radius: 50px;
            padding: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .search-form .form-control {
            border: none;
            border-radius: 50px;
            padding: 15px 25px;
            font-size: 1.1rem;
        }
        .search-form .btn {
            border-radius: 50px;
            padding: 15px 30px;
            font-weight: 600;
            background: var(--warning-gradient);
            border: none;
            color: white;
        }

        .feature-item {
            text-align: center;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .feature-item:hover {
            transform: translateY(-5px);
        }
        .feature-item i {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .btn-outline-primary {
            border: 2px solid #11998e;
            color: #11998e;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            border-color: #11998e;
            transform: translateY(-2px);
        }

        .cta-section {
            background: var(--secondary-gradient);
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="0,0 1000,100 0,100"/></svg>') no-repeat;
            background-size: cover;
        }

        .footer {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }

        .price-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .owner-badge {
            background: var(--accent-gradient);
            color: #333;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 3rem;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        .navbar {
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        .navbar.scrolled {
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-on-scroll {
            opacity: 0;
            animation: fadeInUp 0.8s ease forwards;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }
            .category-card, .product-card {
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-home me-2"></i>RentHub PH
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="browse.php">Browse Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="how-it-works.php">How it Works</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if($auth->isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if($_SESSION['user_role'] == 1): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</a></li>
                                <?php elseif($_SESSION['user_role'] == 2): ?>
                                    <li><a class="dropdown-item" href="renter/dashboard.php"><i class="fas fa-user me-2"></i>My Dashboard</a></li>
                                <?php elseif($_SESSION['user_role'] == 3): ?>
                                    <li><a class="dropdown-item" href="owner/dashboard.php"><i class="fas fa-store me-2"></i>Owner Dashboard</a></li>
                                    <li><a class="dropdown-item" href="renter/dashboard.php"><i class="fas fa-user me-2"></i>Renter Dashboard</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-cog me-2"></i>Profile Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="register.php">Sign Up Free</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center position-relative">
            <div class="animate-on-scroll">
                <h1 class="display-3 mb-4 fw-bold">Rent Anything, Anytime</h1>
                <p class="lead mb-5 fs-4">Discover thousands of items available for rent across the Philippines. From tools to party equipment, find what you need when you need it.</p>
                
                <div class="row justify-content-center mb-5">
                    <div class="col-lg-8">
                        <div class="search-form">
                            <form action="search.php" method="GET" class="d-flex">
                                <input type="text" name="q" class="form-control flex-grow-1" placeholder="Search for cars, tools, electronics, and more..." required>
                                <button type="submit" class="btn btn-warning ms-2">
                                    <i class="fas fa-search me-2"></i>Search Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-4 feature-item animate-on-scroll" style="animation-delay: 0.2s;">
                    <i class="fas fa-search fa-4x mb-3"></i>
                    <h5 class="fw-bold">Find & Browse</h5>
                    <p>Search thousands of items available for rent nationwide</p>
                </div>
                <div class="col-md-4 feature-item animate-on-scroll" style="animation-delay: 0.4s;">
                    <i class="fas fa-calendar-check fa-4x mb-3"></i>
                    <h5 class="fw-bold">Book & Pay</h5>
                    <p>Secure booking with flexible payment options and protection</p>
                </div>
                <div class="col-md-4 feature-item animate-on-scroll" style="animation-delay: 0.6s;">
                    <i class="fas fa-handshake fa-4x mb-3"></i>
                    <h5 class="fw-bold">Enjoy & Return</h5>
                    <p>Use the item safely and return it as agreed</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center section-title">Popular Categories</h2>
            <div class="row">
                <?php if(!empty($parent_categories)): ?>
                    <?php foreach($parent_categories as $index => $category): ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="category-card h-100 text-center animate-on-scroll" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                            <div class="card-body">
                                <div class="category-icon" style="background: linear-gradient(135deg, <?php echo $category['Parent_Color']; ?> 0%, <?php echo $category['Parent_Color']; ?>aa 100%);">
                                    <i class="<?php echo $category['Parent_Icon']; ?>"></i>
                                </div>
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($category['Parent_Name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars(substr($category['Parent_Description'], 0, 80)); ?><?php echo strlen($category['Parent_Description']) > 80 ? '...' : ''; ?></p>
                                <div class="mb-3">
                                    <span class="badge bg-light text-dark"><?php echo $category['category_count']; ?> categories</span>
                                </div>
                                <a href="browse.php?parent_category=<?php echo $category['ParentCategoryID']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-right me-2"></i>Explore
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Categories are being set up. Please check back soon!
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Featured Subcategories -->
            <?php if(!empty($featured_categories)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h4 class="text-center mb-4">Trending Subcategories</h4>
                </div>
                <?php foreach($featured_categories as $index => $category): ?>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100 animate-on-scroll" style="animation-delay: <?php echo $index * 0.1 + 0.8; ?>s;">
                        <div class="card-body text-center">
                            <i class="<?php echo $category['Parent_Icon']; ?> fa-2x mb-3" style="color: <?php echo $category['Parent_Color']; ?>;"></i>
                            <h6 class="fw-bold"><?php echo htmlspecialchars($category['Cat_Name']); ?></h6>
                            <small class="text-muted">under <?php echo htmlspecialchars($category['Parent_Name']); ?></small>
                            <br>
                            <a href="browse.php?category=<?php echo $category['CategoryID']; ?>" class="btn btn-sm btn-outline-primary mt-2">Browse</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5 search-section">
        <div class="container">
            <h2 class="text-center section-title">Featured Rental Items</h2>
            <div class="row">
                <?php if(!empty($featured_products)): ?>
                    <?php foreach($featured_products as $index => $product): ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="product-card h-100 animate-on-scroll" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                            <div class="position-relative overflow-hidden" style="height: 220px;">
                                <img src="<?php echo $product['PI_ImagePath'] ? htmlspecialchars($product['PI_ImagePath']) : 'assets/images/no-image.jpg'; ?>" 
                                     class="card-img-top w-100 h-100" style="object-fit: cover;" 
                                     alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>">
                                <div class="position-absolute top-0 end-0 m-3">
                                    <span class="badge bg-success">Available</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title fw-bold"><?php echo htmlspecialchars(substr($product['Prod_Name'], 0, 40)); ?><?php echo strlen($product['Prod_Name']) > 40 ? '...' : ''; ?></h6>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['Cat_Name']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="price-badge">
                                        ₱<?php echo number_format($product['Prod_RentalPrice'], 2); ?> 
                                        <small>/<?php echo htmlspecialchars($product['Prod_PriceType']); ?></small>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="owner-badge">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(substr($product['User_Name'], 0, 12)); ?>
                                    </span>
                                    <a href="product.php?id=<?php echo $product['ProductID']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-box me-2"></i>Featured products are being curated. Check back soon for amazing rental deals!
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="browse.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-th-large me-2"></i>View All Rental Items
                </a>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 cta-section text-white">
        <div class="container text-center position-relative">
            <div class="animate-on-scroll">
                <h2 class="mb-4 fw-bold">Start Earning with Your Unused Items</h2>
                <p class="lead mb-4">Have items sitting unused at home? Turn them into a steady income source by renting them out to people in your community.</p>
                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <i class="fas fa-camera fa-2x mb-2"></i>
                                <p class="small">Photo & List</p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <i class="fas fa-handshake fa-2x mb-2"></i>
                                <p class="small">Meet & Rent</p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                <p class="small">Earn Money</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if(!$auth->isLoggedIn()): ?>
                    <a href="register.php?type=owner" class="btn btn-warning btn-lg me-3">
                        <i class="fas fa-store me-2"></i>Become an Owner
                    </a>
                    <a href="register.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Sign Up as Renter
                    </a>
                <?php else: ?>
                    <?php if($_SESSION['user_role'] == 2): ?>
                        <a href="owner/upgrade.php" class="btn btn-warning btn-lg">
                            <i class="fas fa-arrow-up me-2"></i>Upgrade to Owner Account
                        </a>
                    <?php else: ?>
                        <a href="owner/dashboard.php" class="btn btn-warning btn-lg">
                            <i class="fas fa-tachometer-alt me-2"></i>Go to Owner Dashboard
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-3">
                        <i class="fas fa-home me-2"></i>RentHub PH
                    </h4>
                    <p class="mb-3">The Philippines' premier rental marketplace. Connecting item owners with renters across the archipelago. Rent anything, anytime, anywhere.</p>
                    <div class="d-flex">
                        <a href="#" class="text-white-50 me-4 fs-5"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white-50 me-4 fs-5"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white-50 me-4 fs-5"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white-50 fs-5"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Company</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="about.php" class="text-white-50 text-decoration-none">About RentHub PH</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-white-50 text-decoration-none">Contact Us</a></li>
                        <li class="mb-2"><a href="careers.php" class="text-white-50 text-decoration-none">Careers</a></li>
                        <li class="mb-2"><a href="press.php" class="text-white-50 text-decoration-none">Press & Media</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="help.php" class="text-white-50 text-decoration-none">Help Center</a></li>
                        <li class="mb-2"><a href="safety.php" class="text-white-50 text-decoration-none">Safety Guidelines</a></li>
                        <li class="mb-2"><a href="terms.php" class="text-white-50 text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="privacy.php" class="text-white-50 text-decoration-none">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Community</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="blog.php" class="text-white-50 text-decoration-none">RentHub Blog</a></li>
                        <li class="mb-2"><a href="forum.php" class="text-white-50 text-decoration-none">Community Forum</a></li>
                        <li class="mb-2"><a href="events.php" class="text-white-50 text-decoration-none">Local Events</a></li>
                        <li class="mb-2"><a href="newsletter.php" class="text-white-50 text-decoration-none">Newsletter</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Resources</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="pricing.php" class="text-white-50 text-decoration-none">Pricing Guide</a></li>
                        <li class="mb-2"><a href="insurance.php" class="text-white-50 text-decoration-none">Insurance Info</a></li>
                        <li class="mb-2"><a href="api.php" class="text-white-50 text-decoration-none">Developer API</a></li>
                        <li class="mb-2"><a href="mobile.php" class="text-white-50 text-decoration-none">Mobile App</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 opacity-25">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 RentHub PH. All rights reserved. Made with ❤️ in the Philippines.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 small">Empowering Filipino communities through sharing economy</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNavbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.animation = entry.target.style.animation || 'fadeInUp 0.8s ease forwards';
                }
            });
        }, observerOptions);

        // Observe all elements with animate-on-scroll class
        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Search form enhancement
        document.querySelector('form[action="search.php"]').addEventListener('submit', function(e) {
            const input = this.querySelector('input[name="q"]');
            if (input.value.trim() === '') {
                e.preventDefault();
                input.focus();
                input.style.borderColor = '#dc3545';
                setTimeout(() => {
                    input.style.borderColor = '';
                }, 2000);
            }
        });

        // Category card click tracking
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                // You can add analytics tracking here
                console.log('Category clicked:', this.querySelector('.card-title').textContent);
            });
        });
    </script>
</body>
</html>