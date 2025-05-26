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
// Fetch average rating and review count
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(rating) as rating_count FROM CustomerRatings WHERE product_id = ?");
$stmt->execute([$product_id]);
$rating_data = $stmt->fetch();
$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$rating_count = $rating_data['rating_count'];

// Fetch all reviews
$stmt = $pdo->prepare("
    SELECT cr.rating, cr.review, cr.created_at, u.username 
    FROM CustomerRatings cr 
    JOIN Users u ON cr.user_id = u.user_id 
    WHERE cr.product_id = ? 
    ORDER BY cr.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();
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
            <div class="productdetails">
                <h2><?php echo $product['display_name']; ?></h2>
                <p><strong>Type:</strong> <?php echo $product['type_name']; ?></p>
                <p><strong>Size:</strong> <?php echo $product['size']; ?></p>
                <p><strong>Price:</strong> ৳<?php echo $product['base_price']; ?></p>
                <p><strong>Minimum Order:</strong> <?php echo $product['min_order_quantity']; ?> pieces</p>
                <p><?php echo $product['description']; ?></p>
                <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="btn">Add to Cart</button>
                
            </div>
             <div class="ratings">
                <h1>Customer Ratings</h1>
                <p>Average Rating: <?php echo $avg_rating ? $avg_rating . " ($rating_count reviews)" : "No ratings yet"; ?></p>
                <?php if ($rating_count > 0): ?>
                    <div class="reviews">
                        <h2>Reviews</h2>
                        <ul>
                            <?php foreach ($reviews as $review): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($review['username']); ?> (<?php echo $review['rating']; ?> Star<?php echo $review['rating'] > 1 ? 's' : ''; ?>)</strong>
                                    <p><?php echo htmlspecialchars($review['review'] ?: 'No review provided'); ?></p>
                                    <small><?php echo $review['created_at']; ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p>No reviews available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Brick Field E-Commerce. All rights reserved.</p>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>