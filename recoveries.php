<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = in_array($_SESSION['role'], ['admin', 'ho_admin', 'circle_admin', 'zone_admin']);

$role = $_SESSION['role'];
$circle_id = intval($_SESSION['circle_id']);
$zone_id = intval($_SESSION['zone_id']);

$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;
$branch_id = intval($_SESSION['branch_id']);

if ($role === 'ho_admin') {

    $sql = "SELECT r.*, c.name AS customer_name, c.account_number, o.name AS officer_name
            FROM recoveries r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN officers o ON r.officer_id = o.id
            ORDER BY r.id DESC";

    $result = $conn->query($sql);

} elseif ($role === 'circle_admin') {

    $sql = "SELECT r.*, c.name AS customer_name, c.account_number, o.name AS officer_name
            FROM recoveries r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN officers o ON r.officer_id = o.id
            LEFT JOIN branches b ON c.branch_id = b.id
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE z.circle_id = ?
            ORDER BY r.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $circle_id);
    $stmt->execute();
    $result = $stmt->get_result();

} elseif ($role === 'zone_admin') {

    $sql = "SELECT r.*, c.name AS customer_name, c.account_number, o.name AS officer_name
            FROM recoveries r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN officers o ON r.officer_id = o.id
            LEFT JOIN branches b ON c.branch_id = b.id
            WHERE b.zone_id = ?
            ORDER BY r.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $zone_id);
    $stmt->execute();
    $result = $stmt->get_result();

} elseif ($role === 'admin') {

    $stmt = $conn->prepare("SELECT r.*, c.name AS customer_name, c.account_number, o.name AS officer_name
                            FROM recoveries r
                            LEFT JOIN customers c ON r.customer_id = c.id
                            LEFT JOIN officers o ON r.officer_id = o.id
                            WHERE c.branch_id = ?
                            ORDER BY r.id DESC");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

} else {

    $stmt = $conn->prepare("SELECT r.*, c.name AS customer_name, c.account_number, o.name AS officer_name
                            FROM recoveries r
                            LEFT JOIN customers c ON r.customer_id = c.id
                            LEFT JOIN officers o ON r.officer_id = o.id
                            WHERE r.officer_id = ? AND c.branch_id = ?
                            ORDER BY r.id DESC");
    $stmt->bind_param("ii", $officer_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>রিকভারি তালিকা</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); overflow-x:auto; }
        .btn { display:inline-block; margin-bottom:15px; padding:10px 15px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
        table { width:100%; border-collapse:collapse; }
        table th, table td { padding:12px; border:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f1f1f1; }
    </style>
</head>
<body>
<div class="topbar">
    রিকভারি তালিকা
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <a class="btn" href="add_recovery.php">নতুন রিকভারি যোগ করুন</a>

        <table>
            <tr>
                <th>আইডি</th>
                <th>গ্রাহক</th>
                <th>অফিসার</th>
                <th>পরিমাণ</th>
                <th>তারিখ</th>
                <th>মন্তব্য</th>
                <th>তৈরির সময়</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td>
                    <?php echo htmlspecialchars($row['customer_name']); ?><br>
                    <small><?php echo htmlspecialchars($row['account_number']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                <td><?php echo number_format($row['amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($row['recovery_date']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['note'])); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>
</body>
</html>