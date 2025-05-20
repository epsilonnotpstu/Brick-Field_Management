<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Ensure only admin can access
require_role('admin');

// Handle Add/Update Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory'])) {
    try {
        $brick_type_id = $_POST['brick_type_id'];
        $current_quantity = $_POST['current_quantity'];

        // Check if inventory record exists for the brick type
        $stmt = $conn->prepare("SELECT inventory_id FROM Inventory WHERE brick_type_id = ?");
        $stmt->execute([$brick_type_id]);
        if ($stmt->fetch()) {
            // Update existing inventory
            $stmt = $conn->prepare("UPDATE Inventory SET current_quantity = ?, last_updated = NOW() WHERE brick_type_id = ?");
            $stmt->execute([$current_quantity, $brick_type_id]);
            $success_message = "Inventory updated successfully!";
        } else {
            // Add new inventory record
            $stmt = $conn->prepare("INSERT INTO Inventory (brick_type_id, current_quantity) VALUES (?, ?)");
            $stmt->execute([$brick_type_id, $current_quantity]);
            $success_message = "Inventory added successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Failed to add/update inventory: " . $e->getMessage();
    }
}

// Handle Delete Inventory
if (isset($_GET['delete_inventory'])) {
    try {
        $inventory_id = intval($_GET['delete_inventory']);
        $stmt = $conn->prepare("DELETE FROM Inventory WHERE inventory_id = ?");
        $stmt->execute([$inventory_id]);
        $success_message = "Inventory record deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to delete inventory: " . $e->getMessage();
    }
}

// Handle Add/Update Raw Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_raw_material'])) {
    try {
        $material_name = $_POST['material_name'];
        $current_stock = $_POST['current_stock'];
        $unit_of_measure = $_POST['unit_of_measure'];
        $reorder_level = $_POST['reorder_level'];
        $field_id = $_POST['field_id'];

        // Check if raw material exists for the field
        $stmt = $conn->prepare("SELECT material_id FROM RawMaterials WHERE material_name = ? AND field_id = ?");
        $stmt->execute([$material_name, $field_id]);
        if ($stmt->fetch()) {
            // Update existing raw material
            $stmt = $conn->prepare("
                UPDATE RawMaterials 
                SET current_stock = ?, unit_of_measure = ?, reorder_level = ?, last_restock_date = NOW() 
                WHERE material_name = ? AND field_id = ?
            ");
            $stmt->execute([$current_stock, $unit_of_measure, $reorder_level, $material_name, $field_id]);
            $success_message = "Raw material updated successfully!";
        } else {
            // Add new raw material
            $stmt = $conn->prepare("
                INSERT INTO RawMaterials (field_id, material_name, current_stock, unit_of_measure, reorder_level)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$field_id, $material_name, $current_stock, $unit_of_measure, $reorder_level]);
            $success_message = "Raw material added successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Failed to add/update raw material: " . $e->getMessage();
    }
}

// Handle Update Product Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    try {
        $product_id = $_POST['product_id'];
        $stock_quantity = $_POST['stock_quantity'];
        $is_available = $_POST['is_available'];

        $stmt = $conn->prepare("
            UPDATE Products 
            SET stock_quantity = ?, is_available = ? 
            WHERE product_id = ?
        ");
        $stmt->execute([$stock_quantity, $is_available, $product_id]);
        $success_message = "Product stock updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to update product stock: " . $e->getMessage();
    }
}

