<?php
require_once '../config_admin/db_admin.php';

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = $_GET['id'];
$stmt = $pdo->prepare("
    SELECT p.*, bt.type_name, bt.size, bt.weight_kg 
    FROM Products p
    JOIN BrickType bt ON p.brick_type_id = bt.brick_type_id
    WHERE p.product_id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php");
    exit();
}

$page_title = $product['display_name'] . " | Brick Field";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css_admin/css_admin.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container product-detail">
        <div class="product-images">
            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['display_name']); ?>">
        </div>
        
        <div class="product-info">
            <h1><?php echo htmlspecialchars($product['display_name']); ?></h1>
            <p class="price">৳<?php echo number_format($product['base_price'], 2); ?></p>
            
            <div class="specs">
                <h3>Specifications</h3>
                <ul>
                    <li><strong>Type:</strong> <?php echo htmlspecialchars($product['type_name']); ?></li>
                    <li><strong>Size:</strong> <?php echo htmlspecialchars($product['size']); ?></li>
                    <li><strong>Weight:</strong> <?php echo htmlspecialchars($product['weight_kg']); ?> kg</li>
                    <li><strong>Minimum Order:</strong> <?php echo $product['min_order_quantity']; ?> pieces</li>
                </ul>
            </div>
            
            <div class="description">
                <h3>Description</h3>
                <p><?php echo htmlspecialchars($product['description']); ?></p>
            </div>
            
            
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>