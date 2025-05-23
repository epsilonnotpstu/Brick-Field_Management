<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Only allow admin access
if ($_SESSION['user_type'] != 'admin') {
    header("Location: ../unauthorized.php");
    exit();
}

// Get statistics from database
try {
    // Total Employees
    $stmt = $conn->query("SELECT COUNT(*) FROM Employees WHERE current_status = 'active'");
    $total_employees = $stmt->fetchColumn();

    // Today's Production
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT SUM(quantity_produced) FROM Production WHERE production_date = ?");
    $stmt->execute([$today]);
    $todays_production = $stmt->fetchColumn() ?? 0;
    //All Production
    $stmt = $conn->prepare("SELECT SUM(quantity_produced) FROM Production");
    $stmt->execute();
    $all_production = $stmt->fetchColumn() ?? 0;
    // Pending Orders
    $stmt = $conn->query("SELECT COUNT(*) FROM Orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetchColumn();

    // Today's Revenue
    $stmt = $conn->prepare("
        SELECT SUM(total_amount) 
        FROM Orders 
        WHERE DATE(order_date) = ? 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$today]);
    $todays_revenue = $stmt->fetchColumn() ?? 0;

    // Recent Activities (combines orders, production, and employee activities)
    $recent_activities = $conn->query("
        (SELECT 
            'order' as type, 
            order_id as id, 
            order_date as date, 
            CONCAT('New order #', order_id, ' from ', 
                (SELECT full_name FROM Customers WHERE customer_id = Orders.customer_id)) as description
        FROM Orders 
        ORDER BY order_date DESC 
        LIMIT 3)
        
        UNION
        
        (SELECT 
            'production' as type, 
            production_id as id, 
            production_date as date, 
            CONCAT('Production of ', quantity_produced, ' ', 
                (SELECT type_name FROM BrickType WHERE brick_type_id = Production.brick_type_id), ' bricks') as description
        FROM Production 
        ORDER BY production_date DESC 
        LIMIT 3)
        
        UNION
        
        (SELECT 
            'employee' as type, 
            employee_id as id, 
            joining_date as date, 
            CONCAT('New employee: ', 
                (SELECT username FROM Users WHERE user_id = Employees.user_id), ' joined as ', role) as description
        FROM Employees 
        ORDER BY joining_date DESC 
        LIMIT 2)
        
        ORDER BY date DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle database errors gracefully
    error_log("Dashboard error: " . $e->getMessage());
    $error = "Unable to load dashboard data. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="header-content">
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1>Admin Dashboard</h1>
            <div class="user-menu">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="dashboard-container">
        <aside class="sidebar">
            <nav>
                <ul>
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="employees.php">
                            <i class="fas fa-users"></i>
                            <span>Employees</span>
                        </a>
                    </li>
                    <li>
                        <a href="production.php">
                            <i class="fas fa-industry"></i>
                            <span>Production</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php">
                            <i class="fas fa-boxes"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="welcome-banner">
                <h2>Welcome to BricksField Management System</h2>
                <p>Today is <?php echo date('l, F j, Y'); ?></p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Employees</h3>
                        <p><?php echo number_format($total_employees); ?></p>
                        <small><?php echo date('M Y'); ?></small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bricks"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Today's Production</h3>
                        <p><?php echo number_format($todays_production); ?></p>
                        <small>Bricks produced today</small>
                    </div>
                    
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pending Orders</h3>
                        <p><?php echo number_format($pending_orders); ?></p>
                        <small>Awaiting processing</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Today's Revenue</h3>
                        <p>৳<?php echo number_format($todays_revenue, 2); ?></p>
                        <small>Paid orders today</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>All Production</h3>
                        <p><?php echo number_format($all_production); ?></p>
                        <small>All Bricks Produced</small>

                    </div>
                </div>
            </div>
            
            <div class="recent-activity">
                <h3>Recent Activities</h3>
                
                <?php if(empty($recent_activities)): ?>
                    <p class="no-activities">No recent activities found</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <?php if($activity['type'] == 'order'): ?>
                                        <i class="fas fa-shopping-cart"></i>
                                    <?php elseif($activity['type'] == 'production'): ?>
                                        <i class="fas fa-industry"></i>
                                    <?php else: ?>
                                        <i class="fas fa-user-plus"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-content">
                                    <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <small><?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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