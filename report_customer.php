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
            customers.id,
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>গ্রাহক রিপোর্ট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; margin-left:15px; text-decoration:none; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; overflow-x:auto; }
        .filter input, .filter select { padding:10px; margin-right:10px; }
        .btn { padding:10px 15px; background:#007bff; color:#fff; border:none; border-radius:6px; cursor:pointer; text-decoration:none; display:inline-block; }
        table { width:100%; border-collapse:collapse; margin-top:15px; min-width:1150px; }
        th, td { padding:12px; border:1px solid #ddd; }
        th { background:#f1f1f1; }
        .zero { color:#999; }
        .ok { color:#166534; font-weight:bold; }
        .bad { color:#991b1b; font-weight:bold; }
    </style>
</head>
<body>
<div class="topbar">গ্রাহক রিপোর্ট <a href="dashboard.php">ড্যাশবোর্ড</a></div>
<div class="container"><div class="box">
    <form method="GET" class="filter">
        শুরু: <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        শেষ: <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        State:
        <select name="customer_state">
            <option value="">All</option>
            <?php foreach($allowed_states as $s) { $sel = ($customer_state === $s) ? 'selected' : ''; echo '<option value="'.htmlspecialchars($s).'" '.$sel.'>'.htmlspecialchars($s).'</option>'; } ?>
        </select>
        <button class="btn" type="submit">ফিল্টার</button>
        <a class="btn" href="?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>">আজ</a>
        <a class="btn" href="?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>">এই মাস</a>
        <a class="btn" href="export_customer.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&customer_state=<?php echo urlencode($customer_state); ?>">এক্সেল এক্সপোর্ট</a>
    </form>

    <table>
        <tr>
            <th>গ্রাহক</th><th>হিসাব নম্বর</th><th>অফিসার</th><th>State</th><th>CL Start Balance</th><th>তারিখ অনুযায়ী আদায়</th><th>মোট আদায়</th><th>বকেয়া</th><th>Audit</th><th>সর্বশেষ আদায়</th>
        </tr>
        <?php while($row = $result->fetch_assoc()) { $diff = abs(floatval($row['cl_start_balance']) - (floatval($row['outstanding']) + floatval($row['total_recovery']))); ?>
        <tr>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo htmlspecialchars($row['account_number']); ?></td>
            <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
            <td><?php echo htmlspecialchars($row['customer_state']); ?></td>
            <td><?php echo number_format($row['cl_start_balance'], 2); ?></td>
            <td><?php echo number_format($row['period_recovery'], 2); ?></td>
            <td><?php echo number_format($row['total_recovery'], 2); ?></td>
            <td><?php echo number_format($row['outstanding'], 2); ?></td>
            <td><?php echo ($diff < 0.01) ? '<span class="ok">OK</span>' : '<span class="bad">Mismatch</span>'; ?></td>
            <td><?php echo $row['last_recovery_date'] ? htmlspecialchars($row['last_recovery_date']) : '<span class="zero">তথ্য নেই</span>'; ?></td>
        </tr>
        <?php } ?>
    </table>
</div></div>
</body>
</html>
