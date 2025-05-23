<?php
// Ensure session is started
if (!session_id()) {
    session_start();
}
require_once '../auth_check.php';

// Only allow supervisor access
if ($_SESSION['user_type'] != 'supervisor') {
    header("Location: ../unauthorized.php");
    exit();
}

// Get supervisor details
require_once '../db_connection.php';
$stmt = $conn->prepare("
    SELECT e.field_id, e.employee_id, f.field_name 
    FROM Employees e
    JOIN BrickField f ON e.field_id = f.field_id
    WHERE e.user_id = ? AND e.role = 'supervisor'
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor) {
    $error_message = "Supervisor details not found.";
}

// Handle Record Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_expense'])) {
    try {
        $category = $_POST['category'];
        $amount = $_POST['amount'];
        $description = $_POST['description'] ?: null;
        $approved_by = $supervisor['employee_id']; // Supervisor approves
        
        if ($amount <= 0) {
            throw new Exception("Amount must be positive.");
        }
        
        // Handle receipt_image upload
        $receipt_image = null;
        if (!empty($_FILES['receipt_image']['name'])) {
            $target_dir = "uploads/receipts/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $file_ext = strtolower(pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception("Invalid file type. Allowed: jpg, jpeg, png, pdf.");
            }
            $receipt_image = "receipt_" . time() . "." . $file_ext;
            $target_file = $target_dir . $receipt_image;
            if (!move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target_file)) {
                throw new Exception("Failed to upload receipt image.");
            }
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO Expenses (field_id, expense_date, category, amount, description, approved_by, receipt_image)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$supervisor['field_id'], $category, $amount, $description, $approved_by, $receipt_image]);
        $conn->commit();
        $success_message = "Expense recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to record expense: " . $e->getMessage();
    }
}

// Handle Record Material Receipt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_receipt'])) {
    try {
        $material_id = $_POST['material_id'];
        $supplier_id = $_POST['supplier_id'];
        $quantity = $_POST['quantity'];
        $unit_price = $_POST['unit_price'];
        $quality_rating = $_POST['quality_rating'];
        $received_by = $supervisor['employee_id'];
        
        if ($quantity <= 0 || $unit_price < 0) {
            throw new Exception("Invalid input values.");
        }
        
        // Verify material_id and supplier_id
        $stmt = $conn->prepare("SELECT material_id FROM RawMaterials WHERE material_id = ? AND field_id = ?");
        $stmt->execute([$material_id, $supervisor['field_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid material selected.");
        }
        
        $stmt = $conn->prepare("SELECT supplier_id FROM Suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplier_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid supplier selected.");
        }
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO MaterialReceipt (material_id, supplier_id, receipt_date, quantity, unit_price, quality_rating, received_by)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?)
        ");
        $stmt->execute([$material_id, $supplier_id, $quantity, $unit_price, $quality_rating, $received_by]);
        
        // Update RawMaterials stock
        $stmt = $conn->prepare("
            UPDATE RawMaterials 
            SET current_stock = current_stock + ?, last_restock_date = CURDATE()
            WHERE material_id = ? AND field_id = ?
        ");
        $stmt->execute([$quantity, $material_id, $supervisor['field_id']]);
        $conn->commit();
        $success_message = "Material receipt recorded successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Failed to record receipt: " . $e->getMessage();
    }
}

// Fetch Recent Expenses
$expense_filter = $_POST['expense_filter'] ?? 'all';
$start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$expense_query = "SELECT e.expense_id, e.category, e.amount, e.expense_date, e.description, e.receipt_image, u.username
    FROM Expenses e
    JOIN Employees emp ON e.approved_by = emp.employee_id
    JOIN Users u ON emp.user_id = u.user_id
    WHERE e.field_id = ? AND e.expense_date BETWEEN ? AND ?";
