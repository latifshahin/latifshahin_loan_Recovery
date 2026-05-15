<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$result = $conn->query("SELECT * FROM branches ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Branches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; margin-left:15px; text-decoration:none; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; }
        .btn { background:#007bff; color:#fff; padding:10px 15px; text-decoration:none; border-radius:6px; }
        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th, td { padding:12px; border:1px solid #ddd; }
        th { background:#f1f1f1; }
    </style>
</head>
<body>

<div class="topbar">
    Branches
    <a href="dashboard.php">Dashboard</a>
</div>

<div class="container">
    <div class="box">
        <a class="btn" href="add_branch.php">+ Add Branch</a>

        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Code</th>
                <th>Status</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['code']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

</body>
</html>