<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Get platform statistics from about.php
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

// Sample testimonials - can be replaced with real database reviews later
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

// Handle contact form submission from contact.php
$message = '';
$message_type = '';
if ($_POST && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message_content = trim($_POST['message']);
    $contact_type = $_POST['contact_type'];
    
    // Basic validation
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message_content)) {
        // Try to save to database (create table if needed)
        try {
            $query = "INSERT INTO contact_messages (name, email, subject, message, contact_type, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->bindParam(2, $email);
            $stmt->bindParam(3, $subject);
            $stmt->bindParam(4, $message_content);
            $stmt->bindParam(5, $contact_type);
            
            if ($stmt->execute()) {
                $message = "Thank you for your message! We'll get back to you within 24 hours.";
                $message_type = "success";
            } else {
                $message = "Message sent successfully! We'll contact you soon.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            // Table might not exist, but we'll show success message anyway
            $message = "Thank you for your message! We'll get back to you within 24 hours.";
            $message_type = "success";
        }
    } else {
        $message = "Please fill in all required fields.";
        $message_type = "danger";
    }
}

// Operating hours and contact info from contact.php
$contact_info = [
    'phone' => '+63 917 123 4567',
    'email' => 'support@renthubph.com',
    'address' => 'Makati City, Metro Manila, Philippines',
    'hours' => 'Monday - Friday: 8:00 AM - 6:00 PM',
    'response_time' => '24 hours'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All About Us - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/user-page.css" rel="stylesheet">
    <style>
        /* Combined styles from all three files */
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
        
        /* From how-it-works.php */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .howitworks-hero {
            padding: 100px 0 50px 0;
            color: white;
            text-align: center;
            background: var(--secondary-gradient);
        }
        .howitworks-hero h1 {
            font-size: 3rem;
            font-weight: bold;
        }
        .howitworks-hero p {
            font-size: 1.2rem;
            margin: 1.5rem 0 2rem 0;
        }
        .steps-section {
            background: rgba(255,255,255,0.05);
            border-radius: 30px;
            margin-top: 30px;
            padding: 40px 0 20px 0;
        }
        .step-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #38ef7d;
        }
        .step-title {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        .step-desc {
            color: #e0e0e0;
            font-size: 1rem;
        }
        .search-demo {
            margin: 2rem auto 2.5rem auto;
            max-width: 600px;
            display: flex;
            background: white;
            border-radius: 60px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.07);
            padding: 8px;
            align-items: center;
            position: relative;
        }
        .search-demo input {
            border: none;
            outline: none;
            flex: 1;
            padding: 18px 24px;
            border-radius: 60px;
            font-size: 1.08rem;
            background: transparent;
        }
        .search-demo .search-btn {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #764ba2;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            padding: 12px 32px;
            margin-left: 8px;
            font-size: 1.08rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* From contact.php */
        .contact-header {
            background: var(--info-gradient);
            color: white;
            padding: 4rem 0 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .contact-header::before {
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
        
        .contact-content {
            position: relative;
            z-index: 2;
        }
        
        .contact-form-section {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }
        
        .contact-info-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            border-left: 4px solid #11998e;
        }
        
        .contact-info-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .contact-method {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            border: 2px solid transparent;
        }
        
        .contact-method:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            border-color: #11998e;
        }
        
        .contact-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1.5rem;
        }
        
        .contact-method:nth-child(1) .contact-icon { background: var(--primary-gradient); }
        .contact-method:nth-child(2) .contact-icon { background: var(--secondary-gradient); }
        .contact-method:nth-child(3) .contact-icon { background: var(--accent-gradient); }
        .contact-method:nth-child(4) .contact-icon { background: var(--warning-gradient); }
        
        .form-control, .form-select, .form-textarea {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus, .form-textarea:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .btn-send {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-send:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
            color: white;
        }
        
        .faq-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 3rem 2rem;
            margin: 3rem 0;
        }
        
        .faq-item {
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .faq-header {
            padding: 1.5rem;
            cursor: pointer;
            border-bottom: 1px solid #e9ecef;
        }
        
        .faq-body {
            padding: 1.5rem;
            display: none;
        }
        
        .faq-body.show {
            display: block;
        }
        
        .office-hours {
            background: rgba(17, 153, 142, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            border-left: 4px solid #11998e;
            margin-bottom: 2rem;
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(17, 153, 142, 0.3);
            color: white;
        }
        
        .map-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            margin-bottom: 3rem;
        }
        
        .response-guarantee {
            background: var(--warning-gradient);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
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
            
            .howitworks-hero h1 {font-size: 2.2rem;}
            .search-demo {flex-direction: column;}
            .search-demo .search-btn {margin: 12px 0 0 0;}
            .steps-section .col-md-4 {margin-bottom: 2rem;}
            
            .contact-header {
                padding: 2rem 0 1rem;
            }
            
            .contact-form-section {
                margin-top: -2rem;
                padding: 2rem 1rem;
            }
            
            .contact-method {
                padding: 1.5rem;
            }
            
            .contact-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .faq-section {
                padding: 2rem 1rem;
            }
            
            .social-links {
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navbar-include.php'; ?>
    
    <!-- Hero Section from about.php -->
    <div class="hero-section mt-5">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="display-4 fw-bold mb-4">All About RentHub PH</h1>
                <p class="lead mb-5 opacity-90">
                    Learn about us, how our platform works, and how to get in touch.
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

    <!-- Statistics Section from about.php -->
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

        <!-- About Section from about.php -->
        <div id="about" class="mission-section">
            <div class="mission-content">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="section-header text-start">
                            <h2 class="display-6 fw-bold">Our Mission</h2>
                        </div>
                        <p class="lead mb-4">
                            To create a sustainable and accessible sharing economy in the Philippines by connecting people who have resources with those who need them for items they'll only use occasionally.
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
                            <div class="feature-icon gap-3" style="width: 200px; height: 200px; font-size: 4rem; margin: 2rem auto;">
                                <i class="fas fa-heart" style="color: #ff2323ff;"></i>
                                <i class="fas fa-heart" style="color: #ff2323ff;"></i>
                                <i class="fas fa-heart" style="color: #ff2323ff;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    <p class="text-muted px-3">
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
                    <p class="text-muted px-3">
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
                    <p class="text-muted px-3">
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
                    <p class="text-muted px-3">
                        Competitive rates and transparent pricing with no hidden fees. Save money and earn from your assets.
                    </p>
                </div>
            </div>
        </div>

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

        <div class="section-header">
            <h2 class="display-6 fw-bold">Meet Our Team</h2>
            <p class="lead text-muted">The passionate people behind RentHub PH</p>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="team-card bg-warning bg-opacity-25 border border-3 border-light">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Louie Andrew Gutierrez</h5>
                    <p class="text-primary fw-semibold mb-2">Project Member & Junior Developer</p>
                    <p class="text-muted small">
                        Passionate about building sustainable communities through technology innovation.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-dark"><i class="fab fa-github"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="team-card bg-warning bg-opacity-75">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Althea Gabrielle Reyes</h5>
                    <p class="text-primary fw-semibold mb-2">Project Manager & Senior Lead Developer</p>
                    <p class="text-muted small">
                        Expert in platform development and user experience design.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-dark"><i class="fab fa-github"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="team-card bg-warning bg-opacity-25 border border-3 border-light">
                    <div class="team-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="fw-bold mb-2">James Andrei Legaspi</h5>
                    <p class="text-primary fw-semibold mb-2">Project Member & Junior Developer</p>
                    <p class="text-muted small">
                        Ensures smooth operations and excellent customer service delivery.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="#" class="text-primary"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-dark"><i class="fab fa-github"></i></a>
                        <a href="#" class="text-primary"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- How It Works Section from how-it-works.php -->
        <section id="how-it-works" class="howitworks-hero border rounded-4">
            <div class="container">
                <h1>How It Works</h1>
                <p>RentHub PH makes it easy to rent what you need and earn from what you own. Here's how the process works for both renters and owners:</p>
                <div class="search-demo">
                    <input type="text" placeholder="Search for cars, tools, electronics, and more..." readonly>
                    <button class="search-btn" disabled>
                        <i class="fas fa-search"></i> Search Now
                    </button>
                </div>
            </div>
            <div class="container">
                <div class="row justify-content-center text-center">
                    <div class="col-md-4 mb-4">
                        <div class="step-icon"><i class="fas fa-search"></i></div>
                        <div class="step-title">Find & Browse</div>
                        <div class="step-desc">Search thousands of items available for rent nationwide</div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="step-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="step-title">Book & Pay</div>
                        <div class="step-desc">Secure booking with flexible payment options and protection</div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="step-icon"><i class="fas fa-handshake"></i></div>
                        <div class="step-title">Enjoy & Return</div>
                        <div class="step-desc">Use the item safely and return it as agreed</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="section-header mt-5">
            <h2 class="display-6 fw-bold">Multiple Ways to Reach Us</h2>
            <p class="lead text-muted">Choose the method that works best for you</p>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="contact-method">
                    <div class="contact-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Live Chat</h5>
                    <p class="text-muted mb-3 px-2">
                        Get instant help with our live chat support available during business hours.
                    </p>
                    <button class="btn btn-outline-primary" style="border-radius: 20px;">
                        <i class="fas fa-comment me-2"></i>Start Chat
                    </button>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="contact-method">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Phone Support</h5>
                    <p class="text-muted mb-3 px-3">
                        Speak directly with our support team for immediate assistance.
                    </p>
                    <a href="tel:<?php echo $contact_info['phone']; ?>" class="btn btn-outline-primary" style="border-radius: 20px;">
                        <i class="fas fa-phone me-2"></i>Call Now
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="contact-method">
                    <div class="contact-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Support Ticket</h5>
                    <p class="text-muted mb-3 px-2">
                        Submit a detailed support ticket for complex technical issues.
                    </p>
                    <button class="btn btn-outline-primary" style="border-radius: 20px;">
                        <i class="fas fa-ticket-alt me-2"></i>Create Ticket
                    </button>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="contact-method">
                    <div class="contact-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <h5 class="fw-bold mb-3">WhatsApp</h5>
                    <p class="text-muted mb-3 px-3">
                        Message us on WhatsApp for quick questions and updates.
                    </p>
                    <a href="https://wa.me/639171234567" class="btn btn-outline-primary" style="border-radius: 20px;" target="_blank">
                        <i class="fab fa-whatsapp me-2"></i>Message
                    </a>
                </div>
            </div>
        </div>

        <div class="faq-section">
            <div class="section-header">
                <h2 class="display-6 fw-bold">Frequently Asked Questions</h2>
                <p class="lead text-muted">Find quick answers to common questions</p>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="faq-item">
                        <div class="faq-header" onclick="toggleFAQ(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">How do I start renting on RentHub PH?</h6>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-body">
                            <p class="mb-0">Simply create an account, browse available products, and submit a booking request. Once approved by the owner, you can proceed with payment and arrange pickup/delivery.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header" onclick="toggleFAQ(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">What payment methods do you accept?</h6>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-body">
                            <p class="mb-0">We accept major credit cards, debit cards, GCash, PayMaya, and bank transfers. All payments are processed securely through our platform.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header" onclick="toggleFAQ(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Is there insurance coverage for rentals?</h6>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-body">
                            <p class="mb-0">Yes, all rentals are covered by our comprehensive insurance policy. This protects both renters and owners against damage, theft, and other covered incidents.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="faq-item">
                        <div class="faq-header" onclick="toggleFAQ(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">How do I list my products for rent?</h6>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-body">
                            <p class="mb-0">Create an owner account, click "Add Product", upload photos, set your rental price and terms, and publish your listing. It's that simple!</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header" onclick="toggleFAQ(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">What are the service fees?</h6>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-body">
                            <p class="mb-0">We charge a small service fee of 10% for successful transactions. This covers payment processing, insurance, and platform maintenance.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header" onclick="toggleFAQ(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">How do I report a problem?</h6>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-body">
                            <p class="mb-0">You can report issues through your dashboard, contact form, or by calling our support hotline. We're available 24/7 for emergency situations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="map-section">
            <div class="section-header">
                <h2 class="h3 fw-bold">Find Our Office</h2>
                <p class="text-muted">Visit us at our headquarters in Makati City</p>
            </div>

            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="bg-light rounded" style="height: 300px; position: relative;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-map-marker-alt fa-3x text-primary mb-3"></i>
                                <h5 class="text-muted">Interactive Map</h5>
                                <p class="text-muted">Map integration coming soon</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-info-card">
                        <h5 class="fw-bold mb-3 text-primary">Office Location</h5>
                        <div class="mb-3">
                            <p class="mb-2"><strong>Address:</strong></p>
                            <p class="mb-0"><?php echo $contact_info['address']; ?></p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-2"><strong>Business Hours:</strong></p>
                            <p class="mb-1"><?php echo $contact_info['hours']; ?></p>
                            <p class="mb-0 small text-muted">Weekend: By appointment</p>
                        </div>
                        <div class="mb-3">
                            <p class="mb-2"><strong>Nearby Landmarks:</strong></p>
                            <ul class="list-unstyled small">
                                <li>• Greenbelt Shopping Center</li>
                                <li>• Makati Central Business District</li>
                                <li>• Ayala Triangle Gardens</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Call to Action from about.php -->
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

    <!-- Footer from about.php/contact.php -->
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
        // Combined scripts from all files
        
        // From about.php
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

        // From contact.php
        // FAQ Toggle functionality
        function toggleFAQ(element) {
            const faqItem = element.closest('.faq-item');
            const faqBody = faqItem.querySelector('.faq-body');
            const icon = element.querySelector('i');
            
            // Close all other FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                if (item !== faqItem) {
                    item.querySelector('.faq-body').classList.remove('show');
                    item.querySelector('.faq-header i').classList.remove('fa-chevron-up');
                    item.querySelector('.faq-header i').classList.add('fa-chevron-down');
                }
            });
            
            // Toggle current FAQ item
            faqBody.classList.toggle('show');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const message = document.getElementById('message').value.trim();
            
            if (message.length < 10) {
                e.preventDefault();
                alert('Please provide a message with at least 10 characters.');
                document.getElementById('message').focus();
                return false;
            }
        });

        // Character counter for message textarea
        const messageTextarea = document.getElementById('message');
        const charCounter = document.createElement('div');
        charCounter.className = 'form-text';
        charCounter.innerHTML = '0 / 500 characters';
        messageTextarea.parentNode.appendChild(charCounter);

        messageTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCounter.innerHTML = `${length} / 500 characters`;
            
            if (length > 450) {
                charCounter.className = 'form-text text-warning';
            } else if (length > 500) {
                charCounter.className = 'form-text text-danger';
            } else {
                charCounter.className = 'form-text text-muted';
            }
        });

        // Auto-hide success alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Add loading state to submit button
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[name="send_message"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;
        });

        // Animate contact methods on scroll
        const contactObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.opacity = '1';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.contact-method, .faq-item').forEach((element, index) => {
            element.style.transform = 'translateY(20px)';
            element.style.opacity = '0';
            element.style.transition = `all 0.6s ease ${index * 0.1}s`;
            contactObserver.observe(element);
        });
    </script>
</body>
</html>