<?php
session_start();
require_once 'config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get customer data
$customer = [];
$stmt = $pdo->prepare("SELECT * FROM Customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle form submission
$success_message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = trim($_POST['phone_number']);
    $organization_name = trim($_POST['organization_name']);
    $shipping_address = trim($_POST['shipping_address']);
    $billing_address = trim($_POST['billing_address']);
    $tax_id = trim($_POST['tax_id']);
    $preferred_payment_method = $_POST['preferred_payment_method'];

    // Validation
    if (empty($shipping_address)) {
        $errors[] = "Shipping address is required";
    }

    if (empty($errors)) {
        try {
            // Update customer record
            $stmt = $pdo->prepare("UPDATE Customers SET 
                                phone_number = ?,
                                  organization_name = ?,
                                  shipping_address = ?,
                                  billing_address = ?,
                                  tax_id = ?,
                                  preferred_payment_method = ?
                                  WHERE user_id = ?");
            
            $stmt->execute([
                $phone_number,
                $organization_name,
                $shipping_address,
                $billing_address ?: $shipping_address, // Use shipping if billing empty
                $tax_id,
                $preferred_payment_method,
                $_SESSION['user_id']
            ]);

            $success_message = "Profile updated successfully!";
            
            // Refresh customer data
            $stmt = $pdo->prepare("SELECT * FROM Customers WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $customer = $stmt->fetch();

        } catch (PDOException $e) {
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Brick Field</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .sidebar {
            background: #35424a;
            color: white;
            padding: 20px;
            border-radius: 5px;
        }
        .sidebar ul {
            list-style: none;
        }
        .sidebar li {
            margin-bottom: 10px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            text-decoration: underline;
        }
        .profile-completion {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .progress-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            margin-top: 10px;
            overflow: hidden;
        }
        .progress {
    height: 100%;
    background: #28a745;
}

        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group textarea {
            min-height: 80px;
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
                <li><a href="cart.php">Cart<span id="cart-count">0</span></a></li>
                <li><a href="dashboard.php">My Account</a></li>
                 <li><a href="logout.php">Logout</a></li> 
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?></h2>
        
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-container">
            <div class="sidebar">
                <h3>My Account</h3>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="dashboard.php">My Orders</a></li>
                    <li><a href="dashboard.php">Profile</a></li>
                    <li><a href="dashboard.php">Addresses</a></li>
                    <li><a href="dashboard.php">Payment Methods</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="profile-completion">
                    <h3>Profile Completion</h3>
                    <p>Complete your profile to get the best experience</p>
                    <div class="progress-bar">
    <div class="progress" style="width: <?php echo calculateProfileCompletion($customer); ?>%;"></div>
</div>

                </div>
                
                <h3>Customer Information</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($customer['full_name'] ?? ''); ?>" readonly>
                    </div>
                    
                    

                    <div class="form-group">
                        <label for="phone_number">Phone Number*</label>
                        <input type="text" id="phone_number" name="phone_number" 
                               value="<?php echo htmlspecialchars($customer['phone_number'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="text" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="organization_name">Organization Name (Optional)</label>
                        <input type="text" id="organization_name" name="organization_name" 
                               value="<?php echo htmlspecialchars($customer['organization_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping_address">Shipping Address*</label>
                        <textarea id="shipping_address" name="shipping_address" required><?php 
                            echo htmlspecialchars($customer['shipping_address'] ?? ''); 
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="billing_address">Billing Address (Leave blank if same as shipping)</label>
                        <textarea id="billing_address" name="billing_address"><?php 
                            echo htmlspecialchars($customer['billing_address'] ?? ''); 
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_id">Tax ID/VAT Number (Optional)</label>
                        <input type="text" id="tax_id" name="tax_id" 
                               value="<?php echo htmlspecialchars($customer['tax_id'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="preferred_payment_method">Preferred Payment Method</label>
                        <select id="preferred_payment_method" name="preferred_payment_method">
                            <option value="cash" <?php echo ($customer['preferred_payment_method'] ?? '') == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="bkash" <?php echo ($customer['preferred_payment_method'] ?? '') == 'bkash' ? 'selected' : ''; ?>>bKash</option>
                            <option value="nagad" <?php echo ($customer['preferred_payment_method'] ?? '') == 'nagad' ? 'selected' : ''; ?>>Nagad</option>
                            <option value="card" <?php echo ($customer['preferred_payment_method'] ?? '') == 'card' ? 'selected' : ''; ?>>Credit/Debit Card</option>
                            <option value="bank_transfer" <?php echo ($customer['preferred_payment_method'] ?? '') == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Brick Field E-Commerce. All rights reserved.</p>
    </footer>
</body>
</html>

<?php
function calculateProfileCompletion($customer) {
    $completed = 0;
    $totalFields = 5; // full_name, shipping_address, billing_address, preferred_payment_method
    
    if (!empty($customer['full_name'])) $completed++;
    if (!empty($customer['phone_number'])) $completed++;
    if (!empty($customer['shipping_address'])) $completed++;
    if (!empty($customer['billing_address'])) $completed++;
    if (!empty($customer['preferred_payment_method'])) $completed++;
    
    return ($completed / $totalFields) * 100;
}
?>