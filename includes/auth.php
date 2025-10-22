<?php
session_start();
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    private $last_user_id; // Add this property to store last inserted user ID
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function login($email, $password) {
        $query = "SELECT UserID, User_Name, User_Email, User_Role, User_Password, User_Status FROM user_accounts WHERE User_Email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['User_Password'])) {
                if($row['User_Status'] === 'Inactive') {
                    return 'deactivated'; // Return specific status for deactivated accounts
                }
                if($row['User_Status'] !== 'Active') {
                    return false; // Other non-active statuses
                }
                $_SESSION['user_id'] = $row['UserID'];
                $_SESSION['user_name'] = $row['User_Name'];
                $_SESSION['user_email'] = $row['User_Email'];
                $_SESSION['user_role'] = $row['User_Role'];
                return true;
            }
        }
        return false;
    }
    
    public function register($name, $email, $password, $phone, $role = 2, $photo = null, $birthdate = null, $gender = null) {
        // Check if email already exists
        $query = "SELECT UserID FROM user_accounts WHERE User_Email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return false; // Email already exists
        }
        
        // Insert new user
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO user_accounts (User_Name, User_Email, User_Password, User_Phone, User_Role, User_Photo, User_Birthdate, User_Gender, User_CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->bindParam(2, $email);
            $stmt->bindParam(3, $hashed_password);
            $stmt->bindParam(4, $phone);
            $stmt->bindParam(5, $role);
            $stmt->bindParam(6, $photo);
            $stmt->bindParam(7, $birthdate);
            $stmt->bindParam(8, $gender);
            if ($stmt->execute()) {
                $this->last_user_id = $this->conn->lastInsertId(); // Store the last inserted ID
                return true;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getLastRegisteredUserId() {
        return $this->last_user_id ?? null;
    }
    
    public function logout() {
        session_destroy();
        header("Location: " . $this->getBaseUrl() . "/index.php");
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireAuth() {
        if(!$this->isLoggedIn()) {
            header("Location: " . $this->getBaseUrl() . "/login.php");
            exit();
        }
        
        // Check if user is still active
        $this->checkUserStatus();
    }
    
    public function checkUserStatus() {
        if($this->isLoggedIn()) {
            $query = "SELECT User_Status FROM user_accounts WHERE UserID = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $_SESSION['user_id']);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if($row['User_Status'] !== 'Active') {
                    // User has been deactivated, log them out
                    session_destroy();
                    header("Location: " . $this->getBaseUrl() . "/login.php?deactivated=1");
                    exit();
                }
            } else {
                // User no longer exists, log them out
                session_destroy();
                header("Location: " . $this->getBaseUrl() . "/login.php");
                exit();
            }
        }
    }
    
    public function requireRole($roles) {
        $this->requireAuth();
        if(!in_array($_SESSION['user_role'], $roles)) {
            header("Location: " . $this->getBaseUrl() . "/unauthorized.php");
            exit();
        }
        
        // Additional check for user status
        $this->checkUserStatus();
    }
    
    private function getBaseUrl() {
        // For local development, return the project root
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host . '/';
    }
}
?> 