// Fetch data for dropdowns and tables
$brick_types = $conn->query("SELECT * FROM BrickType")->fetchAll(PDO::FETCH_ASSOC);
$brick_fields = $conn->query("SELECT * FROM BrickField WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$inventory = $conn->query("
    SELECT i.*, b.type_name 
    FROM Inventory i
    JOIN BrickType b ON i.brick_type_id = b.brick_type_id
    ORDER BY i.last_updated DESC
")->fetchAll(PDO::FETCH_ASSOC);
$raw_materials = $conn->query("
    SELECT r.*, f.field_name 
    FROM RawMaterials r
    JOIN BrickField f ON r.field_id = f.field_id
    ORDER BY r.last_restock_date DESC
")->fetchAll(PDO::FETCH_ASSOC);
$products = $conn->query("
    SELECT p.*, b.type_name 
    FROM Products p
    JOIN BrickType b ON p.brick_type_id = b.brick_type_id
    ORDER BY p.product_id
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <h2>Inventory Management</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <section class="form-section">
                <h3>Add/Update Inventory</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_inventory" value="1">
                    <div class="form-group">
                        <label>Brick Type</label>
                        <select name="brick_type_id" required>
                            <option value="">Select Brick Type</option>
                            <?php foreach ($brick_types as $type): ?>
                                <option value="<?php echo $type['brick_type_id']; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Current Quantity</label>
                        <input type="number" name="current_quantity" required min="0">
                    </div>
                    <button type="submit" class="btn btn-primary">Add/Update Inventory</button>
                </form>
            </section>

            <section class="form-section">
                <h3>Add/Update Raw Material</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_raw_material" value="1">
                    <div class="form-group">
                        <label>Brick Field</label>
                        <select name="field_id" required>
                            <option value="">Select Brick Field</option>
                            <?php foreach ($brick_fields as $field): ?>
                                <option value="<?php echo $field['field_id']; ?>">
                                    <?php echo htmlspecialchars($field['field_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Material Name</label>
                        <select name="material_name" required>
                            <option value="">Select Material</option>
                            <option value="clay">Clay</option>
                            <option value="sand">Sand</option>
                            <option value="coal">Coal</option>
                            <option value="wood">Wood</option>
                            <option value="rice_husk">Rice Husk</option>
                            <option value="chemicals">Chemicals</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Current Stock</label>
                        <input type="number" step="0.01" name="current_stock" required min="0">
                    </div>
                    <div class="form-group">
                        <label>Unit of Measure</label>
                        <select name="unit_of_measure" required>
                            <option value="">Select Unit</option>
                            <option value="ton">Ton</option>
                            <option value="kg">Kilogram</option>
                            <option value="cubic_meter">Cubic Meter</option>
                            <option value="bag">Bag</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reorder Level</label>
                        <input type="number" step="0.01" name="reorder_level" required min="0">
                    </div>
                    <button type="submit" class="btn btn-primary">Add/Update Raw Material</button>
                </form>
            </section>
        </div>

        <section class="inventory-list">
            <h3>Current Inventory</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Brick Type</th>
                    <th>Current Quantity</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td data-label="Brick Type"><?php echo htmlspecialchars($item['type_name']); ?></td>
                        <td data-label="Current Quantity"><?php echo number_format($item['current_quantity']); ?></td>
                        <td data-label="Last Updated"><?php echo date('M j, Y H:i', strtotime($item['last_updated'])); ?></td>
                        <td data-label="Actions">
                            <a href="?delete_inventory=<?php echo $item['inventory_id']; ?>" class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to delete this inventory record?')">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="raw-materials-list">
            <h3>Raw Materials Stock</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Brick Field</th>
                    <th>Material Name</th>
                    <th>Current Stock</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th>Last Restocked</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($raw_materials as $material): ?>
                    <tr>
                        <td data-label="Brick Field"><?php echo htmlspecialchars($material['field_name']); ?></td>
                        <td data-label="Material Name"><?php echo ucfirst($material['material_name']); ?></td>
                        <td data-label="Current Stock"><?php echo number_format($material['current_stock'], 2); ?></td>
                        <td data-label="Unit"><?php echo ucfirst($material['unit_of_measure']); ?></td>
                        <td data-label="Reorder Level"><?php echo number_format($material['reorder_level'], 2); ?></td>
                        <td data-label="Last Restocked"><?php echo $material['last_restock_date'] ? date('M j, Y', strtotime($material['last_restock_date'])) : 'N/A'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="products-list">
            <h3>Product Stock</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Brick Type</th>
                    <th>Stock Quantity</th>
                    <th>Availability</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="update_product" value="1">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        <tr>
                            <td data-label="Product Name"><?php echo htmlspecialchars($product['display_name']); ?></td>
                            <td data-label="Brick Type"><?php echo htmlspecialchars($product['type_name']); ?></td>
                            <td data-label="Stock Quantity">
                                <input type="number" name="stock_quantity" value="<?php echo $product['stock_quantity']; ?>" required min="0">
                            </td>
                            <td data-label="Availability">
                                <select name="is_available">
                                    <option value="1" <?php echo $product['is_available'] ? 'selected' : ''; ?>>Available</option>
                                    <option value="0" <?php echo !$product['is_available'] ? 'selected' : ''; ?>>Unavailable</option>
                                </select>
                            </td>
                            <td data-label="Actions">
                                <button type="submit" class="btn btn-primary">Update</button>
                            </td>
                        </tr>
                    </form>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>