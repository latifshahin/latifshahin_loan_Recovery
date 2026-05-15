<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ho_admin') {
    header("Location: dashboard.php");
    exit;
}

$where_parts = array("u.role IN ('ho_admin','circle_admin','zone_admin','admin')");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where_parts[] = "(u.name LIKE '%$safe_search%' OR u.username LIKE '%$safe_search%')";
}

if ($role_filter !== '' && in_array($role_filter, array('ho_admin','circle_admin','zone_admin','admin'))) {
    $safe_role = $conn->real_escape_string($role_filter);
    $where_parts[] = "u.role = '$safe_role'";
}

if ($status_filter !== '' && in_array($status_filter, array('Active','Inactive'))) {
    $safe_status = $conn->real_escape_string($status_filter);
    $where_parts[] = "u.status = '$safe_status'";
}

$where = implode(' AND ', $where_parts);

$sql = "
SELECT
    u.*,
    b.name AS branch_name,
    z.name AS zone_name,
    c.name AS circle_name
FROM users u
LEFT JOIN branches b ON u.branch_id = b.id
LEFT JOIN zones z ON u.zone_id = z.id
LEFT JOIN circles c ON u.circle_id = c.id
WHERE $where
ORDER BY u.id DESC
";

$result = $conn->query($sql);
$count_res = $conn->query("SELECT COUNT(*) AS total FROM users u WHERE $where");
$total_count = $count_res ? intval($count_res->fetch_assoc()['total']) : 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--bg:#f4f7fb;--surface:#fff;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--blue:#2563eb;--red:#dc2626;--green:#16a34a;--shadow:0 12px 28px rgba(15,23,42,.08)}*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:var(--bg);color:var(--ink)}.topbar{background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom:1px solid var(--line);padding:14px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;position:sticky;top:0;z-index:20}.brand{font-size:19px;font-weight:800}.top-sub{font-size:13px;color:var(--muted);margin-top:3px}.topbar a{display:inline-block;text-decoration:none;background:#fff;border:1px solid var(--line);color:#334155;padding:9px 13px;border-radius:999px;font-size:14px}.container{max-width:1250px;margin:auto;padding:22px}.hero{background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);color:#fff;border-radius:26px;padding:24px;margin-bottom:18px;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.hero h2{margin:0 0 6px;font-size:28px}.hero p{margin:0;color:#dbeafe}.btn{display:inline-block;text-decoration:none;border:0;border-radius:999px;padding:10px 14px;font-weight:800;font-size:14px;cursor:pointer}.btn-blue{background:#2563eb;color:#fff}.btn-gray{background:#e2e8f0;color:#334155}.btn-red{background:#fee2e2;color:#b91c1c}.box{background:var(--surface);border:1px solid var(--line);border-radius:24px;padding:18px;box-shadow:var(--shadow);margin-bottom:18px}.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end}label{display:block;font-size:13px;font-weight:800;color:#334155;margin-bottom:5px}input,select{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:12px;background:#fff}.table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:18px}table{width:100%;border-collapse:collapse;min-width:920px;background:#fff}th{background:#f8fafc;color:#475569;font-size:13px;text-align:left;padding:12px;border-bottom:1px solid var(--line)}td{padding:12px;border-bottom:1px solid #eef2f7;font-size:14px}tr:hover td{background:#f8fbff}.pill{display:inline-block;border-radius:999px;padding:5px 9px;font-weight:800;font-size:12px}.pill-blue{background:#dbeafe;color:#1d4ed8}.pill-green{background:#dcfce7;color:#15803d}.pill-red{background:#fee2e2;color:#b91c1c}.muted{color:var(--muted)}.actions{display:flex;gap:6px;flex-wrap:wrap}@media(max-width:800px){.container{padding:12px}.hero{border-radius:20px;padding:18px}.hero h2{font-size:23px}.filters{grid-template-columns:1fr}.box{border-radius:18px;padding:14px}.topbar{position:relative;padding:12px}.btn{padding:8px 11px;font-size:12px}}
</style>
</head>
<body>
<div class="topbar">
    <div>
        <div class="brand">Admin User Management</div>
        <div class="top-sub">HO Admin only — system/admin users</div>
    </div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_user.php">+ Add Admin User</a>
    </div>
</div>

<div class="container">
    <div class="hero">
        <div>
            <h2>Admin Users</h2>
            <p>HO, Circle, Zone এবং Branch Admin users manage করুন। Officer users এখানে রাখা হয়নি।</p>
        </div>
        <a class="btn btn-blue" href="add_user.php">+ New Admin User</a>
    </div>

    <div class="box">
        <form method="GET" class="filters">
            <div>
                <label>Search</label>
                <input type="text" name="search" placeholder="Name or Username" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div>
                <label>Role</label>
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="ho_admin" <?php if($role_filter=='ho_admin') echo 'selected'; ?>>HO Admin</option>
                    <option value="circle_admin" <?php if($role_filter=='circle_admin') echo 'selected'; ?>>Circle Admin</option>
                    <option value="zone_admin" <?php if($role_filter=='zone_admin') echo 'selected'; ?>>Zone Admin</option>
                    <option value="admin" <?php if($role_filter=='admin') echo 'selected'; ?>>Branch Admin</option>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="Active" <?php if($status_filter=='Active') echo 'selected'; ?>>Active</option>
                    <option value="Inactive" <?php if($status_filter=='Inactive') echo 'selected'; ?>>Inactive</option>
                </select>
            </div>
            <div>
                <button class="btn btn-blue" type="submit">Filter</button>
                <a class="btn btn-gray" href="users.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="box">
        <p class="muted">Total admin users: <strong><?php echo number_format($total_count); ?></strong></p>
        <?php if ($total_count > 0 && $result) { ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Zone</th>
                    <th>Circle</th>
                    <th>Status</th>
                    <th>Officer ID</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
                <?php while($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo intval($row['id']); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><span class="pill pill-blue"><?php echo htmlspecialchars($row['role']); ?></span></td>
                    <td><?php echo htmlspecialchars($row['branch_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['zone_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['circle_name'] ?? '—'); ?></td>
                    <td><span class="pill <?php echo ($row['status']=='Active') ? 'pill-green' : 'pill-red'; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                    <td><?php echo !empty($row['officer_id']) ? intval($row['officer_id']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-gray" href="user_edit.php?id=<?php echo intval($row['id']); ?>">Edit</a>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <?php } else { ?>
            <p class="muted">কোনো admin user পাওয়া যায়নি।</p>
        <?php } ?>
    </div>
</div>
</body>
</html>
