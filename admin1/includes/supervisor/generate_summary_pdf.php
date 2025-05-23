<?php
// Ensure session is started
if (!session_id()) {
    session_start();
}
require_once '../auth_check.php';

if ($_SESSION['user_type'] != 'supervisor') {
    header("Location: ../unauthorized.php");
    exit();
}

require_once '../db_connection.php';
$stmt = $conn->prepare("
    SELECT e.field_id, f.field_name 
    FROM Employees e
    JOIN BrickField f ON e.field_id = f.field_id
    WHERE e.user_id = ? AND e.role = 'supervisor'
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch data for PDF
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');

// Expense Summary
$stmt = $conn->prepare("
    SELECT category, SUM(amount) as total
    FROM Expenses
    WHERE field_id = ? AND expense_date BETWEEN ? AND ?
    GROUP BY category
");
$stmt->execute([$supervisor['field_id'], $start_date, $end_date]);
$expense_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Material Cost Summary
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

// Production Summary
$stmt = $conn->prepare("
    SELECT mold_type, SUM(quantity) as total_produced
    FROM RawBrickProduction
    WHERE field_id = ? AND production_date BETWEEN ? AND ?
    GROUP BY mold_type
");
$stmt->execute([$supervisor['field_id'], $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$production_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Attendance Summary
$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count
    FROM Attendance a
    JOIN Employees e ON a.employee_id = e.employee_id
    WHERE e.field_id = ? AND a.date BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$supervisor['field_id'], $start_date, $end_date]);
$attendance_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Firing Summary
$stmt = $conn->prepare("
    SELECT AVG(success_rate) as avg_success_rate
    FROM Firing
    WHERE supervisor_id IN (SELECT employee_id FROM Employees WHERE field_id = ?) 
    AND end_date IS NOT NULL AND start_date BETWEEN ? AND ?
");
$stmt->execute([$supervisor['field_id'], $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$firing_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Material Stock Status
$stmt = $conn->prepare("
    SELECT material_name, current_stock, reorder_level
    FROM RawMaterials
    WHERE field_id = ?
");
$stmt->execute([$supervisor['field_id']]);
$stock_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate LaTeX content
header('Content-Type: application/x-tex');
header('Content-Disposition: attachment; filename="summary_report.tex"');

$latex_content = "
\\documentclass[a4paper,12pt]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[T1]{fontenc}
\\usepackage{geometry}
\\geometry{margin=1in}
\\usepackage{booktabs}
\\usepackage{enumitem}
\\usepackage{parskip}
\\usepackage{times}

\\begin{document}

\\begin{center}
    \\textbf{\\large Bricks Management System Summary Report} \\\\
    \\vspace{0.2cm}
    Field: " . addslashes($supervisor['field_name']) . " \\\\
    Period: " . $start_date . " to " . $end_date . " \\\\
    Generated on: " . date('Y-m-d') . "
\\end{center}

\\section*{1. Expense Summary}
The following table summarizes expenses by category.

\\begin{tabular}{l r}
    \\toprule
    \\textbf{Category} & \\textbf{Total Amount (BDT)} \\\\
    \\midrule
";
foreach ($expense_summary as $summary) {
    $latex_content .= "    " . ucfirst(str_replace('_', ' ', $summary['category'])) . " & " . number_format($summary['total'], 2) . " \\\\ \n";
}
$latex_content .= "
    \\bottomrule
\\end{tabular}

\\section*{2. Material Receipts}
The total cost of materials received is summarized below.

\\begin{tabular}{l r}
    \\toprule
    \\textbf{Material} & \\textbf{Total Cost (BDT)} \\\\
    \\midrule
";
foreach ($material_summary as $summary) {
    $latex_content .= "    " . ucfirst($summary['material_name']) . " & " . number_format($summary['total_cost'], 2) . " \\\\ \n";
}
$latex_content .= "
    \\bottomrule
\\end{tabular}

\\section*{3. Production Summary}
Total bricks produced, categorized by mold type.

\\begin{tabular}{l r}
    \\toprule
    \\textbf{Mold Type} & \\textbf{Total Produced} \\\\
    \\midrule
";
foreach ($production_summary as $summary) {
    $latex_content .= "    " . ucfirst($summary['mold_type']) . " & " . number_format($summary['total_produced']) . " \\\\ \n";
}
$latex_content .= "
    \\bottomrule
\\end{tabular}

\\section*{4. Attendance Summary}
Worker attendance statistics for the period.

\\begin{tabular}{l r}
    \\toprule
    \\textbf{Status} & \\textbf{Count} \\\\
    \\midrule
";
foreach ($attendance_summary as $summary) {
    $latex_content .= "    " . ucfirst($summary['status']) . " & " . $summary['count'] . " \\\\ \n";
}
$latex_content .= "
    \\bottomrule
\\end{tabular}

\\section*{5. Firing Performance}
Average firing success rate for completed sessions.

\\begin{itemize}
    \\item Average Success Rate: " . ($firing_summary['avg_success_rate'] ? number_format($firing_summary['avg_success_rate'], 2) . "\\%" : "N/A") . "
\\end{itemize}

\\section*{6. Material Stock Status}
Current stock levels compared to reorder levels.

\\begin{tabular}{l r r}
    \\toprule
    \\textbf{Material} & \\textbf{Current Stock} & \\textbf{Reorder Level} \\\\
    \\midrule
";
foreach ($stock_summary as $summary) {
    $latex_content .= "    " . ucfirst($summary['material_name']) . " & " . number_format($summary['current_stock'], 2) . " & " . number_format($summary['reorder_level'], 2) . " \\\\ \n";
}
$latex_content .= "
    \\bottomrule
\\end{tabular}

\\end{document}
";

echo $latex_content;
?>