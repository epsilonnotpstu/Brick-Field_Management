 <?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    // Get user's cart
    $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        echo json_encode(['count' => 0]);
        exit;
    }
    
    // Get item count
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM CartItems WHERE cart_id = ?");
    $stmt->execute([$cart['cart_id']]);
    $result = $stmt->fetch();
    
    echo json_encode(['count' => $result['total'] ?? 0]);
    
} catch (PDOException $e) {
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>