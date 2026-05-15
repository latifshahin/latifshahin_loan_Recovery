<?php
include 'config.php';

/* =========================================================
   1) LOGIN CHECK
   ========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   2) SESSION ROLE DATA
   Role hierarchy:
   officer < admin < zone_admin < circle_admin < ho_admin
   ========================================================= */
$role       = $_SESSION['role'];
$branch_id  = intval($_SESSION['branch_id'] ?? 0);
$circle_id  = intval($_SESSION['circle_id'] ?? 0);
$zone_id    = intval($_SESSION['zone_id'] ?? 0);
$officer_id = intval($_SESSION['officer_id'] ?? 0);

$today = date('Y-m-d');

/* =========================================================
   3) ROLE BASED DATA SCOPE
   ========================================================= */
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

/* =========================================================
   4) FILTER VALUES
   ========================================================= */
$f_circle  = intval($_GET['circle_id'] ?? 0);
$f_zone    = intval($_GET['zone_id'] ?? 0);
$f_branch  = intval($_GET['branch_id'] ?? 0);
$f_officer = intval($_GET['officer_id'] ?? 0);

$filter = "";

if ($f_circle > 0)  $filter .= " AND ci.id = $f_circle";
if ($f_zone > 0)    $filter .= " AND z.id = $f_zone";
if ($f_branch > 0)  $filter .= " AND b.id = $f_branch";
if ($f_officer > 0) $filter .= " AND c.assigned_officer = $f_officer";

/* =========================================================
   5) PAGINATION
   ========================================================= */
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = intval($_GET['limit'] ?? 50);

if (!in_array($limit, [25, 50, 100, 200])) {
    $limit = 50;
}

$offset = ($page - 1) * $limit;

/* =========================================================
   6) ROLE SCOPED DROPDOWN DATA
   ========================================================= */
$circle_sql = "SELECT id, name FROM circles WHERE 1=1";
$zone_sql = "SELECT z.id, z.name, z.circle_id FROM zones z WHERE 1=1";
$branch_sql = "SELECT b.id, b.name, b.zone_id FROM branches b WHERE 1=1";
$officer_sql = "SELECT o.id, o.name, o.branch_id FROM officers o WHERE 1=1";

if ($role === 'circle_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND z.circle_id = $circle_id";
    $branch_sql .= " AND b.zone_id IN (SELECT id FROM zones WHERE circle_id = $circle_id)";
    $officer_sql .= " AND o.branch_id IN (
        SELECT b.id
        FROM branches b
        JOIN zones z ON b.zone_id = z.id
        WHERE z.circle_id = $circle_id
    )";
} elseif ($role === 'zone_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND z.id = $zone_id";
    $branch_sql .= " AND b.zone_id = $zone_id";
    $officer_sql .= " AND o.branch_id IN (SELECT id FROM branches WHERE zone_id = $zone_id)";
} elseif ($role === 'admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND z.id = $zone_id";
    $branch_sql .= " AND b.id = $branch_id";
    $officer_sql .= " AND o.branch_id = $branch_id";
} elseif ($role === 'officer') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND z.id = $zone_id";
    $branch_sql .= " AND b.id = $branch_id";
    $officer_sql .= " AND o.id = $officer_id";
}

$circle_sql .= " ORDER BY name";
$zone_sql .= " ORDER BY name";
$branch_sql .= " ORDER BY name";
$officer_sql .= " ORDER BY name";

$circles  = $conn->query($circle_sql);
$zones    = $conn->query($zone_sql);
$branches = $conn->query($branch_sql);
$officers = $conn->query($officer_sql);

/* =========================================================
   7) MAIN QUERY
   Latest contact per customer only.
   Due means next_followup <= today.
   ========================================================= */
$sql = "
SELECT 
    c.id,
    c.name,
    c.account_number,
    c.phone,
    c.outstanding,
    c.assigned_officer,

    ct.officer_id AS contact_officer_id,
    ct.contact_type,
    ct.action_result,
    ct.commitment_amount,
    ct.note,
    ct.next_followup,
    ct.created_at,

    o.name AS officer_name,
    b.name AS branch_name,
    z.name AS zone_name,
    ci.name AS circle_name

FROM contacts ct

JOIN (
    SELECT customer_id, MAX(id) AS latest_contact_id
    FROM contacts
    WHERE next_followup IS NOT NULL
    GROUP BY customer_id
) latest ON ct.id = latest.latest_contact_id

JOIN customers c ON ct.customer_id = c.id
LEFT JOIN officers o ON ct.officer_id = o.id
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
LEFT JOIN circles ci ON z.circle_id = ci.id

WHERE $scope_where
AND ct.next_followup <= '$today'
$filter

ORDER BY ct.next_followup ASC, ct.created_at DESC
LIMIT $limit OFFSET $offset
";

$result = $conn->query($sql);

$rows = [];

if ($result) {
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
}

/* =========================================================
   8) TOTAL ROW COUNT
   ========================================================= */
$count_sql = "
SELECT COUNT(*) AS total_rows

FROM contacts ct

