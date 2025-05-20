<?php
session_start();
require_once 'db_connection.php';

// Only allow admin and supervisor signups
$allowed_types = ['admin', 'supervisor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_type = in_array($_POST['user_type'], $allowed_types) ? $_POST['user_type'] : null;
    
    if (!$user_type) {
        $error = "Invalid user type selected";
    } else {
        try {
            $conn->beginTransaction();
            
            // Create user
            $stmt = $conn->prepare("
                INSERT INTO Users (username, password_hash, email, phone, user_type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $password, $email, $phone, $user_type]);
            
            $user_id = $conn->lastInsertId();
            
            // Create employee record if supervisor
            if ($user_type === 'supervisor') {
                $stmt = $conn->prepare("
                    INSERT INTO Employees (user_id, field_id, nid_number, role, joining_date, salary)
                    VALUES (?, ?, ?, 'supervisor', CURDATE(), 35000)
                ");
                $stmt->execute([$user_id, $_POST['field_id'], $_POST['nid_number']]);
            }
            
            $conn->commit();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $user_type;
            
            header("Location: {$user_type}/dashboard.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

// Fetch active brick fields for dropdown
$brick_fields = $conn->query("SELECT * FROM BrickField WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bricks Field - Sign Up</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <div class="logo">
                <i class="fas fa-bricks"></i>
                <h1>BricksField</h1>
            </div>
            
            <h2>Create Administrator/Supervisor Account</h2>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="signupForm">
                <div class="form-group">
                    <label for="user_type">Account Type</label>
                    <select id="user_type" name="user_type" required>
                        <option value="">Select Account Type</option>
                        <option value="admin">Administrator</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-with-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                </div>
                
                <div class="form-group" id="nidField" style="display: none;">
                    <label for="nid_number">NID Number (Required for Supervisors)</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="nid_number" name="nid_number">
                    </div>
                </div>
                
                <div class="form-group" id="fieldField" style="display: none;">
                    <label for="field_id">Brick Field (Required for Supervisors)</label>
                    <select id="field_id" name="field_id">
                        <option value="">Select Brick Field</option>
                        <?php foreach ($brick_fields as $field): ?>
                            <option value="<?php echo $field['field_id']; ?>">
                                <?php echo htmlspecialchars($field['field_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <small class="form-text">Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('user_type').addEventListener('change', function() {
            const nidField = document.getElementById('nidField');
            const fieldField = document.getElementById('fieldField');
            if (this.value === 'supervisor') {
                nidField.style.display = 'block';
                fieldField.style.display = 'block';
                document.getElementById('nid_number').setAttribute('required', '');
                document.getElementById('field_id').setAttribute('required', '');
            } else {
                nidField.style.display = 'none';
                fieldField.style.display = 'none';
                document.getElementById('nid_number').removeAttribute('required');
                document.getElementById('field_id').removeAttribute('required');
            }
        });

        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                confirmPassword.focus();
            }
            
            if (password.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                password.focus();
            }
        });
    </script>
</body>
</html>