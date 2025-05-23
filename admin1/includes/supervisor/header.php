<?php
// Ensure session is started (in case header.php is included directly)
// Make sure session and user data are available
if (!isset($_SESSION)) session_start();
?>

<header class="dashboard-header">
    <div class="header-content">
        <button class="menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Supervisor Dashboard</h1>
        <div class="user-menu">
            <span>Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
            <a href="../logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>
</header>