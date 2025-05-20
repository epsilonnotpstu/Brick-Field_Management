<?php
session_start();
require_once 'config/db.php';

// Redirect if cart is empty
// if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
//     header("Location: cheekout.php");
//     exit();
// }

// Process checkout
$errors = [];
$success = false;
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number']);
    $shipping_address = trim($_POST['shipping_address']);
    $billing_address = trim($_POST['billing_address']) ?: $shipping_address;
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($shipping_address)) $errors[] = "Shipping address is required";
    if (!in_array($payment_method, ['cash', 'bkash', 'nagad', 'card', 'bank_transfer'])) {
        $errors[] = "Invalid payment method";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Calculate order totals
            $subtotal = 0;
            $vat_rate = 0.20; // 20% VAT
            foreach ($_SESSION['cart'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $vat_amount = round($subtotal * $vat_rate, 2);
            $total_amount = $subtotal + $vat_amount;

            // 1. Save/update customer
            $customer_id = $_SESSION['user_id'] ?? null;
            if (!$customer_id) {
                $stmt = $pdo->prepare("INSERT INTO Customers (user_id, full_name, phone_number, shipping_address, billing_address) 
                                     VALUES (NULL, ?, ?, ?, ?)");
                $stmt->execute([$name,  $phone, $shipping_address, $billing_address]);
                $customer_id = $pdo->lastInsertId();
            }

            // 2. Create order
            $stmt = $pdo->prepare("
                INSERT INTO Orders (
                    customer_id, shipping_address, billing_address,
                    subtotal, vat_amount, total_amount, 
                    payment_method, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $customer_id, $shipping_address, $billing_address,
                $subtotal, $vat_amount, $total_amount,
                $payment_method, $notes
            ]);
            $order_id = $pdo->lastInsertId();

            // 3. Save order items
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO OrderDetails (
                        order_id, product_id, quantity, 
                        unit_price, discount_percentage
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id, $product_id, $item['quantity'],
                    $item['price'], $item['discount'] ?? 0
                ]);

                // Update inventory
                $stmt = $pdo->prepare("UPDATE Products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $product_id]);
            }

            // Commit transaction
            $pdo->commit();

            // Clear cart and set success
            unset($_SESSION['cart']);
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Order processing failed: " . $e->getMessage();
            error_log("Checkout Error: " . $e->getMessage());
        }
    }
}

// Calculate cart totals for display
$cart_subtotal = 0;
$cart_vat = 0;
$cart_total = 0;

if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_subtotal += $item['price'] * $item['quantity'];
    }
    $cart_vat = round($cart_subtotal * 0.15, 2);
    $cart_total = $cart_subtotal + $cart_vat;
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
                <!-- <li><a href="../logout.php">Logout</a></li> -->
            </ul>
        </nav>
    </header>

    <main class="container checkout-page">
        <h1>Checkout</h1>

        <?php if ($success): ?>
            <div class="success-message">
                <h2>Order #<?= $order_id ?> Placed Successfully!</h2>
                <p>Thank you for your purchase. We'll process your order shortly.</p>
                <div class="order-summary">
                    <p><strong>Total Amount:</strong> ৳<?= number_format($cart_total, 2) ?></p>
                    <p><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $payment_method)) ?></p>
                </div>
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

                    <form method="POST">
                        <?php if (!isset($_SESSION['user_id'])): ?>
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
                                <input type="tel" id="phone" name="phone" required
                                       value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
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
                        
                        <button type="submit" class="btn">Place Order</button>
                    </form>
                </div>
                
                <div class="order-summary">
                    <h2>Your Order</h2>
                    <div class="summary-items">
                        <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                            <div class="summary-item">
                                <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="item-quantity"><?= $item['quantity'] ?> ×</span>
                                <span class="item-price">৳<?= number_format($item['price'], 2) ?></span>
                                <?php if ($item['discount'] > 0): ?>
                                    <span class="item-discount">(<?= $item['discount'] ?>% off)</span>
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
        <p>&copy; <?php echo date('Y'); ?> Brick Field E-Commerce. All rights reserved.</p>
    </footer>
</body>
</html>