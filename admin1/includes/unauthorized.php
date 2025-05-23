 <?php
// Start session to check user status
session_start();

// Determine if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access | Bricks Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="unauthorized-container">
        <div class="alert alert-danger">
            <h2>Unauthorized Access</h2>
            <p>You do not have permission to access this page.</p>
            <?php if ($is_logged_in): ?>
                <p>Please return to the dashboard or contact an administrator.</p>
                <a href="admin/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            <?php else: ?>
                <p>Please log in to access the system.</p>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 