JOIN (
    SELECT customer_id, MAX(id) AS latest_contact_id
    FROM contacts
    WHERE next_followup IS NOT NULL
    GROUP BY customer_id
) latest ON ct.id = latest.latest_contact_id

JOIN customers c ON ct.customer_id = c.id
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
LEFT JOIN circles ci ON z.circle_id = ci.id

WHERE $scope_where
AND ct.next_followup <= '$today'
$filter
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
    <title>Due Followups</title>
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

    .badge {
        display:inline-block;
        padding:5px 10px;
        background:#eef2ff;
        border-radius:999px;
        color:#3730a3;
        font-size:12px;
        font-weight:bold;
    }

    .filter-box {
        background:#fff;
        padding:22px;
        border-radius:18px;
        margin-bottom:18px;
        box-shadow:0 6px 20px rgba(15,23,42,0.08);
        border:1px solid #e5e7eb;
    }

    .filter-title {
        font-size:18px;
        font-weight:bold;
        margin-bottom:14px;
        color:#111827;
    }

    .filter-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
        gap:14px;
        align-items:end;
    }

    .form-group label {
        display:block;
        font-size:13px;
        font-weight:bold;
        color:#475569;
        margin-bottom:6px;
    }

    select {
        width:100%;
        padding:10px 12px;
        border:1px solid #cbd5e1;
        border-radius:10px;
        background:#f8fafc;
        font-size:14px;
    }

    select:focus {
        outline:none;
        border-color:#2563eb;
        background:#fff;
        box-shadow:0 0 0 3px rgba(37,99,235,0.12);
    }

    .filter-actions {
        display:flex;
        gap:8px;
        flex-wrap:wrap;
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
        border-left:5px solid #2563eb;
    }

    .card.orange { border-left-color:#f97316; }
    .card.red { border-left-color:#dc2626; }

    .card span {
        color:#64748b;
        font-size:13px;
        display:block;
        margin-bottom:8px;
    }

    .card strong {
        font-size:24px;
        color:#111827;
    }

    .box {
        background:#fff;
        padding:20px;
        border-radius:18px;
        box-shadow:0 6px 20px rgba(15,23,42,0.06);
        overflow-x:auto;
    }

    table {
        width:100%;
        border-collapse:collapse;
        min-width:1250px;
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

    .overdue {
        background:#fff1f2;
    }

    .today {
        background:#ecfeff;
    }

    .btn {
        display:inline-block;
        padding:9px 12px;
        background:#2563eb;
        color:#fff;
        border-radius:10px;
        text-decoration:none;
        border:0;
        cursor:pointer;
        font-size:14px;
        line-height:1;
        margin:2px;
    }

    .btn-orange { background:#f97316; }
    .btn-gray { background:#64748b; }

    .pagination {
        margin-top:16px;
        display:flex;
        gap:8px;
        flex-wrap:wrap;
        align-items:center;
        justify-content:space-between;
    }

    .pagination-info {
        color:#475569;
        font-size:14px;
        padding:9px 12px;
        background:#f8fafc;
        border-radius:10px;
    }

    .empty {
        color:#64748b;
        padding:15px;
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

        .hero h2 {
            font-size:22px;
        }
    }
</style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">Due Followups</div>
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

    <!-- PAGE HEADER -->
    <div class="hero">
        <div>
            <h2>Due Followups</h2>
            <p>যেসব গ্রাহকের follow-up date আজ বা আজকের আগের তারিখে আছে, তারা এখানে দেখাবে।</p>
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

    <!-- FILTER -->
    <div class="filter-box">
        <div class="filter-title">Followup Filter</div>

        <form method="GET" class="filter-grid">
            <input type="hidden" name="page" value="1">

            <div class="form-group">
                <label>Circle</label>
                <select name="circle_id" id="circle">
                    <option value="0">All Circle</option>
                    <?php while ($c = $circles->fetch_assoc()) { ?>
                        <option value="<?php echo intval($c['id']); ?>" <?php echo ($f_circle == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Zone</label>
                <select name="zone_id" id="zone">
                    <option value="0">All Zone</option>
                </select>
            </div>

            <div class="form-group">
                <label>Branch</label>
                <select name="branch_id" id="branch">
                    <option value="0">All Branch</option>
                </select>
            </div>

            <div class="form-group">
                <label>Officer</label>
                <select name="officer_id" id="officer">
                    <option value="0">All Officer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Rows Per Page</label>
                <select name="limit">
                    <option value="25" <?php if ($limit == 25) echo 'selected'; ?>>25</option>
                    <option value="50" <?php if ($limit == 50) echo 'selected'; ?>>50</option>
                    <option value="100" <?php if ($limit == 100) echo 'selected'; ?>>100</option>
                    <option value="200" <?php if ($limit == 200) echo 'selected'; ?>>200</option>
                </select>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <div class="filter-actions">
                    <button class="btn" type="submit">Filter</button>
                    <a class="btn btn-orange" href="due_followups.php">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- SUMMARY -->
    <div class="cards">
        <div class="card red">
            <span>Due Followups</span>
            <strong><?php echo $total_rows; ?></strong>
        </div>

        <div class="card">
            <span>Showing This Page</span>
            <strong><?php echo count($rows); ?></strong>
        </div>

        <div class="card orange">
            <span>Current Page</span>
            <strong><?php echo $page; ?> / <?php echo $total_pages; ?></strong>
        </div>
    </div>

    <!-- TABLE -->
    <div class="box">
        <?php if (count($rows) > 0) { ?>
            <table>
                <tr>
                    <th>Customer</th>
                    <th>Account</th>
                    <th>Phone</th>
                    <th>Outstanding</th>
                    <th>Next Followup</th>
                    <th>Branch</th>
                    <th>Zone</th>
                    <th>Circle</th>
                    <th>Officer</th>
                    <th>Note</th>
                    <th>Action Result</th>
                    <th>Action</th>
                </tr>

                <?php foreach ($rows as $r) {
                    $class = "";
                    if ($r['next_followup'] < $today) {
                        $class = "overdue";
                    } elseif ($r['next_followup'] == $today) {
                        $class = "today";
                    }
                ?>
                    <tr class="<?php echo $class; ?>">
                        <td><strong><?php echo htmlspecialchars($r['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['account_number']); ?></td>
                        <td><?php echo htmlspecialchars($r['phone']); ?></td>
                        <td><strong><?php echo number_format($r['outstanding'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['next_followup']); ?></td>
                        <td><?php echo htmlspecialchars($r['branch_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['zone_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['circle_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['officer_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['note'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['action_result'] ?? ''); ?></td>
                        <td>
                            <a class="btn" href="customer_view.php?id=<?php echo intval($r['id']); ?>">View</a>
                            <a class="btn btn-orange" href="add_contact.php?customer_id=<?php echo intval($r['id']); ?>&officer_id=<?php echo intval($r['assigned_officer']); ?>">Follow</a>
                        </td>
                    </tr>
                <?php } ?>
            </table>

            <!-- PAGINATION -->
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
            <div class="empty">এই scope অনুযায়ী due followup পাওয়া যায়নি।</div>
        <?php } ?>
    </div>

</div>

<!-- CASCADING FILTER SCRIPT -->
<script>
const zones = <?php echo json_encode($zones->fetch_all(MYSQLI_ASSOC)); ?>;
const branches = <?php echo json_encode($branches->fetch_all(MYSQLI_ASSOC)); ?>;
const officers = <?php echo json_encode($officers->fetch_all(MYSQLI_ASSOC)); ?>;

let selectedZone = "<?php echo $f_zone; ?>";
let selectedBranch = "<?php echo $f_branch; ?>";
let selectedOfficer = "<?php echo $f_officer; ?>";

const circleSelect = document.getElementById('circle');
const zoneSelect = document.getElementById('zone');
const branchSelect = document.getElementById('branch');
const officerSelect = document.getElementById('officer');

function loadZones(resetChild = false) {
    let cid = circleSelect.value;

    if (resetChild) {
        selectedZone = "0";
        selectedBranch = "0";
        selectedOfficer = "0";
    }

    zoneSelect.innerHTML = '<option value="0">All Zone</option>';
    branchSelect.innerHTML = '<option value="0">All Branch</option>';
    officerSelect.innerHTML = '<option value="0">All Officer</option>';

    zones.forEach(function(z) {
        if (cid == 0 || z.circle_id == cid) {
            let selected = (z.id == selectedZone) ? 'selected' : '';
            zoneSelect.innerHTML += `<option value="${z.id}" ${selected}>${z.name}</option>`;
        }
    });

    loadBranches(false);
}

function loadBranches(resetChild = false) {
    let zid = zoneSelect.value;

    if (resetChild) {
        selectedBranch = "0";
        selectedOfficer = "0";
    }

    branchSelect.innerHTML = '<option value="0">All Branch</option>';
    officerSelect.innerHTML = '<option value="0">All Officer</option>';

    branches.forEach(function(b) {
        if (zid == 0 || b.zone_id == zid) {
            let selected = (b.id == selectedBranch) ? 'selected' : '';
            branchSelect.innerHTML += `<option value="${b.id}" ${selected}>${b.name}</option>`;
        }
    });

    loadOfficers(false);
}

function loadOfficers(resetChild = false) {
    let bid = branchSelect.value;

    if (resetChild) {
        selectedOfficer = "0";
    }

    officerSelect.innerHTML = '<option value="0">All Officer</option>';

    officers.forEach(function(o) {
        if (bid == 0 || o.branch_id == bid) {
            let selected = (o.id == selectedOfficer) ? 'selected' : '';
            officerSelect.innerHTML += `<option value="${o.id}" ${selected}>${o.name}</option>`;
        }
    });
}

circleSelect.addEventListener('change', function() {
    loadZones(true);
});

zoneSelect.addEventListener('change', function() {
    loadBranches(true);
});

branchSelect.addEventListener('change', function() {
    loadOfficers(true);
});

/* Initial load */
loadZones(false);
</script>

</body>
</html>