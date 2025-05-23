<?php
// Ensure session is started and supervisor details are available
if (!isset($_SESSION)) session_start();
require_once '../db_connection.php';
$stmt = $conn->prepare("
    SELECT e.*, f.field_name 
    FROM Employees e
    JOIN BrickField f ON e.field_id = f.field_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<aside class="sidebar">
    <div class="profile-info">
        <div class="profile-image">
            <i class="fas fa-user-tie"></i>
        </div>
        <p><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></p>
        <p>Supervisor</p>
        <p><?php echo isset($supervisor['field_name']) ? htmlspecialchars($supervisor['field_name']) : 'N/A'; ?></p>
    </div>
    
    <nav >
        <ul>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'production.php' ? 'active' : ''; ?>">
                <a href="production.php">
                    <i class="fas fa-industry"></i>
                    <span>Production</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'quality.php' ? 'active' : ''; ?>">
                <a href="quality.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Quality Control</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'workers.php' ? 'active' : ''; ?>">
                <a href="workers.php">
                    <i class="fas fa-users"></i>
                    <span>Workers</span>
                </a>
            </li>

            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'firing.php' ? 'active' : ''; ?>">
                <a href="firing.php">
                    <i class="fas fa-users"></i>
                    <span>Kiln & Firing</span>
                </a>
            </li>

            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : ''; ?>">
                <a href="suppliers.php">
                    <i class="fas fa-truck"></i>
                    <span>Suppliers</span>
                </a>
            </li>

            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>