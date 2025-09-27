<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$query = "SELECT CategoryID, Cat_Name, Cat_Description, ParentCategoryID FROM categories ORDER BY Cat_Name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($categories);
