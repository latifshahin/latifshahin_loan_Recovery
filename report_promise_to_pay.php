<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role       = $_SESSION['role'];
$branch_id  = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$circle_id  = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id    = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

/* FILTER */
$f_circle  = isset($_GET['circle_id']) ? intval($_GET['circle_id']) : 0;
$f_zone    = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : 0;
$f_branch  = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$f_officer = isset($_GET['officer_id']) ? intval($_GET['officer_id']) : 0;
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';

/* PAGINATION */
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

if (!in_array($limit, array(25, 50, 100, 200))) {
    $limit = 50;
}

$offset = ($page - 1) * $limit;

/* ROLE SCOPE */
$scope_where = "1=1";

if ($role === 'circle_admin') {
    $scope_where = "z.circle_id = $circle_id";
} elseif ($role === 'zone_admin') {
    $scope_where = "b.zone_id = $zone_id";
} elseif ($role === 'admin') {
    $scope_where = "c.branch_id = $branch_id";
} elseif ($role === 'officer') {
    $scope_where = "c.branch_id = $branch_id AND c.assigned_officer = $officer_id";
}

/* EXTRA FILTER */
$filter = "";

if ($f_circle > 0)  $filter .= " AND ci.id = $f_circle";
if ($f_zone > 0)    $filter .= " AND z.id = $f_zone";
if ($f_branch > 0)  $filter .= " AND b.id = $f_branch";
if ($f_officer > 0) $filter .= " AND c.assigned_officer = $f_officer";

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $filter .= " AND (
        c.name LIKE '%$safe%' OR
        c.account_number LIKE '%$safe%' OR
        c.phone LIKE '%$safe%' OR
        o.name LIKE '%$safe%' OR
        b.name LIKE '%$safe%' OR
        z.name LIKE '%$safe%'
    )";
}

/* DROPDOWN DATA */
$circle_sql = "SELECT id, name FROM circles WHERE 1=1";
$zone_sql = "SELECT id, name, circle_id FROM zones WHERE 1=1";
$branch_sql = "
SELECT b.id, b.name, b.zone_id, z.circle_id
FROM branches b
JOIN zones z ON b.zone_id = z.id
WHERE 1=1
";
$officer_sql = "SELECT id, name, branch_id FROM officers WHERE 1=1";

if ($role === 'admin') {
    $branch_sql .= " AND b.id = $branch_id";
    $circle_sql .= " AND id IN (
        SELECT z.circle_id FROM branches b JOIN zones z ON b.zone_id = z.id WHERE b.id = $branch_id
    )";
    $zone_sql .= " AND id IN (SELECT zone_id FROM branches WHERE id = $branch_id)";
    $officer_sql .= " AND branch_id = $branch_id";
} elseif ($role === 'zone_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND id = $zone_id";
    $branch_sql .= " AND b.zone_id = $zone_id";
    $officer_sql .= " AND branch_id IN (SELECT id FROM branches WHERE zone_id = $zone_id)";
} elseif ($role === 'circle_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND circle_id = $circle_id";
    $branch_sql .= " AND z.circle_id = $circle_id";
    $officer_sql .= " AND branch_id IN (
        SELECT b.id FROM branches b JOIN zones z ON b.zone_id = z.id WHERE z.circle_id = $circle_id
    )";
} elseif ($role === 'officer') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND id = $zone_id";
    $branch_sql .= " AND b.id = $branch_id";
    $officer_sql .= " AND id = $officer_id";
}

$circle_sql .= " ORDER BY name";
$zone_sql .= " ORDER BY name";
$branch_sql .= " ORDER BY b.name";
$officer_sql .= " ORDER BY name";

$circles  = $conn->query($circle_sql);
$zones    = $conn->query($zone_sql);
$branches = $conn->query($branch_sql);
$officers = $conn->query($officer_sql);

