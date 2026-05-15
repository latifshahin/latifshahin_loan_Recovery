<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$branch_id  = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$circle_id  = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id    = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

$search = isset($_GET['search']) ? trim($_GET['search']) : "";

$filter_circle  = isset($_GET['circle_id']) ? intval($_GET['circle_id']) : 0;
$filter_zone    = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : 0;
$filter_branch  = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$filter_officer = isset($_GET['officer_id']) ? intval($_GET['officer_id']) : 0;
$filter_customer_state = isset($_GET['customer_state']) ? trim($_GET['customer_state']) : '';
$filter_missing_date = isset($_GET['missing_first_default_date']) ? intval($_GET['missing_first_default_date']) : 0;
$filter_cl_date = isset($_GET['first_default_date']) ? trim($_GET['first_default_date']) : '';
$allowed_customer_states = ['Old CL','New CL','Standard Risky','Fully Recovered'];
if (!in_array($filter_customer_state, $allowed_customer_states)) { $filter_customer_state = ''; }

/* Role scope */
$scope_where = "1=1";

if ($role === 'ho_admin') {
    $scope_where = "1=1";
} elseif ($role === 'circle_admin') {
    $scope_where = "z.circle_id = $circle_id";
} elseif ($role === 'zone_admin') {
    $scope_where = "b.zone_id = $zone_id";
} elseif ($role === 'admin') {
    /* Branch admin must have branch_id; if not set, avoid blank by allowing own visible data only when branch exists */
    $scope_where = ($branch_id > 0) ? "c.branch_id = $branch_id" : "1=1";
} else {
    $scope_where = "c.branch_id = $branch_id AND c.assigned_officer = $officer_id";
}

/* Filters */
$filter_where = "";

if ($filter_circle > 0) $filter_where .= " AND ci.id = $filter_circle";
if ($filter_zone > 0) $filter_where .= " AND z.id = $filter_zone";
if ($filter_branch > 0) $filter_where .= " AND b.id = $filter_branch";
if ($filter_officer > 0) $filter_where .= " AND c.assigned_officer = $filter_officer";
if ($filter_customer_state !== '') {
    $safe_state = $conn->real_escape_string($filter_customer_state);
    $filter_where .= " AND c.customer_state = '$safe_state'";
}
// Missing date filter
if ($filter_missing_date == 1) {
    $filter_where .= " AND (
        c.first_default_date IS NULL 
        OR c.first_default_date = '' 
        OR c.first_default_date = '0000-00-00'
    )";
}

// Specific CL date filter
if ($filter_cl_date !== '') {
    $safe_date = $conn->real_escape_string($filter_cl_date);
    $filter_where .= " AND c.first_default_date = '$safe_date'";
}

if ($search !== "") {
    $safe_search = $conn->real_escape_string($search);
    $filter_where .= " AND (
        c.name LIKE '%$safe_search%' OR
        c.account_number LIKE '%$safe_search%' OR
        c.phone LIKE '%$safe_search%' OR
        c.cl_class LIKE '%$safe_search%' OR
        c.status LIKE '%$safe_search%' OR
        c.customer_state LIKE '%$safe_search%' OR
        o.name LIKE '%$safe_search%'
    )";
}

/* Dropdown SQL */
$circle_sql = "SELECT id, name FROM circles WHERE 1=1";
$zone_sql = "SELECT z.id, z.name FROM zones z WHERE 1=1";
$branch_sql = "SELECT b.id, b.name FROM branches b WHERE 1=1";
$officer_sql = "SELECT o.id, o.name FROM officers o WHERE 1=1";

