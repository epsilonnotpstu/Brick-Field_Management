<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    // Get user's cart
    $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        echo json_encode(['items' => []]);
        exit;
    }
    
    // Get cart items with product details
    $stmt = $pdo->prepare("
        SELECT ci.*, p.display_name, p.base_price, p.image_url 
        FROM CartItems ci
        JOIN Products p ON ci.product_id = p.product_id
        WHERE ci.cart_id = ?
    ");
    $stmt->execute([$cart['cart_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['items' => $items]);
    
} catch (PDOException $e) {
    echo json_encode(['items' => [], 'error' => $e->getMessage()]);
}
?>