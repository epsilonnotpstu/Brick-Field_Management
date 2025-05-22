<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Ensure only admin can access
require_role('admin');

// Fetch current user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, phone FROM Users WHERE user_id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Add Brick Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_brick_field'])) {
    try {
        $field_name = $_POST['field_name'];
        $location = $_POST['location'];
        $district= $_POST['district'];
        $upazila = $_POST['upazila'];
        $total_area = $_POST['total_area'];
        $owner_name = $_POST['owner_name'];
        $license_number = $_POST['license_number'];
        $establishment_date = $_POST['establishment_date'];
        $contact_number = $_POST['contact_number'];
        $tax_id = $_POST['tax_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO BrickField (field_name, location,district,
        upazila,total_area,owner_name,license_number,establishment_date,contact_number,tax_id, is_active) VALUES (?,?,?,?, ?, ?,?,?,?,?,?)");
        $stmt->execute([$field_name, $location,$district,$upazila,$total_area,
        $owner_name,$license_number,$establishment_date,$contact_number,$tax_id, $is_active]);
        $success_message = "Brick field added successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to add brick field: " . $e->getMessage();
    }
}

// Handle Update Brick Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brick_field'])) {
    try {
        $field_id = $_POST['field_id'];
        $field_name = $_POST['field_name'];
        $location = $_POST['location'];
        $owner_name = $_POST['owner_name'];
        $contact_number = $_POST['contact_number'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE BrickField SET field_name = ?, location = ?,owner_name =?,contact_number =?, is_active = ? WHERE field_id = ?");
        $stmt->execute([$field_name, $location,$owner_name,$contact_number, $is_active, $field_id]);
        $success_message = "Brick field updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to update brick field: " . $e->getMessage();
    }
}

// Handle Add Brick Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_brick_type'])) {
    try {
        $type_name = $_POST['type_name'];
        //$description = $_POST['description'] ?: null;
        $size = $_POST['size'];
        $weight_kg = $_POST['weight_kg'];
        $compressive_strength_psi = $_POST['compressive_strength_psi'];
        $water_absorption = $_POST['water_absorption'];
        $standard = $_POST['standard'];


        $stmt = $conn->prepare("INSERT INTO BrickType (type_name, size,
        weight_kg,compressive_strength_psi,water_absorption,standard) VALUES (?,?,?,?,?, ?)");
        $stmt->execute([$type_name,$size,$weight_kg,$compressive_strength_psi,
        $water_absorption ,$standard ]);
        $success_message = "Brick type added successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to add brick type: " . $e->getMessage();
    }
}

// Handle Update Brick Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brick_type'])) {
    try {
        $brick_type_id = $_POST['brick_type_id'];
        $type_name = $_POST['type_name'];
        //$description = $_POST['description'] ?: null;

        $stmt = $conn->prepare("UPDATE BrickType SET type_name = ?, description = ? WHERE brick_type_id = ?");
        $stmt->execute([$type_name, $description, $brick_type_id]);
        $success_message = "Brick type updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to update brick type: " . $e->getMessage();
    }
}

// Handle Delete Brick Type
if (isset($_GET['delete_brick_type'])) {
    try {
        $brick_type_id = intval($_GET['delete_brick_type']);
        // Check if brick type is used in Production, Inventory, or Products
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM Production WHERE brick_type_id = ?
            UNION ALL
            SELECT COUNT(*) FROM Inventory WHERE brick_type_id = ?
            UNION ALL
            SELECT COUNT(*) FROM Products WHERE brick_type_id = ?
        ");
        $stmt->execute([$brick_type_id, $brick_type_id, $brick_type_id]);
        $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (array_sum($counts) == 0) {
            $stmt = $conn->prepare("DELETE FROM BrickType WHERE brick_type_id = ?");
            $stmt->execute([$brick_type_id]);
            $success_message = "Brick type deleted successfully!";
        } else {
            $error_message = "Cannot delete brick type; it is in use.";
        }
    } catch (PDOException $e) {
        $error_message = "Failed to delete brick type: " . $e->getMessage();
    }
}

// Handle Update User Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $current_user['password'];

        $stmt = $conn->prepare("
            UPDATE Users 
            SET username = ?, email = ?, phone = ?, password_hash = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$username, $email, $phone, $password, $user_id]);
        $_SESSION['username'] = $username;
        $success_message = "Profile updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to update profile: " . $e->getMessage();
    }
}

