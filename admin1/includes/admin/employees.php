<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Restrict to admin only
require_role('admin');

// Handle delete request
if (isset($_GET['delete'])) {
    $emp_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM Employees WHERE employee_id = ?");
    $stmt->execute([$emp_id]);
    header("Location: employees.php");
    exit();
}

// Handle add new employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    try {
        $user_stmt = $conn->prepare("
            INSERT INTO Users (username, password_hash, email, phone, user_type) 
            VALUES (?, ?, ?, ?, 'worker')
        ");
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $user_stmt->execute([
            $_POST['username'],
            $password_hash,
            $_POST['email'],
            $_POST['phone']
        ]);
        $user_id = $conn->lastInsertId();

        $emp_stmt = $conn->prepare("
            INSERT INTO Employees (user_id, field_id, nid_number, role, joining_date, salary, bank_account, emergency_contact, current_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $emp_stmt->execute([
            $user_id,
            $_POST['field_id'],
            $_POST['nid_number'],
            $_POST['role'],
            $_POST['joining_date'],
            $_POST['salary'],
            $_POST['bank_account'],
            $_POST['emergency_contact'],
            'active'
        ]);

        $success_message = "Employee added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding employee: " . $e->getMessage();
    }
}

// Fetch employees list
$employees = $conn->query("
    SELECT e.*, u.username, u.email, u.phone, f.field_name 
    FROM Employees e
    JOIN Users u ON e.user_id = u.user_id
    JOIN BrickField f ON e.field_id = f.field_id
    ORDER BY e.joining_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch fields list for dropdown
$fields = $conn->query("SELECT * FROM BrickField WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees | Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <h2>Manage Employees</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section class="form-section">
            <h3>Add New Employee</h3>
            <form method="POST" class="auth-form">
                <input type="hidden" name="add_employee" value="1">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" required>
                </div>
                <div class="form-group">
                    <label>NID Number</label>
                    <input type="text" name="nid_number" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="molder">Molder</option>
                        <option value="kiln_operator">Kiln Operator</option>
                        <option value="driver">Driver</option>
                        <option value="accountant">Accountant</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Joining Date</label>
                    <input type="date" name="joining_date" required>
                </div>
                <div class="form-group">
                    <label>Salary (৳)</label>
                    <input type="number" step="0.01" name="salary" required>
                </div>
                <div class="form-group">
                    <label>Bank Account</label>
                    <input type="text" name="bank_account">
                </div>
                <div class="form-group">
                    <label>Emergency Contact</label>
                    <input type="text" name="emergency_contact">
                </div>
                <div class="form-group">
                    <label>Brick Field</label>
                    <select name="field_id" required>
                        <?php foreach ($fields as $field): ?>
                            <option value="<?php echo $field['field_id']; ?>">
                                <?php echo htmlspecialchars($field['field_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Employee</button>
            </form>
        </section>

        <section class="employee-list">
            <h3>Employee Directory</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Name (Username)</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Field</th>
                    <th>Status</th>
                    <th>Joining Date</th>
                    <th>Salary</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['username']); ?></td>
                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                        <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                        <td><?php echo ucfirst($emp['role']); ?></td>
                        <td><?php echo htmlspecialchars($emp['field_name']); ?></td>
                        <td><?php echo ucfirst($emp['current_status']); ?></td>
                        <td><?php echo htmlspecialchars($emp['joining_date']); ?></td>
                        <td>৳<?php echo number_format($emp['salary'], 2); ?></td>
                        <td>
                            <a href="?delete=<?php echo $emp['employee_id']; ?>" class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to delete this employee?')">
                                Remove
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>
