<?php
include 'config.php';
include 'log_activity.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $status = trim($_POST['status']);

    if ($name == "") {
        $message = "সার্কেল নাম প্রয়োজন।";
    } else {
        $stmt = $conn->prepare("INSERT INTO circles (name, status) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $status);

        if ($stmt->execute()) {
            logActivity($conn, "Add Circle", "Circle: $name");
            header("Location: circles.php");
            exit;
        } else {
            $message = "সার্কেল সংরক্ষণ করা যায়নি।";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>নতুন সার্কেল</title>
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
    নতুন সার্কেল
    <a href="circles.php">সার্কেল তালিকা</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <h3>নতুন সার্কেল তৈরি</h3>

    <?php if($message) { ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>

    <form method="POST">
        <label>সার্কেল নাম</label>
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