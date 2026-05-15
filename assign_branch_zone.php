<?php
include 'config.php';
include 'log_activity.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$message = "";

$branches = $conn->query("SELECT id, name, code, zone_id FROM branches ORDER BY name ASC");

$zones = $conn->query("SELECT z.id, z.name, c.name AS circle_name
                       FROM zones z
                       LEFT JOIN circles c ON z.circle_id = c.id
                       WHERE z.status='Active'
                       ORDER BY c.name ASC, z.name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch_id = intval($_POST['branch_id']);
    $zone_id = intval($_POST['zone_id']);

    if ($branch_id <= 0 || $zone_id <= 0) {
        $message = "Branch ও Zone নির্বাচন করুন।";
    } else {
        $stmt = $conn->prepare("UPDATE branches SET zone_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $zone_id, $branch_id);

        if ($stmt->execute()) {
            logActivity($conn, "Assign Branch Zone", "Branch ID: $branch_id, Zone ID: $zone_id");
            $message = "Branch zone mapping update হয়েছে।";
        } else {
            $message = "Mapping update করা যায়নি।";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Branch Zone Mapping</title>
    <style>
        body { font-family:Arial; background:#eef2f7; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:750px; margin:30px auto; background:#fff; padding:25px; border-radius:12px; }
        select { width:100%; padding:12px; margin:8px 0 15px; box-sizing:border-box; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; }
        .msg { color:green; font-weight:bold; margin-bottom:15px; }
    </style>
</head>
<body>
<div class="topbar">
    Branch Zone Mapping
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <h3>Branch কে Zone-এর সাথে যুক্ত করুন</h3>

    <?php if($message) { ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>

    <form method="POST">
        <label>Branch</label>
        <select name="branch_id" required>
            <option value="">Branch নির্বাচন করুন</option>
            <?php while($b = $branches->fetch_assoc()) { ?>
                <option value="<?php echo $b['id']; ?>">
                    <?php echo htmlspecialchars($b['name']) . " (" . htmlspecialchars($b['code']) . ")"; ?>
                </option>
            <?php } ?>
        </select>

        <label>Zone</label>
        <select name="zone_id" required>
            <option value="">Zone নির্বাচন করুন</option>
            <?php while($z = $zones->fetch_assoc()) { ?>
                <option value="<?php echo $z['id']; ?>">
                    <?php echo htmlspecialchars($z['circle_name']) . " > " . htmlspecialchars($z['name']); ?>
                </option>
            <?php } ?>
        </select>

        <button type="submit">Update Mapping</button>
    </form>
</div>
</body>
</html>