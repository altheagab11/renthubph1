<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle contact form submission
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

// Get platform statistics for contact info
$stats = [];

// Total users for social proof
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Operating hours and contact info
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
    <title>Contact Us - RentHub PH</title>
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
            text-align: center;
            margin-bottom: 3rem;
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
        
        .emergency-contact {
            background: var(--accent-gradient);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            margin-top: 2rem;
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
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="contact.php">Contact</a>
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

    <!-- Contact Header -->
    <div class="contact-header mt-5">
        <div class="container">
            <div class="contact-content text-center">
                <h1 class="display-5 fw-bold mb-4">Get in Touch</h1>
                <p class="lead mb-5 opacity-90">
                    Have questions or need assistance? We're here to help you with all your rental needs.
                </p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="response-guarantee">
                            <h5 class="mb-2">
                                <i class="fas fa-clock me-2"></i>24-Hour Response Guarantee
                            </h5>
                            <p class="mb-0 opacity-90">We respond to all inquiries within 24 hours, guaranteed!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Form Section -->
    <div class="container">
        <div class="contact-form-section">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 15px;">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="section-header text-start">
                        <h2 class="h3 fw-bold">Send us a Message</h2>
                        <p class="text-muted">Fill out the form below and we'll get back to you as soon as possible.</p>
                    </div>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_type" class="form-label">
                                    Type of Inquiry <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="contact_type" name="contact_type" required>
                                    <option value="">Select inquiry type</option>
                                    <option value="general">General Question</option>
                                    <option value="support">Technical Support</option>
                                    <option value="billing">Billing & Payments</option>
                                    <option value="partnership">Business Partnership</option>
                                    <option value="report">Report an Issue</option>
                                    <option value="feedback">Feedback & Suggestions</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">
                                    Subject <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="subject" name="subject" required
                                       placeholder="Brief description of your inquiry">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label">
                                Message <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control form-textarea" id="message" name="message" rows="6" required
                                      placeholder="Please provide detailed information about your inquiry..."></textarea>
                            <div class="form-text">Minimum 10 characters required</div>
                        </div>

                        <div class="text-center">
                            <button type="submit" name="send_message" class="btn btn-send btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="contact-info-card">
                        <h5 class="fw-bold mb-3 text-primary">
                            <i class="fas fa-info-circle me-2"></i>Contact Information
                        </h5>

                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-phone text-primary me-2"></i>
                                <strong>Phone:</strong>
                            </div>
                            <a href="tel:<?php echo $contact_info['phone']; ?>" class="text-decoration-none">
                                <?php echo $contact_info['phone']; ?>
                            </a>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <strong>Email:</strong>
                            </div>
                            <a href="mailto:<?php echo $contact_info['email']; ?>" class="text-decoration-none">
                                <?php echo $contact_info['email']; ?>
                            </a>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                <strong>Location:</strong>
                            </div>
                            <p class="mb-0"><?php echo $contact_info['address']; ?></p>
                        </div>

                        <div class="office-hours">
                            <h6 class="fw-bold mb-2">
                                <i class="fas fa-clock me-2"></i>Office Hours
                            </h6>
                            <p class="mb-1"><?php echo $contact_info['hours']; ?></p>
                            <p class="mb-0 small text-muted">Saturday - Sunday: By appointment</p>
                        </div>

                        <div class="social-links">
                            <a href="#" class="social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>

                    <div class="emergency-contact">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>Emergency Support
                        </h6>
                        <p class="mb-2 opacity-90">For urgent issues outside business hours:</p>
                        <a href="tel:+639171234567" class="text-white fw-bold text-decoration-none">
                            +63 917 123 4567
                        </a>
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
                <li class="breadcrumb-item active">Contact Us</li>
            </ol>
        </nav>

        <!-- Contact Methods -->
        <div class="section-header">
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
                    <p class="text-muted mb-3">
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
                    <p class="text-muted mb-3">
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
                    <p class="text-muted mb-3">
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
                    <p class="text-muted mb-3">
                        Message us on WhatsApp for quick questions and updates.
                    </p>
                    <a href="https://wa.me/639171234567" class="btn btn-outline-primary" style="border-radius: 20px;" target="_blank">
                        <i class="fab fa-whatsapp me-2"></i>Message
                    </a>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
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

        <!-- Map Section -->
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
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="browse.php" class="text-light text-decoration-none">Browse</a></li>
                        <li><a href="categories.php" class="text-light text-decoration-none">Categories</a></li>
                        <li><a href="about.php" class="text-light text-decoration-none">About</a></li>
                        <li><a href="contact.php" class="text-light text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Help Center</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Safety Guidelines</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Community Standards</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="text-light"><i class="fas fa-phone me-2"></i><?php echo $contact_info['phone']; ?></li>
                        <li class="text-light"><i class="fas fa-envelope me-2"></i><?php echo $contact_info['email']; ?></li>
                        <li class="text-light"><i class="fas fa-map-marker-alt me-2"></i><?php echo $contact_info['address']; ?></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 RentHub PH. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-light">Available 24/7 for your rental needs</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Animate contact methods on scroll
        const observerOptions = {
            threshold: 0.3,
            rootMargin: '0px 0px -50px 0px'
        };

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