if ($expense_filter !== 'all') {
    $expense_query .= " AND e.category = ?";
}
$stmt = $conn->prepare($expense_query);
$params = [$supervisor['field_id'], $start_date, $end_date];
if ($expense_filter !== 'all') {
    $params[] = $expense_filter;
}
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Expense Summary
$stmt = $conn->prepare("
    SELECT category, SUM(amount) as total
    FROM Expenses
    WHERE field_id = ? AND expense_date BETWEEN ? AND ?
    GROUP BY category
");
$stmt->execute([$supervisor['field_id'], $start_date, $end_date]);
$expense_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Material Receipts
$stmt = $conn->prepare("
    SELECT mr.receipt_id, mr.quantity, mr.receipt_date, mr.unit_price, mr.quality_rating, rm.material_name, s.name as supplier_name
    FROM MaterialReceipt mr
    JOIN RawMaterials rm ON mr.material_id = rm.material_id
    JOIN Suppliers s ON mr.supplier_id = s.supplier_id
    JOIN Employees e ON mr.received_by = e.employee_id
    WHERE e.field_id = ? AND mr.receipt_date BETWEEN ? AND ?
    ORDER BY mr.receipt_date DESC
    LIMIT 10
");
$stmt->execute([$supervisor['field_id'], $start_date, $end_date]);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Material Cost Summary
$stmt = $conn->prepare("
    SELECT rm.material_name, SUM(mr.quantity * mr.unit_price) as total_cost
    FROM MaterialReceipt mr
    JOIN RawMaterials rm ON mr.material_id = rm.material_id
    JOIN Employees e ON mr.received_by = e.employee_id
    WHERE e.field_id = ? AND mr.receipt_date BETWEEN ? AND ?
    GROUP BY rm.material_name
");
$stmt->execute([$supervisor['field_id'], $start_date, $end_date]);
$material_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Production Summary
$stmt = $conn->prepare("
    SELECT mold_type, SUM(quantity) as total_produced
    FROM RawBrickProduction
    WHERE field_id = ? AND production_date BETWEEN ? AND ?
    GROUP BY mold_type
");
$stmt->execute([$supervisor['field_id'], $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$production_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Attendance Summary
$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count, SUM(overtime_hours) as total_overtime
    FROM Attendance a
    JOIN Employees e ON a.employee_id = e.employee_id
    WHERE e.field_id = ? AND a.date BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$supervisor['field_id'], $start_date, $end_date]);
$attendance_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Firing Summary
$stmt = $conn->prepare("
    SELECT AVG(success_rate) as avg_success_rate
    FROM Firing
    WHERE supervisor_id = ? AND end_date IS NOT NULL AND start_date BETWEEN ? AND ?
");
$stmt->execute([$supervisor['employee_id'], $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$firing_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Material Stock Status
$stmt = $conn->prepare("
    SELECT material_name, current_stock, reorder_level, unit_of_measure
    FROM RawMaterials
    WHERE field_id = ?
");
$stmt->execute([$supervisor['field_id']]);
$stock_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Materials and Suppliers for Forms
$stmt = $conn->prepare("SELECT material_id, material_name FROM RawMaterials WHERE field_id = ?");
$stmt->execute([$supervisor['field_id']]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT supplier_id, name FROM Suppliers");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | BricksField</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <h2>Reports</h2>
            <p>Financial and operational reports for <?php echo htmlspecialchars($supervisor['field_name'] ?? 'N/A'); ?></p>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Record Expense -->
            <section class="form-container">
                <h3>Record Expense</h3>
                <form method="POST" class="auth-form" enctype="multipart/form-data">
                    <input type="hidden" name="record_expense" value="1">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            <option value="fuel">Fuel</option>
                            <option value="labor">Labor</option>
                            <option value="raw_material">Raw Material</option>
                            <option value="equipment">Equipment</option>
                            <option value="transport">Transport</option>
                            <option value="utility">Utility</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Receipt Image (jpg, jpeg, png, pdf)</label>
                        <input type="file" name="receipt_image" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <button type="submit" class="btn btn-primary">Record Expense</button>
                </form>
            </section>
            
            <!-- Record Material Receipt -->
            <section class="form-container">
                <h3>Record Material Receipt</h3>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="record_receipt" value="1">
                    <div class="form-group">
                        <label>Material</label>
                        <select name="material_id" required>
                            <option value="">Select Material</option>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?php echo $material['material_id']; ?>">
                                    <?php echo htmlspecialchars($material['material_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['supplier_id']; ?>">
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Price</label>
                        <input type="number" name="unit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Quality Rating</label>
                        <select name="quality_rating" required>
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="average">Average</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Record Receipt</button>
                </form>
            </section>
            
            <!-- Filter Expenses -->
            <section class="form-container">
                <h3>Filter Expenses</h3>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="expense_filter">
                            <option value="all" <?php echo $expense_filter == 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="fuel" <?php echo $expense_filter == 'fuel' ? 'selected' : ''; ?>>Fuel</option>
                            <option value="labor" <?php echo $expense_filter == 'labor' ? 'selected' : ''; ?>>Labor</option>
                            <option value="raw_material" <?php echo $expense_filter == 'raw_material' ? 'selected' : ''; ?>>Raw Material</option>
                            <option value="equipment" <?php echo $expense_filter == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                            <option value="transport" <?php echo $expense_filter == 'transport' ? 'selected' : ''; ?>>Transport</option>
                            <option value="utility" <?php echo $expense_filter == 'utility' ? 'selected' : ''; ?>>Utility</option>
                            <option value="other" <?php echo $expense_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </section>
            
            <!-- Recent Expenses -->
            <section class="form-container">
                <h3>Recent Expenses</h3>
                <?php if (empty($expenses)): ?>
                    <p>No expenses found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Approved By</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                    <td><?php echo number_format($expense['amount'], 2); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($expense['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($expense['username']); ?></td>
                                    <td>
                                        <?php if ($expense['receipt_image']): ?>
                                            <a href="uploads/receipts/<?php echo htmlspecialchars($expense['receipt_image']); ?>" target="_blank">View</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Expense Summary -->
            <section class="form-container">
                <h3>Expense Summary</h3>
                <?php if (empty($expense_summary)): ?>
                    <p>No expense data available.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_summary as $summary): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['category']); ?></td>
                                    <td><?php echo number_format($summary['total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Recent Material Receipts -->
            <section class="form-container">
                <h3>Recent Material Receipts</h3>
                <?php if (empty($receipts)): ?>
                    <p>No receipts found.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Quantity</th>
                                <th>Total Cost</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Quality</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($receipt['material_name']); ?></td>
                                    <td><?php echo number_format($receipt['quantity'], 2); ?></td>
                                    <td><?php echo number_format($receipt['quantity'] * $receipt['unit_price'], 2); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($receipt['receipt_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['supplier_name']); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['quality_rating']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Material Cost Summary -->
            <section class="form-container">
                <h3>Material Cost Summary</h3>
                <?php if (empty($material_summary)): ?>
                    <p>No material cost data available.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($material_summary as $summary): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['material_name']); ?></td>
                                    <td><?php echo number_format($summary['total_cost'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Production Summary -->
            <section class="form-container">
                <h3>Production Summary</h3>
                <?php if (empty($production_summary)): ?>
                    <p>No production data available.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Mold Type</th>
                                <th>Total Produced</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($production_summary as $summary): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['mold_type']); ?></td>
                                    <td><?php echo number_format($summary['total_produced']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Attendance Summary -->
            <section class="form-container">
                <h3>Attendance Summary</h3>
                <?php if (empty($attendance_summary)): ?>
                    <p>No attendance data available.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Total Overtime (Hours)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_summary as $summary): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['status']); ?></td>
                                    <td><?php echo $summary['count']; ?></td>
                                    <td><?php echo number_format($summary['total_overtime'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- Firing Performance -->
            <section class="form-container">
                <h3>Firing Performance</h3>
                <?php if (!$firing_summary['avg_success_rate']): ?>
                    <p>No firing data available.</p>
                <?php else: ?>
                    <p>Average Success Rate: <?php echo number_format($firing_summary['avg_success_rate'], 2); ?>%</p>
                <?php endif; ?>
            </section>
            
            <!-- Material Stock Status -->
            <section class="form-container">
                <h3>Material Stock Status</h3>
                <?php if (empty($stock_summary)): ?>
                    <p>No stock data available.</p>
                <?php else: ?>
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stock_summary as $summary): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['material_name']); ?></td>
                                    <td><?php echo number_format($summary['current_stock'], 2); ?></td>
                                    <td><?php echo number_format($summary['reorder_level'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($summary['unit_of_measure']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            
            <!-- PDF Download -->
            <section class="form-container">
                <h3>Download Summary Report</h3>
                <p>Download a LaTeX file and compile it with <code>latexmk -pdf summary_report.tex</code> using a LaTeX distribution like TeX Live.</p>
                <a href="generate_summary_pdf.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-file-pdf"></i> Download PDF Summary (.tex)
                </a>
            </section>
        </main>
    </div>

    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>