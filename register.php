<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$auth = new Auth();
$error = '';
$success = '';
$user_type = isset($_GET['type']) ? $_GET['type'] : 'renter';

// Initialize database connection for address handling
$database = new Database();
$conn = $database->getConnection();

if($_POST) {
    $role = ($_POST['user_type'] == 'owner') ? 3 : 2;
    
    if($auth->register($_POST['name'], $_POST['email'], $_POST['password'], $_POST['phone'], $role)) {
        // Get the newly registered user ID
        $new_user_id = $auth->getLastRegisteredUserId();
        
        // If address information is provided, save it
        if (isset($_POST['save_address']) && $_POST['save_address'] == '1' && 
            !empty($_POST['street']) && !empty($_POST['barangay']) && 
            !empty($_POST['city']) && !empty($_POST['province'])) {
            
            try {
                $street = trim($_POST['street']);
                $barangay = trim($_POST['barangay']);
                $city = trim($_POST['city']);
                $province = trim($_POST['province']);
                $zipcode = trim($_POST['zipcode']);
                $latitude = $_POST['latitude'] ?? null;
                $longitude = $_POST['longitude'] ?? null;
                $address_type = $_POST['address_type'] ?? 'Home';
                
                $query = "INSERT INTO user_addresses (UserID, UA_Street, UA_Barangay, UA_City, UA_Province, UA_ZipCode, UA_Latitude, UA_Longitude, UA_AddressType, UA_IsDefault, UA_CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $new_user_id);
                $stmt->bindParam(2, $street);
                $stmt->bindParam(3, $barangay);
                $stmt->bindParam(4, $city);
                $stmt->bindParam(5, $province);
                $stmt->bindParam(6, $zipcode);
                $stmt->bindParam(7, $latitude);
                $stmt->bindParam(8, $longitude);
                $stmt->bindParam(9, $address_type);
                $stmt->execute();
                
                $success = "Registration successful with address! You can now login.";
            } catch (PDOException $e) {
                $success = "Registration successful! You can now login. (Address can be added later)";
            }
        } else {
            $success = "Registration successful! You can now login.";
        }
    } else {
        $error = "Registration failed. Email may already exist.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .register-card {
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-radius: 20px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-outline-success {
            border: 2px solid #28a745;
            color: #28a745;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-success:hover {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .input-group-text {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #ced4da;
            color: #667eea;
        }
        
        .alert-danger {
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            border-left: 4px solid #ff5252;
        }
        
        .alert-success {
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-left: 4px solid #28a745;
        }
        
        .register-card {
            border: none;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .text-primary {
            color: #667eea !important;
        }
        
        a.text-decoration-none:hover {
            color: #764ba2 !important;
        }
        
        hr {
            border-color: #e9ecef;
            opacity: 0.5;
        }
        
        .address-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 4px solid #667eea;
        }
        
        .address-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .address-toggle:hover {
            color: #667eea;
        }
        
        .btn-location {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            border: none;
            color: white;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-location:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
            color: white;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
    </style>
</head>
<body>
    <div class="register-container d-flex align-items-center justify-content-center py-5">
        <div class="register-card bg-white rounded-3 p-4 w-100 mx-3">
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-home"></i> RentHub PH
                </h2>
                <p class="text-muted">Join our community and start renting today!</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-light">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Now
                        </a>
                    </div>
                </div>
            <?php else: ?>

            <form method="POST" id="registrationForm">
                <div class="mb-4">
                    <label class="form-label fw-bold">I want to:</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="user_type" id="renter" value="renter" <?php echo $user_type == 'renter' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary w-100 py-3" for="renter">
                                <i class="fas fa-search fa-2x d-block mb-2"></i>
                                <strong>Rent Items</strong>
                                <small class="d-block text-muted">Find and rent products</small>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="user_type" id="owner" value="owner" <?php echo $user_type == 'owner' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success w-100 py-3" for="owner">
                                <i class="fas fa-store fa-2x d-block mb-2"></i>
                                <strong>Rent Out Items</strong>
                                <small class="d-block text-muted">List your products for rent</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   placeholder="Enter your full name">
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="your@email.com">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="+63 9XX XXX XXXX">
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   placeholder="Create a strong password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="text-muted">At least 8 characters with numbers and letters</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your password">
                    </div>
                </div>

                <!-- Address Section -->
                <div class="address-section">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                        <h6 class="mb-0 me-auto">Delivery Address (Optional)</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="includeAddress" name="save_address" value="1">
                            <label class="form-check-label" for="includeAddress">
                                Add address now
                            </label>
                        </div>
                    </div>
                    
                    <small class="text-muted d-block mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Adding your address now will make future rentals faster. You can also add it later.
                    </small>

                    <div id="addressFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="street" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="street" name="street" 
                                       placeholder="House/Unit No., Street Name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="barangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="barangay" name="barangay" 
                                       placeholder="Barangay">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       placeholder="City">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="province" name="province" 
                                       placeholder="Province">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="zipcode" class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" id="zipcode" name="zipcode" 
                                       placeholder="Postal Code">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address_type" class="form-label">Address Type</label>
                                <select class="form-select" id="address_type" name="address_type">
                                    <option value="Home">Home</option>
                                    <option value="Work">Work</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-location btn-sm" id="getCurrentLocation">
                                <i class="fas fa-crosshairs me-2"></i>Use Current Location
                            </button>
                            <small class="text-muted d-block mt-1">
                                Optional: Helps improve delivery accuracy
                            </small>
                        </div>

                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="terms.php" target="_blank" class="text-decoration-none">Terms of Service</a> and <a href="privacy.php" target="_blank" class="text-decoration-none">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-user-plus me-2"></i>Create RentHub PH Account
                </button>
            </form>

            <?php endif; ?>

            <hr class="my-4">

            <div class="text-center">
                <p class="mb-2 text-muted">Already have an account?</p>
                <a href="login.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to RentHub PH
                </a>
            </div>

            <div class="text-center mt-4">
                <a href="index.php" class="text-decoration-none text-muted">
                    <i class="fas fa-arrow-left me-1"></i>Back to Homepage
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength';
            if (strength < 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });

        // Address fields toggle
        document.getElementById('includeAddress').addEventListener('change', function() {
            const addressFields = document.getElementById('addressFields');
            if (this.checked) {
                addressFields.style.display = 'block';
                // Make required fields required
                document.getElementById('street').required = true;
                document.getElementById('barangay').required = true;
                document.getElementById('city').required = true;
                document.getElementById('province').required = true;
            } else {
                addressFields.style.display = 'none';
                // Remove required from address fields
                document.getElementById('street').required = false;
                document.getElementById('barangay').required = false;
                document.getElementById('city').required = false;
                document.getElementById('province').required = false;
            }
        });

        // Get current location
        document.getElementById('getCurrentLocation').addEventListener('click', function() {
            if (navigator.geolocation) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Getting location...';
                this.disabled = true;
                
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        document.getElementById('latitude').value = position.coords.latitude;
                        document.getElementById('longitude').value = position.coords.longitude;
                        
                        this.innerHTML = '<i class="fas fa-check me-2"></i>Location Captured';
                        this.classList.remove('btn-location');
                        this.classList.add('btn-success');
                        
                        setTimeout(() => {
                            this.disabled = false;
                        }, 2000);
                    },
                    (error) => {
                        this.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Location Error';
                        this.classList.remove('btn-location');
                        this.classList.add('btn-danger');
                        this.disabled = false;
                        
                        setTimeout(() => {
                            this.innerHTML = '<i class="fas fa-crosshairs me-2"></i>Use Current Location';
                            this.classList.remove('btn-danger');
                            this.classList.add('btn-location');
                        }, 3000);
                    }
                );
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        });

        // Enhanced form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const includeAddress = document.getElementById('includeAddress').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            // Validate address fields if included
            if (includeAddress) {
                const street = document.getElementById('street').value;
                const barangay = document.getElementById('barangay').value;
                const city = document.getElementById('city').value;
                const province = document.getElementById('province').value;
                
                if (!street || !barangay || !city || !province) {
                    e.preventDefault();
                    alert('Please fill in all required address fields or uncheck "Add address now".');
                    return false;
                }
            }
        });

        // Enhanced input animations
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Auto-hide success alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 10000);
    </script>
</body>
</html>