if ($role === 'circle_admin') {
    $circle_sql .= " AND id = $circle_id";
    $zone_sql .= " AND z.circle_id = $circle_id";
    $branch_sql .= " AND b.zone_id IN (SELECT id FROM zones WHERE circle_id = $circle_id)";
    $officer_sql .= " AND o.branch_id IN (
        SELECT b.id FROM branches b
        JOIN zones z ON b.zone_id = z.id
        WHERE z.circle_id = $circle_id
    )";
} elseif ($role === 'zone_admin') {
    $circle_sql .= " AND id IN (SELECT circle_id FROM zones WHERE id = $zone_id)";
    $zone_sql .= " AND z.id = $zone_id";
    $branch_sql .= " AND b.zone_id = $zone_id";
    $officer_sql .= " AND o.branch_id IN (SELECT id FROM branches WHERE zone_id = $zone_id)";
} elseif ($role === 'admin') {
    if ($branch_id > 0) {
        $circle_sql .= " AND id IN (
            SELECT z.circle_id FROM branches b JOIN zones z ON b.zone_id = z.id WHERE b.id = $branch_id
        )";
        $zone_sql .= " AND z.id IN (SELECT zone_id FROM branches WHERE id = $branch_id)";
        $branch_sql .= " AND b.id = $branch_id";
        $officer_sql .= " AND o.branch_id = $branch_id";
    }
} elseif ($role === 'officer') {
    $circle_sql .= " AND id IN (
        SELECT z.circle_id FROM branches b JOIN zones z ON b.zone_id = z.id WHERE b.id = $branch_id
    )";
    $zone_sql .= " AND z.id IN (SELECT zone_id FROM branches WHERE id = $branch_id)";
    $branch_sql .= " AND b.id = $branch_id";
    $officer_sql .= " AND o.id = $officer_id";
}

$circle_sql .= " ORDER BY name";
$zone_sql .= " ORDER BY name";
$branch_sql .= " ORDER BY name";
$officer_sql .= " ORDER BY name";

/* Main customer query */
$sql = "
SELECT 
    c.id,
    c.name,
    c.account_number,
    c.cl_class,
    c.outstanding,
    c.cl_start_balance,
    c.customer_state,
    COALESCE((SELECT SUM(r.amount) FROM recoveries r WHERE r.customer_id = c.id),0) AS total_recovery,
    c.phone,
    c.status,
    c.first_default_date,
    c.last_note,
    c.assigned_officer,
    o.name AS officer_name,
    b.name AS branch_name,
    z.name AS zone_name,
    ci.name AS circle_name,
    (
        SELECT MAX(ct.created_at)
        FROM contacts ct
        WHERE ct.customer_id = c.id
    ) AS last_contact_date
FROM customers c
LEFT JOIN officers o ON c.assigned_officer = o.id
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
LEFT JOIN circles ci ON z.circle_id = ci.id
WHERE $scope_where $filter_where
ORDER BY c.id DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("SQL Error: " . $conn->error . "<br><pre>" . htmlspecialchars($sql) . "</pre>");
}

$total_count = $result->num_rows;
$total_outstanding = 0;
$total_cl_start_balance = 0;
$rows = array();

