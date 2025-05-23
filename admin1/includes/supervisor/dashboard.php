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
    SELECT e.*, f.field_name 
    FROM Employees e
    JOIN BrickField f ON e.field_id = f.field_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor) {
    $error_message = "Supervisor details not found.";
}

// Fetch Today's Production
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT SUM(quantity_produced) as total_produced
    FROM Production
    WHERE field_id = ? AND DATE(production_date) = ?
");
$stmt->execute([$supervisor['field_id'], $today]);
$today_production = $stmt->fetch(PDO::FETCH_ASSOC)['total_produced'] ?? 0;
$daily_target = 3750; // Example target, adjust as needed
$production_percentage = $daily_target > 0 ? ($today_production / $daily_target) * 100 : 0;

// Fetch Quality Rating
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_checks, 
           SUM(CASE WHEN grade = 'A' THEN 1 ELSE 0 END) as grade_a
    FROM QualityControl
    WHERE field_id = ? AND DATE(inspection_date) = ?
");
$stmt->execute([$supervisor['field_id'], $today]);
$quality_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_checks = $quality_data['total_checks'] ?? 0;
$grade_a = $quality_data['grade_a'] ?? 0;
$quality_rating = $total_checks > 0 ? ($grade_a / $total_checks) * 100 : 0;
$defect_percentage = 100 - $quality_rating;

// Fetch Workers Present
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_workers,
           SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present_workers
    FROM Employees e
    LEFT JOIN Attendance a ON e.employee_id = a.employee_id 
        AND DATE(a.date) = ?
    WHERE e.field_id = ? AND e.role = 'worker'
");
$stmt->execute([$today, $supervisor['field_id']]);
$attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_workers = $attendance_data['total_workers'] ?? 0;
$present_workers = $attendance_data['present_workers'] ?? 0;
$attendance_percentage = $total_workers > 0 ? ($present_workers / $total_workers) * 100 : 0;
$absent_workers = $total_workers - $present_workers;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php else: ?>
                <div class="welcome-banner">
                    <h2>Production Management</h2>
                    <p>Manage your brick production activities for <?php echo htmlspecialchars($supervisor['field_name']); ?></p>
                </div>
                
                <div class="quick-actions">
                    <a href="production.php?action=new" class="quick-action">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Production</span>
                    </a>
                    <a href="quality.php" class="quick-action">
                        <i class="fas fa-check-double"></i>
                        <span>Quality Check</span>
                    </a>
                    <a href="workers.php" class="quick-action">
                        <i class="fas fa-user-clock"></i>
                        <span>Attendance</span>
                    </a>
                </div>
                
                <div class="production-stats">
                    <div class="stat-card">
                        <h3>Today's Production</h3>
                        <p><?php echo number_format($today_production); ?> <span>Bricks</span></p>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo min($production_percentage, 100); ?>%"></div>
                        </div>
                        <small><?php echo round($production_percentage, 1); ?>% of daily target (<?php echo number_format($daily_target); ?>)</small>
                    </div>
                    <div class="stat-card">
                        <h3>Quality Rating</h3>
                        <p><?php echo round($quality_rating, 1); ?>% <span>Grade A</span></p>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo $quality_rating; ?>%"></div>
                        </div>
                        <small><?php echo round($defect_percentage, 1); ?>% defects</small>
                    </div>
                    <div class="stat-card">
                        <h3>Workers Present</h3>
                        <p><?php echo $present_workers; ?> <span>/ <?php echo $total_workers; ?></span></p>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo $attendance_percentage; ?>%"></div>
                        </div>
                        <small><?php echo $absent_workers; ?> absent or on leave today</small>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>