<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Fetch cart items
$cart_items = [];
$cart_subtotal = 0;
$cart_vat = 0;
$cart_total = 0;
$vat_rate = 0.15; // 15% VAT as per original cheekout.php

if ($user_id) {
    try {
        // Get user's cart
        $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch();

        if ($cart) {
            // Fetch cart items with product details
            $stmt = $pdo->prepare("
                SELECT ci.product_id, ci.quantity, p.display_name, p.base_price, p.discount_price, p.image_url
                FROM CartItems ci
                JOIN Products p ON ci.product_id = p.product_id
                WHERE ci.cart_id = ?
            ");
            $stmt->execute([$cart['cart_id']]);
            $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            foreach ($cart_items as $item) {
                $price = $item['discount_price'] ?? $item['base_price'];
                $cart_subtotal += $price * $item['quantity'];
            }
            $cart_vat = round($cart_subtotal * $vat_rate, 2);
            $cart_total = $cart_subtotal + $cart_vat;
        }
    } catch (PDOException $e) {
        error_log("Checkout Error: " . $e->getMessage());
        $errors[] = "Failed to load cart: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Brick Field</title>
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
                <li><a href="dashboard.php">My Account</a></li>
            </ul>
        </nav>
    </header>

    <main class="container checkout-page">
        <h1>Checkout</h1>

        <?php if (empty($cart_items)): ?>
            <div class="error-message">
                <p>Your cart is empty. Please add items to proceed.</p>
                <a href="products.php" class="btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="checkout-grid">
                <div class="checkout-form">
                    <h2>Billing & Shipping</h2>

                    <?php if (!empty($errors)): ?>
                        <div class="error-message">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="order.php">
                        <?php if (!$user_id): ?>
                            <div class="form-group">
                                <label for="name">Full Name*</label>
                                <input type="text" id="name" name="name" required
                                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email*</label>
                                <input type="email" id="email" name="email" required
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone*</label>
                                <input type="tel" id="phone" name="phone_number" required
                                       value="<?= isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '' ?>">
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address*</label>
                            <textarea id="shipping_address" name="shipping_address" required><?= 
                                isset($_POST['shipping_address']) ? htmlspecialchars($_POST['shipping_address']) : '' 
                            ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="billing_address">Billing Address (if different)</label>
                            <textarea id="billing_address" name="billing_address"><?= 
                                isset($_POST['billing_address']) ? htmlspecialchars($_POST['billing_address']) : '' 
                            ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Payment Method*</label>
                            <div class="payment-options">
                                <label><input type="radio" name="payment_method" value="cash" <?= 
                                    (!isset($_POST['payment_method']) || $_POST['payment_method'] === 'cash' ? 'checked' : '') 
                                ?>> Cash on Delivery</label>
                                <label><input type="radio" name="payment_method" value="bkash" <?= 
                                    (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bkash' ? 'checked' : '') 
                                ?>> bKash</label>
                                <label><input type="radio" name="payment_method" value="nagad" <?= 
                                    (isset($_POST['payment_method']) && $_POST['payment_method'] === 'nagad' ? 'checked' : '') 
                                ?>> Nagad</label>
                                <label><input type="radio" name="payment_method" value="card" <?= 
                                    (isset($_POST['payment_method']) && $_POST['payment_method'] === 'card' ? 'checked' : '') 
                                ?>> Credit/Debit Card</label>
                                <label><input type="radio" name="payment_method" value="bank_transfer" <?= 
                                    (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer' ? 'checked' : '') 
                                ?>> Bank Transfer</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="notes">Order Notes</label>
                            <textarea id="notes" name="notes"><?= 
                                isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' 
                            ?></textarea>
                        </div>
                        <button type="submit" class="btn"><a href="order.php">Place Order</a></button>
                    </form>
                </div>
                <div class="order-summary">
                    <h2>Your Order</h2>
                    <div class="summary-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="summary-item">
                                <span class="item-name"><?= htmlspecialchars($item['display_name']) ?></span>
                                <span class="item-quantity"><?= $item['quantity'] ?> ×</span>
                                <span class="item-price">৳<?= number_format($item['discount_price'] ?? $item['base_price'], 2) ?></span>
                                <?php if ($item['discount_price'] && $item['discount_price'] < $item['base_price']): ?>
                                    <span class="item-discount">
                                        (<?= number_format((($item['base_price'] - $item['discount_price']) / $item['base_price']) * 100, 2) ?>% off)
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="summary-totals">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>৳<?= number_format($cart_subtotal, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>VAT (15%):</span>
                            <span>৳<?= number_format($cart_vat, 2) ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>৳<?= number_format($cart_total, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>© <?= date('Y') ?> Brick Field E-Commerce. All rights reserved.</p>
    </footer>
</body>
</html>