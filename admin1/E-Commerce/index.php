<?php
session_start(); 
include 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brick Field - Home</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
<h1>Brick Field E-Commerce</h1>
<nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <!-- <li><a href="products.php">Products</a></li> -->
        <!-- <li><a href="cart.php">Cart <span id="cart-count">0</span></a></li> -->
        <!-- <li><a href="login.php">Login</a></li> -->
        <!-- <li><a href="login_tst.php">Test_Login</a></li> -->
        <!-- <li><a href="register_tst.php">Register_test</a></li> -->
         <?php if (isset($_SESSION['user_id'])): // Check if user is logged in ?>
                <li><a href="products.php">Products</a></li>
                <li><a href="cart.php">Cart <span id="cart-count">0</span></a></li>
                <li><a href="dashboard.php">My Account</a></li>
                <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <!-- <li><a href="register.php">Register</a></li> -->
            <?php endif; ?>
    </ul>
</nav>
</header>

<div class="container">
<h2>Featured Bricks</h2>
<div class="product-grid">
<?php
$stmt = $pdo->query("SELECT * FROM Products WHERE is_featured = 1 LIMIT 3");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<div class="product-card">';
    echo '<img src="'.$row['image_url'].'" alt="'.$row['display_name'].'">';
    echo '<h3>'.$row['display_name'].'</h3>';
    echo '<p>Price: ৳'.$row['base_price'].'</p>';
    // echo '<a href="product-detail.php?id='.$row['product_id'].'" class="btn">View Details</a>';
    echo '</div>';
}
?>
</div>
</div>

<footer>
<p>&copy; 2023 Brick Field E-Commerce. All rights reserved.</p>
</footer>


<!-- see -->

<script src="js/main.js"></script>
</body>
</html>
