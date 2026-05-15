<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$is_admin = ($_SESSION['role'] === 'admin');
$current_user_id = intval($_SESSION['user_id']);

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : "";

$users_stmt = $conn->prepare("SELECT id, name, username FROM users WHERE branch_id = ? ORDER BY name ASC");
$users_stmt->bind_param("i", $branch_id);
$users_stmt->execute();
$users = $users_stmt->get_result();

$actions_stmt = $conn->prepare("SELECT DISTINCT action FROM activity_logs WHERE branch_id = ? ORDER BY action ASC");
$actions_stmt->bind_param("i", $branch_id);
$actions_stmt->execute();
$actions = $actions_stmt->get_result();

$sql = "SELECT l.*, u.name 
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.branch_id = ?
        AND DATE(l.created_at) BETWEEN ? AND ?";

$params = [$branch_id, $from, $to];
$types = "iss";

if (!$is_admin) {
    $sql .= " AND l.user_id = ?";
    $params[] = $current_user_id;
    $types .= "i";
} else {
    if ($filter_user_id > 0) {
        $sql .= " AND l.user_id = ?";
        $params[] = $filter_user_id;
        $types .= "i";
    }
}

if ($filter_action !== "") {
    $sql .= " AND l.action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

$sql .= " ORDER BY l.id DESC LIMIT 500";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Activity Log</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
.topbar { background:#343a40; color:#fff; padding:15px 20px; }
.topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
.container { padding:20px; }
.box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); overflow-x:auto; }
.filter { margin-bottom:18px; display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
.filter label { font-size:13px; color:#555; display:block; margin-bottom:5px; }
.filter input, .filter select { padding:10px; border:1px solid #ccc; border-radius:6px; min-width:170px; }
.btn { padding:10px 14px; background:#007bff; color:#fff; border:0; border-radius:6px; text-decoration:none; cursor:pointer; display:inline-block; }
.btn-reset { background:#6c757d; }
table { width:100%; border-collapse:collapse; }
th, td { padding:10px; border:1px solid #ddd; text-align:left; vertical-align:top; }
th { background:#343a40; color:#fff; }
.badge { display:inline-block; padding:4px 8px; border-radius:4px; background:#eef2ff; font-size:12px; }
</style>
</head>
<body>

<div class="topbar">
    Activity Log
    <a href="logout.php">Logout</a>
    <a href="dashboard.php">Dashboard</a>
</div>

<div class="container">
<div class="box">

    <form method="GET" class="filter">
        <div>
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>

        <?php if ($is_admin) { ?>
        <div>
            <label>User</label>
            <select name="user_id">
                <option value="0">All Users</option>
                <?php while($u = $users->fetch_assoc()) { ?>
                    <option value="<?php echo $u['id']; ?>" <?php if($filter_user_id == $u['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($u['name']) . " (" . htmlspecialchars($u['username']) . ")"; ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <?php } ?>

        <div>
            <label>Action</label>
            <select name="action">
                <option value="">All Actions</option>
                <?php while($a = $actions->fetch_assoc()) { ?>
                    <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php if($filter_action === $a['action']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($a['action']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <button class="btn" type="submit">Filter</button>
        <a class="btn btn-reset" href="activity_logs.php">Reset</a>
    </form>

    <table>
        <tr>
            <th>Date</th>
            <th>User</th>
            <th>Action</th>
            <th>Description</th>
            <th>IP</th>
        </tr>

        <?php while($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><span class="badge"><?php echo htmlspecialchars($row['action']); ?></span></td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
        </tr>
        <?php } ?>
    </table>

</div>
</div>

</body>
</html>