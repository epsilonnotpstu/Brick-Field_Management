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

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    try {
        $employee_id = $_POST['employee_id'];
        $date = $_POST['date'];
        $status = $_POST['status'];
        $check_in = $_POST['check_in'] ?: null;
        $check_out = $_POST['check_out'] ?: null;
        $overtime_hours = $_POST['overtime_hours'] ?: 0.00;
        $notes = $_POST['notes'] ?: null;
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO Attendance (employee_id, date, check_in, check_out, status, overtime_hours, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $date, $check_in, $check_out, $status, $overtime_hours, $notes]);
        $conn->commit();
        $success_message = "Attendance recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to record attendance: " . $e->getMessage();
    }
}

// Handle Worker Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $employee_id = $_POST['employee_id'];
        $current_status = $_POST['current_status'];
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE Employees 
            SET current_status = ?
            WHERE employee_id = ? AND field_id = ?
        ");
        $stmt->execute([$current_status, $employee_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Worker status updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to update status: " . $e->getMessage();
    }
}

// Fetch Workers
$stmt = $conn->prepare("
    SELECT e.employee_id, e.nid_number, e.role, e.joining_date, e.salary, e.current_status, u.username
    FROM Employees e
    JOIN Users u ON e.user_id = u.user_id
    WHERE e.field_id = ? AND e.role IN ('molder', 'kiln_operator', 'driver', 'other')
");
$stmt->execute([$supervisor['field_id']]);
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Attendance (last 7 days)
$stmt = $conn->prepare("
    SELECT a.attendance_id, a.employee_id, a.date, a.check_in, a.check_out, a.status, a.overtime_hours, u.username
    FROM Attendance a
    JOIN Employees e ON a.employee_id = e.employee_id
    JOIN Users u ON e.user_id = u.user_id
    WHERE e.field_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.date DESC
");
$stmt->execute([$supervisor['field_id']]);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Worker Production
$stmt = $conn->prepare("
    SELECT rbp.production_id, rbp.employee_id, rbp.mold_type, rbp.quantity, rbp.production_date, u.username
    FROM RawBrickProduction rbp
    JOIN Employees e ON rbp.employee_id = e.employee_id
    JOIN Users u ON e.user_id = u.user_id
    WHERE rbp.field_id = ?
    ORDER BY rbp.production_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id']]);
$worker_productions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Worker-Reported Losses
$stmt = $conn->prepare("
    SELECT rbl.loss_id, rbl.production_id, rbl.loss_date, rbl.loss_reason, rbl.quantity_lost, u.username
    FROM RawBrickLoss rbl
    JOIN Employees e ON rbl.reported_by = e.employee_id
    JOIN Users u ON e.user_id = u.user_id
    JOIN RawBrickProduction rbp ON rbl.production_id = rbp.production_id
    WHERE rbp.field_id = ?
    ORDER BY rbl.loss_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id']]);
$worker_losses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Management | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <h2>Worker Management</h2>
            <p>Manage workers and attendance for <?php echo htmlspecialchars($supervisor['field_name'] ?? 'N/A'); ?></p>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Worker List -->
            <section class="form-container">
                <h3>Worker List</h3>
                <?php if (empty($workers)): ?>
                    <p>No workers found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>NID</th>
                                <th>Role</th>
                                <th>Joining Date</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $worker): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($worker['username']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['nid_number']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['role']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($worker['joining_date'])); ?></td>
                                    <td><?php echo number_format($worker['salary'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($worker['current_status']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="employee_id" value="<?php echo $worker['employee_id']; ?>">
                                            <select name="current_status" required>
                                                <option value="active" <?php echo $worker['current_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="on_leave" <?php echo $worker['current_status'] == 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                                <option value="terminated" <?php echo $worker['current_status'] == 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Record Attendance -->
            <section class="form-container">
                <h3>Record Attendance</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_attendance" value="1">
                    <div class="form-group">
                        <label>Worker</label>
                        <select name="employee_id" required>
                            <option value="">Select Worker</option>
                            <?php foreach ($workers as $worker): ?>
                                <option value="<?php echo $worker['employee_id']; ?>">
                                    <?php echo htmlspecialchars($worker['username'] . ' (' . $worker['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                            <option value="half_day">Half Day</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Check In</label>
                        <input type="time" name="check_in">
                    </div>
                    <div class="form-group">
                        <label>Check Out</label>
                        <input type="time" name="check_out">
                    </div>
                    <div class="form-group">
                        <label>Overtime Hours</label>
                        <input type="number" name="overtime_hours" step="0.01" min="0" value="0.00">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Record Attendance</button>
                </form>
            </section>
            
            <!-- Recent Attendance -->
            <section class="form-container">
                <h3>Recent Attendance</h3>
                <?php if (empty($attendances)): ?>
                    <p>No attendance records found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Overtime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendances as $attendance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attendance['username']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($attendance['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['status']); ?></td>
                                    <td><?php echo $attendance['check_in'] ? htmlspecialchars($attendance['check_in']) : 'N/A'; ?></td>
                                    <td><?php echo $attendance['check_out'] ? htmlspecialchars($attendance['check_out']) : 'N/A'; ?></td>
                                    <td><?php echo number_format($attendance['overtime_hours'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Worker Production -->
            <section class="form-container">
                <h3>Worker Production</h3>
                <?php if (empty($worker_productions)): ?>
                    <p>No production records found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Mold Type</th>
                                <th>Quantity</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($worker_productions as $production): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($production['username']); ?></td>
                                    <td><?php echo htmlspecialchars($production['mold_type']); ?></td>
                                    <td><?php echo number_format($production['quantity']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($production['production_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Worker-Reported Losses -->
            <section class="form-container">
                <h3>Worker-Reported Losses</h3>
                <?php if (empty($worker_losses)): ?>
                    <p>No loss records found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Loss Reason</th>
                                <th>Quantity Lost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($worker_losses as $loss): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loss['username']); ?></td>
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