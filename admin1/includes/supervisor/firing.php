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

// Handle Kiln Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kiln_status'])) {
    try {
        $kiln_id = $_POST['kiln_id'];
        $status = $_POST['status'];
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE Kiln 
            SET status = ?
            WHERE kiln_id = ? AND field_id = ?
        ");
        $stmt->execute([$status, $kiln_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Kiln status updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to update kiln status: " . $e->getMessage();
    }
}

// Handle Kiln Maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_maintenance'])) {
    try {
        $kiln_id = $_POST['kiln_id'];
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE Kiln 
            SET last_maintenance_date = CURDATE(), status = 'active'
            WHERE kiln_id = ? AND field_id = ?
        ");
        $stmt->execute([$kiln_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Kiln maintenance recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to record maintenance: " . $e->getMessage();
    }
}

// Handle Start Firing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_firing'])) {
    try {
        $kiln_id = $_POST['kiln_id'];
        
        // Verify kiln is active
        $stmt = $conn->prepare("SELECT status FROM Kiln WHERE kiln_id = ? AND field_id = ?");
        $stmt->execute([$kiln_id, $supervisor['field_id']]);
        $kiln_status = $stmt->fetchColumn();
        if ($kiln_status !== 'active') {
            throw new Exception("Selected kiln is not active.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO Firing (kiln_id, start_date, supervisor_id)
            VALUES (?, NOW(), ?)
        ");
        $stmt->execute([$kiln_id, $supervisor['employee_id']]);
        $conn->commit();
        $success_message = "Firing session started successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to start firing: " . $e->getMessage();
    }
}

// Handle End Firing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_firing'])) {
    try {
        $firing_id = $_POST['firing_id'];
        $fuel_consumed = $_POST['fuel_consumed'];
        $temperature_profile = $_POST['temperature_profile'] ?: '{}';
        $success_rate = $_POST['success_rate'];
        
        if ($fuel_consumed < 0 || $success_rate < 0 || $success_rate > 100) {
            throw new Exception("Invalid input values.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE Firing 
            SET end_date = NOW(), fuel_consumed = ?, temperature_profile = ?, success_rate = ?
            WHERE firing_id = ? AND supervisor_id = ?
        ");
        $stmt->execute([$fuel_consumed, $temperature_profile, $success_rate, $firing_id, $supervisor['employee_id']]);
        $conn->commit();
        $success_message = "Firing session ended successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to end firing: " . $e->getMessage();
    }
}

// Handle Start Drying
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_drying'])) {
    try {
        $production_id = $_POST['production_id'];
        $weather_condition = $_POST['weather_condition'];
        
        // Verify production is wet
        $stmt = $conn->prepare("SELECT status FROM RawBrickProduction WHERE production_id = ? AND field_id = ?");
        $stmt->execute([$production_id, $supervisor['field_id']]);
        $status = $stmt->fetchColumn();
        if ($status !== 'wet') {
            throw new Exception("Selected production is not in 'wet' status.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO DryingProcess (production_id, start_date, weather_condition)
            VALUES (?, CURDATE(), ?)
        ");
        $stmt->execute([$production_id, $weather_condition]);
        
        // Update RawBrickProduction status
        $stmt = $conn->prepare("
            UPDATE RawBrickProduction 
            SET status = 'drying'
            WHERE production_id = ?
        ");
        $stmt->execute([$production_id]);
        $conn->commit();
        $success_message = "Drying process started successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to start drying: " . $e->getMessage();
    }
}

// Handle Update Drying
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_drying'])) {
    try {
        $drying_id = $_POST['drying_id'];
        $temperature = $_POST['temperature'];
        $humidity = $_POST['humidity'];
        $flipped_count = $_POST['flipped_count'];
        $quality_check_notes = $_POST['quality_check_notes'] ?: null;
        $end_date = $_POST['end_drying'] ? 'CURDATE()' : 'NULL';
        
        if ($temperature < -50 || $temperature > 50 || $humidity < 0 || $humidity > 100 || $flipped_count < 0) {
            throw new Exception("Invalid input values.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE DryingProcess 
            SET temperature = ?, humidity = ?, flipped_count = ?, quality_check_notes = ?, end_date = $end_date
            WHERE drying_id = ? AND production_id IN (
                SELECT production_id FROM RawBrickProduction WHERE field_id = ?
            )
        ");
        $stmt->execute([$temperature, $humidity, $flipped_count, $quality_check_notes, $drying_id, $supervisor['field_id']]);
        
        // If drying ended, update RawBrickProduction status
        if ($_POST['end_drying']) {
            $stmt = $conn->prepare("
                UPDATE RawBrickProduction 
                SET status = 'dry'
                WHERE production_id = (SELECT production_id FROM DryingProcess WHERE drying_id = ?)
            ");
            $stmt->execute([$drying_id]);
        }
        $conn->commit();
        $success_message = "Drying process updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to update drying: " . $e->getMessage();
    }
}

// Fetch Kilns
$stmt = $conn->prepare("
    SELECT kiln_id, kiln_type, capacity, fuel_type, status, last_maintenance_date
    FROM Kiln
    WHERE field_id = ?
");
$stmt->execute([$supervisor['field_id']]);
$kilns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Firings
$stmt = $conn->prepare("
    SELECT f.firing_id, f.kiln_id, f.start_date, f.end_date, f.fuel_consumed, f.success_rate, k.kiln_type
    FROM Firing f
    JOIN Kiln k ON f.kiln_id = k.kiln_id
    WHERE f.supervisor_id = ?
    ORDER BY f.start_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['employee_id']]);
$firings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Drying Processes
$stmt = $conn->prepare("
    SELECT d.drying_id, d.production_id, d.start_date, d.end_date, d.weather_condition, d.flipped_count, rbp.mold_type
    FROM DryingProcess d
    JOIN RawBrickProduction rbp ON d.production_id = rbp.production_id
    WHERE rbp.field_id = ?
    ORDER BY d.start_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id']]);
$drying_processes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Raw Brick Productions for Drying
$stmt = $conn->prepare("
    SELECT production_id, mold_type, quantity
    FROM RawBrickProduction
    WHERE field_id = ? AND status = 'wet'
");
$stmt->execute([$supervisor['field_id']]);
$productions_for_drying = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Ongoing Firings
$stmt = $conn->prepare("
    SELECT firing_id, kiln_id
    FROM Firing
    WHERE supervisor_id = ? AND end_date IS NULL
");
$stmt->execute([$supervisor['employee_id']]);
$ongoing_firings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firing & Drying Management | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <h2>Firing & Drying Management</h2>
            <p>Manage kilns, firing sessions, and drying processes for <?php echo htmlspecialchars($supervisor['field_name'] ?? 'N/A'); ?></p>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Kiln Management -->
            <section class="form-container">
                <h3>Kiln Management</h3>
                <?php if (empty($kilns)): ?>
                    <p>No kilns found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Fuel</th>
                                <th>Status</th>
                                <th>Last Maintenance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kilns as $kiln): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($kiln['kiln_type']); ?></td>
                                    <td><?php echo number_format($kiln['capacity']); ?></td>
                                    <td><?php echo htmlspecialchars($kiln['fuel_type']); ?></td>
                                    <td><?php echo htmlspecialchars($kiln['status']); ?></td>
                                    <td><?php echo $kiln['last_maintenance_date'] ? date('Y-m-d', strtotime($kiln['last_maintenance_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="update_kiln_status" value="1">
                                            <input type="hidden" name="kiln_id" value="<?php echo $kiln['kiln_id']; ?>">
                                            <select name="status" required>
                                                <option value="active" <?php echo $kiln['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="maintenance" <?php echo $kiln['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                <option value="inactive" <?php echo $kiln['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="record_maintenance" value="1">
                                            <input type="hidden" name="kiln_id" value="<?php echo $kiln['kiln_id']; ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-tools"></i> Maintenance
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Start Firing -->
            <section class="form-container">
                <h3>Start Firing Session</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="start_firing" value="1">
                    <div class="form-group">
                        <label>Kiln</label>
                        <select name="kiln_id" required>
                            <option value="">Select Kiln</option>
                            <?php foreach ($kilns as $kiln): ?>
                                <?php if ($kiln['status'] == 'active'): ?>
                                    <option value="<?php echo $kiln['kiln_id']; ?>">
                                        <?php echo htmlspecialchars($kiln['kiln_type'] . ' (' . $kiln['capacity'] . ' bricks)'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Start Firing</button>
                </form>
            </section>
            
            <!-- End Firing -->
            <section class="form-container">
                <h3>End Firing Session</h3>
                <?php if (empty($ongoing_firings)): ?>
                    <p>No ongoing firing sessions.</p>
                <?php else: ?>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="end_firing" value="1">
                        <div class="form-group">
                            <label>Firing Session</label>
                            <select name="firing_id" required>
                                <option value="">Select Session</option>
                                <?php foreach ($ongoing_firings as $firing): ?>
                                    <option value="<?php echo $firing['firing_id']; ?>">
                                        Kiln ID: <?php echo $firing['kiln_id']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fuel Consumed (kg)</label>
                            <input type="number" name="fuel_consumed" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Temperature Profile (JSON)</label>
                            <textarea name="temperature_profile" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Success Rate (%)</label>
                            <input type="number" name="success_rate" step="0.01" min="0" max="100" required>
                        </div>
                        <button type="submit" class="btn btn-primary">End Firing</button>
                    </form>
                <?php endif; ?>
            </section>
            
            <!-- Recent Firings -->
            <section class="form-container">
                <h3>Recent Firing Sessions</h3>
                <?php if (empty($firings)): ?>
                    <p>No firing records found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Kiln</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Fuel Consumed</th>
                                <th>Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($firings as $firing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($firing['kiln_type']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($firing['start_date'])); ?></td>
                                    <td><?php echo $firing['end_date'] ? date('Y-m-d H:i', strtotime($firing['end_date'])) : 'Ongoing'; ?></td>
                                    <td><?php echo $firing['fuel_consumed'] ? number_format($firing['fuel_consumed'], 2) : 'N/A'; ?></td>
                                    <td><?php echo $firing['success_rate'] ? number_format($firing['success_rate'], 2) . '%' : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Start Drying -->
            <section class="form-container">
                <h3>Start Drying Process</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="start_drying" value="1">
                    <div class="form-group">
                        <label>Raw Brick Production</label>
                        <select name="production_id" required>
                            <option value="">Select Production</option>
                            <?php foreach ($productions_for_drying as $production): ?>
                                <option value="<?php echo $production['production_id']; ?>">
                                    <?php echo htmlspecialchars($production['mold_type'] . ' - ' . $production['quantity'] . ' bricks'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Weather Condition</label>
                        <select name="weather_condition" required>
                            <option value="sunny">Sunny</option>
                            <option value="cloudy">Cloudy</option>
                            <option value="rainy">Rainy</option>
                            <option value="stormy">Stormy</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Start Drying</button>
                </form>
            </section>
            
            <!-- Update Drying -->
            <section class="form-container">
                <h3>Update Drying Process</h3>
                <?php if (empty($drying_processes)): ?>
                    <p>No drying processes found.</p>
                <?php else: ?>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="update_drying" value="1">
                        <div class="form-group">
                            <label>Drying Process</label>
                            <select name="drying_id" required>
                                <option value="">Select Process</option>
                                <?php foreach ($drying_processes as $drying): ?>
                                    <?php if (!$drying['end_date']): ?>
                                        <option value="<?php echo $drying['drying_id']; ?>">
                                            <?php echo htmlspecialchars($drying['mold_type'] . ' - Started ' . date('Y-m-d', strtotime($drying['start_date']))); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Temperature (°C)</label>
                            <input type="number" name="temperature" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Humidity (%)</label>
                            <input type="number" name="humidity" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="form-group">
                            <label>Flipped Count</label>
                            <input type="number" name="flipped_count" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Quality Check Notes</label>
                            <textarea name="quality_check_notes" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label>End Drying</label>
                            <input type="checkbox" name="end_drying" value="1">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Drying</button>
                    </form>
                <?php endif; ?>
            </section>
            
            <!-- Recent Drying Processes -->
            <section class="form-container">
                <h3>Recent Drying Processes</h3>
                <?php if (empty($drying_processes)): ?>
                    <p>No drying records found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Mold Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Weather</th>
                                <th>Flipped Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drying_processes as $drying): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($drying['mold_type']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($drying['start_date'])); ?></td>
                                    <td><?php echo $drying['end_date'] ? date('Y-m-d', strtotime($drying['end_date'])) : 'Ongoing'; ?></td>
                                    <td><?php echo htmlspecialchars($drying['weather_condition']); ?></td>
                                    <td><?php echo $drying['flipped_count']; ?></td>
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