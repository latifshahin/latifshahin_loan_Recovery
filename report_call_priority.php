<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$is_admin = ($_SESSION['role'] === 'admin');
$officer_id = $_SESSION['officer_id'] ?? 0;

$sql = "SELECT 
            c.id,
            c.name,
            c.account_number,
            c.phone,
            c.outstanding,
            c.status,
            o.name AS officer_name,
            (SELECT MAX(ct.created_at) FROM contacts ct WHERE ct.customer_id = c.id) AS last_contact,
            (SELECT SUM(r.amount) FROM recoveries r WHERE r.customer_id = c.id) AS total_recovery,
            EXISTS (
                SELECT 1 FROM contacts ct2
                WHERE ct2.customer_id = c.id
                AND ct2.action_result = 'Promise to Pay'
                AND NOT EXISTS (
                    SELECT 1 FROM recoveries r2
                    WHERE r2.customer_id = c.id
                    AND r2.recovery_date >= DATE(ct2.created_at)
                )
            ) AS promise_pending
        FROM customers c
        LEFT JOIN officers o ON c.assigned_officer = o.id
        WHERE c.branch_id = ?";

if (!$is_admin) {
    $sql .= " AND c.assigned_officer = ?";
}

$stmt = $conn->prepare($sql);

if ($is_admin) {
    $stmt->bind_param("i", $branch_id);
} else {
    $stmt->bind_param("ii", $branch_id, $officer_id);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $score = 0;

    if (!$row['last_contact'] || strtotime($row['last_contact']) < strtotime('-7 days')) {
        $score += 2;
        $row['no_contact'] = true;
    } else {
        $row['no_contact'] = false;
    }

    if (!$row['total_recovery']) {
        $score += 2;
        $row['no_recovery'] = true;
    } else {
        $row['no_recovery'] = false;
    }

    if ($row['promise_pending']) {
        $score += 2;
    }

    if ($row['outstanding'] > 100000) {
        $score += 2;
        $row['high_risk'] = true;
    } else {
        $row['high_risk'] = false;
    }

    $row['score'] = $score;

    if ($score > 0) {
        $data[] = $row;
    }
}

usort($data, function($a, $b) {
    return $b['score'] <=> $a['score'];
});
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>কল অগ্রাধিকার রিপোর্ট</title>
<style>
body { font-family: Arial; background:#f4f6f9; margin:0; }
.topbar { background:#343a40; color:#fff; padding:15px 20px; }
.topbar a { color:#fff; float:right; margin-left:15px; text-decoration:none; }
.container { padding:20px; }
.box { background:#fff; padding:20px; border-radius:10px; }
table { width:100%; border-collapse:collapse; }
th, td { padding:12px; border:1px solid #ddd; }
th { background:#f1f1f1; }
.high { background:#ffdddd; }
.medium { background:#fff3cd; }
.low { background:#e7f5ff; }
.action-link {
    display:inline-block;
    padding:6px 8px;
    background:#007bff;
    color:#fff;
    text-decoration:none;
    border-radius:5px;
    margin:2px;
    font-size:13px;
}
</style>
</head>
<body>

<div class="topbar">
    কল অগ্রাধিকার রিপোর্ট
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
<div class="box">

<table>
<tr>
    <th>অগ্রাধিকার</th>
    <th>গ্রাহক</th>
    <th>হিসাব নম্বর</th>
    <th>মোবাইল</th>
    <th>অফিসার</th>
    <th>বকেয়া</th>
    <th>স্ট্যাটাস</th>
    <th>ফ্ল্যাগ</th>
    <th>যোগাযোগ</th>
</tr>

<?php foreach ($data as $row) { 
    $class = ($row['score'] >= 6) ? 'high' : (($row['score'] >= 4) ? 'medium' : 'low');

    $flags = [];
    if ($row['no_contact']) $flags[] = "যোগাযোগ নেই";
    if ($row['no_recovery']) $flags[] = "আদায় নেই";
    if ($row['promise_pending']) $flags[] = "অপেক্ষমাণ প্রতিশ্রুতি";
    if ($row['high_risk']) $flags[] = "উচ্চ বকেয়া";
?>
<tr class="<?php echo $class; ?>">
    <td><?php echo $row['score']; ?></td>
    <td><a href="customer_view.php?id=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
    <td><?php echo htmlspecialchars($row['account_number']); ?></td>
    <td><?php echo htmlspecialchars($row['phone']); ?></td>
    <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
    <td><?php echo number_format($row['outstanding'], 2); ?></td>
    <td><?php echo htmlspecialchars($row['status']); ?></td>
    <td><?php echo implode(", ", $flags); ?></td>
    <?php
    $clean_phone = preg_replace('/\D+/', '', $row['phone']);
    $wa_phone = $clean_phone;
    
    if (substr($wa_phone, 0, 1) == '0') {
        $wa_phone = '88' . $wa_phone;
    }
    
    $msg_text = "আসসালামু আলাইকুম, আপনার ঋণ হিসাব বিষয়ে শাখায় যোগাযোগ করার জন্য অনুরোধ করা হলো।";
    ?>
    
    <td>
        <a class="action-link" href="tel:<?php echo htmlspecialchars($clean_phone); ?>">Call</a>
        <a class="action-link" href="sms:<?php echo htmlspecialchars($clean_phone); ?>?body=<?php echo urlencode($msg_text); ?>">SMS</a>
        <a class="action-link" target="_blank" href="https://wa.me/<?php echo htmlspecialchars($wa_phone); ?>?text=<?php echo urlencode($msg_text); ?>">WhatsApp</a>
    </td>
</tr>
<?php } ?>

</table>

</div>
</div>

</body>
</html>