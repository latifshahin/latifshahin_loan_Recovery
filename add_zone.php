<?php
include 'config.php';
include 'log_activity.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$message = "";

$circles = $conn->query("SELECT id, name FROM circles WHERE status='Active' ORDER BY name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $circle_id = intval($_POST['circle_id']);
    $name = trim($_POST['name']);
    $status = trim($_POST['status']);

    if ($circle_id <= 0 || $name == "") {
        $message = "সার্কেল ও জোন নাম প্রয়োজন।";
    } else {
        $stmt = $conn->prepare("INSERT INTO zones (circle_id, name, status) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $circle_id, $name, $status);

        if ($stmt->execute()) {
            logActivity($conn, "Add Zone", "Zone: $name, Circle ID: $circle_id");
            header("Location: zones.php");
            exit;
        } else {
            $message = "জোন সংরক্ষণ করা যায়নি।";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>নতুন জোন</title>
    <style>
        body { font-family:Arial; background:#eef2f7; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:650px; margin:30px auto; background:#fff; padding:25px; border-radius:12px; }
        input, select { width:100%; padding:12px; margin:8px 0 15px; box-sizing:border-box; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; }
        .msg { color:red; font-weight:bold; }
    </style>
</head>
<body>
<div class="topbar">
    নতুন জোন
    <a href="zones.php">জোন তালিকা</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <h3>নতুন জোন তৈরি</h3>

    <?php if($message) { ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>

    <form method="POST">
        <label>সার্কেল</label>
        <select name="circle_id" required>
            <option value="">নির্বাচন করুন</option>
            <?php while($c = $circles->fetch_assoc()) { ?>
                <option value="<?php echo $c['id']; ?>">
                    <?php echo htmlspecialchars($c['name']); ?>
                </option>
            <?php } ?>
        </select>

        <label>জোন নাম</label>
        <input type="text" name="name" required>

        <label>Status</label>
        <select name="status">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>

        <button type="submit">সংরক্ষণ করুন</button>
    </form>
</div>
</body>
</html>