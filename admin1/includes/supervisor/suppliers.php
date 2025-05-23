<?php
require_once '../auth_check.php';

// Only allow supervisor access
if ($_SESSION['user_type'] != 'supervisor') {
    header("Location: ../unauthorized.php");
    exit();
}

// Get supervisor details
require_once '../db_connection.php';
$stmt = $conn->prepare("
    SELECT e.field_id, e.employee_id, f.field_name 
    FROM Employees e
    JOIN BrickField f ON e.field_id = f.field_id
    WHERE e.user_id = ? AND e.role = 'supervisor'
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor) {
    $error_message = "Supervisor details not found.";
}

// Handle Add Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    try {
        $name = trim($_POST['name']);
        $contact_person = trim($_POST['contact_person']) ?: null;
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']) ?: null;
        $supplied_materials = trim($_POST['supplied_materials']);
        $address = trim($_POST['address']) ?: null;
        $tax_id = trim($_POST['tax_id']) ?: null;
        $account_number = trim($_POST['account_number']) ?: null;

        if (empty($name) || empty($phone) || empty($supplied_materials)) {
            throw new Exception("Name, phone, and supplied materials are required.");
        }

        $stmt = $conn->prepare("
            INSERT INTO Suppliers (name, contact_person, phone, email, supplied_materials, address, tax_id, account_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $contact_person, $phone, $email, $supplied_materials, $address, $tax_id, $account_number]);
        $success_message = "Supplier added successfully!";
    } catch (Exception $e) {
        $error_message = "Failed to add supplier: " . $e->getMessage();
    }
}

// Handle Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    try {
        $supplier_id = $_POST['supplier_id'];
        $name = trim($_POST['name']);
        $contact_person = trim($_POST['contact_person']) ?: null;
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']) ?: null;
        $supplied_materials = trim($_POST['supplied_materials']);
        $address = trim($_POST['address']) ?: null;
        $tax_id = trim($_POST['tax_id']) ?: null;
        $account_number = trim($_POST['account_number']) ?: null;

        if (empty($name) || empty($phone) || empty($supplied_materials)) {
            throw new Exception("Name, phone, and supplied materials are required.");
        }

        $stmt = $conn->prepare("
            UPDATE Suppliers 
            SET name = ?, contact_person = ?, phone = ?, email = ?, supplied_materials = ?, address = ?, tax_id = ?, account_number = ?
            WHERE supplier_id = ?
        ");
        $stmt->execute([$name, $contact_person, $phone, $email, $supplied_materials, $address, $tax_id, $account_number, $supplier_id]);
        $success_message = "Supplier updated successfully!";
    } catch (Exception $e) {
        $error_message = "Failed to update supplier: " . $e->getMessage();
    }
}

// Handle Delete Supplier
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM MaterialReceipt WHERE supplier_id = ?");
        $stmt->execute([$_GET['id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cannot delete supplier with associated material receipts.");
        }

        $stmt = $conn->prepare("DELETE FROM Suppliers WHERE supplier_id = ?");
        $stmt->execute([$_GET['id']]);
        $success_message = "Supplier deleted successfully!";
        header("Location: suppliers.php");
        exit();
    } catch (Exception $e) {
        $error_message = "Failed to delete supplier: " . $e->getMessage();
    }
}

// Handle Add Material Receipt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_receipt'])) {
    try {
        $supplier_id = $_POST['supplier_id'];
        $material_id = $_POST['material_id'];
        $quantity = $_POST['quantity'];
        $unit_price = $_POST['unit_price'];
        $quality_rating = $_POST['quality_rating'];

        if ($quantity <= 0 || $unit_price < 0) {
            throw new Exception("Quantity must be positive and unit price non-negative.");
        }

        $stmt = $conn->prepare("
            INSERT INTO MaterialReceipt (material_id, supplier_id, receipt_date, quantity, unit_price, quality_rating, received_by)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?)
        ");
        $stmt->execute([$material_id, $supplier_id, $quantity, $unit_price, $quality_rating, $supervisor['employee_id']]);
        
        // Update RawMaterials stock
        $stmt = $conn->prepare("
            UPDATE RawMaterials 
            SET current_stock = current_stock + ?, last_restock_date = CURDATE()
            WHERE material_id = ? AND field_id = ?
        ");
        $stmt->execute([$quantity, $material_id, $supervisor['field_id']]);
        
        $success_message = "Material receipt added successfully!";
    } catch (Exception $e) {
        $error_message = "Failed to add material receipt: " . $e->getMessage();
    }
}

