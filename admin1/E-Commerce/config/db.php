<?php
//$host = '127.0.0.1';
$dbname = 'Bricks_Management';
$username = 'root';
$password = '';

try {
   $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $dbname . ';charset=utf8', $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "হান্দানো সঠিক!! ";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
