<?php
require_once '../config_admin/db_admin.php';
$page_title = "Our Brick Products | Brick Field";
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
        <h1>Our Brick Products</h1>
        
        <div class="product-filters">
            <form method="get">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="red">Red Bricks</option>
                    <option value="hollow">Hollow Bricks</option>
                </select>
                <input type="submit" value="Filter" class="btn">
            </form>
        </div>

        <div class="product-grid">
            <?php
            $sql = "SELECT * FROM Products WHERE is_available = 1";
            if (isset($_GET['type']) && $_GET['type'] != '') {
                $sql .= " AND brick_type_id IN (SELECT brick_type_id FROM BrickType WHERE type_name LIKE ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%" . $_GET['type'] . "%"]);
            } else {
                $stmt = $pdo->query($sql);
            }
            
            while ($product = $stmt->fetch()):
            ?>
            <div class="product-card">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['display_name']); ?>">
                <h3><?php echo htmlspecialchars($product['display_name']); ?></h3>
                <p class="price">৳<?php echo number_format($product['base_price'], 2); ?></p>
                <p id="quantity">Min Order: <?php echo $product['min_order_quantity']; ?> pieces</p>
                <a href="product_detail.php?id=<?php echo $product['product_id']; ?>" class="btn">View Details</a>
            </div>
            <?php endwhile; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>