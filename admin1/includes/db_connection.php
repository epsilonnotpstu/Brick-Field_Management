<?php
$host = '127.0.0.1';
$dbname = 'Bricks_Management';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "yah baby";    
    // Create admin user if not exists (for initial setup)
    $stmt = $conn->query("SELECT COUNT(*) FROM Users WHERE user_type = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->exec("
            INSERT INTO Users (username, password_hash, email, phone, user_type) 
            VALUES ('admin', '$password_hash', 'admin@bricksfield.com', '0123456789', 'admin')
        ");
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>