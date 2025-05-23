<?php
// Ensure session is started
if (!session_id()) {
    session_start();
}
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

// Handle Raw Materials Receipt Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material_receipt'])) {
    try {
        $material_id = $_POST['material_id'];
        $quantity = $_POST['quantity'];
        
        if ($quantity <= 0) {
            throw new Exception("Quantity must be positive.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE RawMaterials 
            SET current_stock = current_stock + ?, last_restock_date = CURDATE()
            WHERE material_id = ? AND field_id = ?
        ");
        $stmt->execute([$quantity, $material_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Material receipt recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to add material receipt: " . $e->getMessage();
    }
}

// Handle Raw Materials Consumption Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consume_material'])) {
    try {
        $material_id = $_POST['material_id'];
        $quantity = $_POST['quantity'];
        
        if ($quantity <= 0) {
            throw new Exception("Quantity must be positive.");
        }
        
        $stmt = $conn->prepare("SELECT current_stock FROM RawMaterials WHERE material_id = ? AND field_id = ?");
        $stmt->execute([$material_id, $supervisor['field_id']]);
        $current_stock = $stmt->fetchColumn();
        
        if ($quantity > $current_stock) {
            throw new Exception("Insufficient stock.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE RawMaterials 
            SET current_stock = current_stock - ?
            WHERE material_id = ? AND field_id = ?
        ");
        $stmt->execute([$quantity, $material_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Material consumption recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to record consumption: " . $e->getMessage();
    }
}

// Handle Raw Brick Production Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_raw_production'])) {
    try {
        $mold_type = $_POST['mold_type'];
        $quantity = $_POST['quantity'];
        $drying_location = $_POST['drying_location'];
        $notes = $_POST['notes'] ?: null;
        
        if ($quantity <= 0) {
            throw new Exception("Quantity must be positive.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO RawBrickProduction (field_id, employee_id, mold_type, quantity, drying_location, drying_start_date, notes)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
        ");
        $stmt->execute([$supervisor['field_id'], $supervisor['employee_id'], $mold_type, $quantity, $drying_location, $notes]);
        $conn->commit();
        $success_message = "Raw brick production recorded!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to add raw production: " . $e->getMessage();
    }
}

// Handle Approve Raw Brick Production
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_production'])) {
    try {
        $production_id = $_POST['production_id'];
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE RawBrickProduction 
            SET supervisor_approved = 1
            WHERE production_id = ? AND field_id = ?
        ");
        $stmt->execute([$production_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Production record approved!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to approve production: " . $e->getMessage();
    }
}

// Handle Raw Brick Loss Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_loss'])) {
    try {
        $production_id = $_POST['production_id'];
        $loss_reason = $_POST['loss_reason'];
        $quantity_lost = $_POST['quantity_lost'];
        
        if ($quantity_lost <= 0) {
            throw new Exception("Quantity lost must be positive.");
        }
        
        $stmt = $conn->prepare("SELECT quantity FROM RawBrickProduction WHERE production_id = ? AND field_id = ?");
        $stmt->execute([$production_id, $supervisor['field_id']]);
        $production_quantity = $stmt->fetchColumn();
        
        if ($quantity_lost > $production_quantity) {
            throw new Exception("Loss quantity exceeds production quantity.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO RawBrickLoss (production_id, loss_date, loss_reason, quantity_lost, reported_by)
            VALUES (?, CURDATE(), ?, ?, ?)
        ");
        $stmt->execute([$production_id, $loss_reason, $quantity_lost, $supervisor['employee_id']]);
        $conn->commit();
        $success_message = "Brick loss recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to record brick loss: " . $e->getMessage();
    }
}

// Fetch Raw Materials for Supervisor's Field
$stmt = $conn->prepare("
    SELECT material_id, material_name, current_stock, unit_of_measure, reorder_level
    FROM RawMaterials
    WHERE field_id = ?
");
$stmt->execute([$supervisor['field_id']]);
$raw_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Raw Brick Productions
$stmt = $conn->prepare("
    SELECT production_id, mold_type, quantity, drying_location, status, supervisor_approved, production_date
    FROM RawBrickProduction
    WHERE field_id = ?
    ORDER BY production_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id']]);
$raw_productions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Raw Brick Losses
$stmt = $conn->prepare("
    SELECT rbl.loss_id, rbl.production_id, rbl.loss_date, rbl.loss_reason, rbl.quantity_lost, rbp.mold_type
    FROM RawBrickLoss rbl
    JOIN RawBrickProduction rbp ON rbl.production_id = rbp.production_id
    WHERE rbp.field_id = ?
    ORDER BY rbl.loss_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id']]);
$raw_losses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Raw Brick Productions for Loss Form
$stmt = $conn->prepare("
    SELECT production_id, mold_type, quantity
    FROM RawBrickProduction
    WHERE field_id = ? AND status = 'wet'
");
$stmt->execute([$supervisor['field_id']]);
$productions_for_loss = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Management | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    < class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <h2>Production Management</h2>
            <p>Manage raw brick production and material stock for <?php echo htmlspecialchars($supervisor['field_name'] ?? 'N/A'); ?></p>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Raw Materials Section -->
            <section class="raw-materials">
                <h3>Raw Materials Management</h3>
                
                <!-- View Current Stock -->
                <div class="form-container">
                    <h4>Current Stock</h4>
                    <?php if (empty($raw_materials)): ?>
                        <p>No materials found.</p>
                    <?php else: ?>
                        <table class="record-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Stock</th>
                                    <th>Unit</th>
                                    <th>Reorder Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($raw_materials as $material): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                        <td><?php echo number_format($material['current_stock'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($material['unit_of_measure']); ?></td>
                                        <td><?php echo number_format($material['reorder_level'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Add Material Receipt -->
             <div class="forms-wrapper">   
                <div class="form-container">
                    <h4>Add Material Receipt</h4>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="add_material_receipt" value="1">
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
                            <label>Quantity Received</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Receipt</button>
                    </form>
                </div>
                
                <!-- Consume Material -->
                <div class="form-container">
                    <h4>Record Material Consumption</h4>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="consume_material" value="1">
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
                            <label>Quantity Consumed</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Record Consumption</button>
                    </form>
                </div>
              </div>   
            </section>
            
            <!-- Raw Brick Production Section -->
            <section class="form-container">
                <h3>Add Raw Brick Production</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_raw_production" value="1">
                    <div class="form-group">
                        <label>Mold Type</label>
                        <select name="mold_type" required>
                            <option value="">Select Mold Type</option>
                            <option value="standard">Standard</option>
                            <option value="hollow">Hollow</option>
                            <option value="perforated">Perforated</option>
                            <option value="special">Special</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Drying Location</label>
                        <select name="drying_location" required>
                            <option value="">Select Location</option>
                            <option value="north_yard">North Yard</option>
                            <option value="south_yard">South Yard</option>
                            <option value="east_yard">East Yard</option>
                            <option value="west_yard">West Yard</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Production</button>
                </form>
            </section>
            
            <!-- Approve Raw Brick Production -->
            <section class="form-container">
                <h3>Approve Raw Brick Production</h3>
                <?php if (empty($raw_productions)): ?>
                    <p>No pending production records.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Mold Type</th>
                                <th>Quantity</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($raw_productions as $production): ?>
                                <?php if (!$production['supervisor_approved']): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($production['mold_type']); ?></td>
                                        <td><?php echo number_format($production['quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($production['drying_location'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($production['status']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($production['production_date'])); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="approve_production" value="1">
                                                <input type="hidden" name="production_id" value="<?php echo $production['production_id']; ?>">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Raw Brick Loss Section -->
            <section class="form-container">
                <h3>Record Raw Brick Loss</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_loss" value="1">
                    <div class="form-group">
                        <label>Production Record</label>
                        <select name="production_id" required>
                            <option value="">Select Production</option>
                            <?php foreach ($productions_for_loss as $production): ?>
                                <option value="<?php echo $production['production_id']; ?>">
                                    <?php echo htmlspecialchars($production['mold_type'] . ' - ' . $production['quantity'] . ' bricks'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loss Reason</label>
                        <select name="loss_reason" required>
                            <option value="">Select Reason</option>
                            <option value="cracking">Cracking</option>
                            <option value="breaking">Breaking</option>
                            <option value="rain_damage">Rain Damage</option>
                            <option value="theft">Theft</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity Lost</label>
                        <input type="number" name="quantity_lost" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Record Loss</button>
                </form>
            </section>
            
            <!-- Recent Raw Brick Losses -->
            <section class="form-container">
                <h3>Recent Raw Brick Losses</h3>
                <?php if (empty($raw_losses)): ?>
                    <p>No loss records found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Mold Type</th>
                                <th>Loss Reason</th>
                                <th>Quantity Lost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($raw_losses as $loss): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loss['mold_type']); ?></td>
                                    <td><?php echo htmlspecialchars($loss['loss_reason']); ?></td>
                                    <td><?php echo number_format($loss['quantity_lost']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($loss['loss_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>