<?php
$page_title = "About Brick Field | Quality Brick Manufacturer";
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
        <section class="about-section">
            <h1>About Brick Field</h1>
            <div class="about-content">
                <div class="about-text">
                    <h2>30 Years of Excellence in Brick Manufacturing</h2>
                    <p>Founded in 1995, Brick Field has grown from a small local manufacturer to one of Bangladesh's most trusted brick suppliers. Our commitment to quality and customer satisfaction has remained unchanged throughout our journey.</p>
                    
                    <h3>Our Mission</h3>
                    <p>To provide high-quality, durable bricks using sustainable manufacturing practices while supporting the growth of Bangladesh's construction industry.</p>
                    
                    <h3>Our Facilities</h3>
                    <ul>
                        <li>State-of-the-art kilns with temperature control</li>
                        <li>Quality testing laboratory</li>
                        <li>50,000 sq ft production facility</li>
                        <li>Modern storage warehouses</li>
                    </ul>
                </div>
                
                <div class="about-image">
                    <img src="Bricks_factory.webp" alt="Brick Field Factory">
                </div>
            </div>
        </section>
        
        <section class="team-section">
            <h2>Our Leadership Team</h2>
            <div class="team-grid">
                <div class="team-member">
                    <img src="profile.avif" alt="CEO">
                    <h3>Md. Rahman</h3>
                    <p>Founder & CEO</p>
                </div>
                <div class="team-member">
                    <img src="profile.avif" alt="Operations Manager">
                    <h3>Md. Rafiq</h3>
                    <p>Supervisor</p>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>