<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = intval($_SESSION['user_id']);
$role      = $_SESSION['role'];
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

/* Message scope: same logic as dashboard */
$where = "(
    m.recipient_type = 'all'
    OR m.recipient_type = '$role'
    OR (m.recipient_type = 'specific' AND m.recipient_user_id = $user_id)
)";

if ($branch_id > 0) {
    $where .= " AND (m.branch_id = $branch_id OR m.branch_id = 0)";
}

/* Read/unread filter */
if ($filter == 'unread') {
    $where .= " AND mr.id IS NULL";
} elseif ($filter == 'read') {
    $where .= " AND mr.id IS NOT NULL";
}

/* Fetch messages */
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
ORDER BY m.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

/* Count unread */
$count_sql = "
SELECT COUNT(*) AS total
FROM messages m
LEFT JOIN message_reads mr 
    ON m.id = mr.message_id 
    AND mr.user_id = $user_id
WHERE (
    m.recipient_type = 'all'
    OR m.recipient_type = '$role'
    OR (m.recipient_type = 'specific' AND m.recipient_user_id = $user_id)
)
";

if ($branch_id > 0) {
    $count_sql .= " AND (m.branch_id = $branch_id OR m.branch_id = 0)";
}

$count_sql .= " AND mr.id IS NULL";

$count_result = $conn->query($count_sql);
$unread_count = $count_result ? intval($count_result->fetch_assoc()['total']) : 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>আমার বার্তা</title>
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
    max-width:1100px;
    margin:auto;
    padding:22px;
}

.hero {
    background:#fff;
    padding:22px;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
    margin-bottom:18px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
    flex-wrap:wrap;
}

.hero h2 {
    margin:0 0 8px;
    font-size:26px;
}

.hero p {
    margin:0;
    color:#64748b;
}

.badge {
    display:inline-block;
    padding:6px 10px;
    background:#fee2e2;
    color:#991b1b;
    border-radius:999px;
    font-weight:bold;
    font-size:13px;
}

.filter-box {
    background:#fff;
    padding:16px;
    border-radius:16px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
    margin-bottom:18px;
}

.btn {
    display:inline-block;
    padding:8px 12px;
    background:#2563eb;
    color:#fff;
    border-radius:8px;
    text-decoration:none;
    font-size:14px;
    margin:2px;
}

.btn-gray { background:#64748b; }
.btn-orange { background:#f97316; }

.message-card {
    background:#fff;
    border-radius:16px;
    padding:18px;
    margin-bottom:14px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
    border-left:5px solid #64748b;
}

.message-card.unread {
    border-left-color:#dc2626;
    background:#fffafa;
}

.message-head {
    display:flex;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:8px;
}

.message-title {
    font-size:18px;
    font-weight:bold;
    color:#111827;
}

.message-meta {
    color:#64748b;
    font-size:13px;
}

.priority {
    display:inline-block;
    padding:4px 8px;
    background:#eef2ff;
    color:#3730a3;
    border-radius:999px;
    font-size:12px;
    font-weight:bold;
}

.message-body {
    margin-top:12px;
    line-height:1.6;
    color:#334155;
    white-space:pre-wrap;
}

.empty {
    background:#fff;
    padding:18px;
    border-radius:14px;
    color:#64748b;
}
</style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">আমার বার্তা</div>
        <div style="font-size:13px;color:#cbd5e1;margin-top:4px;">
            Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div>
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <div class="hero">
        <div>
            <h2>My Messages</h2>
            <p>Admin/HO/Circle/Zone থেকে পাঠানো notice/message এখানে দেখাবে।</p>
        </div>

        <div>
            <span class="badge">Unread: <?php echo $unread_count; ?></span>
        </div>
    </div>

    <div class="filter-box">
        <a class="btn <?php echo ($filter == 'all') ? 'btn-orange' : ''; ?>" href="my_messages.php">All</a>
        <a class="btn <?php echo ($filter == 'unread') ? 'btn-orange' : ''; ?>" href="my_messages.php?filter=unread">Unread</a>
        <a class="btn <?php echo ($filter == 'read') ? 'btn-orange' : ''; ?>" href="my_messages.php?filter=read">Read</a>
    </div>

    <?php if ($result->num_rows > 0) { ?>

        <?php while ($row = $result->fetch_assoc()) { 
            $is_unread = empty($row['read_id']);
        ?>
            <div class="message-card <?php echo $is_unread ? 'unread' : ''; ?>">
                <div class="message-head">
                    <div>
                        <div class="message-title">
                            <?php echo htmlspecialchars($row['subject']); ?>
                        </div>

                        <div class="message-meta">
                            Sent: <?php echo htmlspecialchars($row['created_at']); ?>
                            <?php if (!$is_unread && !empty($row['read_at'])) { ?>
                                | Read: <?php echo htmlspecialchars($row['read_at']); ?>
                            <?php } ?>
                        </div>
                    </div>

                    <div>
                        <span class="priority">
                            <?php echo htmlspecialchars($row['priority']); ?>
                        </span>

                        <?php if ($is_unread) { ?>
                            <a class="btn" href="message_view.php?id=<?php echo intval($row['id']); ?>">Open / Mark Read</a>
                        <?php } else { ?>
                            <a class="btn btn-gray" href="message_view.php?id=<?php echo intval($row['id']); ?>">Open</a>
                        <?php } ?>
                    </div>
                </div>

                <div class="message-body">
                    <?php
                    if (isset($row['message'])) {
                        echo htmlspecialchars($row['message']);
                    } elseif (isset($row['body'])) {
                        echo htmlspecialchars($row['body']);
                    } else {
                        echo '<span style="color:#94a3b8;">No message body found.</span>';
                    }
                    ?>
                </div>
            </div>
        <?php } ?>

    <?php } else { ?>
        <div class="empty">কোনো বার্তা পাওয়া যায়নি।</div>
    <?php } ?>

</div>

</body>
</html>