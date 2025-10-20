<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Get categories with product counts
$query = "SELECT c.*, COUNT(p.ProductID) as product_count,
          (SELECT COUNT(*) FROM bookings b JOIN products p2 ON b.ProductID = p2.ProductID WHERE p2.CategoryID = c.CategoryID AND b.Book_Status = 'Completed') as booking_count
          FROM categories c
          LEFT JOIN products p ON c.CategoryID = p.CategoryID AND p.Prod_Status = 'Active' AND p.Prod_Availability = 1
          GROUP BY c.CategoryID
          ORDER BY product_count DESC, c.Cat_Name ASC";

$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products from each category
$featured_by_category = [];
foreach($categories as $category) {
    if($category['product_count'] > 0) {
        $query = "SELECT p.*, pi.PI_ImagePath, u.User_Name as Owner_Name,
                  (SELECT AVG(Rev_Rating) FROM reviews r JOIN bookings b ON r.BookingID = b.BookingID WHERE b.ProductID = p.ProductID) as avg_rating
                  FROM products p
                  LEFT JOIN product_images pi ON p.ProductID = pi.ProductID AND pi.PI_IsMain = 1
                  JOIN user_accounts u ON p.OwnerID = u.UserID
                  WHERE p.CategoryID = ? AND p.Prod_Status = 'Active' AND p.Prod_Availability = 1
                  ORDER BY p.Prod_CreatedAt DESC
                  LIMIT 3";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $category['CategoryID']);
        $stmt->execute();
        $featured_by_category[$category['CategoryID']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get overall statistics
$stats = [];

// Total categories
$stats['total_categories'] = count($categories);

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Active' AND Prod_Availability = 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Most popular category
$most_popular = !empty($categories) ? $categories[0] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - RentHub PH</title>
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
        
        /* Navbar look aligned with homepage */
        .navbar {
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        .navbar.scrolled {
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.8rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        /* Navbar Sign Up button look (like homepage) */
        .navbar .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 8px 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #fff;
        }
        .navbar .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: #fff;
        }
        
        .categories-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 4rem 0 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .categories-header::before {
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
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.categories { background: var(--primary-gradient); color: white; }
        .stat-card.products { background: var(--secondary-gradient); color: white; }
        .stat-card.bookings { background: var(--info-gradient); color: white; }
        .stat-card.popular { background: var(--warning-gradient); color: white; }
        
        .category-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            background: white;
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .category-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .category-header::before {
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
        
        .category-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .product-mini-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .product-mini-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-mini-card .card-img-top {
            height: 120px;
            object-fit: cover;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 0.8rem;
        }
        
        .price-tag {
            background: var(--primary-gradient);
            color: white;
            border-radius: 10px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
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
        
        .section-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            text-align: center;
            border-left: 4px solid #11998e;
        }
        
        .btn-explore {
            background: var(--primary-gradient);
            border: none;
            border-radius: 15px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-explore:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .empty-category {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-category i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .category-stats {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .quick-stats {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .categories-header {
                padding: 2rem 0 1rem;
            }
            
            .quick-stats {
                margin-top: -2rem;
                padding: 1.5rem;
            }
            
            .category-card {
                margin-bottom: 1.5rem;
            }
            
            .category-header {
                padding: 1.5rem;
            }
            
            .category-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .featured-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navbar-include.php'; ?>

    <!-- Categories Header -->
    <div class="categories-header mt-5">
        <div class="container">
            <div class="row align-items-center" style="position: relative; z-index: 2;">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-3">Explore Categories</h1>
                    <p class="lead opacity-90 mb-0">
                        Discover products organized by category. From electronics to sports equipment, find exactly what you need.
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column align-items-end">
                        <h3 class="mb-1"><?php echo $stats['total_categories']; ?></h3>
                        <span class="opacity-75">Categories Available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="container">
        <div class="quick-stats">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="card stat-card categories">
                        <div class="card-body text-center">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <i class="fas fa-tags fa-2x opacity-75"></i>
                                </div>
                                <div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo $stats['total_categories']; ?></div>
                                    <div class="text-xs font-weight-bold text-uppercase opacity-75">Categories</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="card stat-card products">
                        <div class="card-body text-center">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <i class="fas fa-box fa-2x opacity-75"></i>
                                </div>
                                <div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_products']); ?></div>
                                    <div class="text-xs font-weight-bold text-uppercase opacity-75">Products</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="card stat-card bookings">
                        <div class="card-body text-center">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                                </div>
                                <div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_bookings']); ?></div>
                                    <div class="text-xs font-weight-bold text-uppercase opacity-75">Bookings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card popular">
                        <div class="card-body text-center">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <i class="fas fa-crown fa-2x opacity-75"></i>
                                </div>
                                <div>
                                    <div class="h5 mb-0 font-weight-bold" style="font-size: 1rem;">
                                        <?php echo $most_popular ? htmlspecialchars($most_popular['Cat_Name']) : 'N/A'; ?>
                                    </div>
                                    <div class="text-xs font-weight-bold text-uppercase opacity-75">Most Popular</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Categories</li>
            </ol>
        </nav>

        <!-- Section Header -->
        <div class="section-header">
            <h2 class="text-primary mb-3">
                <i class="fas fa-th-large me-2"></i>Product Categories
            </h2>
            <p class="text-muted mb-0">Browse products by category to find exactly what you're looking for. Each category showcases the latest and most popular items available for rent.</p>
        </div>

        <!-- Categories Grid -->
        <?php if(empty($categories)): ?>
            <div class="empty-category">
                <i class="fas fa-tags"></i>
                <h4 class="text-muted">No categories available</h4>
                <p class="text-muted">Categories will appear here as products are added to the platform.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach($categories as $category): ?>
                <div class="col-lg-6 mb-4">
                    <div class="category-card">
                        <div class="category-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="category-icon">
                                        <i class="fas fa-<?php 
                                            $icons = [
                                                'Electronics' => 'laptop',
                                                'Sports' => 'futbol',
                                                'Tools' => 'tools',
                                                'Vehicles' => 'car',
                                                'Home' => 'home',
                                                'Fashion' => 'tshirt',
                                                'Books' => 'book',
                                                'Music' => 'music',
                                                'Photography' => 'camera',
                                                'Gaming' => 'gamepad'
                                            ];
                                            echo isset($icons[$category['Cat_Name']]) ? $icons[$category['Cat_Name']] : 'tag';
                                        ?>"></i>
                                    </div>
                                    <h4 class="mb-2" style="position: relative; z-index: 2;">
                                        <?php echo htmlspecialchars($category['Cat_Name']); ?>
                                    </h4>
                                    <p class="mb-0 opacity-90" style="position: relative; z-index: 2;">
                                        <?php echo htmlspecialchars($category['Cat_Description'] ?? 'Explore products in this category'); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="category-stats">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h5 class="mb-0 text-primary"><?php echo number_format($category['product_count']); ?></h5>
                                                <small class="text-muted">Products</small>
                                            </div>
                                            <div class="col-6">
                                                <h5 class="mb-0 text-success"><?php echo number_format($category['booking_count']); ?></h5>
                                                <small class="text-muted">Bookings</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body p-4">
                            <?php if($category['product_count'] > 0 && isset($featured_by_category[$category['CategoryID']])): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0 text-primary">
                                        <i class="fas fa-star me-2"></i>Featured Products
                                    </h6>
                                    <a href="browse.php?category=<?php echo $category['CategoryID']; ?>" class="btn-explore">
                                        <i class="fas fa-arrow-right me-1"></i>View All
                                    </a>
                                </div>
                                
                                <div class="featured-grid">
                                    <?php foreach($featured_by_category[$category['CategoryID']] as $product): ?>
                                    <div class="product-mini-card card">
                                        <img src="<?php echo $product['PI_ImagePath'] ? htmlspecialchars($product['PI_ImagePath']) : 'assets/images/no-image.jpg'; ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($product['Prod_Name']); ?>">
                                        <div class="card-body p-2">
                                            <h6 class="card-title mb-1" style="font-size: 0.8rem; line-height: 1.2;">
                                                <?php echo htmlspecialchars(substr($product['Prod_Name'], 0, 20)); ?>...
                                            </h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="price-tag">
                                                    ₱<?php echo number_format($product['Prod_RentalPrice'], 0); ?>
                                                </div>
                                                <?php if($product['avg_rating']): ?>
                                                    <div class="rating-stars">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $product['avg_rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">by <?php echo htmlspecialchars($product['Owner_Name']); ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-category">
                                    <i class="fas fa-box-open"></i>
                                    <h6 class="text-muted">No products yet</h6>
                                    <p class="text-muted small mb-3">Be the first to list products in this category!</p>
                                    <?php if(isset($_SESSION['user_id'])): ?>
                                        <a href="owner/add-product.php" class="btn-explore">
                                            <i class="fas fa-plus me-1"></i>Add Product
                                        </a>
                                    <?php else: ?>
                                        <a href="register.php" class="btn-explore">
                                            <i class="fas fa-user-plus me-1"></i>Join Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Call to Action -->
        <div class="text-center mt-5">
            <div class="card" style="background: var(--info-gradient); color: white; border-radius: 20px;">
                <div class="card-body p-5">
                    <h3 class="mb-3">Can't Find What You're Looking For?</h3>
                    <p class="lead mb-4 opacity-90">
                        Try our advanced search or browse all products to discover amazing rental opportunities.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="browse.php" class="btn btn-light btn-lg" style="border-radius: 25px;">
                            <i class="fas fa-search me-2"></i>Browse All Products
                        </a>
                        <?php if(!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="btn btn-outline-light btn-lg" style="border-radius: 25px;">
                                <i class="fas fa-user-plus me-2"></i>Join RentHub PH
                            </a>
                        <?php else: ?>
                            <a href="owner/add-product.php" class="btn btn-outline-light btn-lg" style="border-radius: 25px;">
                                <i class="fas fa-plus me-2"></i>List Your Product
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect to match homepage
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNavbar');
            if (!navbar) return;
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
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

        // Add loading state to buttons
        document.querySelectorAll('.btn-explore').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.href || this.href.includes('#')) return;
                
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';
                
                // Reset after a delay if page doesn't change
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 3000);
            });
        });

        // Animate stats on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.opacity = '1';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.transform = 'translateY(20px)';
            card.style.opacity = '0';
            card.style.transition = 'all 0.6s ease';
            statsObserver.observe(card);
        });

        // Add hover effects for category cards
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.borderLeft = '4px solid #11998e';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.borderLeft = 'none';
            });
        });
    </script>
</body>
</html>