// Fetch Suppliers
$stmt = $conn->prepare("SELECT * FROM Suppliers ORDER BY name");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Raw Materials for Receipt Form
$stmt = $conn->prepare("SELECT material_id, material_name FROM RawMaterials WHERE field_id = ?");
$stmt->execute([$supervisor['field_id']]);
$raw_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Material Receipts
$stmt = $conn->prepare("
    SELECT mr.*, s.name AS supplier_name, rm.material_name 
    FROM MaterialReceipt mr
    JOIN Suppliers s ON mr.supplier_id = s.supplier_id
    JOIN RawMaterials rm ON mr.material_id = rm.material_id
    WHERE rm.field_id = ?
    ORDER BY mr.receipt_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id']]);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers Management | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <h2>Suppliers Management</h2>
            <p>Manage suppliers for <?php echo htmlspecialchars($supervisor['field_name'] ?? 'N/A'); ?></p>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="forms-wrapper">
                <section class="form-container">
                    <h3>Add New Supplier</h3>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="add_supplier" value="1">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Contact Person</label>
                            <input type="text" name="contact_person">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email">
                        </div>
                        <div class="form-group">
                            <label>Supplied Materials (comma-separated)</label>
                            <input type="text" name="supplied_materials" required placeholder="e.g., clay,sand,coal">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Tax ID</label>
                            <input type="text" name="tax_id">
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="account_number">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Supplier</button>
                    </form>
                </section>
                
                <section class="form-container">
                    <h3>Add Material Receipt</h3>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="add_receipt" value="1">
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Material</label>
                            <select name="material_id" required>
                                <option value="">Select Material</option>
                                <?php foreach ($raw_materials as $material): ?>
                                    <option value="<?php echo $material['material_id']; ?>">
                                        <?php echo htmlspecialchars($material['material_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Unit Price (BDT)</label>
                            <input type="number" name="unit_price" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Quality Rating</label>
                            <select name="quality_rating" required>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="average">Average</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Receipt</button>
                    </form>
                </section>
            </div>
            
            <section class="recent-records">
                <h3>Suppliers List</h3>
                <?php if (empty($suppliers)): ?>
                    <p>No suppliers found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Materials</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['supplied_materials']); ?></td>
                                    <td>
                                        <button class="btn btn-primary edit-btn" 
                                                data-id="<?php echo $supplier['supplier_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($supplier['name']); ?>"
                                                data-contact="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                                data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                data-materials="<?php echo htmlspecialchars($supplier['supplied_materials']); ?>"
                                                data-address="<?php echo htmlspecialchars($supplier['address']); ?>"
                                                data-tax="<?php echo htmlspecialchars($supplier['tax_id']); ?>"
                                                data-account="<?php echo htmlspecialchars($supplier['account_number']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?action=delete&id=<?php echo $supplier['supplier_id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this supplier?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <section class="recent-records">
                <h3>Recent Material Receipts</h3>
                <?php if (empty($receipts)): ?>
                    <p>No material receipts found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Material</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Quality</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($receipt['supplier_name']); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['material_name']); ?></td>
                                    <td><?php echo number_format($receipt['quantity'], 2); ?></td>
                                    <td><?php echo number_format($receipt['unit_price'], 2); ?></td>
                                    <td><?php echo ucfirst($receipt['quality_rating']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($receipt['receipt_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Edit Supplier Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Supplier</h3>
            <form method="POST" class="auth-form">
                <input type="hidden" name="edit_supplier" value="1">
                <input type="hidden" name="supplier_id" id="edit_supplier_id">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" id="edit_contact_person">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="edit_phone" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label>Supplied Materials</label>
                    <input type="text" name="supplied_materials" id="edit_supplied_materials" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address"></textarea>
                </div>
                <div class="form-group">
                    <label>Tax ID</label>
                    <input type="text" name="tax_id" id="edit_tax_id">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" id="edit_account_number">
                </div>
                <button type="submit" class="btn btn-primary">Update Supplier</button>
            </form>
        </div>
    </div>

    <script>
        // Modal handling
        const modal = document.getElementById('editModal');
        const closeBtn = document.querySelector('.modal .close');
        const editButtons = document.querySelectorAll('.edit-btn');

        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_supplier_id').value = this.dataset.id;
                document.getElementById('edit_name').value = this.dataset.name;
                document.getElementById('edit_contact_person').value = this.dataset.contact || '';
                document.getElementById('edit_phone').value = this.dataset.phone;
                document.getElementById('edit_email').value = this.dataset.email || '';
                document.getElementById('edit_supplied_materials').value = this.dataset.materials;
                document.getElementById('edit_address').value = this.dataset.address || '';
                document.getElementById('edit_tax_id').value = this.dataset.tax || '';
                document.getElementById('edit_account_number').value = this.dataset.account || '';
                modal.style.display = 'block';
            });
        });

        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });

        // Sidebar toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>