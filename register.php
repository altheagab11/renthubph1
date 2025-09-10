<?php
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';
$success = '';
$user_type = isset($_GET['type']) ? $_GET['type'] : 'renter';

if($_POST) {
    $role = ($_POST['user_type'] == 'owner') ? 3 : 2;
    
    if($auth->register($_POST['name'], $_POST['email'], $_POST['password'], $_POST['phone'], $role)) {
        $success = "Registration successful! You can now login.";
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
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
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
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-success btn-sm">Login Now</a>
                    </div>
                </div>
            <?php else: ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">I want to:</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="user_type" id="renter" value="renter" <?php echo $user_type == 'renter' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary w-100" for="renter">
                                <i class="fas fa-search"></i><br>Rent Items
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="user_type" id="owner" value="owner" <?php echo $user_type == 'owner' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success w-100" for="owner">
                                <i class="fas fa-store"></i><br>Rent Out Items
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+63">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <?php endif; ?>

            <hr class="my-4">

            <div class="text-center">
                <p class="mb-2">Already have an account?</p>
                <a href="login.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>

            <div class="text-center mt-3">
                <a href="index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left"></i> Back to Homepage
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>