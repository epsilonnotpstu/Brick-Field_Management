<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<aside class="sidebar">
    <nav>
        <ul>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
                <a href="employees.php">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'production.php' ? 'active' : ''; ?>">
                <a href="production.php">
                    <i class="fas fa-industry"></i>
                    <span>Production</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                <a href="inventory.php">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                <a href="orders.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>