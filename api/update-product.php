<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireRole([3]); // Owner only

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['ProductID'] ?? null;
    if (!$product_id) {
        $response['message'] = 'Missing ProductID.';
        echo json_encode($response);
        exit;
    }

    // Update products table
    $fields = [
        'Prod_Name', 'Prod_Brand', 'Prod_Model', 'Prod_Condition', 'CategoryID',
        'Prod_Description', 'Prod_RentalPrice', 'Prod_PriceType', 'Prod_SecurityDeposit',
        'Prod_MinRentalDuration', 'Prod_MaxRentalDuration'
    ];
    $set = [];
    $params = [];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $set[] = "$field = ?";
            $params[] = $_POST[$field];
        }
    }
    $params[] = $product_id;
    $params[] = $user_id;
    $sql = "UPDATE products SET ".implode(', ', $set)." WHERE ProductID = ? AND OwnerID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // Update product_locations
    $loc_fields = ['AddressID', 'PL_PickupAvailable', 'PL_DeliveryAvailable', 'PL_DeliveryRadius', 'PL_DeliveryFee'];
    $loc_set = [];
    $loc_params = [];
    foreach ($loc_fields as $field) {
        if (isset($_POST[$field])) {
            $loc_set[] = "$field = ?";
            $loc_params[] = $_POST[$field];
        }
    }
    $loc_params[] = $product_id;
    $loc_sql = "UPDATE product_locations SET ".implode(', ', $loc_set)." WHERE ProductID = ?";
    $loc_stmt = $conn->prepare($loc_sql);
    $loc_stmt->execute($loc_params);

    // Handle removed images
    if (!empty($_POST['removed_images'])) {
        $removed = json_decode($_POST['removed_images'], true);
        if (is_array($removed)) {
            foreach ($removed as $imgPath) {
                $del_stmt = $conn->prepare("DELETE FROM product_images WHERE ProductID = ? AND PI_ImagePath = ?");
                $del_stmt->execute([$product_id, $imgPath]);
                // Delete file from uploads folder (ensure correct path)
                $file = __DIR__ . '/../' . $imgPath;
                if (file_exists($file)) @unlink($file);
            }
            // If main image was deleted, set another as main
            $mainCheck = $conn->prepare("SELECT COUNT(*) FROM product_images WHERE ProductID = ? AND PI_IsMain = 1");
            $mainCheck->execute([$product_id]);
            if ($mainCheck->fetchColumn() == 0) {
                $setMain = $conn->prepare("UPDATE product_images SET PI_IsMain = 1 WHERE ProductID = ? ORDER BY PI_UploadedAt ASC LIMIT 1");
                $setMain->execute([$product_id]);
            }
        }
    }

    // Handle new image uploads
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = '../uploads/products/';
        // Get current max image order
        $order_stmt = $conn->prepare("SELECT MAX(PI_ImageOrder) FROM product_images WHERE ProductID = ?");
        $order_stmt->execute([$product_id]);
        $img_order = (int)$order_stmt->fetchColumn();
        // Check if there is a main image
        $main_stmt = $conn->prepare("SELECT COUNT(*) FROM product_images WHERE ProductID = ? AND PI_IsMain = 1");
        $main_stmt->execute([$product_id]);
        $has_main = $main_stmt->fetchColumn() > 0;
        foreach ($_FILES['images']['tmp_name'] as $idx => $tmp_name) {
            $name = basename($_FILES['images']['name'][$idx]);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $new_name = 'product_' . $product_id . '_' . rand(1000,9999) . '_' . time() . '.' . $ext;
            $target = $upload_dir . $new_name;
            if (move_uploaded_file($tmp_name, $target)) {
                $img_order++;
                $is_main = (!$has_main && $idx == 0) ? 1 : 0;
                if ($is_main) $has_main = true;
                $img_stmt = $conn->prepare("INSERT INTO product_images (ProductID, PI_ImagePath, PI_IsMain, PI_ImageOrder, PI_UploadedAt) VALUES (?, ?, ?, ?, NOW())");
                $img_stmt->execute([$product_id, 'uploads/products/' . $new_name, $is_main, $img_order]);
            }
        }
    }

    $response['success'] = true;
}
header('Content-Type: application/json');
echo json_encode($response);
