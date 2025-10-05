<?php
// This file should be included on every page to check if user is still active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "SELECT User_Status FROM user_accounts WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row['User_Status'] !== 'Active') {
                // User has been deactivated, destroy session and redirect
                session_destroy();
                
                // Determine the correct path to login.php based on current location
                $currentPath = $_SERVER['PHP_SELF'];
                $pathParts = explode('/', trim($currentPath, '/'));
                
                // Count directory levels to determine relative path
                $levelsUp = count($pathParts) - 1;
                $relativePath = str_repeat('../', $levelsUp);
                
                header("Location: {$relativePath}login.php?deactivated=1");
                exit();
            }
        } else {
            // User no longer exists, destroy session
            session_destroy();
            
            // Determine the correct path to login.php based on current location
            $currentPath = $_SERVER['PHP_SELF'];
            $pathParts = explode('/', trim($currentPath, '/'));
            
            // Count directory levels to determine relative path
            $levelsUp = count($pathParts) - 1;
            $relativePath = str_repeat('../', $levelsUp);
            
            header("Location: {$relativePath}login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Database error, but don't break the page
        error_log("Session check error: " . $e->getMessage());
    }
}
?>