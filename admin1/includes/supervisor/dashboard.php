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
$supervisor = $stmt->fetch();
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
    <header class="dashboard-header">
        <div class="header-content">
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1>Supervisor Dashboard</h1>
            <div class="user-menu">
                <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                <a href="../logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="profile-info">
                <div class="profile-image">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3><?php echo $_SESSION['username']; ?></h3>
                <p>Supervisor</p>
                <p><?php echo htmlspecialchars($supervisor['field_name']); ?></p>
            </div>
            
            <nav>
                <ul>
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="production.php">
                            <i class="fas fa-industry"></i>
                            <span>Production</span>
                        </a>
                    </li>
                    <li>
                        <a href="quality.php">
                            <i class="fas fa-check-circle"></i>
                            <span>Quality Control</span>
                        </a>
                    </li>
                    <li>
                        <a href="workers.php">
                            <i class="fas fa-users"></i>
                            <span>Workers</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h2>Production Management</h2>
                <p>Manage your brick production activities</p>
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
                    <p>2,450 <span>Bricks</span></p>
                    <div class="progress-bar">
                        <div class="progress" style="width: 65%"></div>
                    </div>
                    <small>65% of daily target</small>
                </div>
                
                <div class="stat-card">
                    <h3>Quality Rating</h3>
                    <p>92% <span>Grade A</span></p>
                    <div class="progress-bar">
                        <div class="progress" style="width: 92%"></div>
                    </div>
                    <small>8% defects</small>
                </div>
                
                <div class="stat-card">
                    <h3>Workers Present</h3>
                    <p>18 <span>/ 20</span></p>
                    <div class="progress-bar">
                        <div class="progress" style="width: 90%"></div>
                    </div>
                    <small>2 absent today</small>
                </div>
            </div>
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