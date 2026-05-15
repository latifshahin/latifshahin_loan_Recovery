<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}
$sql = "SELECT z.*, c.name AS circle_name
        FROM zones z
        LEFT JOIN circles c ON z.circle_id = c.id
        ORDER BY z.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>জোন তালিকা</title>
    <style>
        body { font-family:Arial; background:#eef2f7; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:12px; overflow-x:auto; }
        .btn { display:inline-block; background:#007bff; color:#fff; padding:10px 14px; border-radius:6px; text-decoration:none; margin-bottom:15px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border:1px solid #ddd; text-align:left; }
        th { background:#f1f1f1; }
    </style>
</head>
<body>
<div class="topbar">
    জোন তালিকা
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <a class="btn" href="add_zone.php">নতুন জোন</a>

        <table>
            <tr>
                <th>ID</th>
                <th>জোন নাম</th>
                <th>সার্কেল</th>
                <th>Status</th>
                <th>Created</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['circle_name']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>
</body>
</html>