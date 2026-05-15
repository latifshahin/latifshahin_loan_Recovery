<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-01-01');
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$customer_state = isset($_GET['customer_state']) ? trim($_GET['customer_state']) : '';
$allowed_states = ['Old CL','New CL','Standard Risky','Fully Recovered'];
if (!in_array($customer_state, $allowed_states)) { $customer_state = ''; }
$where_state = $customer_state !== '' ? " AND customers.customer_state = ?" : "";

$sql = "SELECT 
            customers.name,
            customers.account_number,
            customers.outstanding,
            customers.cl_start_balance,
            customers.customer_state,
            officers.name AS officer_name,
            COALESCE(SUM(r_period.amount),0) AS period_recovery,
            COALESCE(r_all.total_recovery,0) AS total_recovery,
            MAX(r_period.recovery_date) AS last_recovery_date
        FROM customers
        LEFT JOIN officers ON customers.assigned_officer = officers.id
        LEFT JOIN recoveries r_period
            ON customers.id = r_period.customer_id
            AND r_period.recovery_date BETWEEN ? AND ?
        LEFT JOIN (
            SELECT customer_id, COALESCE(SUM(amount),0) AS total_recovery
            FROM recoveries
            GROUP BY customer_id
        ) r_all ON r_all.customer_id = customers.id
        WHERE customers.branch_id = ?
        $where_state
        GROUP BY customers.id
        ORDER BY period_recovery DESC, total_recovery DESC";

$stmt = $conn->prepare($sql);
if ($customer_state !== '') {
    $stmt->bind_param("ssis", $from, $to, $branch_id, $customer_state);
} else {
    $stmt->bind_param("ssi", $from, $to, $branch_id);
}
$stmt->execute();
$result = $stmt->get_result();

$filename = "customer_report_" . $from . "_to_" . $to . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$output = fopen('php://output', 'w');
fputcsv($output, ['Customer','Account Number','Officer','State','CL Start Balance','Period Recovery','Total Recovery','Outstanding','Audit','Last Period Recovery Date']);
while ($row = $result->fetch_assoc()) {
    $diff = abs(floatval($row['cl_start_balance']) - (floatval($row['outstanding']) + floatval($row['total_recovery'])));
    fputcsv($output, [
        $row['name'], $row['account_number'], $row['officer_name'], $row['customer_state'],
        number_format($row['cl_start_balance'], 2, '.', ''),
        number_format($row['period_recovery'], 2, '.', ''),
        number_format($row['total_recovery'], 2, '.', ''),
        number_format($row['outstanding'], 2, '.', ''),
        ($diff < 0.01 ? 'OK' : 'Mismatch'),
        $row['last_recovery_date'] ? $row['last_recovery_date'] : ''
    ]);
}
fclose($output);
exit;
?>
