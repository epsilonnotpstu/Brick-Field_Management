<?php

require_once '../config_admin/db_admin.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($message)) {
        $error_message = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address";
    } else {
        try {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO contact_submissions (name, email, phone, message, submitted_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $phone, $message]);
            
            $success_message = "Thank you for your message! We'll contact you soon.";
            
            // Clear form
            $_POST = array();
            
        } catch (PDOException $e) {
            error_log("Contact form error: " . $e->getMessage());
            $error_message = "There was an error submitting your message. Please try again later.";
        }
    }
}

$page_title = "Contact Us | Brick Field";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css_admin/css_admin.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <h1>Contact Brick Field</h1>
        
        <div class="contact-container">
            <div class="contact-form">
                <?php if ($success_message): ?>
                    <div class="success-message"><?php echo $success_message; ?></div>
                <?php elseif ($error_message): ?>
                    <div class="error-message"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Your Name*</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address*</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Your Message*</label>
                        <textarea id="message" name="message" required><?php 
                            echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; 
                        ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">Send Message</button>
                </form>
            </div>
            
            <div class="contact-info">
                <h2>Our Office</h2>
                <address>
                    <p><strong>Joney Brick Field Ltd.</strong></p>
                    <p>Adorsha Para, Magura</p>
                    <p>Magura, Bangladesh</p>
                    <p>Postal Code: 7600</p>
                </address>
                
                <h2>Contact Details</h2>
                <ul class="contact-methods">
                    <li><strong>Phone:</strong> +880 833054648</li>
                    <li><strong>Email:</strong> info@brickfield.com</li>
                    <li><strong>Hours:</strong> 8:00 AM - 6:00 PM (Sat-Thu)</li>
                </ul>
                
                <div class="map-container">
                    <iframe src="https://maps.google.com/maps?q=adorsha para,magura,khulna&output=embed" 
                            width="100%" height="300" frameborder="0" style="border:0" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>