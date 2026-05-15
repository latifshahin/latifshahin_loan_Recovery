<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-01-01');
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$sql = "SELECT officers.name AS officer_name, 
               SUM(recoveries.amount) AS total_amount
        FROM recoveries
        LEFT JOIN customers ON recoveries.customer_id = customers.id
        LEFT JOIN officers ON recoveries.officer_id = officers.id
        WHERE customers.branch_id = ?
        AND recoveries.recovery_date BETWEEN ? AND ?
        GROUP BY recoveries.officer_id
        ORDER BY total_amount DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $branch_id, $from, $to);
$stmt->execute();
$result = $stmt->get_result();

$filename = "recovery_report_" . $from . "_to_" . $to . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

fputcsv($output, ['Officer', 'Total Recovery']);

$grand_total = 0;

while ($row = $result->fetch_assoc()) {
    $amount = $row['total_amount'] ? $row['total_amount'] : 0;
    $grand_total += $amount;

    fputcsv($output, [
        $row['officer_name'],
        number_format($amount, 2, '.', '')
    ]);
}

fputcsv($output, []);
fputcsv($output, ['Grand Total', number_format($grand_total, 2, '.', '')]);

fclose($output);
exit;
?>