// Fetch data
$brick_fields = $conn->query("SELECT * FROM BrickField ORDER BY field_name")->fetchAll(PDO::FETCH_ASSOC);
$brick_types = $conn->query("SELECT * FROM BrickType ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <h2>System Settings</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <!-- Brick Field Management -->
            <section class="form-section">
                <h3>Add Brick Field</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_brick_field" value="1">
                    <div class="form-group">
                        <label>Field Name</label>
                        <input type="text" name="field_name" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" required>
                    </div>
                    <div class="form-group">
                        <label>District</label>
                        <input type="text" name="district" required>
                    </div>

                    <div class="form-group">
                        <label>Upazila</label>
                        <input type="text" name="upazila" required>
                    </div>
                    <div class="form-group">
                        <label>Total Area</label>
                        <input type="number" name="total_area" required>
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" required>
                    </div>
                    <div class="form-group">
                        <label>Lisence Number</label>
                         <input type="number" name="license_number" >  <!--lisence number not required -->
                    </div>
                    <div class="form-group">
                        <label>Date of Establishment</label>
                         <input type="date" name="establishment_date" required >  <!--lisence number not required -->
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                         <input type="number" name="contact_number" required >  <!--lisence number not required -->
                    </div>

                    <div class="form-group">
                        <label>Tax ID</label>
                         <input type="number" name="tax_id" required >  <!--lisence number not required -->
                    </div>

                    <div class="form-group">
                        <label>Active</label>
                        <input type="checkbox" name="is_active" checked>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Brick Field</button>
                </form>
            </section>

            <!-- Brick Type Management -->
            <section class="form-section">
                <h3>Add Brick Type</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="add_brick_type" value="1">
                    <div class="form-group">
                        <label>Type Name</label>
                        <input type="text" name="type_name" required>
                    </div>
                    <!-- <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div> -->

                    <div class="form-group">
                        <label>Brick Size</label>
                        <input type="text" name="size" required>
                    </div>

                    <div class="form-group">
                        <label>Brick Weight (Kg)</label>
                        <input type="number" name="weight_kg" required>
                    </div>

                    <div class="form-group">
                        <label>Strength (PSI)</label>
                        <input type="number" name="compressive_strength_psi" required>
                    </div>

                    <div class="form-group">
                        <label>Absorp Water</label>
                        <input type="number" name="	water_absorption" required>
                    </div>

                    <div class="form-group">
                        <label>Standarded Type</label>
                        <select name="standard">
                            <option value="ASTM">ASTM</option>
                            <option value="custom">CUSTOM</option>
                            <option value="BNBC" selected>BNBC</option>
                        </select>
                    </div>


                    <button type="submit" class="btn btn-primary">Add Brick Type</button>
                </form>
            </section>
        </div>

        <!-- User Profile -->
        <section class="form-section">
            <h3>Update Profile</h3>
            <form method="POST" class="auth-form">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($current_user['phone']); ?>" required>
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password">
                </div>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </section>

        <!-- Brick Fields List -->
        <section class="settings-list">
            <h3>Brick Fields</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Owner Name</th>
                    <th>Phone Number</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($brick_fields as $field): ?>
                    <tr>
                        <td data-label="Name"><?php echo htmlspecialchars($field['field_name']); ?></td>
                        <td data-label="Location"><?php echo htmlspecialchars($field['location']); ?></td>
                        <td data-label="Owner Name"><?php echo htmlspecialchars($field['owner_name']); ?></td>
                        <td data-label="Phone Number"><?php echo htmlspecialchars($field['contact_number']); ?></td>

                        <td data-label="Status"><?php echo $field['is_active'] ? 'Active' : 'Inactive'; ?></td>
                        <td data-label="Actions">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="update_brick_field" value="1">
                                <input type="hidden" name="field_id" value="<?php echo $field['field_id']; ?>">
                                <input type="text" name="field_name" value="<?php echo htmlspecialchars($field['field_name']); ?>" required>
                                <input type="text" name="location" value="<?php echo htmlspecialchars($field['location']); ?>" required>
                                <input type="text" name="owner_name" value="<?php echo htmlspecialchars($field['owner_name']); ?>" required>
                                <input type="number" name="contact_number" value="<?php echo htmlspecialchars($field['contact_number']); ?>" required>
                                <input type="checkbox" name="is_active" <?php echo $field['is_active'] ? 'checked' : ''; ?>>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- Brick Types List -->
        <section class="settings-list">
            <h3>Brick Types</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Weight</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($brick_types as $type): ?>
                    <tr>
                        <td data-label="Name"><?php echo htmlspecialchars($type['type_name']); ?></td>
                        <td data-label="size"><?php echo htmlspecialchars($type['size'] ?: 'N/A'); ?></td>
                        <td data-label="Actions">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="update_brick_type" value="1">
                                <input type="hidden" name="brick_type_id" value="<?php echo $type['brick_type_id']; ?>">
                                <input type="text" name="type_name" value="<?php echo htmlspecialchars($type['type_name']); ?>" required>
                                <input type="text" name="size" value="<?php echo htmlspecialchars($type['size'] ?: ''); ?>">
                                <button type="submit" class="btn btn-primary">Update</button>
                            </form>
                            <a href="?delete_brick_type=<?php echo $type['brick_type_id']; ?>" class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to delete this brick type? It must not be in use.')">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>