<?php
session_start();
require_once 'config/db.php';

$errors = [];
$success = false;
$order_id = null;

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $shipping_address = trim($_POST['shipping_address']);
    $billing_address = trim($_POST['billing_address']) ?: $shipping_address;
    $payment_method = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (!$user_id) {
        if (empty($name)) $errors[] = "Name is required";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
        if (empty($phone)) $errors[] = "Phone number is required";
    }
    if (empty($shipping_address)) $errors[] = "Shipping address is required";
    if (!in_array($payment_method, ['cash', 'bkash', 'nagad', 'card', 'bank_transfer'])) {
        $errors[] = "Invalid payment method";
    }

    // Fetch cart items
    $cart_items = [];
    $cart_subtotal = 0;
    $vat_rate = 0.15; // 15% VAT
    try {
        // Get user's cart
        $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch();

        if ($cart) {
            // Fetch cart items with product details
            $stmt = $pdo->prepare("
                SELECT ci.product_id, ci.quantity, p.display_name, p.base_price, p.discount_price, p.stock_quantity
                FROM CartItems ci
                JOIN Products p ON ci.product_id = p.product_id
                WHERE ci.cart_id = ?
            ");
            $stmt->execute([$cart['cart_id']]);
            $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Validate stock and calculate subtotal
            foreach ($cart_items as $item) {
                if ($item['quantity'] > $item['stock_quantity']) {
                    $errors[] = "Insufficient stock for {$item['display_name']}. Available: {$item['stock_quantity']}";
                }
                $price = $item['discount_price'] ?? $item['base_price'];
                $cart_subtotal += $price * $item['quantity'];
            }
        } else {
            $errors[] = "Cart not found";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }

    if (empty($cart_items)) {
        $errors[] = "Your cart is empty";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Create or update customer
            $customer_id = null;
            if ($user_id) {
                // Check if customer exists
                $stmt = $pdo->prepare("SELECT customer_id FROM Customers WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $customer = $stmt->fetch();
                if ($customer) {
                    $customer_id = $customer['customer_id'];
                }
            } else {
                // Create new customer for guest
                $stmt = $pdo->prepare("
                    INSERT INTO Customers (user_id, full_name, phone_number, shipping_address, billing_address)
                    VALUES (NULL, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $phone, $shipping_address, $billing_address]);
                $customer_id = $pdo->lastInsertId();
            }

            // Calculate order totals
            $vat_amount = round($cart_subtotal * $vat_rate, 2);
            $total_amount = $cart_subtotal + $vat_amount;

            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO Orders (
                    customer_id, shipping_address, billing_address,
                    subtotal, vat_amount, total_amount,
                    payment_method, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $customer_id, $shipping_address, $billing_address,
                $cart_subtotal, $vat_amount, $total_amount,
                $payment_method, $notes
            ]);
            $order_id = $pdo->lastInsertId();

            // Save order items
            foreach ($cart_items as $item) {
                $price = $item['discount_price'] ?? $item['base_price'];
                $discount_percentage = $item['discount_price'] ? 
                    (($item['base_price'] - $item['discount_price']) / $item['base_price']) * 100 : 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO OrderDetails (
                        order_id, product_id, quantity, unit_price, discount_percentage
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $price, $discount_percentage]);

                // Update stock
                $stmt = $pdo->prepare("
                    UPDATE Products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE product_id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }

            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM CartItems WHERE cart_id = ?");
            $stmt->execute([$cart['cart_id']]);
            $stmt = $pdo->prepare("DELETE FROM Cart WHERE cart_id = ?");
            $stmt->execute([$cart['cart_id']]);

            // Commit transaction
            $pdo->commit();
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Order processing failed: " . $e->getMessage();
            error_log("Order Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Brick Field</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Brick Field E-Commerce</h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="cart.php">Cart<span id="cart-count">0</span></a></li>
                <li><a href="dashboard.php">My Account</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <?php if ($success): ?>
            <div class="success-message">
                <h2>Order #<?= $order_id ?> Placed Successfully!</h2>
                <p>Thank you for your purchase. We'll process your order shortly.</p>
                <div class="order-summary">
                    <p><strong>Total Amount:</strong> ৳<?= number_format($total_amount, 2) ?></p>
                    <p><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $payment_method)) ?></p>
                    <p><strong>Shipping Address:</strong> <?= htmlspecialchars($shipping_address) ?></p>
                </div>
                <a href="products.php" class="btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="error-message">
                <h2>Order Failed</h2>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="checkout.php" class="btn">Back to Checkout</a>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>© <?= date('Y') ?> Brick Field E-Commerce. All rights reserved.</p>
    </footer>
</body>
</html>