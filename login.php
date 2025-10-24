<?php
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';

// Check if user was redirected due to deactivation or suspension
if(isset($_GET['deactivated']) && $_GET['deactivated'] == '1') {
    $error = "Your account has been deactivated. Please contact the administrator for assistance.";
}
if(isset($_GET['suspended']) && $_GET['suspended'] == '1') {
    $error = "Your account has been suspended due to policy violations. Please contact the administrator for assistance.";
}

if($_POST) {
    $login_result = $auth->login($_POST['email'], $_POST['password']);
    if($login_result === true) {
        $role = $_SESSION['user_role'];
        switch($role) {
            case 1: // Admin
                header("Location: admin/dashboard.php");
                break;
            case 2: // Renter
                header("Location: renter/dashboard.php");
                break;
            case 3: // Both Renter/Owner
                header("Location: owner/dashboard.php");
                break;
            default:
                header("Location: index.php");
        }
        exit();
    } else if($login_result === 'deactivated') {
        $error = "Your account has been deactivated. Please contact the administrator for assistance.";
    } else if($login_result === 'suspended') {
        $error = "Your account has been suspended due to policy violations. Please contact the administrator for assistance.";
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="login-container d-flex align-items-center justify-content-center">
        <div class="login-card bg-white rounded-3 p-4 w-100 mx-3">
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-home"></i> RentHub PH
                </h2>
                <p class="text-muted">Welcome back! Please login to your account.</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div class="text-center">
                    <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
                </div>
            </form>

            <hr class="my-4">

            <div class="text-center">
                <p class="mb-2">Don't have an account?</p>
                <a href="register.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-user-plus"></i> Create Account
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
</body>
</html>