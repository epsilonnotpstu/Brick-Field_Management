<?php 
include 'config/db.php';

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT p.*, bt.type_name, bt.size FROM Products p 
                      JOIN BrickType bt ON p.brick_type_id = bt.brick_type_id 
                      WHERE p.product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['display_name']; ?> - Brick Field</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Brick Field E-Commerce</h1>
        <nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart <span id="cart-count">0</span></a></li>
        <!-- <li><a href="login.php">Login</a></li> -->
          <li><a href="logout.php">Logout</a></li> 

    </ul>
</nav>
    </header>

    <div class="container">
        <div class="product-detail">
            <img src="<?php echo $product['image_url']; ?>" alt="<?php echo $product['display_name']; ?>">
            <div>
                <h2><?php echo $product['display_name']; ?></h2>
                <p><strong>Type:</strong> <?php echo $product['type_name']; ?></p>
                <p><strong>Size:</strong> <?php echo $product['size']; ?></p>
                <p><strong>Price:</strong> ৳<?php echo $product['base_price']; ?></p>
                <p><strong>Minimum Order:</strong> <?php echo $product['min_order_quantity']; ?> pieces</p>
                <p><?php echo $product['description']; ?></p>
                <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="btn">Add to Cart</button>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Brick Field E-Commerce. All rights reserved.</p>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>