while ($row = $result->fetch_assoc()) {
    $total_outstanding += floatval($row['outstanding']);
    $total_cl_start_balance += floatval($row['cl_start_balance']);
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>গ্রাহক তালিকা</title>
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
            max-width:1400px;
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
            border-left:5px solid #2563eb;
        }

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

        .card.orange { border-left-color:#f97316; }
        .card.purple { border-left-color:#7c3aed; }

        .box {
            background:#fff;
            padding:20px;
            border-radius:18px;
            box-shadow:0 6px 20px rgba(15,23,42,0.06);
            overflow-x:auto;
        }

        label {
            display:block;
            font-size:13px;
            color:#475569;
            margin-bottom:5px;
            font-weight:bold;
        }

        select, input {
            width:100%;
            padding:9px;
            border:1px solid #cbd5e1;
            border-radius:8px;
            background:#fff;
        }

        table {
            width:100%;
            border-collapse:collapse;
            min-width:1200px;
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

        .muted {
            color:#64748b;
            font-size:13px;
        }

        @media(max-width:700px) {
            .container { padding:14px; }

            .topbar-links a {
                margin-left:0;
                margin-right:6px;
                margin-top:8px;
            }
        }
        .extra-col {
            display: none;
        }
        .show-all-columns .extra-col {
            display: table-cell;
        }
    </style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">গ্রাহক তালিকা</div>
        <div style="font-size:13px; color:#cbd5e1; margin-top:4px;">
            Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="topbar-links">
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <div class="hero">
        <div>
            <h2>Customer List</h2>
            <p>Role অনুযায়ী অনুমোদিত গ্রাহক তালিকা, filter এবং action সহ।</p>
        </div>

        <div>
            <span class="badge">
                <?php
                    if ($role === 'ho_admin') echo "All Branch";
                    elseif ($role === 'circle_admin') echo "Circle Scope";
                    elseif ($role === 'zone_admin') echo "Zone Scope";
                    elseif ($role === 'admin') echo "Branch Scope";
                    else echo "Officer Scope";
                ?>
            </span>
        
            <?php if ($role !== 'officer' || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'free')): ?>
                <a class="btn btn-green" href="add_customer.php">নতুন গ্রাহক</a>
            <?php endif; ?>
        </div>
        
    </div>

    <div class="box" style="margin-bottom:18px;">
        <form method="GET" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; align-items:end;">

            <div>
                <label>Search</label>
                <input type="text" name="search" placeholder="নাম / হিসাব / মোবাইল / শ্রেণি / স্ট্যাটাস" value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div>
                <label>Circle</label>
                <select name="circle_id">
                    <option value="0">All Circle</option>
                    <?php
                    $circles = $conn->query($circle_sql);
                    while ($c = $circles->fetch_assoc()) {
                        $selected = ($filter_circle == $c['id']) ? 'selected' : '';
                        echo "<option value='{$c['id']}' $selected>" . htmlspecialchars($c['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label>Zone</label>
                <select name="zone_id">
                    <option value="0">All Zone</option>
                    <?php
                    $zones = $conn->query($zone_sql);
                    while ($z = $zones->fetch_assoc()) {
                        $selected = ($filter_zone == $z['id']) ? 'selected' : '';
                        echo "<option value='{$z['id']}' $selected>" . htmlspecialchars($z['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label>Branch</label>
                <select name="branch_id">
                    <option value="0">All Branch</option>
                    <?php
                    $branches = $conn->query($branch_sql);
                    while ($b = $branches->fetch_assoc()) {
                        $selected = ($filter_branch == $b['id']) ? 'selected' : '';
                        echo "<option value='{$b['id']}' $selected>" . htmlspecialchars($b['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label>Customer State</label>
                <select name="customer_state">
                    <option value="">All</option>
                    <?php foreach($allowed_customer_states as $state) { $selected = ($filter_customer_state === $state) ? "selected" : ""; echo "<option value=\"" . htmlspecialchars($state) . "\" $selected>" . htmlspecialchars($state) . "</option>"; } ?>
                </select>
            </div>

            <div>
                <label>Officer</label>
                <select name="officer_id">
                    <option value="0">All Officer</option>
                    <?php
                    $officers = $conn->query($officer_sql);
                    while ($o = $officers->fetch_assoc()) {
                        $selected = ($filter_officer == $o['id']) ? 'selected' : '';
                        echo "<option value='{$o['id']}' $selected>" . htmlspecialchars($o['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <button class="btn" type="submit">Filter</button>
                <a class="btn btn-orange" href="customers.php">Reset</a>
            </div>

        </form>
    </div>

    <div class="cards">
        <div class="card">
            <span>মোট গ্রাহক</span>
            <strong><?php echo $total_count; ?></strong>
        </div>

        <div class="card orange">
            <span>মোট বকেয়া</span>
            <strong><?php echo number_format($total_outstanding, 2); ?></strong>
            <div class="muted">of <?php echo number_format($total_cl_start_balance, 2); ?><?php echo ($total_cl_start_balance > 0) ? " যা প্রায় " . number_format(($total_outstanding / $total_cl_start_balance) * 100, 2) . "%" : ""; ?></div>
        </div>

        <div class="card purple">
            <span>Scope</span>
            <strong><?php echo htmlspecialchars($role); ?></strong>
        </div>
    </div>

    <div class="box">
        <button type="button" id="toggleColumnsBtn" class="btn btn-sm btn-primary mb-2">
            View All Columns
        </button>
        <?php if ($total_count > 0) { ?>
        <div id="customerTableWrapper">
        <table class="table table-bordered table-striped">
        <table>
            <tr>
                <th>গ্রাহক</th>
                <th>হিসাব নম্বর</th>
                <th class="extra-col">Branch</th>
                <th class="extra-col">Zone</th>
                <th class="extra-col">Circle</th>
                <th>Officer</th>
                <th>শ্রেণি</th>
                <th>Customer State</th>
                <th class="extra-col">CL Start Balance</th>
                <th>মোট আদায়</th>
                <th>বকেয়া</th>
                <th class="extra-col">Audit</th>
                <th>মোবাইল</th>
                <th>Status</th>
                <th>Last Contact</th>
                <th>Action</th>
            </tr>

            <?php foreach ($rows as $row) { ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                    <span class="muted"><?php echo htmlspecialchars($row['last_note'] ?? ''); ?></span>
                </td>

                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                <td class="extra-col"><?php echo htmlspecialchars($row['branch_name'] ?? ''); ?></td>
                <td class="extra-col"><?php echo htmlspecialchars($row['zone_name'] ?? ''); ?></td>
                <td class="extra-col"><?php echo htmlspecialchars($row['circle_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['officer_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['cl_class']); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars($row['customer_state'] ?? 'Old CL'); ?></span></td>
                <td class="extra-col"><?php echo number_format($row['cl_start_balance'] ?? 0, 2); ?></td>
                <td><?php echo number_format($row['total_recovery'] ?? 0, 2); ?></td>
                <td><strong><?php echo number_format($row['outstanding'], 2); ?></strong></td>
                <td class="extra-col"><?php $audit_diff = abs(floatval($row['cl_start_balance'] ?? 0) - (floatval($row['outstanding']) + floatval($row['total_recovery'] ?? 0))); echo ($audit_diff < 0.01) ? '<span class="badge" style="background:#dcfce7;color:#166534;">OK</span>' : '<span class="badge" style="background:#fee2e2;color:#991b1b;">Mismatch</span>'; ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['last_contact_date'] ?? ''); ?></td>

                <td>
                    <a class="btn" href="customer_view.php?id=<?php echo intval($row['id']); ?>">View</a>

                    <?php if ($role !== 'officer') { ?>
                        <a class="btn btn-gray" href="customer_edit.php?id=<?php echo intval($row['id']); ?>">Edit</a>
                    <?php } ?>

                    <a class="btn btn-orange" href="add_contact.php?customer_id=<?php echo intval($row['id']); ?>&officer_id=<?php echo intval($row['assigned_officer'] ?? 0); ?>">Follow-up</a>

                    <a class="btn btn-green" href="add_recovery.php?customer_id=<?php echo intval($row['id']); ?>&officer_id=<?php echo intval($row['assigned_officer'] ?? 0); ?>">Recovery</a>
                </td>
            </tr>
            <?php } ?>
        </table>
        <div id="customerTableWrapper">
    <table class="table table-bordered table-striped">

            </table>
        </div>
        <?php } else { ?>
            <p class="muted">এই scope-এ কোনো গ্রাহক পাওয়া যায়নি।</p>
        <?php } ?>
    </div>

</div>
<script>
document.getElementById('toggleColumnsBtn').addEventListener('click', function () {
    const tableWrapper = document.getElementById('customerTableWrapper');

    tableWrapper.classList.toggle('show-all-columns');

    if (tableWrapper.classList.contains('show-all-columns')) {
        this.textContent = 'Hide Extra Columns';
    } else {
        this.textContent = 'View All Columns';
    }
});
</script>
</body>
</html>