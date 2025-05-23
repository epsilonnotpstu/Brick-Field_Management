<?php
     require_once '../auth_check.php';
     if ($_SESSION['user_type'] != 'supervisor') {
         header("Location: ../unauthorized.php");
         exit();
     }
     require_once '../db_connection.php';
     $stmt = $conn->prepare("SELECT field_id FROM Employees WHERE user_id = ?");
     $stmt->execute([$_SESSION['user_id']]);
     $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quality_check'])) {
         try {
             $grade = $_POST['grade'];
             $notes = $_POST['notes'] ?: null;
             $stmt = $conn->prepare("
                 INSERT INTO QualityControl (field_id, grade, inspection_date, notes)
                 VALUES (?, ?, NOW(), ?)
             ");
             $stmt->execute([$supervisor['field_id'], $grade, $notes]);
             $success_message = "Quality check added successfully!";
         } catch (PDOException $e) {
             $error_message = "Failed to add quality check: " . $e->getMessage();
         }
     }
     ?>
     <!DOCTYPE html>
     <html lang="en">
     <head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title>Quality Control | BricksField</title>
         <link rel="stylesheet" href="../css/style.css">
         <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     </head>
     <body>
         <?php include 'header.php'; ?>
         <div class="dashboard-container">
             <?php include 'sidebar.php'; ?>
             <main class="main-content">
                 <h2>Quality Control</h2>
                 <?php if (isset($success_message)): ?>
                     <div class="alert alert-success"><?php echo $success_message; ?></div>
                 <?php elseif (isset($error_message)): ?>
                     <div class="alert alert-danger"><?php echo $error_message; ?></div>
                 <?php endif; ?>
                 <div class="form-container">
                     <section class="form-section">
                         <h3>Add Quality Check</h3>
                         <form method="POST" class="auth-form">
                             <input type="hidden" name="add_quality_check" value="1">
                             <div class="form-group">
                                 <label>Grade</label>
                                 <select name="grade" required>
                                     <option value="A">A</option>
                                     <option value="B">B</option>
                                     <option value="C">C</option>
                                 </select>
                             </div>
                             <div class="form-group">
                                 <label>Notes</label>
                                 <textarea name="notes" rows="4"></textarea>
                             </div>
                             <button type="submit" class="btn btn-primary">Add Quality Check</button>
                         </form>
                     </section>
                 </div>
             </main>
         </div>
         <script>
             document.querySelector('.menu-toggle').addEventListener('click', function() {
                 document.querySelector('.sidebar').classList.toggle('active');
             });
         </script>
     </body>
     </html>