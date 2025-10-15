<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Read the SQL file
    $sql = file_get_contents('database/add_refunds_table.sql');
    
    // Execute the SQL
    $conn->exec($sql);
    
    echo "Refunds table created successfully!\n";
    
} catch (Exception $e) {
    echo "Error creating refunds table: " . $e->getMessage() . "\n";
}
?>