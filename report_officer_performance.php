<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$is_admin = ($_SESSION['role'] === 'admin');
$session_officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-01-01');
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

if ($is_admin) {
    $sql = "SELECT 
                o.id,
                o.name AS officer_name,
                COUNT(DISTINCT c.id) AS total_customers,
                COALESCE(SUM(DISTINCT c.outstanding), 0) AS total_outstanding,
                COALESCE((
                    SELECT SUM(r.amount)
                    FROM recoveries r
                    INNER JOIN customers c2 ON r.customer_id = c2.id
                    WHERE r.officer_id = o.id
                    AND c2.branch_id = ?
                    AND r.recovery_date BETWEEN ? AND ?
                ), 0) AS total_recovery,
                COALESCE((
                    SELECT COUNT(*)
                    FROM contacts ct
                    INNER JOIN customers c3 ON ct.customer_id = c3.id
                    WHERE ct.officer_id = o.id
                    AND c3.branch_id = ?
                    AND DATE(ct.created_at) BETWEEN ? AND ?
                ), 0) AS total_contacts,
                COALESCE((
                    SELECT COUNT(*)
                    FROM contacts cf
                    INNER JOIN customers c4 ON cf.customer_id = c4.id
                    WHERE cf.officer_id = o.id
                    AND c4.branch_id = ?
                    AND cf.next_followup IS NOT NULL
                    AND cf.next_followup <= CURDATE()
                ), 0) AS due_followups
            FROM officers o
            LEFT JOIN customers c ON o.id = c.assigned_officer AND c.branch_id = ?
            WHERE o.branch_id = ?
            GROUP BY o.id, o.name
            ORDER BY total_recovery DESC, total_contacts DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ississiii", $branch_id, $from, $to, $branch_id, $from, $to, $branch_id, $branch_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

} else {
    $sql = "SELECT 
                o.id,
                o.name AS officer_name,
                COUNT(DISTINCT c.id) AS total_customers,
                COALESCE(SUM(DISTINCT c.outstanding), 0) AS total_outstanding,
                COALESCE((
                    SELECT SUM(r.amount)
                    FROM recoveries r
                    INNER JOIN customers c2 ON r.customer_id = c2.id
                    WHERE r.officer_id = o.id
                    AND c2.branch_id = ?
                    AND r.recovery_date BETWEEN ? AND ?
                ), 0) AS total_recovery,
                COALESCE((
                    SELECT COUNT(*)
                    FROM contacts ct
                    INNER JOIN customers c3 ON ct.customer_id = c3.id
                    WHERE ct.officer_id = o.id
                    AND c3.branch_id = ?
                    AND DATE(ct.created_at) BETWEEN ? AND ?
                ), 0) AS total_contacts,
                COALESCE((
                    SELECT COUNT(*)
                    FROM contacts cf
                    INNER JOIN customers c4 ON cf.customer_id = c4.id
                    WHERE cf.officer_id = o.id
                    AND c4.branch_id = ?
                    AND cf.next_followup IS NOT NULL
                    AND cf.next_followup <= CURDATE()
                ), 0) AS due_followups
            FROM officers o
            LEFT JOIN customers c ON o.id = c.assigned_officer AND c.branch_id = ?
            WHERE o.id = ? AND o.branch_id = ?
            GROUP BY o.id, o.name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ississiiii", $branch_id, $from, $to, $branch_id, $from, $to, $branch_id, $branch_id, $session_officer_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>অফিসার পারফরম্যান্স রিপোর্ট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; margin-left:15px; text-decoration:none; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; overflow-x:auto; }
        .filter input { padding:10px; margin-right:10px; }
        .btn {
            padding:10px 15px;
            background:#007bff;
            color:#fff;
            border:none;
            border-radius:6px;
            cursor:pointer;
            display:inline-block;
            text-decoration:none;
        }
        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th, td { padding:12px; border:1px solid #ddd; text-align:left; }
        th { background:#f1f1f1; }
    </style>
</head>
<body>

<div class="topbar">
    অফিসার পারফরম্যান্স রিপোর্ট
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">

        <form method="GET" class="filter">
            শুরু: <input type="date" name="from" value="<?php echo $from; ?>">
            শেষ: <input type="date" name="to" value="<?php echo $to; ?>">
            <button class="btn" type="submit">ফিল্টার</button>
            <a class="btn" href="?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>">আজ</a>
            <a class="btn" href="?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>">এই মাস</a>
        </form>

        <table>
            <tr>
                <th>অফিসার</th>
                <th>মোট গ্রাহক</th>
                <th>মোট বকেয়া</th>
                <th>মোট আদায়</th>
                <th>মোট যোগাযোগ</th>
                <th>বকেয়া ফলো-আপ</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                <td><?php echo $row['total_customers']; ?></td>
                <td><?php echo number_format($row['total_outstanding'], 2); ?></td>
                <td><?php echo number_format($row['total_recovery'], 2); ?></td>
                <td><?php echo $row['total_contacts']; ?></td>
                <td><?php echo $row['due_followups']; ?></td>
            </tr>
            <?php } ?>
        </table>

    </div>
</div>

</body>
</html>