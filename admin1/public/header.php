<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine active page for navigation styling
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Brick Field - Quality Bricks'; ?></title>
    <link rel="stylesheet" href="../css_admin/css_admin.css">
    <link rel="icon" href="../images/favicon.ico" type="image/x-icon">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="header-branding">
                <a href="index.php">
                    <img src="rsz_2green_modern_and_minimalist_agriculture_farm_barn_logo.png" alt="Brick Field Logo" class="logo">
                    <h1>Brick Field</h1>
                </a>
            </div>
            
            <nav class="main-navigation">
                <ul>
                    <li <?php echo ($current_page == 'index.php') ? 'class="active"' : ''; ?>>
                        <a href="index.php">Home</a>
                    </li>
                    <li <?php echo ($current_page == 'products.php') ? 'class="active"' : ''; ?>>
                        <a href="products.php">Products</a>
                    </li>
                    <li <?php echo ($current_page == 'about.php') ? 'class="active"' : ''; ?>>
                        <a href="about.php">About Us</a>
                    </li>
                    <li <?php echo ($current_page == 'contact.php') ? 'class="active"' : ''; ?>>
                        <a href="contact.php">Contact</a>
                    </li>
                    
                        <li <?php echo ($current_page == 'login.php') ? 'class="active"' : ''; ?>>
                            <a href="../includes/login.php">Login</a>
                        </li>

                        <li><a href="../E-Commerce/login.php">Visit Our E-Commerce Website</a></li>
                </ul>
            </nav>
            
            <div class="mobile-menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <main class="main-content">