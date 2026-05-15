<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$branch_id = intval($_SESSION['branch_id']);
$circle_id = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$user_id = intval($_SESSION['user_id']);

if ($role === 'ho_admin') {
    $stmt = $conn->prepare("
        SELECT l.*, c.name AS customer_name, c.account_number, u.name AS user_name
        FROM message_send_logs l
        LEFT JOIN customers c ON l.customer_id = c.id
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.id DESC
    ");
} elseif ($role === 'circle_admin') {
    $stmt = $conn->prepare("
        SELECT l.*, c.name AS customer_name, c.account_number, u.name AS user_name
        FROM message_send_logs l
        LEFT JOIN customers c ON l.customer_id = c.id
        LEFT JOIN branches b ON l.branch_id = b.id
        LEFT JOIN zones z ON b.zone_id = z.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE z.circle_id = ?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param("i", $circle_id);
} elseif ($role === 'zone_admin') {
    $stmt = $conn->prepare("
        SELECT l.*, c.name AS customer_name, c.account_number, u.name AS user_name
        FROM message_send_logs l
        LEFT JOIN customers c ON l.customer_id = c.id
        LEFT JOIN branches b ON l.branch_id = b.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE b.zone_id = ?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param("i", $zone_id);
} elseif ($role === 'admin') {
    $stmt = $conn->prepare("
        SELECT l.*, c.name AS customer_name, c.account_number, u.name AS user_name
        FROM message_send_logs l
        LEFT JOIN customers c ON l.customer_id = c.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.branch_id = ?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param("i", $branch_id);
} else {
    $stmt = $conn->prepare("
        SELECT l.*, c.name AS customer_name, c.account_number, u.name AS user_name
        FROM message_send_logs l
        LEFT JOIN customers c ON l.customer_id = c.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.branch_id = ?
        AND l.user_id = ?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param("ii", $branch_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>মেসেজ সেন্ড লগ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border:1px solid #ddd; text-align:left; vertical-align:top; }
        th { background:#f1f1f1; }
        .badge { display:inline-block; padding:4px 8px; border-radius:4px; background:#eef2ff; }
    </style>
</head>
<body>

<div class="topbar">
    মেসেজ সেন্ড লগ
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <table>
            <tr>
                <th>তারিখ</th>
                <th>গ্রাহক</th>
                <th>হিসাব নম্বর</th>
                <th>ইউজার</th>
                <th>চ্যানেল</th>
                <th>মোবাইল</th>
                <th>মেসেজ</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <a href="customer_view.php?id=<?php echo intval($row['customer_id']); ?>">
                        <?php echo htmlspecialchars($row['customer_name']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars($row['channel']); ?></span></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['message_text'])); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

</body>
</html>