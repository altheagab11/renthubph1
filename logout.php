<?php
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->logout();

// If for some reason the redirect in logout() doesn't work, add a fallback
header("Location: index.php?logout=success");
exit();
?>