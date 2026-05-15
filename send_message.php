<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$sender_user_id = intval($_SESSION['user_id']);
$message_text = "";
$success = "";

$users_stmt = $conn->prepare("SELECT id, name, username, role FROM users WHERE branch_id = ? AND status='Active' ORDER BY name ASC");
$users_stmt->bind_param("i", $branch_id);
$users_stmt->execute();
$users = $users_stmt->get_result();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recipient_type = trim($_POST['recipient_type']);
    $recipient_user_id = !empty($_POST['recipient_user_id']) ? intval($_POST['recipient_user_id']) : NULL;
    $subject = trim($_POST['subject']);
    $msg = trim($_POST['message']);
    $priority = trim($_POST['priority']);

    if ($recipient_type !== 'specific') {
        $recipient_user_id = NULL;
    }

    if ($subject === '' || $msg === '') {
        $message_text = "Subject and message are required.";
    } elseif ($recipient_type === 'specific' && !$recipient_user_id) {
        $message_text = "Please select a specific user.";
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (branch_id, sender_user_id, recipient_type, recipient_user_id, subject, message, priority)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisisss", $branch_id, $sender_user_id, $recipient_type, $recipient_user_id, $subject, $msg, $priority);

        if ($stmt->execute()) {
            $success = "Message sent successfully.";
        } else {
            $message_text = "Message sending failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>বার্তা পাঠান</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:850px; margin:30px auto; background:#fff; padding:25px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); }
        input, select, textarea { width:100%; padding:12px; margin:8px 0 15px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        textarea { min-height:160px; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; cursor:pointer; }
        .msg { margin-bottom:15px; font-weight:bold; color:#d9534f; }
        .ok { color:green; }
        .hint { color:#666; font-size:13px; margin-top:-8px; margin-bottom:12px; }
    </style>
    <script>
        function toggleSpecificUser() {
            var type = document.getElementById('recipient_type').value;
            var box = document.getElementById('specific_user_box');
            box.style.display = (type === 'specific') ? 'block' : 'none';
        }
    </script>
</head>
<body>

<div class="topbar">
    বার্তা পাঠান
    <a href="logout.php">লগআউট</a>
    <a href="messages.php">বার্তা তালিকা</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <?php if($message_text != "") { ?>
        <div class="msg"><?php echo htmlspecialchars($message_text); ?></div>
    <?php } ?>

    <?php if($success != "") { ?>
        <div class="msg ok"><?php echo htmlspecialchars($success); ?></div>
    <?php } ?>

    <form method="POST">
        <label>প্রাপক নির্বাচন</label>
        <select name="recipient_type" id="recipient_type" onchange="toggleSpecificUser()" required>
            <option value="all">সবাই</option>
            <option value="admin">শুধু Admin</option>
            <option value="officer">শুধু Officer/User</option>
            <option value="specific">নির্দিষ্ট User</option>
        </select>

        <div id="specific_user_box" style="display:none;">
            <label>নির্দিষ্ট User</label>
            <select name="recipient_user_id">
                <option value="">User নির্বাচন করুন</option>
                <?php while($u = $users->fetch_assoc()) { ?>
                    <option value="<?php echo $u['id']; ?>">
                        <?php echo htmlspecialchars($u['name']) . " (" . htmlspecialchars($u['username']) . " - " . htmlspecialchars($u['role']) . ")"; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <label>Priority</label>
        <select name="priority">
            <option value="Normal">Normal</option>
            <option value="Important">Important</option>
            <option value="Urgent">Urgent</option>
        </select>

        <label>Subject</label>
        <input type="text" name="subject" required>

        <label>Message</label>
        <textarea name="message" required></textarea>

        <button type="submit">বার্তা পাঠান</button>
    </form>
</div>

<script>
    toggleSpecificUser();
</script>

</body>
</html>