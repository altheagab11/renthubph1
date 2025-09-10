<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Get platform statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE Prod_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE Book_Status = 'Completed'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Cities served (approximate)
$query = "SELECT COUNT(DISTINCT ua.UA_City) as total FROM user_addresses ua 
          JOIN user_accounts u ON ua.UserID = u.UserID 
          WHERE u.User_Status = 'Active' AND ua.UA_City IS NOT NULL AND ua.UA_City != ''";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['cities_served'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent testimonials (if reviews table exists)
$testimonials = [];
try {
    $query = "SELECT r.Rev_Comment, r.Rev_Rating, u.User_Name, p.Prod_Name
              FROM reviews r
              JOIN bookings b ON r.BookingID = b.BookingID
              JOIN user_accounts u ON b.RenterID = u.UserID
              JOIN products prod ON b.ProductID = prod.ProductID
              WHERE r.Rev_Rating >= 4 AND LENGTH(r.Rev_Comment) > 50
              ORDER BY r.Rev_CreatedAt DESC
              LIMIT 6";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Reviews table might not exist, use sample testimonials
    $testimonials = [
        [
            'Rev_Comment' => 'Amazing platform! Found exactly what I needed for my event. The owner was very helpful and the product was in perfect condition.',
            'Rev_Rating' => 5,
            'User_Name' => 'Maria Santos',
            'Prod_Name' => 'Professional Camera'
        ],
        [
            'Rev_Comment' => 'Great experience renting sports equipment. Easy booking process and fair pricing. Will definitely use again!',
            'Rev_Rating' => 5,
            'User_Name' => 'John Dela Cruz',
            'Prod_Name' => 'Mountain Bike'
        ],
        [
            'Rev_Comment' => 'RentHub PH made it so convenient to find tools for my home project. Saved me money instead of buying expensive equipment.',
            'Rev_Rating' => 4,
            'User_Name' => 'Ana Reyes',
            'Prod_Name' => 'Power Tools Set'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - RentHub PH</title>
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
        
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 6rem 0 4rem;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
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
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 2rem;
            background: white;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card .stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .stat-card.users .stat-icon { background: var(--primary-gradient); }
        .stat-card.products .stat-icon { background: var(--secondary-gradient); }
        .stat-card.bookings .stat-icon { background: var(--info-gradient); }
        .stat-card.cities .stat-icon { background: var(--warning-gradient); }
        
        .stats-section {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-top: -4rem;
            position: relative;
            z-index: 10;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
        }
        
        .section-header h2 {
            color: #11998e;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }
        
        .section-header h2::after {
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
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            text-align: center;
            margin-bottom: 2rem;
            border: 2px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: #11998e;
        }
        
        .feature-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin: 0 auto 1.5rem;
            position: relative;
        }
        
        .feature-card:nth-child(1) .feature-icon { background: var(--primary-gradient); }
        .feature-card:nth-child(2) .feature-icon { background: var(--secondary-gradient); }
        .feature-card:nth-child(3) .feature-icon { background: var(--info-gradient); }
        .feature-card:nth-child(4) .feature-icon { background: var(--accent-gradient); }
        
        .mission-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 4rem 2rem;
            margin: 4rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .mission-section::before {
            content: '';
            position: absolute;
            top: -30%;
            left: -10%;
            width: 50%;
            height: 150%;
            background: rgba(17, 153, 142, 0.05);
            border-radius: 50%;
            transform: rotate(-10deg);
        }
        
        .mission-content {
            position: relative;
            z-index: 2;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            position: relative;
            border-left: 4px solid #11998e;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: -10px;
            left: 20px;
            font-size: 4rem;
            color: #11998e;
            opacity: 0.3;
            font-family: serif;
        }
        
        .rating-stars {
            color: #ffc107;
            margin-bottom: 1rem;
        }
        
        .team-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .team-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(17, 153, 142, 0.2);
        }
        
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .value-item {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-left: 4px solid #11998e;
            transition: all 0.3s ease;
        }
        
        .value-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .value-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .cta-section {
            background: var(--info-gradient);
            color: white;
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
            margin: 4rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
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
        
        .cta-content {
            position: relative;
            z-index: 2;
        }
        
        .btn-cta {
            background: white;
            color: #4facfe;
            border: none;
            border-radius: 25px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
        }
        
        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255,255,255,0.3);
            color: #4facfe;
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
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .hero-section {
                padding: 3rem 0 2rem;
            }
            
            .stats-section {
                margin-top: -2rem;
                padding: 2rem 1rem;
            }
            
            .feature-card,
            .testimonial-card,
            .team-card {
                padding: 1.5rem;
            }
            
            .mission-section,
            .cta-section {
                padding: 2rem 1rem;
            }
            
            .values-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
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
                        <a class="nav-link" href="browse.php">Browse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="about.php">About</a>
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

    <!-- Hero Section -->
    <div class="hero-section mt-5">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="display-4 fw-bold mb-4">About RentHub PH</h1>
                <p class="lead mb-5 opacity-90">
                    Revolutionizing the way Filipinos access and share resources through our innovative rental marketplace platform.
                </p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <p class="fs-5 opacity-75">
                            We believe in the power of sharing economy to create sustainable communities where everyone can access what they need without the burden of ownership.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <div class="container">
        <div class="stats-section">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card users">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="fw-bold text-primary"><?php echo number_format($stats['total_users']); ?>+</h3>
                            <p class="text-muted mb-0">Active Users</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card products">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <h3 class="fw-bold text-primary"><?php echo number_format($stats['total_products']); ?>+</h3>
                            <p class="text-muted mb-0">Products Listed</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card bookings">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h3 class="fw-bold text-primary"><?php echo number_format($stats['total_bookings']); ?>+</h3>
                            <p class="text-muted mb-0">Successful Rentals</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card cities">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h3 class="fw-bold text-primary"><?php echo number_format($stats['cities_served']); ?>+</h3>
                            <p class="text-muted mb-0">Cities Served</p>
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
                <li class="breadcrumb-item active">About Us</li>
            </ol>
        </nav>

        <!-- Mission Section -->
        <div class="mission-section">
            <div class="mission-content">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="section-header text-start">
                            <h2 class="display-6 fw-bold">Our Mission</h2>
                        </div>
                        <p class="lead mb-4">
                            To create a sustainable and accessible sharing economy in the Philippines by connecting people who have resources with those who need them.
                        </p>
                        <p class="mb-4">
                            We envision a future where every Filipino can access the tools, equipment, and resources they need for their projects, events, and daily life without the financial burden of purchasing items they'll only use occasionally.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>Sustainable Resource Sharing</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>Community Building</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span>Economic Empowerment</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="text-center">
                            <div class="feature-icon" style="width: 200px; height: 200px; font-size: 4rem; margin: 2rem auto;">
                                <i class="fas fa-heart"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="section-header">
            <h2 class="display-6 fw-bold">Why Choose RentHub PH?</h2>
            <p class="lead text-muted">Discover what makes us the leading rental marketplace in the Philippines</p>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Secure & Trusted</h5>
                    <p class="text-muted">
                        Advanced verification system and secure payment processing ensure safe transactions for all users.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5 class="fw-bold mb-3">24/7 Support</h5>
                    <p class="text-muted">
                        Round-the-clock customer support to assist you with any questions or concerns about your rentals.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Easy to Use</h5>
                    <p class="text-muted">
                        Intuitive platform design makes it simple to list products, search for rentals, and manage bookings.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Fair Pricing</h5>
                    <p class="text-muted">
                        Competitive rates and transparent pricing with no hidden fees. Save money while earning from your assets.
                    </p>
                </div>
            </div>
        </div>

        <!-- Values Section -->
        <div class="section-header">
            <h2 class="display-6 fw-bold">Our Core Values</h2>
            <p class="lead text-muted">The principles that guide everything we do</p>
        </div>

        <div class="values-grid">
            <div class="value-item">
                <div class="value-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h5 class="fw-bold mb-2">Trust & Transparency</h5>
                <p class="text-muted mb-0">
                    We believe in building relationships based on honesty, clear communication, and mutual respect between all platform users.
                </p>
            </div>
            
            <div class="value-item">
                <div class="value-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h5 class="fw-bold mb-2">Sustainability</h5>
                <p class="text-muted mb-0">
                    Promoting a circular economy that reduces waste and maximizes the utility of existing resources for a greener future.
                </p>
            </div>
            
            <div class="value-item">
                <div class="value-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h5 class="fw-bold mb-2">Community First</h5>
                <p class="text-muted mb-0">
                    Fostering strong local communities where neighbors help neighbors access the resources they need.
                </p>
            </div>
            
            <div class="value-item">
                <div class="value-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h5 class="fw-bold mb-2">Innovation</h5>
                <p class="text-muted mb-0">
                    Continuously improving our platform with cutting-edge technology to enhance user experience and accessibility.
                </p>
            </div>
        </div>

        <!-- Testimonials Section -->
        <div class="section-header">
            <h2 class="display-6 fw-bold">What Our Users Say</h2>
            <p class="lead text-muted">Real experiences from our amazing community</p>
        </div>

        <div class="row">
            <?php foreach(array_slice($testimonials, 0, 3) as $testimonial): ?>
            <div class="col-lg-4 mb-4">
                <div class="testimonial-card">
                    <div class="rating-stars mb-3">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $testimonial['Rev_Rating'] ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="mb-3"><?php echo htmlspecialchars($testimonial['Rev_Comment']); ?></p>
                    <div class="d-flex align-items-center">
                        <div class="team-avatar me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                            <?php echo strtoupper(substr($testimonial['User_Name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($testimonial['User_Name']); ?></h6>
                            <small class="text-muted">Rented: <?php echo htmlspecialchars($testimonial['Prod_Name']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Team Section -->
        <div class="section-header">
            <h2 class="display-6 fw-bold">Meet Our Team</h2>
            <p class="lead text-muted">The passionate people behind RentHub PH</p>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="team-card">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">John Santos</h5>
                    <p class="text-primary fw-semibold mb-2">Chief Executive Officer</p>
                    <p class="text-muted small">
                        Passionate about building sustainable communities through technology innovation.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="team-card">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Maria Garcia</h5>
                    <p class="text-primary fw-semibold mb-2">Chief Technology Officer</p>
                    <p class="text-muted small">
                        Expert in platform development and user experience design.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-github"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="team-card">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Carlos Reyes</h5>
                    <p class="text-primary fw-semibold mb-2">Head of Operations</p>
                    <p class="text-muted small">
                        Ensures smooth operations and excellent customer service delivery.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="team-card">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Ana Cruz</h5>
                    <p class="text-primary fw-semibold mb-2">Community Manager</p>
                    <p class="text-muted small">
                        Builds and nurtures our vibrant community of renters and owners.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <div class="cta-content">
                <h2 class="display-6 fw-bold mb-4">Ready to Join Our Community?</h2>
                <p class="lead mb-4 opacity-90">
                    Whether you want to rent products or earn money from your unused items, RentHub PH is here to help you succeed.
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="btn-cta">
                            <i class="fas fa-user-plus me-2"></i>Sign Up Now
                        </a>
                        <a href="browse.php" class="btn-cta">
                            <i class="fas fa-search me-2"></i>Browse Products
                        </a>
                    <?php else: ?>
                        <a href="owner/add-product.php" class="btn-cta">
                            <i class="fas fa-plus me-2"></i>List Your Product
                        </a>
                        <a href="browse.php" class="btn-cta">
                            <i class="fas fa-search me-2"></i>Start Renting
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-home text-success me-2"></i>RentHub PH
                    </h5>
                    <p class="text-light">Your trusted platform for renting quality products across the Philippines.</p>
                    <div class="d-flex">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h6 class="fw-bold mb-3">Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="about.php" class="text-light text-decoration-none">About Us</a></li>
                        <li><a href="contact.php" class="text-light text-decoration-none">Contact</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Careers</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Press</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Help Center</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Safety</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Trust & Safety</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Community Guidelines</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Legal</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Privacy Policy</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Terms of Service</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Cookie Policy</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Accessibility</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 RentHub PH. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-light">Made with <i class="fas fa-heart text-danger"></i> in the Philippines</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Observe stat cards
        document.querySelectorAll('.stat-card, .feature-card, .testimonial-card, .team-card').forEach(card => {
            card.style.transform = 'translateY(20px)';
            card.style.opacity = '0';
            card.style.transition = 'all 0.6s ease';
            statsObserver.observe(card);
        });

        // Add staggered animation delay
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.transitionDelay = `${index * 0.1}s`;
        });

        document.querySelectorAll('.feature-card').forEach((card, index) => {
            card.style.transitionDelay = `${index * 0.1}s`;
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

        // Add loading state to CTA buttons
        document.querySelectorAll('.btn-cta').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.href || this.href.includes('#')) return;
                
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                
                // Reset after a delay if page doesn't change
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 3000);
            });
        });
    </script>
</body>
</html>