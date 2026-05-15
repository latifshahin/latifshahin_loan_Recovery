<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = intval($_SESSION['user_id']);
$role      = $_SESSION['role'];
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;

$message_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($message_id <= 0) {
    die("Invalid message ID.");
}

/* =========================
   Message access scope
========================= */
$where = "m.id = $message_id AND (
    m.recipient_type = 'all'
    OR m.recipient_type = '$role'
    OR (m.recipient_type = 'specific' AND m.recipient_user_id = $user_id)
)";

if ($branch_id > 0) {
    $where .= " AND (m.branch_id = $branch_id OR m.branch_id = 0)";
}

/* =========================
   Fetch message
========================= */
$sql = "
SELECT 
    m.*,
    mr.id AS read_id,
    mr.read_at
FROM messages m
LEFT JOIN message_reads mr 
    ON m.id = mr.message_id 
    AND mr.user_id = $user_id
WHERE $where
LIMIT 1
";

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

if ($result->num_rows == 0) {
    die("Message not found or you are not allowed to view this message.");
}

$message = $result->fetch_assoc();

/* =========================
   Mark as read
========================= */
if (empty($message['read_id'])) {
    $insert_read = $conn->query("
        INSERT INTO message_reads (message_id, user_id, read_at)
        VALUES ($message_id, $user_id, NOW())
    ");

    if ($insert_read) {
        $message['read_at'] = date('Y-m-d H:i:s');
        $message['read_id'] = $conn->insert_id;
    }
}

/* =========================
   Message body fallback
========================= */
$message_body = "";

if (isset($message['message'])) {
    $message_body = $message['message'];
} elseif (isset($message['body'])) {
    $message_body = $message['body'];
} elseif (isset($message['content'])) {
    $message_body = $message['content'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>বার্তা দেখুন</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* { box-sizing:border-box; }

body {
    font-family: Arial, sans-serif;
    background:#eef2f7;
    margin:0;
    color:#1f2937;
}

.topbar {
    background:linear-gradient(135deg,#1f2937,#111827);
    color:#fff;
    padding:18px 22px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
}

.topbar-title {
    font-size:20px;
    font-weight:bold;
}

.topbar a {
    color:#fff;
    text-decoration:none;
    margin-left:8px;
    padding:8px 12px;
    background:rgba(255,255,255,0.12);
    border-radius:8px;
    display:inline-block;
    font-size:14px;
}

.container {
    max-width:900px;
    margin:auto;
    padding:22px;
}

.box {
    background:#fff;
    padding:24px;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
}

.title {
    font-size:26px;
    font-weight:bold;
    margin-bottom:10px;
    color:#111827;
}

.meta {
    color:#64748b;
    font-size:14px;
    margin-bottom:16px;
    line-height:1.7;
}

.badge {
    display:inline-block;
    padding:5px 10px;
    background:#eef2ff;
    color:#3730a3;
    border-radius:999px;
    font-size:12px;
    font-weight:bold;
}

.badge-read {
    background:#dcfce7;
    color:#166534;
}

.badge-priority {
    background:#fee2e2;
    color:#991b1b;
}

.message-body {
    background:#f8fafc;
    padding:18px;
    border-radius:14px;
    line-height:1.7;
    white-space:pre-wrap;
    color:#334155;
    border:1px solid #e5e7eb;
}

.actions {
    margin-top:18px;
}

.btn {
    display:inline-block;
    padding:10px 14px;
    background:#2563eb;
    color:#fff;
    text-decoration:none;
    border-radius:10px;
    margin-right:6px;
}

.btn-gray {
    background:#64748b;
}
</style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">বার্তা দেখুন</div>
        <div style="font-size:13px;color:#cbd5e1;margin-top:4px;">
            Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div>
        <a href="my_messages.php">আমার বার্তা</a>
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">
    <div class="box">

        <div class="title">
            <?php echo htmlspecialchars($message['subject']); ?>
        </div>

        <div class="meta">
            <span class="badge badge-priority">
                Priority: <?php echo htmlspecialchars($message['priority']); ?>
            </span>

            <span class="badge badge-read">
                Read
            </span>

            <br>

            Sent:
            <?php echo htmlspecialchars($message['created_at']); ?>

            <?php if (!empty($message['read_at'])) { ?>
                <br>
                Read At:
                <?php echo htmlspecialchars($message['read_at']); ?>
            <?php } ?>

            <?php if (isset($message['recipient_type'])) { ?>
                <br>
                Recipient Type:
                <?php echo htmlspecialchars($message['recipient_type']); ?>
            <?php } ?>
        </div>

        <div class="message-body">
            <?php
            if ($message_body != "") {
                echo htmlspecialchars($message_body);
            } else {
                echo "No message body found.";
            }
            ?>
        </div>

        <div class="actions">
            <a class="btn" href="my_messages.php">Back to Messages</a>
            <a class="btn btn-gray" href="dashboard.php">Dashboard</a>
        </div>

    </div>
</div>

</body>
</html>