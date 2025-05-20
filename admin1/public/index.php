<?php
require_once '../config_admin/db_admin.php';
$page_title = "Brick Field - Premium Quality Bricks";
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

    <main class="container">
        <section class="hero">
            <h1>Premium Quality Bricks for Your Construction Needs</h1>
            <p>Manufactured with the finest materials and traditional craftsmanship</p>
            <a href="products.php" class="btn">Browse Our Products</a>
        </section>

        <section class="featured-products">
            <h2>Featured Products</h2>
            <div class="product-grid">
                <?php
                $stmt = $pdo->query("SELECT * FROM Products WHERE is_featured = 1 LIMIT 4");
                while ($product = $stmt->fetch()):
                ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['display_name']); ?>">
                    <h3><?php echo htmlspecialchars($product['display_name']); ?></h3>
                    <p class="price">৳<?php echo number_format($product['base_price'], 2); ?></p>
                    <a href="product_detail.php?id=<?php echo $product['product_id']; ?>" class="btn">View Details</a>
                </div>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="about-teaser">
            <h2>Bangladesh's Trusted Brick Manufacturer Since 1995</h2>
            <p>With over 25 years of experience, we provide the highest quality bricks for residential and commercial projects across the country.</p>
            <a href="about.php" class="btn-outline">Learn More About Us</a>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>