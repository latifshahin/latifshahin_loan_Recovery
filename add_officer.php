<?php
include 'config.php';

$admin_roles = array('admin', 'zone_admin', 'circle_admin', 'ho_admin');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$role = $_SESSION['role'];
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$zone_id = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$circle_id = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;

$error = '';

$branch_where = "1=1";

if ($role == 'admin') {
    $branch_where = "b.id = $branch_id";
} elseif ($role == 'zone_admin') {
    $branch_where = "b.zone_id = $zone_id";
} elseif ($role == 'circle_admin') {
    $branch_where = "z.circle_id = $circle_id";
}

$allowed_user_roles = array('officer');
if (in_array($role, array('admin', 'zone_admin', 'circle_admin', 'ho_admin'))) {
    $allowed_user_roles[] = 'admin'; // Branch Admin
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $mobile = trim($_POST['mobile']);
    $designation = trim($_POST['designation']);
    $selected_branch = intval($_POST['branch_id']);
    $user_role = trim($_POST['user_role']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $status = trim($_POST['status']);

    if ($name == '' || $username == '' || $password == '' || $selected_branch <= 0) {
        $error = "Name, Username, Password এবং Branch লাগবে।";
    } elseif (!in_array($status, array('Active', 'Inactive'))) {
        $error = "Invalid status selected.";
    } elseif (!in_array($user_role, $allowed_user_roles)) {
        $error = "Invalid login role selected.";
    } elseif ($mobile != '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $mobile)) {
        $error = "Mobile number valid নয়। শুধু number, +, - এবং space ব্যবহার করুন।";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = "Username ৩-৫০ character হতে হবে এবং শুধু letter, number, dot, underscore বা dash ব্যবহার করা যাবে।";
    } elseif (strlen($password) < 6) {
        $error = "Password কমপক্ষে ৬ character হতে হবে।";
    } else {
        $safe_name = $conn->real_escape_string($name);
        $safe_mobile = $conn->real_escape_string($mobile);
        $safe_designation = $conn->real_escape_string($designation);
        $safe_username = $conn->real_escape_string($username);
        $safe_user_role = $conn->real_escape_string($user_role);
        $safe_status = $conn->real_escape_string($status);

        $branch_check = $conn->query("
            SELECT 
                b.id AS branch_id,
                b.zone_id,
                z.circle_id
            FROM branches b
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE b.id = $selected_branch AND $branch_where
            LIMIT 1
        ");

        if (!$branch_check || $branch_check->num_rows == 0) {
            $error = "এই Branch আপনার scope-এর মধ্যে নেই।";
        } else {
            $branch_row = $branch_check->fetch_assoc();
            $new_branch_id = intval($branch_row['branch_id']);
            $new_zone_id = intval($branch_row['zone_id']);
            $new_circle_id = intval($branch_row['circle_id']);

            $user_check = $conn->query("
                SELECT id
                FROM users
                WHERE username = '$safe_username'
                LIMIT 1
            ");

            if ($user_check && $user_check->num_rows > 0) {
                $error = "এই Username already exists.";
            } else {
                $conn->begin_transaction();

                try {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $safe_password_hash = $conn->real_escape_string($password_hash);

                    /* Step 1: Create login user */
                    $user_insert = $conn->query("
                        INSERT INTO users
                        (name, username, password, role, branch_id, circle_id, zone_id, status)
                        VALUES
                        ('$safe_name', '$safe_username', '$safe_password_hash', '$safe_user_role', $new_branch_id, $new_circle_id, $new_zone_id, '$safe_status')
                    ");

                    if (!$user_insert) {
                        throw new Exception("User Insert Error: " . $conn->error);
                    }

                    $new_user_id = intval($conn->insert_id);

                    /* Step 2: Create officer profile linked with user */
                    $officer_insert = $conn->query("
                        INSERT INTO officers
                        (user_id, name, mobile, designation, branch_id, status)
                        VALUES
                        ($new_user_id, '$safe_name', '$safe_mobile', '$safe_designation', $new_branch_id, '$safe_status')
                    ");

                    if (!$officer_insert) {
                        throw new Exception("Officer Insert Error: " . $conn->error);
                    }

                    $new_officer_id = intval($conn->insert_id);

                    /* Step 3: Back-link user to officer */
                    $user_link_update = $conn->query("
                        UPDATE users
                        SET officer_id = $new_officer_id
                        WHERE id = $new_user_id
                    ");

                    if (!$user_link_update) {
                        throw new Exception("User Officer Link Error: " . $conn->error);
                    }

                    $conn->commit();
                    header("Location: officers.php");
                    exit;

                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$branches = $conn->query("
    SELECT b.id, b.name, z.name AS zone_name, c.name AS circle_name
    FROM branches b
    LEFT JOIN zones z ON b.zone_id = z.id
    LEFT JOIN circles c ON z.circle_id = c.id
    WHERE $branch_where
    ORDER BY c.name, z.name, b.name
");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#eef2f7;margin:0;color:#1f2937}.topbar{background:linear-gradient(135deg,#1f2937,#111827);color:#fff;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.topbar a{color:#fff;text-decoration:none;background:rgba(255,255,255,.12);padding:8px 12px;border-radius:8px}.container{max-width:820px;margin:auto;padding:22px}.box{background:#fff;padding:22px;border-radius:18px;box-shadow:0 6px 20px rgba(15,23,42,.06)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:block;margin-top:12px;font-weight:bold;color:#334155;font-size:14px}input,select{width:100%;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:9px;background:#fff}.full{grid-column:1/-1}.btn{display:inline-block;margin-top:16px;padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;border:0;border-radius:9px;cursor:pointer;font-weight:bold}.btn-gray{background:#64748b}.error{background:#fee2e2;color:#991b1b;padding:11px;border-radius:10px;margin-bottom:12px}.muted{color:#64748b;font-size:13px;margin-top:6px}@media(max-width:700px){.container{padding:14px}.grid{grid-template-columns:1fr}.box{padding:16px}}
</style>
</head>
<body>
<div class="topbar">
    <div><strong>Add Officer</strong></div>
    <div><a href="officers.php">Back to Officers</a></div>
</div>

<div class="container">
<div class="box">

<?php if ($error != '') { ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php } ?>

<form method="POST">
    <div class="grid">
        <div>
            <label>Name</label>
            <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>

        <div>
            <label>Mobile</label>
            <input type="text" name="mobile" placeholder="01XXXXXXXXX" value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
        </div>

        <div>
            <label>Designation</label>
            <input type="text" name="designation" value="<?php echo isset($_POST['designation']) ? htmlspecialchars($_POST['designation']) : ''; ?>">
        </div>

        <div>
            <label>Status</label>
            <select name="status" required>
                <option value="Active" <?php if (!isset($_POST['status']) || $_POST['status'] == 'Active') echo 'selected'; ?>>Active</option>
                <option value="Inactive" <?php if (isset($_POST['status']) && $_POST['status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>

        <div class="full">
            <label>Branch</label>
            <select name="branch_id" required>
                <option value="">Select Branch</option>
                <?php if ($branches) { while ($b = $branches->fetch_assoc()) { ?>
                    <option value="<?php echo intval($b['id']); ?>" <?php if (isset($_POST['branch_id']) && intval($_POST['branch_id']) == intval($b['id'])) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($b['circle_name'] . ' - ' . $b['zone_name'] . ' - ' . $b['name']); ?>
                    </option>
                <?php } } ?>
            </select>
        </div>

        <div>
            <label>Login Role</label>
            <select name="user_role" required>
                <option value="officer" <?php if (!isset($_POST['user_role']) || $_POST['user_role'] == 'officer') echo 'selected'; ?>>Officer</option>
                <?php if (in_array($role, array('admin', 'zone_admin', 'circle_admin', 'ho_admin'))) { ?>
                    <option value="admin" <?php if (isset($_POST['user_role']) && $_POST['user_role'] == 'admin') echo 'selected'; ?>>Branch Admin</option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label>Username</label>
            <input type="text" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>

        <div class="full">
            <label>Password</label>
            <input type="password" name="password" required>
            <div class="muted">কমপক্ষে ৬ character.</div>
        </div>
    </div>

    <button class="btn" type="submit">Create Officer</button>
    <a class="btn btn-gray" href="officers.php">Back</a>
</form>

</div>
</div>
</body>
</html>
