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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_circle = isset($_GET['circle_id']) ? intval($_GET['circle_id']) : 0;
$filter_zone = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : 0;
$filter_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

/* Main role scope */
$where = "1=1";

if ($role == 'admin') {
    $where = "o.branch_id = $branch_id";
} elseif ($role == 'zone_admin') {
    $where = "b.zone_id = $zone_id";
} elseif ($role == 'circle_admin') {
    $where = "z.circle_id = $circle_id";
}

/* Extra filters */
if ($filter_circle > 0) {
    $where .= " AND z.circle_id = $filter_circle";
}

if ($filter_zone > 0) {
    $where .= " AND b.zone_id = $filter_zone";
}

if ($filter_branch > 0) {
    $where .= " AND o.branch_id = $filter_branch";
}

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $where .= " AND (
        o.name LIKE '%$safe%' OR
        o.mobile LIKE '%$safe%' OR
        o.designation LIKE '%$safe%' OR
        o.status LIKE '%$safe%' OR
        b.name LIKE '%$safe%' OR
        z.name LIKE '%$safe%'
    )";
}

/* Dropdown data according to role */
$circle_sql = "SELECT id, name FROM circles WHERE 1=1";
$zone_sql = "SELECT id, name, circle_id FROM zones WHERE 1=1";
$branch_sql = "
SELECT 
    b.id,
    b.name,
    b.zone_id,
    z.circle_id
FROM branches b
LEFT JOIN zones z ON b.zone_id = z.id
WHERE 1=1
";

if ($role == 'admin') {
    $branch_sql .= " AND b.id = $branch_id";

    $circle_sql .= " AND id IN (
        SELECT z.circle_id
        FROM branches b
        JOIN zones z ON b.zone_id = z.id
        WHERE b.id = $branch_id
    )";

    $zone_sql .= " AND id IN (
        SELECT zone_id
        FROM branches
        WHERE id = $branch_id
    )";
} elseif ($role == 'zone_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND id = $zone_id";
    $branch_sql .= " AND b.zone_id = $zone_id";
} elseif ($role == 'circle_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND circle_id = $circle_id";
    $branch_sql .= " AND z.circle_id = $circle_id";
}

$circle_sql .= " ORDER BY name";
$zone_sql .= " ORDER BY name";
$branch_sql .= " ORDER BY b.name";

$circles = $conn->query($circle_sql);
$zones = $conn->query($zone_sql);
$branches = $conn->query($branch_sql);

/* Officer list */
$sql = "
SELECT 
    o.*,
    b.name AS branch_name,
    z.name AS zone_name,
    c.name AS circle_name
FROM officers o
LEFT JOIN branches b ON o.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
LEFT JOIN circles c ON z.circle_id = c.id
WHERE $where
ORDER BY o.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

