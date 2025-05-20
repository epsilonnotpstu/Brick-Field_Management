<?php
header('Content-Type: application/json');
include '../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$cart = $data['cart'];

$productIds = array_map(function($item) {
    return $item['id'];
}, $cart);

$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$sql = "SELECT product_id, display_name, base_price FROM Products WHERE product_id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($productIds);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map quantities to products
$result = [];
foreach ($products as $product) {
    // Find matching cart item
    $cartItem = null;
    foreach ($cart as $item) {
        if ($item['id'] == $product['product_id']) {
            $cartItem = $item;
            break;
        }
    }
    if ($cartItem) {
        $product['quantity'] = $cartItem['quantity'];
        $result[] = $product;
    }
}

echo json_encode(['items' => $result]);
?>