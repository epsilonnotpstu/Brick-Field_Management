<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Ensure only admin can access
require_role('admin');

// Add new production entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_production'])) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO Production (field_id, brick_type_id, quantity_produced, production_date, supervisor_id, quality_rating)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['field_id'],
            $_POST['brick_type_id'],
            $_POST['quantity_produced'],
            $_POST['production_date'],
            $_POST['supervisor_id'],
            $_POST['quality_rating']
        ]);
        $success_message = "Production record added successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to add production: " . $e->getMessage();
    }
}

// Fetch all production records
$productions = $conn->query("
    SELECT p.*, f.field_name, b.type_name, e.employee_id, u.username AS supervisor_name 
    FROM Production p
    JOIN BrickField f ON p.field_id = f.field_id
    JOIN BrickType b ON p.brick_type_id = b.brick_type_id
    JOIN Employees e ON p.supervisor_id = e.employee_id
    JOIN Users u ON e.user_id = u.user_id
    ORDER BY p.production_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch dropdown data
$brick_fields = $conn->query("SELECT * FROM BrickField WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$brick_types = $conn->query("SELECT * FROM BrickType")->fetchAll(PDO::FETCH_ASSOC);
$supervisors = $conn->query("
    SELECT e.employee_id, u.username 
    FROM Employees e
    JOIN Users u ON e.user_id = u.user_id
    WHERE e.role = 'supervisor' AND e.current_status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

// Handle Add Product Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO Products (brick_type_id, display_name, description, base_price, discount_price, min_order_quantity, stock_quantity, is_featured, is_available, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['brick_type_id'],
            $_POST['display_name'],
            $_POST['description'],
            $_POST['base_price'],
            $_POST['discount_price'],
            $_POST['min_order_quantity'],
            $_POST['stock_quantity'],
            $_POST['is_featured'],
            $_POST['is_available'],
            $_POST['image_url']
        ]);
        $success_message = "Product added successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to add product: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production | Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-container {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .form-section {
            flex: 1;
            min-width: 300px;
        }
        @media (max-width: 768px) {
            .form-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <h2>Production Management</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <section class="form-section">
                <h3>Add Production Record</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_production" value="1">

                    <div class="form-group">
                        <label>Brick Field</label>
                        <select name="field_id" required>
                            <?php foreach ($brick_fields as $field): ?>
                                <option value="<?php echo $field['field_id']; ?>">
                                    <?php echo htmlspecialchars($field['field_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Brick Type</label>
                        <select name="brick_type_id" required>
                            <?php foreach ($brick_types as $type): ?>
                                <option value="<?php echo $type['brick_type_id']; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity Produced</label>
                        <input type="number" name="quantity_produced" required>
                    </div>

                    <div class="form-group">
                        <label>Production Date</label>
                        <input type="date" name="production_date" required>
                    </div>

                    <div class="form-group">
                        <label>Supervisor</label>
                        <select name="supervisor_id" required>
                            <?php foreach ($supervisors as $sup): ?>
                                <option value="<?php echo $sup['employee_id']; ?>">
                                    <?php echo htmlspecialchars($sup['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quality Rating</label>
                        <select name="quality_rating" required>
                            <option value="A">A - Excellent</option>
                            <option value="B">B - Good</option>
                            <option value="C">C - Fair</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Production</button>
                </form>
            </section>

            <section class="form-section">
                <h3>Add New Product</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_product" value="1">

                    <div class="form-group">
                        <label>Brick Type</label>
                        <select name="brick_type_id" required>
                            <?php foreach ($brick_types as $type): ?>
                                <option value="<?php echo $type['brick_type_id']; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description">
                    </div>

                    <div class="form-group">
                        <label>Base Price (৳)</label>
                        <input type="number" step="0.01" name="base_price" required>
                    </div>

                    <div class="form-group">
                        <label>Discount Price (৳)</label>
                        <input type="number" step="0.01" name="discount_price">
                    </div>

                    <div class="form-group">
                        <label>Min Order Quantity</label>
                        <input type="number" name="min_order_quantity" required>
                    </div>

                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock_quantity" required>
                    </div>

                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="text" name="image_url">
                    </div>

                    <div class="form-group">
                        <label>Is Featured?</label>
                        <select name="is_featured">
                            <option value="1">Yes</option>
                            <option value="0" selected>No</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Is Available?</label>
                        <select name="is_available">
                            <option value="1" selected>Available</option>
                            <option value="0">Unavailable</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Product</button>
                </form>
            </section>
        </div>

        <section class="employee-list">
            <h3>Production History</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Brick Field</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Supervisor</th>
                    <th>Quality</th>
                </tr>
                </thead>
                <tbody>
<?php foreach ($productions as $p): ?>
    <tr>
        <td data-label="Date"><?php echo htmlspecialchars($p['production_date']); ?></td>
        <td data-label="Brick Field"><?php echo htmlspecialchars($p['field_name']); ?></td>
        <td data-label="Type"><?php echo htmlspecialchars($p['type_name']); ?></td>
        <td data-label="Quantity"><?php echo number_format($p['quantity_produced']); ?></td>
        <td data-label="Supervisor"><?php echo htmlspecialchars($p['supervisor_name']); ?></td>
        <td data-label="Quality"><?php echo ucfirst($p['quality_rating']); ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>