/* MAIN QUERY */
$sql = "
SELECT 
    ct.id AS contact_id,
    ct.customer_id,
    ct.officer_id AS contact_officer_id,
    ct.contact_type,
    ct.action_result,
    ct.commitment_amount,
    ct.note,
    ct.next_followup,
    ct.created_at,

    c.id,
    c.name,
    c.account_number,
    c.phone,
    c.outstanding,
    c.status,
    c.assigned_officer,

    o.name AS officer_name,
    b.name AS branch_name,
    z.name AS zone_name,
    ci.name AS circle_name

FROM contacts ct
INNER JOIN customers c ON ct.customer_id = c.id
LEFT JOIN officers o ON c.assigned_officer = o.id
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
LEFT JOIN circles ci ON z.circle_id = ci.id

WHERE $scope_where
$filter
AND ct.action_result = 'Promise to Pay'
AND NOT EXISTS (
    SELECT 1 
    FROM recoveries r
    WHERE r.customer_id = ct.customer_id
    AND r.recovery_date >= DATE(ct.created_at)
)

ORDER BY ct.next_followup ASC, ct.created_at DESC
LIMIT $limit OFFSET $offset
";

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

$rows = array();
$total_commitment = 0;
$total_outstanding = 0;

while ($row = $result->fetch_assoc()) {
    $total_commitment += floatval($row['commitment_amount']);
    $total_outstanding += floatval($row['outstanding']);
    $rows[] = $row;
}

/* COUNT QUERY */
$count_sql = "
SELECT COUNT(*) AS total_rows
FROM contacts ct
INNER JOIN customers c ON ct.customer_id = c.id
LEFT JOIN officers o ON c.assigned_officer = o.id
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
LEFT JOIN circles ci ON z.circle_id = ci.id
WHERE $scope_where
$filter
AND ct.action_result = 'Promise to Pay'
AND NOT EXISTS (
    SELECT 1 
    FROM recoveries r
    WHERE r.customer_id = ct.customer_id
    AND r.recovery_date >= DATE(ct.created_at)
)
";

$count_result = $conn->query($count_sql);
$total_rows = $count_result ? intval($count_result->fetch_assoc()['total_rows']) : 0;
$total_pages = max(1, ceil($total_rows / $limit));

$query_params = $_GET;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Promise to Pay Report</title>
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
    box-shadow:0 4px 18px rgba(0,0,0,0.15);
}

.topbar-title {
    font-size:20px;
    font-weight:bold;
}

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

.container {
    max-width:1450px;
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
    border-left:5px solid #f97316;
}

