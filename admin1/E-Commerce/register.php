<?php
include 'config/db.php';

$success_message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $user_type = 'customer';
    
    try {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            if ($existingUser['username'] === $username) {
                $error = "Username already exists";
            } else {
                $error = "Email already registered";
            }
        } else {
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO Users (username, password_hash, email, phone, user_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $phone, $user_type]);
            
            $user_id = $pdo->lastInsertId();
            
            // Also create customer record
            $stmt = $pdo->prepare("INSERT INTO Customers (user_id, full_name) VALUES (?, ?)");
            $stmt->execute([$user_id, $_POST['full_name']]);
            
            // Set success message and clear form
            $success_message = "Registration successful! You can now login.";
            $_POST = array(); // Clear form fields
        }
    } catch (PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Brick Field</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .success-message {
            color: green;
            background-color: #ddffdd;
            padding: 10px;
            border: 1px solid green;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .error-message {
            color: red;
            background-color: #ffdddd;
            padding: 10px;
            border: 1px solid red;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Brick Field E-Commerce</h1>
         <nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart <span id="cart-count">0</span></a></li>
        <li><a href="login.php">Login</a></li>
    </ul>
</nav>
    </header>

    <div class="container">
        <h2>Register</h2>
        
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
                <p>You can now <a href="login.php">login here</a>.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div>
                <label>Full Name:</label>
                <input type="text" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
            </div>
            <div>
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div>
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <div>
                <label>Phone:</label>
                <input type="text" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
            </div>
            <div>
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        
        <p style="margin-top: 15px;">Already have an account? <a href="login.php">Login here</a></p>
    </div>

    <footer>
        <p>&copy; 2023 Brick Field E-Commerce. All rights reserved.</p>
    </footer>
</body>
</html>