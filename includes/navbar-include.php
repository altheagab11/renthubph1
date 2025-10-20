<?php
if (!isset($auth)) {
    require_once __DIR__ . '/auth.php';
    $auth = new Auth();
}
?>
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
                    <a class="nav-link fw-semibold<?php echo basename($_SERVER['PHP_SELF']) === 'browse.php' ? ' active' : ''; ?>" href="browse.php">Browse Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold<?php echo basename($_SERVER['PHP_SELF']) === 'categories.php' ? ' active' : ''; ?>" href="categories.php">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold<?php echo basename($_SERVER['PHP_SELF']) === 'discover-us.php' ? ' active' : ''; ?>" href="discover-us.php">Discover Us</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if($auth->isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 1): ?>
                                <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</a></li>
                            <?php elseif(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 2): ?>
                                <li><a class="dropdown-item" href="renter/dashboard.php"><i class="fas fa-user me-2"></i>My Dashboard</a></li>
                            <?php elseif(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 3): ?>
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