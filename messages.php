<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);

$stmt = $conn->prepare("SELECT m.*, u.name AS sender_name, ru.name AS recipient_name
                        FROM messages m
                        LEFT JOIN users u ON m.sender_user_id = u.id
                        LEFT JOIN users ru ON m.recipient_user_id = ru.id
                        WHERE m.branch_id = ?
                        ORDER BY m.id DESC");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>বার্তা তালিকা</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); overflow-x:auto; }
        .btn { display:inline-block; margin-bottom:15px; padding:10px 15px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border:1px solid #ddd; text-align:left; vertical-align:top; }
        th { background:#f1f1f1; }
    </style>
</head>
<body>
<div class="topbar">
    বার্তা তালিকা
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <a class="btn" href="send_message.php">নতুন বার্তা পাঠান</a>

        <table>
            <tr>
                <th>তারিখ</th>
                <th>প্রেরক</th>
                <th>প্রাপক</th>
                <th>Priority</th>
                <th>Subject</th>
                <th>Message</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { 
                $recipient = $row['recipient_type'];
                if ($row['recipient_type'] === 'specific') {
                    $recipient = 'Specific: ' . $row['recipient_name'];
                }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                <td><?php echo htmlspecialchars($recipient); ?></td>
                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                <td><?php echo nl2br(htmlspecialchars(substr($row['message'], 0, 120))); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>
</body>
</html>