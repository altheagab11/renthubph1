<?php
class Database {
    private $host = "mysql.hostinger.com";
    private $db_name = "u689218423_renthub";
    private $username = "u689218423_renthub";
    private $password = "@Renthub12345";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>