<?php
include 'config.php';

$admin_roles = array('admin', 'zone_admin', 'circle_admin', 'ho_admin');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$role = $_SESSION['role'];
$circle_id = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id   = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;

$error = "";

/* Role অনুযায়ী Zone list */
$zone_sql = "
SELECT 
    z.id,
    z.name AS zone_name,
    c.name AS circle_name
FROM zones z
LEFT JOIN circles c ON z.circle_id = c.id
WHERE 1=1
";

if ($role == 'circle_admin') {
    $zone_sql .= " AND z.circle_id = $circle_id";
} elseif ($role == 'zone_admin') {
    $zone_sql .= " AND z.id = $zone_id";
} elseif ($role == 'admin') {
    $zone_sql .= " AND z.id = $zone_id";
}

$zone_sql .= " ORDER BY c.name, z.name";
$zones = $conn->query($zone_sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $code = $conn->real_escape_string(trim($_POST['code']));
    $selected_zone_id = intval($_POST['zone_id']);

    if ($name == '') {
        $error = "Branch name is required.";
    } elseif ($selected_zone_id <= 0) {
        $error = "Zone select করতে হবে।";
    } else {

        /* Selected zone user scope-এর মধ্যে আছে কিনা check */
        $check_sql = "
        SELECT z.id
        FROM zones z
        WHERE z.id = $selected_zone_id
        ";

        if ($role == 'circle_admin') {
            $check_sql .= " AND z.circle_id = $circle_id";
        } elseif ($role == 'zone_admin') {
            $check_sql .= " AND z.id = $zone_id";
        } elseif ($role == 'admin') {
            $check_sql .= " AND z.id = $zone_id";
        }

        $check = $conn->query($check_sql);

        if (!$check || $check->num_rows == 0) {
            $error = "এই Zone আপনার scope-এর মধ্যে নেই।";
        } else {
            $insert = $conn->query("
                INSERT INTO branches (name, code, zone_id)
                VALUES ('$name', '$code', $selected_zone_id)
            ");

            if ($insert) {
                header("Location: branches.php");
                exit;
            } else {
                $error = "SQL Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>নতুন শাখা যোগ করুন</title>
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
    max-width:760px;
    margin:auto;
    padding:22px;
}

.box {
    background:#fff;
    padding:24px;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
}

label {
    display:block;
    font-size:13px;
    color:#475569;
    margin-bottom:6px;
    font-weight:bold;
}

input, select {
    width:100%;
    padding:11px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    background:#fff;
    margin-bottom:14px;
}

.btn {
    display:inline-block;
    padding:10px 14px;
    background:#2563eb;
    color:#fff;
    border:0;
    border-radius:10px;
    text-decoration:none;
    cursor:pointer;
}

.btn-gray {
    background:#64748b;
}

.error {
    background:#fee2e2;
    color:#991b1b;
    padding:12px;
    border-radius:10px;
    margin-bottom:14px;
}
</style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">নতুন শাখা যোগ করুন</div>
        <div style="font-size:13px;color:#cbd5e1;margin-top:4px;">
            Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div>
        <a href="branches.php">শাখা তালিকা</a>
        <a href="dashboard.php">ড্যাশবোর্ড</a>
    </div>
</div>

<div class="container">
    <div class="box">

        <?php if ($error != '') { ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <form method="POST">
            <label>Branch Name</label>
            <input type="text" name="name" required>

            <label>Branch Code</label>
            <input type="text" name="code">

            <label>Zone</label>
            <select name="zone_id" required>
                <option value="">Select Zone</option>
                <?php if ($zones) { while ($z = $zones->fetch_assoc()) { ?>
                    <option value="<?php echo intval($z['id']); ?>">
                        <?php echo htmlspecialchars($z['circle_name'] . ' - ' . $z['zone_name']); ?>
                    </option>
                <?php } } ?>
            </select>

            <button class="btn" type="submit">Save Branch</button>
            <a class="btn btn-gray" href="branches.php">Back</a>
        </form>

    </div>
</div>

</body>
</html>