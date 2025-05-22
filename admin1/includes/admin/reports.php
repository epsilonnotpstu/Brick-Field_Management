<?php
require_once '../auth_check.php';
require_once '../db_connection.php';

// Ensure only admin can access
require_role('admin');

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$field_id = isset($_GET['field_id']) ? intval($_GET['field_id']) : 0;

// Fetch brick fields for filter dropdown
$brick_fields = $conn->query("SELECT * FROM BrickField WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

// Production Report
$production_query = "
    SELECT p.*, f.field_name, b.type_name, u.username AS supervisor_name
    FROM Production p
    JOIN BrickField f ON p.field_id = f.field_id
    JOIN BrickType b ON p.brick_type_id = b.brick_type_id
    JOIN Employees e ON p.supervisor_id = e.employee_id
    JOIN Users u ON e.user_id = u.user_id
    WHERE p.production_date BETWEEN ? AND ?
";
$params = [$start_date, $end_date];
if ($field_id) {
    $production_query .= " AND p.field_id = ?";
    $params[] = $field_id;
}
$production_query .= " ORDER BY p.production_date DESC";
$stmt = $conn->prepare($production_query);
$stmt->execute($params);
$production_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inventory Report
$inventory_report = $conn->query("
    SELECT i.*, b.type_name
    FROM Inventory i
    JOIN BrickType b ON i.brick_type_id = b.brick_type_id
    ORDER BY i.current_quantity ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Raw Materials Report
$raw_materials_query = "
    SELECT r.*, f.field_name
    FROM RawMaterials r
    JOIN BrickField f ON r.field_id = f.field_id
";
if ($field_id) {
    $raw_materials_query .= " WHERE r.field_id = ?";
    $stmt = $conn->prepare($raw_materials_query);
    $stmt->execute([$field_id]);
} else {
    $stmt = $conn->prepare($raw_materials_query);
    $stmt->execute();
}
$raw_materials_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Order Report
$order_query = "
    SELECT o.*, c.full_name, u.email
    FROM Orders o
    JOIN Customers c ON o.customer_id = c.customer_id
    JOIN Users u ON c.user_id = u.user_id
    WHERE o.order_date BETWEEN ? AND ?
";
$params = [$start_date, $end_date];
if ($field_id) {
    $order_query .= " AND o.order_id IN (
        SELECT od.order_id FROM OrderDetails od
        JOIN Products p ON od.product_id = p.product_id
        JOIN Production pr ON p.brick_type_id = pr.brick_type_id
        WHERE pr.field_id = ?
    )";
    $params[] = $field_id;
}
$order_query .= " ORDER BY o.order_date DESC";
$stmt = $conn->prepare($order_query);
$stmt->execute($params);
$order_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Financial Report (Payments Summary)
$financial_query = "
    SELECT p.payment_date, p.amount, p.payment_method, o.order_id, c.full_name
    FROM Payments p
    JOIN Orders o ON p.order_id = o.order_id
    JOIN Customers c ON o.customer_id = c.customer_id
    WHERE p.payment_date BETWEEN ? AND ?
";
$params = [$start_date, $end_date];
if ($field_id) {
    $financial_query .= " AND o.order_id IN (
        SELECT od.order_id FROM OrderDetails od
        JOIN Products p ON od.product_id = p.product_id
        JOIN Production pr ON p.brick_type_id = pr.brick_type_id
        WHERE pr.field_id = ?
    )";
    $params[] = $field_id;
}
$financial_query .= " ORDER BY p.payment_date DESC";
$stmt = $conn->prepare($financial_query);
$stmt->execute($params);
$financial_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <h2>Reports</h2>

        <!-- Report Filters -->
        <section class="form-section">
            <h3>Filter Reports</h3>
            <form method="GET" class="auth-form">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                <div class="form-group">
                    <label>Brick Field</label>
                    <select name="field_id">
                        <option value="0">All Fields</option>
                        <?php foreach ($brick_fields as $field): ?>
                            <option value="<?php echo $field['field_id']; ?>" <?php echo $field_id == $field['field_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($field['field_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </form>
        </section>

        <!-- Production Report -->
        <section class="report-section">
            <h3>Production Report</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Brick Field</th>
                    <th>Brick Type</th>
                    <th>Quantity Produced</th>
                    <th>Supervisor</th>
                    <th>Quality</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($production_report as $row): ?>
                    <tr>
                        <td data-label="Date"><?php echo htmlspecialchars($row['production_date']); ?></td>
                        <td data-label="Brick Field"><?php echo htmlspecialchars($row['field_name']); ?></td>
                        <td data-label="Brick Type"><?php echo htmlspecialchars($row['type_name']); ?></td>
                        <td data-label="Quantity Produced"><?php echo number_format($row['quantity_produced']); ?></td>
                        <td data-label="Supervisor"><?php echo htmlspecialchars($row['supervisor_name']); ?></td>
                        <td data-label="Quality"><?php echo ucfirst($row['quality_rating']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button class="btn btn-primary export-csv" data-report="production">Export to CSV</button>
        </section>

        <!-- Inventory Report -->
        <section class="report-section">
            <h3>Inventory Report</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Brick Type</th>
                    <th>Current Quantity</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($inventory_report as $row): ?>
                    <tr class="<?php echo $row['current_quantity'] < 1000 ? 'low-stock' : ''; ?>">
                        <td data-label="Brick Type"><?php echo htmlspecialchars($row['type_name']); ?></td>
                        <td data-label="Current Quantity"><?php echo number_format($row['current_quantity']); ?></td>
                        <td data-label="Status"><?php echo $row['current_quantity'] < 1000 ? 'Low Stock' : 'Sufficient'; ?></td>
                        <td data-label="Last Updated"><?php echo date('M j, Y H:i', strtotime($row['last_updated'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button class="btn btn-primary export-csv" data-report="inventory">Export to CSV</button>
        </section>

        <!-- Raw Materials Report -->
        <section class="report-section">
            <h3>Raw Materials Report</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Brick Field</th>
                    <th>Material Name</th>
                    <th>Current Stock</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($raw_materials_report as $row): ?>
                    <tr class="<?php echo $row['current_stock'] < $row['reorder_level'] ? 'low-stock' : ''; ?>">
                        <td data-label="Brick Field"><?php echo htmlspecialchars($row['field_name']); ?></td>
                        <td data-label="Material Name"><?php echo ucfirst($row['material_name']); ?></td>
                        <td data-label="Current Stock"><?php echo number_format($row['current_stock'], 2); ?></td>
                        <td data-label="Unit"><?php echo ucfirst($row['unit_of_measure']); ?></td>
                        <td data-label="Reorder Level"><?php echo number_format($row['reorder_level'], 2); ?></td>
                        <td data-label="Status"><?php echo $row['current_stock'] < $row['reorder_level'] ? 'Reorder Needed' : 'Sufficient'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button class="btn btn-primary export-csv" data-report="raw_materials">Export to CSV</button>
        </section>

        <!-- Order Report -->
        <section class="report-section">
            <h3>Order Report</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Order Date</th>
                    <th>Total Amount</th>
                    <th>Payment Status</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($order_report as $row): ?>
                    <tr>
                        <td data-label="Order ID"><?php echo $row['order_id']; ?></td>
                        <td data-label="Customer"><?php echo htmlspecialchars($row['full_name']); ?> (<?php echo htmlspecialchars($row['email']); ?>)</td>
                        <td data-label="Order Date"><?php echo date('M j, Y H:i', strtotime($row['order_date'])); ?></td>
                        <td data-label="Total Amount">৳<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td data-label="Payment Status"><?php echo ucfirst($row['payment_status']); ?></td>
                        <td data-label="Status"><?php echo ucfirst($row['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button class="btn btn-primary export-csv" data-report="orders">Export to CSV</button>
        </section>

        <!-- Financial Report -->
        <section class="report-section">
            <h3>Financial Report</h3>
            <table class="table">
                <thead>
                <tr>
                    <th>Payment Date</th>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($financial_report as $row): ?>
                    <tr>
                        <td data-label="Payment Date"><?php echo date('M j, Y H:i', strtotime($row['payment_date'])); ?></td>
                        <td data-label="Order ID"><?php echo $row['order_id']; ?></td>
                        <td data-label="Customer"><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td data-label="Amount">৳<?php echo number_format($row['amount'], 2); ?></td>
                        <td data-label="Payment Method"><?php echo ucfirst($row['payment_method']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button class="btn btn-primary export-csv" data-report="financial">Export to CSV</button>
        </section>
    </main>
</div>

<script>
    // CSV Export Functionality
    document.querySelectorAll('.export-csv').forEach(button => {
        button.addEventListener('click', () => {
            const reportType = button.getAttribute('data-report');
            const table = button.previousElementSibling;
            const rows = table.querySelectorAll('tr');
            let csv = [];

            // Headers
            const headers = Array.from(rows[0].querySelectorAll('th')).map(th => th.textContent);
            csv.push(headers.join(','));

            // Data Rows
            for (let i = 1; i < rows.length; i++) {
                const cols = Array.from(rows[i].querySelectorAll('td')).map(td => `"${td.textContent.replace(/"/g, '""')}"`);
                csv.push(cols.join(','));
            }

            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${reportType}_report_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        });
    });
</script>
</body>
</html>