.card.green { border-left-color:#16a34a; }
.card.purple { border-left-color:#7c3aed; }

.card span {
    color:#64748b;
    font-size:13px;
    display:block;
    margin-bottom:8px;
}

.card strong {
    font-size:26px;
    color:#111827;
}

.box {
    background:#fff;
    padding:20px;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(15,23,42,0.06);
    overflow-x:auto;
    margin-bottom:18px;
}

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

table {
    width:100%;
    border-collapse:collapse;
    min-width:1300px;
}

th, td {
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    vertical-align:top;
    font-size:14px;
}

th {
    background:#f8fafc;
    color:#334155;
}

tr:hover {
    background:#f8fafc;
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

.zero {
    color:#94a3b8;
    font-size:13px;
}

.note {
    color:#64748b;
    font-size:13px;
}

.pagination {
    margin-top:16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.pagination-info {
    color:#475569;
    font-size:14px;
    padding:9px 12px;
    background:#f8fafc;
    border-radius:10px;
}

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
        <div class="topbar-title">Promise to Pay Report</div>
        <div style="font-size:13px; color:#cbd5e1; margin-top:4px;">
            Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="topbar-links">
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="customers.php">গ্রাহক তালিকা</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <div class="hero">
        <div>
            <h2>Pending Promise to Pay</h2>
            <p>যেসব গ্রাহক পরিশোধের প্রতিশ্রুতি দিয়েছে কিন্তু প্রতিশ্রুতির পরে recovery entry হয়নি।</p>
        </div>

        <div>
            <span class="badge">
                <?php
                if ($role === 'ho_admin') echo "All Scope";
                elseif ($role === 'circle_admin') echo "Circle Scope";
                elseif ($role === 'zone_admin') echo "Zone Scope";
                elseif ($role === 'admin') echo "Branch Scope";
                else echo "Officer Scope";
                ?>
            </span>
        </div>
    </div>

    <div class="box">
        <form method="GET" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; align-items:end;">

            <div>
                <label>Search</label>
                <input type="text" name="search" placeholder="Name / Account / Mobile / Officer"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div>
                <label>Circle</label>
                <select name="circle_id" id="circle">
                    <option value="0">All Circle</option>
                    <?php while($c = $circles->fetch_assoc()) { ?>
                        <option value="<?php echo intval($c['id']); ?>" <?php if($f_circle == intval($c['id'])) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div>
                <label>Zone</label>
                <select name="zone_id" id="zone">
                    <option value="0">All Zone</option>
                </select>
            </div>

            <div>
                <label>Branch</label>
                <select name="branch_id" id="branch">
                    <option value="0">All Branch</option>
                </select>
            </div>

            <div>
                <label>Officer</label>
                <select name="officer_id" id="officer">
                    <option value="0">All Officer</option>
                </select>
            </div>

            <div>
                <label>Rows Per Page</label>
                <select name="limit">
                    <option value="25" <?php if($limit == 25) echo 'selected'; ?>>25</option>
                    <option value="50" <?php if($limit == 50) echo 'selected'; ?>>50</option>
                    <option value="100" <?php if($limit == 100) echo 'selected'; ?>>100</option>
                    <option value="200" <?php if($limit == 200) echo 'selected'; ?>>200</option>
                </select>
            </div>

            <div>
                <button class="btn" type="submit">Filter</button>
                <a class="btn btn-orange" href="report_promise_to_pay.php">Reset</a>
            </div>

        </form>
    </div>

    <div class="cards">
        <div class="card">
            <span>Pending Promise</span>
            <strong><?php echo $total_rows; ?></strong>
        </div>

        <div class="card green">
            <span>Commitment Amount</span>
            <strong><?php echo number_format($total_commitment, 2); ?></strong>
        </div>

        <div class="card purple">
            <span>Outstanding</span>
            <strong><?php echo number_format($total_outstanding, 2); ?></strong>
        </div>
    </div>

    <div class="box">
        <?php if (count($rows) > 0) { ?>
            <table>
                <tr>
                    <th>গ্রাহক</th>
                    <th>হিসাব নম্বর</th>
                    <th>Branch</th>
                    <th>Zone</th>
                    <th>Circle</th>
                    <th>Officer</th>
                    <th>Outstanding</th>
                    <th>Commitment</th>
                    <th>Promise Date</th>
                    <th>Next Follow-up</th>
                    <th>Note</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

                <?php foreach($rows as $r) { ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($r['name']); ?></strong><br>
                        <span class="note"><?php echo htmlspecialchars($r['phone']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($r['account_number']); ?></td>
                    <td><?php echo htmlspecialchars($r['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['zone_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['circle_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['officer_name']); ?></td>
                    <td><strong><?php echo number_format($r['outstanding'], 2); ?></strong></td>
                    <td><strong><?php echo number_format($r['commitment_amount'], 2); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                    <td>
                        <?php echo $r['next_followup'] ? htmlspecialchars($r['next_followup']) : '<span class="zero">তথ্য নেই</span>'; ?>
                    </td>
                    <td><?php echo htmlspecialchars($r['note']); ?></td>
                    <td><?php echo htmlspecialchars($r['status']); ?></td>
                    <td>
                        <a class="btn" href="customer_view.php?id=<?php echo intval($r['id']); ?>">View</a>
                        <a class="btn btn-orange" href="add_contact.php?customer_id=<?php echo intval($r['id']); ?>&officer_id=<?php echo intval($r['assigned_officer']); ?>">Follow-up</a>
                        <a class="btn btn-green" href="add_recovery.php?customer_id=<?php echo intval($r['id']); ?>&officer_id=<?php echo intval($r['assigned_officer']); ?>">Recovery</a>
                    </td>
                </tr>
                <?php } ?>
            </table>

            <div class="pagination">
                <div>
                    <?php if ($page > 1) {
                        $query_params['page'] = $page - 1;
                    ?>
                        <a class="btn btn-gray" href="?<?php echo http_build_query($query_params); ?>">Previous</a>
                    <?php } ?>

                    <?php if ($page < $total_pages) {
                        $query_params['page'] = $page + 1;
                    ?>
                        <a class="btn" href="?<?php echo http_build_query($query_params); ?>">Next</a>
                    <?php } ?>
                </div>

                <div class="pagination-info">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    | Total Rows: <?php echo $total_rows; ?>
                    | Showing: <?php echo count($rows); ?>
                </div>
            </div>

        <?php } else { ?>
            <p class="zero">এই scope/filter অনুযায়ী pending promise পাওয়া যায়নি।</p>
        <?php } ?>
    </div>

</div>

<script>
var zones = <?php echo json_encode($zones->fetch_all(MYSQLI_ASSOC)); ?>;
var branches = <?php echo json_encode($branches->fetch_all(MYSQLI_ASSOC)); ?>;
var officers = <?php echo json_encode($officers->fetch_all(MYSQLI_ASSOC)); ?>;

var selectedZone = "<?php echo $f_zone; ?>";
var selectedBranch = "<?php echo $f_branch; ?>";
var selectedOfficer = "<?php echo $f_officer; ?>";

var circle = document.getElementById('circle');
var zone = document.getElementById('zone');
var branch = document.getElementById('branch');
var officer = document.getElementById('officer');

function loadZones(resetChild) {
    var cid = circle.value;

    if (resetChild) {
        selectedZone = "0";
        selectedBranch = "0";
        selectedOfficer = "0";
    }

    zone.innerHTML = '<option value="0">All Zone</option>';
    branch.innerHTML = '<option value="0">All Branch</option>';
    officer.innerHTML = '<option value="0">All Officer</option>';

    for (var i = 0; i < zones.length; i++) {
        var z = zones[i];
        if (cid == "0" || z.circle_id == cid) {
            var selected = (z.id == selectedZone) ? 'selected' : '';
            zone.innerHTML += '<option value="' + z.id + '" ' + selected + '>' + z.name + '</option>';
        }
    }

    loadBranches(false);
}

function loadBranches(resetChild) {
    var zid = zone.value;

    if (resetChild) {
        selectedBranch = "0";
        selectedOfficer = "0";
    }

    branch.innerHTML = '<option value="0">All Branch</option>';
    officer.innerHTML = '<option value="0">All Officer</option>';

    for (var i = 0; i < branches.length; i++) {
        var b = branches[i];
        if (zid == "0" || b.zone_id == zid) {
            var selected = (b.id == selectedBranch) ? 'selected' : '';
            branch.innerHTML += '<option value="' + b.id + '" ' + selected + '>' + b.name + '</option>';
        }
    }

    loadOfficers(false);
}

function loadOfficers(resetChild) {
    var bid = branch.value;

    if (resetChild) {
        selectedOfficer = "0";
    }

    officer.innerHTML = '<option value="0">All Officer</option>';

    for (var i = 0; i < officers.length; i++) {
        var o = officers[i];
        if (bid == "0" || o.branch_id == bid) {
            var selected = (o.id == selectedOfficer) ? 'selected' : '';
            officer.innerHTML += '<option value="' + o.id + '" ' + selected + '>' + o.name + '</option>';
        }
    }
}

circle.onchange = function() {
    loadZones(true);
};

zone.onchange = function() {
    loadBranches(true);
};

branch.onchange = function() {
    loadOfficers(true);
};

loadZones(false);
</script>

</body>
</html>