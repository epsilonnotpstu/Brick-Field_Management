<?php include 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Brick Field</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Brick Field E-Commerce</h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="cart.php">Cart</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Your Shopping Cart</h2>
        <div id="cart-items">
            <!-- Cart items will be loaded via JavaScript -->
        </div>
        <div id="cart-total">
            <h3>Total: ৳<span id="total-amount">0</span></h3>
            <a href="cheekout.php" class="btn">Proceed to Checkout</a>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 Brick Field E-Commerce. All rights reserved.</p>
    </footer>

    <script src="js/main.js"></script>
    <script>
        // Load cart items when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
        });
    </script>
</body>
</html>