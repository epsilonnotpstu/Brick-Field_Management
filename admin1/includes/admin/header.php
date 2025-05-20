<?php
// Make sure session and user data are available
if (!isset($_SESSION)) session_start();
?>
<header class="dashboard-header">
    <div class="header-content">
        <button class="menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1>BricksField Admin</h1>
        <div class="user-menu">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            <a href="../logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>
</header>

<script>
    // Sidebar toggle for mobile
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('.menu-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                document.querySelector('.sidebar')?.classList.toggle('active');
            });
        }
    });
</script>
