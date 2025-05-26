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
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>