$total_count = $result->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Officers</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* { box-sizing:border-box; }
body { font-family:Arial,sans-serif; background:#eef2f7; margin:0; color:#1f2937; }

.topbar {
    background:linear-gradient(135deg,#1f2937,#111827);
    color:#fff;
    padding:18px 22px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
}
.topbar-title { font-size:20px; font-weight:bold; }
.topbar-links a {
    color:#fff;
    text-decoration:none;
    margin-left:8px;
    padding:8px 12px;
    background:rgba(255,255,255,0.12);
    border-radius:8px;
    display:inline-block;
    font-size:14px;
}

.container { max-width:1400px; margin:auto; padding:22px; }

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
.hero h2 { margin:0 0 8px; font-size:26px; }
.hero p { margin:0; color:#64748b; }

.box {
    background:#fff;
    padding:20px;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
    overflow-x:auto;
    margin-bottom:18px;
}

.cards {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:14px;
    margin-bottom:18px;
}
.card {
    background:#fff;
    padding:20px;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
    border-left:5px solid #7c3aed;
}
.card span { color:#64748b; font-size:13px; display:block; margin-bottom:8px; }
.card strong { font-size:26px; color:#111827; }

label {
    display:block;
    font-size:13px;
    color:#475569;
    margin-bottom:5px;
    font-weight:bold;
}
input, select {
    width:100%;
    padding:9px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    background:#fff;
}

.btn {
    display:inline-block;
    padding:7px 10px;
    background:#2563eb;
    color:#fff;
    text-decoration:none;
    border-radius:8px;
    font-size:13px;
    margin:2px;
    border:0;
    cursor:pointer;
}
.btn-green { background:#16a34a; }
.btn-orange { background:#f97316; }
.btn-gray { background:#64748b; }

.badge {
    display:inline-block;
    padding:4px 8px;
    background:#eef2ff;
    border-radius:999px;
    color:#3730a3;
    font-size:12px;
    font-weight:bold;
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:1050px;
}
th, td {
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    vertical-align:top;
    font-size:14px;
}
th { background:#f8fafc; color:#334155; }
tr:hover { background:#f8fafc; }

.muted { color:#64748b; font-size:13px; }

@media(max-width:700px) {
    .container { padding:14px; }
    .topbar-links a {
        margin-left:0;
        margin-right:6px;
        margin-top:8px;
    }
}
</style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">Officers</div>
        <div style="font-size:13px;color:#cbd5e1;margin-top:4px;">
            Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="topbar-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="hero">
        <div>
            <h2>Officer List</h2>
            <p>Role অনুযায়ী officer list, search এবং filtering।</p>
        </div>

        <div>
            <span class="badge"><?php echo htmlspecialchars($role); ?></span>
            <a class="btn btn-green" href="add_officer.php">+ Add Officer</a>
        </div>
    </div>

    <div class="box">
        <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
            <div>
                <label>Search</label>
                <input type="text" name="search" placeholder="Name / Mobile / Designation"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div>
                <label>Circle</label>
                <select name="circle_id" id="circle_id">
                    <option value="0">All Circle</option>
                    <?php while ($c = $circles->fetch_assoc()) { ?>
                        <option value="<?php echo intval($c['id']); ?>" <?php if ($filter_circle == intval($c['id'])) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div>
                <label>Zone</label>
                <select name="zone_id" id="zone_id">
                    <option value="0">All Zone</option>
                </select>
            </div>

            <div>
                <label>Branch</label>
                <select name="branch_id" id="branch_id">
                    <option value="0">All Branch</option>
                </select>
            </div>

            <div>
                <button class="btn" type="submit">Filter</button>
                <a class="btn btn-orange" href="officers.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="cards">
        <div class="card">
            <span>Total Officers</span>
            <strong><?php echo $total_count; ?></strong>
        </div>

        <div class="card">
            <span>Scope</span>
            <strong><?php echo htmlspecialchars($role); ?></strong>
        </div>
    </div>

    <div class="box">
        <?php if ($total_count > 0) { ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Mobile</th>
                <th>Designation</th>
                <th>Branch</th>
                <th>Zone</th>
                <th>Circle</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo intval($row['id']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                <td><?php echo htmlspecialchars($row['designation']); ?></td>
                <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                <td><?php echo htmlspecialchars($row['zone_name']); ?></td>
                <td><?php echo htmlspecialchars($row['circle_name']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <a class="btn btn-gray" href="officer_edit.php?id=<?php echo intval($row['id']); ?>">Edit</a>
                </td>
            </tr>
            <?php } ?>
        </table>
        <?php } else { ?>
            <p class="muted">এই scope/filter অনুযায়ী কোনো officer পাওয়া যায়নি।</p>
        <?php } ?>
    </div>

</div>

<script>
var zones = <?php echo json_encode($zones->fetch_all(MYSQLI_ASSOC)); ?>;
var branches = <?php echo json_encode($branches->fetch_all(MYSQLI_ASSOC)); ?>;

var selectedZone = "<?php echo $filter_zone; ?>";
var selectedBranch = "<?php echo $filter_branch; ?>";

var circleSelect = document.getElementById('circle_id');
var zoneSelect = document.getElementById('zone_id');
var branchSelect = document.getElementById('branch_id');

function loadZones(resetChild) {
    var cid = circleSelect.value;

    if (resetChild) {
        selectedZone = "0";
        selectedBranch = "0";
    }

    zoneSelect.innerHTML = '<option value="0">All Zone</option>';
    branchSelect.innerHTML = '<option value="0">All Branch</option>';

    for (var i = 0; i < zones.length; i++) {
        var z = zones[i];

        if (cid == "0" || z.circle_id == cid) {
            var selected = (z.id == selectedZone) ? 'selected' : '';
            zoneSelect.innerHTML += '<option value="' + z.id + '" ' + selected + '>' + z.name + '</option>';
        }
    }

    loadBranches(false);
}

function loadBranches(resetChild) {
    var zid = zoneSelect.value;
    var cid = circleSelect.value;

    if (resetChild) {
        selectedBranch = "0";
    }

    branchSelect.innerHTML = '<option value="0">All Branch</option>';

    for (var i = 0; i < branches.length; i++) {
        var b = branches[i];

        if ((cid == "0" || b.circle_id == cid) && (zid == "0" || b.zone_id == zid)) {
            var selected = (b.id == selectedBranch) ? 'selected' : '';
            branchSelect.innerHTML += '<option value="' + b.id + '" ' + selected + '>' + b.name + '</option>';
        }
    }
}

circleSelect.onchange = function() {
    loadZones(true);
};

zoneSelect.onchange = function() {
    loadBranches(true);
};

loadZones(false);
</script>

</body>
</html>