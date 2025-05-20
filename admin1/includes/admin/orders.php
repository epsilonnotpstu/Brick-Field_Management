<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Ensure only admin can access
require_role('admin');

// Handle Update Order Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    try {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$status, $order_id]);
        $success_message = "Order status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to update order status: " . $e->getMessage();
    }
}

// Handle Cancel Order
if (isset($_GET['cancel_order'])) {
    try {
        $order_id = intval($_GET['cancel_order']);
        $stmt = $conn->prepare("UPDATE Orders SET status = 'cancelled' WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $success_message = "Order cancelled successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to cancel order: " . $e->getMessage();
    }
}

// Handle Add Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    try {
        $order_id = $_POST['order_id'];
        $amount = $_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $transaction_id = $_POST['transaction_id'] ?: null;
        $notes = $_POST['notes'] ?: null;

        // Insert payment
        $stmt = $conn->prepare("
            INSERT INTO Payments (order_id, amount, payment_method, transaction_id, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $amount, $payment_method, $transaction_id, $notes]);

        // Update order payment status
        $stmt = $conn->prepare("
            SELECT SUM(amount) as total_paid
            FROM Payments
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);
        $total_paid = $stmt->fetchColumn();

        $stmt = $conn->prepare("SELECT total_amount FROM Orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $total_amount = $stmt->fetchColumn();

        $payment_status = ($total_paid >= $total_amount) ? 'paid' : ($total_paid > 0 ? 'partial' : 'pending');
        $stmt = $conn->prepare("UPDATE Orders SET payment_status = ? WHERE order_id = ?");
        $stmt->execute([$payment_status, $order_id]);

        $success_message = "Payment recorded successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to record payment: " . $e->getMessage();
    }
}

// Fetch all orders
$orders = $conn->query("
    SELECT o.*, c.full_name, u.email
    FROM Orders o
    JOIN Customers c ON o.customer_id = c.customer_id
    JOIN Users u ON c.user_id = u.user_id
    ORDER BY o.order_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch order details for each order
$order_details = [];
foreach ($orders as $order) {
    $stmt = $conn->prepare("
        SELECT od.*, p.display_name
        FROM OrderDetails od
        JOIN Products p ON od.product_id = p.product_id
        WHERE od.order_id = ?
    ");
    $stmt->execute([$order['order_id']]);
    $order_details[$order['order_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch payments for each order
$order_payments = [];
foreach ($orders as $order) {
    $stmt = $conn->prepare("
        SELECT p.*
        FROM Payments p
        WHERE p.order_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$order['order_id']]);
    $order_payments[$order['order_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders | Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <h2>Order Management</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section class="orders-list">
            <h3>All Orders</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Order Date</th>
                    <th>Total Amount</th>
                    <th>Payment Status</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td data-label="Order ID"><?php echo $order['order_id']; ?></td>
                        <td data-label="Customer"><?php echo htmlspecialchars($order['full_name']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</td>
                        <td data-label="Order Date"><?php echo date('M j, Y H:i', strtotime($order['order_date'])); ?></td>
                        <td data-label="Total Amount">৳<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td data-label="Payment Status"><?php echo ucfirst($order['payment_status']); ?></td>
                        <td data-label="Status">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="update_order_status" value="1">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </form>
                        </td>
                        <td data-label="Actions">
                            <a href="?cancel_order=<?php echo $order['order_id']; ?>" class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to cancel this order?')">
                                Cancel
                            </a>
                        </td>
                    </tr>
                    <!-- Order Details -->
                    <tr class="order-details">
                        <td colspan="7">
                            <div class="order-details-content">
                                <h4>Order Details</h4>
                                <table class="sub-table">
                                    <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Discount (%)</th>
                                        <th>Subtotal</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($order_details[$order['order_id']] as $detail): ?>
                                        <tr>
                                            <td data-label="Product"><?php echo htmlspecialchars($detail['display_name']); ?></td>
                                            <td data-label="Quantity"><?php echo number_format($detail['quantity']); ?></td>
                                            <td data-label="Unit Price">৳<?php echo number_format($detail['unit_price'], 2); ?></td>
                                            <td data-label="Discount"><?php echo number_format($detail['discount_percentage'], 2); ?>%</td>
                                            <td data-label="Subtotal">৳<?php echo number_format($detail['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <!-- Payment Form -->
                                <h4>Add Payment</h4>
                                <form method="POST" class="auth-form inline-form">
                                    <input type="hidden" name="add_payment" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <div class="form-group">
                                        <label>Amount (৳)</label>
                                        <input type="number" step="0.01" name="amount" required min="0">
                                    </div>
                                    <div class="form-group">
                                        <label>Payment Method</label>
                                        <select name="payment_method" required>
                                            <option value="cash">Cash</option>
                                            <option value="bkash">bKash</option>
                                            <option value="nagad">Nagad</option>
                                            <option value="card">Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Transaction ID (Optional)</label>
                                        <input type="text" name="transaction_id">
                                    </div>
                                    <div class="form-group">
                                        <label>Notes (Optional)</label>
                                        <textarea name="notes" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Record Payment</button>
                                </form>
                                <!-- Payments History -->
                                <?php if (!empty($order_payments[$order['order_id']])): ?>
                                    <h4>Payment History</h4>
                                    <table class="sub-table">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Transaction ID</th>
                                            <th>Notes</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($order_payments[$order['order_id']] as $payment): ?>
                                            <tr>
                                                <td data-label="Date"><?php echo date('M j, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                                <td data-label="Amount">৳<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td data-label="Method"><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td data-label="Transaction ID"><?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?></td>
                                                <td data-label="Notes"><?php echo htmlspecialchars($payment['notes'] ?: 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<script>
    // Toggle order details visibility
    document.addEventListener('DOMContentLoaded', () => {
        const rows = document.querySelectorAll('.table tbody tr:not(.order-details)');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                const detailsRow = row.nextElementSibling;
                if (detailsRow && detailsRow.classList.contains('order-details')) {
                    detailsRow.classList.toggle('active');
                }
            });
        });
    });
</script>
</body>
</html>