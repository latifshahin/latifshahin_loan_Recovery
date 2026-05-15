<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ho_admin') {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$allowed_roles = array('ho_admin','circle_admin','zone_admin','admin');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);
    $circle_id = isset($_POST['circle_id']) ? intval($_POST['circle_id']) : 0;
    $zone_id = isset($_POST['zone_id']) ? intval($_POST['zone_id']) : 0;
    $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;

    if ($name == '' || $username == '' || $password == '' || $role == '') {
        $error = "Name, Username, Password এবং Role লাগবে।";
    } elseif (!in_array($role, $allowed_roles)) {
        $error = "Invalid role selected.";
    } elseif (!in_array($status, array('Active','Inactive'))) {
        $error = "Invalid status selected.";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = "Username ৩-৫০ character হতে হবে এবং শুধু letter, number, dot, underscore বা dash ব্যবহার করা যাবে।";
    } elseif (strlen($password) < 6) {
        $error = "Password কমপক্ষে ৬ character হতে হবে।";
    } elseif ($role == 'circle_admin' && $circle_id <= 0) {
        $error = "Circle Admin-এর জন্য Circle select করতে হবে।";
    } elseif ($role == 'zone_admin' && $zone_id <= 0) {
        $error = "Zone Admin-এর জন্য Zone select করতে হবে।";
    } elseif ($role == 'admin' && $branch_id <= 0) {
        $error = "Branch Admin-এর জন্য Branch select করতে হবে।";
    } else {
        $safe_name = $conn->real_escape_string($name);
        $safe_username = $conn->real_escape_string($username);
        $safe_role = $conn->real_escape_string($role);
        $safe_status = $conn->real_escape_string($status);

        $user_check = $conn->query("SELECT id FROM users WHERE username = '$safe_username' LIMIT 1");

        if ($user_check && $user_check->num_rows > 0) {
            $error = "এই Username already exists.";
        } else {
            if ($role == 'ho_admin') {
                $circle_id = 0;
                $zone_id = 0;
                $branch_id = 0;
            } elseif ($role == 'circle_admin') {
                $zone_id = 0;
                $branch_id = 0;
            } elseif ($role == 'zone_admin') {
                $branch_id = 0;
                $zone_check = $conn->query("SELECT circle_id FROM zones WHERE id = $zone_id LIMIT 1");
                if ($zone_check && $zone_check->num_rows > 0) {
                    $zone_row = $zone_check->fetch_assoc();
                    $circle_id = intval($zone_row['circle_id']);
                }
            } elseif ($role == 'admin') {
                $branch_check = $conn->query("
                    SELECT b.zone_id, z.circle_id
                    FROM branches b
                    LEFT JOIN zones z ON b.zone_id = z.id
                    WHERE b.id = $branch_id
                    LIMIT 1
                ");
                if ($branch_check && $branch_check->num_rows > 0) {
                    $branch_row = $branch_check->fetch_assoc();
                    $zone_id = intval($branch_row['zone_id']);
                    $circle_id = intval($branch_row['circle_id']);
                } else {
                    $error = "Invalid branch selected.";
                }
            }

            if ($error == '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $safe_password_hash = $conn->real_escape_string($password_hash);

                $insert = $conn->query("
                    INSERT INTO users
                    (name, username, password, role, officer_id, branch_id, circle_id, zone_id, status)
                    VALUES
                    ('$safe_name', '$safe_username', '$safe_password_hash', '$safe_role', NULL, $branch_id, $circle_id, $zone_id, '$safe_status')
                ");

                if ($insert) {
                    header("Location: users.php");
                    exit;
                } else {
                    $error = "User Insert Error: " . $conn->error;
                }
            }
        }
    }
}

$circles = $conn->query("SELECT id, name FROM circles ORDER BY name");
$zones = $conn->query("
    SELECT z.id, z.name, c.name AS circle_name
    FROM zones z
    LEFT JOIN circles c ON z.circle_id = c.id
    ORDER BY c.name, z.name
");
$branches = $conn->query("
    SELECT b.id, b.name, z.name AS zone_name, c.name AS circle_name
    FROM branches b
    LEFT JOIN zones z ON b.zone_id = z.id
    LEFT JOIN circles c ON z.circle_id = c.id
    ORDER BY c.name, z.name, b.name
");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Admin User</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--bg:#f4f7fb;--surface:#fff;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--blue:#2563eb;--red:#dc2626;--shadow:0 12px 28px rgba(15,23,42,.08)}*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:var(--bg);color:var(--ink)}.topbar{background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom:1px solid var(--line);padding:14px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;position:sticky;top:0;z-index:20}.brand{font-size:19px;font-weight:800}.top-sub{font-size:13px;color:var(--muted);margin-top:3px}.topbar a{display:inline-block;text-decoration:none;background:#fff;border:1px solid var(--line);color:#334155;padding:9px 13px;border-radius:999px;font-size:14px}.container{max-width:850px;margin:auto;padding:22px}.hero{background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);color:#fff;border-radius:26px;padding:24px;margin-bottom:18px;box-shadow:var(--shadow)}.hero h2{margin:0 0 6px;font-size:28px}.hero p{margin:0;color:#dbeafe}.box{background:var(--surface);border:1px solid var(--line);border-radius:24px;padding:20px;box-shadow:var(--shadow)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.full{grid-column:1/-1}label{display:block;font-size:13px;font-weight:800;color:#334155;margin:0 0 5px}input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:12px;background:#fff}.btn{display:inline-block;text-decoration:none;border:0;border-radius:999px;padding:10px 14px;font-weight:800;font-size:14px;cursor:pointer;margin-top:16px}.btn-blue{background:#2563eb;color:#fff}.btn-gray{background:#e2e8f0;color:#334155}.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:14px;margin-bottom:14px}.note{background:#eff6ff;color:#1d4ed8;padding:12px;border-radius:14px;margin-bottom:14px;font-size:14px}.muted{color:var(--muted);font-size:13px;margin-top:6px}@media(max-width:760px){.container{padding:12px}.grid{grid-template-columns:1fr}.hero,.box{border-radius:20px;padding:16px}.topbar{position:relative;padding:12px}.hero h2{font-size:23px}}
</style>
<script>
function toggleScopeFields() {
    var role = document.getElementById('role').value;
    document.getElementById('circleField').style.display = (role === 'circle_admin') ? 'block' : 'none';
    document.getElementById('zoneField').style.display = (role === 'zone_admin') ? 'block' : 'none';
    document.getElementById('branchField').style.display = (role === 'admin') ? 'block' : 'none';
}
window.addEventListener('DOMContentLoaded', toggleScopeFields);
</script>
</head>
<body>
<div class="topbar">
    <div>
        <div class="brand">Add Admin User</div>
        <div class="top-sub">HO Admin only — no officer profile will be created</div>
    </div>
    <div>
        <a href="users.php">Back to Users</a>
        <a href="dashboard.php">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="hero">
        <h2>Create Admin User</h2>
        <p>HO, Circle, Zone অথবা Branch Admin তৈরি করুন। Officer তৈরি করতে add_officer.php ব্যবহার করুন।</p>
    </div>

    <div class="box">
        <?php if ($error != '') { ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php } ?>
        <div class="note">এই page শুধু admin users তৈরি করবে। এখানে officers table-এ কোনো data add হবে না।</div>

        <form method="POST">
            <div class="grid">
                <div>
                    <label>Name</label>
                    <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                <div>
                    <label>Username</label>
                    <input type="text" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <div class="muted">কমপক্ষে ৬ character.</div>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Active" <?php if (!isset($_POST['status']) || $_POST['status']=='Active') echo 'selected'; ?>>Active</option>
                        <option value="Inactive" <?php if (isset($_POST['status']) && $_POST['status']=='Inactive') echo 'selected'; ?>>Inactive</option>
                    </select>
                </div>
                <div class="full">
                    <label>Role</label>
                    <select name="role" id="role" required onchange="toggleScopeFields()">
                        <option value="">Select Role</option>
                        <option value="ho_admin" <?php if(isset($_POST['role']) && $_POST['role']=='ho_admin') echo 'selected'; ?>>HO Admin</option>
                        <option value="circle_admin" <?php if(isset($_POST['role']) && $_POST['role']=='circle_admin') echo 'selected'; ?>>Circle Admin</option>
                        <option value="zone_admin" <?php if(isset($_POST['role']) && $_POST['role']=='zone_admin') echo 'selected'; ?>>Zone Admin</option>
                        <option value="admin" <?php if(isset($_POST['role']) && $_POST['role']=='admin') echo 'selected'; ?>>Branch Admin</option>
                    </select>
                </div>

                <div class="full" id="circleField">
                    <label>Circle</label>
                    <select name="circle_id">
                        <option value="">Select Circle</option>
                        <?php if ($circles) { while($c = $circles->fetch_assoc()) { ?>
                            <option value="<?php echo intval($c['id']); ?>" <?php if(isset($_POST['circle_id']) && intval($_POST['circle_id'])==intval($c['id'])) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php } } ?>
                    </select>
                </div>

                <div class="full" id="zoneField">
                    <label>Zone</label>
                    <select name="zone_id">
                        <option value="">Select Zone</option>
                        <?php if ($zones) { while($z = $zones->fetch_assoc()) { ?>
                            <option value="<?php echo intval($z['id']); ?>" <?php if(isset($_POST['zone_id']) && intval($_POST['zone_id'])==intval($z['id'])) echo 'selected'; ?>><?php echo htmlspecialchars($z['circle_name'] . ' - ' . $z['name']); ?></option>
                        <?php } } ?>
                    </select>
                </div>

                <div class="full" id="branchField">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">Select Branch</option>
                        <?php if ($branches) { while($b = $branches->fetch_assoc()) { ?>
                            <option value="<?php echo intval($b['id']); ?>" <?php if(isset($_POST['branch_id']) && intval($_POST['branch_id'])==intval($b['id'])) echo 'selected'; ?>><?php echo htmlspecialchars($b['circle_name'] . ' - ' . $b['zone_name'] . ' - ' . $b['name']); ?></option>
                        <?php } } ?>
                    </select>
                </div>
            </div>

            <button class="btn btn-blue" type="submit">Create Admin User</button>
            <a class="btn btn-gray" href="users.php">Back</a>
        </form>
    </div>
</div>
</body>
</html>
