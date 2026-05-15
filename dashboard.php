<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$circle_id = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

$is_admin = in_array($role, array('admin', 'ho_admin', 'circle_admin', 'zone_admin'));

function lr_table_exists($conn, $table) {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$safe'");
    return ($res && $res->num_rows > 0);
}

function lr_no_contact_condition($days) {
    $days = intval($days);
    if ($days < 1) $days = 7;

    return "
    (
        (SELECT MAX(ct.created_at) FROM contacts ct WHERE ct.customer_id = c.id) IS NULL
        OR DATE((SELECT MAX(ct.created_at) FROM contacts ct WHERE ct.customer_id = c.id)) < DATE_SUB(CURDATE(), INTERVAL $days DAY)
    )
    ";
}

$total_customers = 0;
$total_officers = 0;
$total_outstanding = 0;
$total_recovery = 0;
$due_followups = 0;
$today_recovery = 0;
$no_recovery_count = 0;
$no_contact_count = 0;
$promise_pending_count = 0;
$unread_message_count = 0;
$latest_unread_message = null;
$total_cl_start_balance = 0;
$new_cl_count = 0;
$standard_risky_count = 0;

/* =========================
   Scope
========================= */
$scope_join = "";
$scope_where = "1=1";

if ($role === 'ho_admin') {
    $scope_join = "";
    $scope_where = "1=1";
} elseif ($role === 'circle_admin') {
    $scope_join = "
        LEFT JOIN branches b ON c.branch_id = b.id
        LEFT JOIN zones z ON b.zone_id = z.id
    ";
    $scope_where = "z.circle_id = $circle_id";
} elseif ($role === 'zone_admin') {
    $scope_join = "LEFT JOIN branches b ON c.branch_id = b.id";
    $scope_where = "b.zone_id = $zone_id";
} elseif ($role === 'admin') {
    $scope_join = "";
    $scope_where = "c.branch_id = $branch_id";
} else {
    $scope_join = "";
    $scope_where = "c.branch_id = $branch_id AND c.assigned_officer = $officer_id";
}

/* =========================
   Counts
========================= */
$res1 = $conn->query("SELECT COUNT(*) AS total FROM customers c $scope_join WHERE $scope_where");
$total_customers = $res1 ? intval($res1->fetch_assoc()['total']) : 0;

if ($role === 'ho_admin') {
    $res2 = $conn->query("SELECT COUNT(*) AS total FROM officers");
} elseif ($role === 'circle_admin') {
    $res2 = $conn->query("
        SELECT COUNT(*) AS total
        FROM officers o
        INNER JOIN branches b ON o.branch_id = b.id
        INNER JOIN zones z ON b.zone_id = z.id
        WHERE z.circle_id = $circle_id
    ");
} elseif ($role === 'zone_admin') {
    $res2 = $conn->query("
        SELECT COUNT(*) AS total
        FROM officers o
        INNER JOIN branches b ON o.branch_id = b.id
        WHERE b.zone_id = $zone_id
    ");
} elseif ($role === 'admin') {
    $res2 = $conn->query("SELECT COUNT(*) AS total FROM officers WHERE branch_id = $branch_id");
} else {
    $res2 = false;
}
$total_officers = $res2 ? intval($res2->fetch_assoc()['total']) : 0;

$res3 = $conn->query("
    SELECT COALESCE(SUM(c.outstanding),0) AS total
    FROM customers c
    $scope_join
    WHERE $scope_where
    AND c.customer_state = 'Old CL'
");
$total_outstanding = $res3 ? floatval($res3->fetch_assoc()['total']) : 0;

$res3b = $conn->query("
    SELECT COALESCE(SUM(c.cl_start_balance),0) AS total
    FROM customers c
    $scope_join
    WHERE $scope_where
    AND c.customer_state = 'Old CL'
");
$total_cl_start_balance = $res3b ? floatval($res3b->fetch_assoc()['total']) : 0;

$outstanding_percent = $total_cl_start_balance > 0
    ? round(($total_outstanding / $total_cl_start_balance) * 100, 2)
    : 0;

$res3c = $conn->query("SELECT COUNT(*) AS total FROM customers c $scope_join WHERE $scope_where AND c.customer_state = 'New CL'");
$new_cl_count = $res3c ? intval($res3c->fetch_assoc()['total']) : 0;

$res3d = $conn->query("SELECT COUNT(*) AS total FROM customers c $scope_join WHERE $scope_where AND c.customer_state = 'Standard Risky'");
$standard_risky_count = $res3d ? intval($res3d->fetch_assoc()['total']) : 0;

$res4 = $conn->query("
    SELECT COALESCE(SUM(r.amount),0) AS total
    FROM recoveries r
    INNER JOIN customers c ON r.customer_id = c.id
    $scope_join
    WHERE $scope_where
");
$total_recovery = $res4 ? floatval($res4->fetch_assoc()['total']) : 0;

$res5 = $conn->query("
    SELECT COUNT(*) AS total
    FROM contacts ct
    INNER JOIN customers c ON ct.customer_id = c.id
    $scope_join
    WHERE $scope_where
    AND ct.next_followup IS NOT NULL
    AND ct.next_followup <= CURDATE()
");
$due_followups = $res5 ? intval($res5->fetch_assoc()['total']) : 0;

$res6 = $conn->query("
    SELECT COALESCE(SUM(r.amount),0) AS total
    FROM recoveries r
    INNER JOIN customers c ON r.customer_id = c.id
    $scope_join
    WHERE $scope_where
    AND r.recovery_date = CURDATE()
");
$today_recovery = $res6 ? floatval($res6->fetch_assoc()['total']) : 0;

$res7 = $conn->query("
    SELECT c.id
    FROM customers c
    $scope_join
    LEFT JOIN recoveries r ON c.id = r.customer_id
    WHERE $scope_where
    GROUP BY c.id
    HAVING COALESCE(SUM(r.amount),0) = 0
");
$no_recovery_count = $res7 ? $res7->num_rows : 0;

$no_contact_cond = lr_no_contact_condition(7);
$res8 = $conn->query("
    SELECT c.id
    FROM customers c
    $scope_join
    WHERE $scope_where
    AND $no_contact_cond
");
$no_contact_count = $res8 ? $res8->num_rows : 0;

$res9 = $conn->query("
    SELECT COUNT(*) AS total
    FROM contacts ct
    INNER JOIN customers c ON ct.customer_id = c.id
    $scope_join
    WHERE $scope_where
    AND ct.action_result = 'Promise to Pay'
    AND NOT EXISTS (
        SELECT 1 FROM recoveries r
        WHERE r.customer_id = ct.customer_id
        AND r.recovery_date >= DATE(ct.created_at)
    )
");
$promise_pending_count = $res9 ? intval($res9->fetch_assoc()['total']) : 0;

/* =========================
   Messages
========================= */
if (lr_table_exists($conn, 'messages') && lr_table_exists($conn, 'message_reads')) {
    $message_scope = "
        (
            m.recipient_type = 'all'
            OR m.recipient_type = '$role'
            OR (m.recipient_type = 'specific' AND m.recipient_user_id = $user_id)
        )
    ";

    if ($branch_id > 0) {
        $message_scope .= " AND (m.branch_id = $branch_id OR m.branch_id = 0)";
    }

    $msg_res = $conn->query("
        SELECT m.*
        FROM messages m
        LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = $user_id
        WHERE mr.id IS NULL
        AND $message_scope
        ORDER BY m.id DESC
        LIMIT 1
    ");
    $latest_unread_message = ($msg_res && $msg_res->num_rows > 0) ? $msg_res->fetch_assoc() : null;

    $msg_count = $conn->query("
        SELECT COUNT(*) AS total
        FROM messages m
        LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = $user_id
        WHERE mr.id IS NULL
        AND $message_scope
    ");
    $unread_message_count = $msg_count ? intval($msg_count->fetch_assoc()['total']) : 0;
}

/* =========================
   CL Date wise table
========================= */
$scope_join_cl_date = "";
$scope_where_cl_date = "1=1";

if ($role === 'ho_admin') {
    $scope_join_cl_date = "";
    $scope_where_cl_date = "1=1";
} elseif ($role === 'circle_admin') {
    $scope_join_cl_date = "
        LEFT JOIN branches b ON c.branch_id = b.id
        LEFT JOIN zones z ON b.zone_id = z.id
    ";
    $scope_where_cl_date = "z.circle_id = $circle_id";
} elseif ($role === 'zone_admin') {
    $scope_join_cl_date = "LEFT JOIN branches b ON c.branch_id = b.id";
    $scope_where_cl_date = "b.zone_id = $zone_id";
} elseif ($role === 'admin') {
    $scope_join_cl_date = "";
    $scope_where_cl_date = "c.branch_id = $branch_id";
} else {
    $scope_join_cl_date = "";
    $scope_where_cl_date = "c.branch_id = $branch_id AND c.assigned_officer = $officer_id";
}

$sql = "
    SELECT 
        c.first_default_date AS cl_date,
        COUNT(c.id) AS customer_count,
        COALESCE(SUM(c.cl_start_balance),0) AS cl_amount,
        COALESCE(SUM(c.outstanding),0) AS outstanding
    FROM customers c
    $scope_join_cl_date
    WHERE $scope_where_cl_date
    AND c.customer_state IN ('Old CL', 'New CL')
    GROUP BY c.first_default_date
    ORDER BY 
        CASE 
            WHEN c.first_default_date IS NULL 
              OR c.first_default_date = '' 
              OR c.first_default_date = '0000-00-00'
            THEN 1 ELSE 0 
        END,
        c.first_default_date DESC
";
$result = $conn->query($sql);

/* =========================
   New CL outstanding Total
========================= */
$res_new_cl_out = $conn->query("
    SELECT COALESCE(SUM(c.outstanding),0) AS total
    FROM customers c
    $scope_join
    WHERE $scope_where
    AND c.customer_state = 'New CL'
");
$new_cl_outstanding = $res_new_cl_out ? floatval($res_new_cl_out->fetch_assoc()['total']) : 0;

$recovery_rate = ($total_recovery + $total_outstanding) > 0
    ? round(($total_recovery / ($total_recovery + $total_outstanding)) * 100, 2)
    : 0;

$urgent_tasks = $due_followups + $no_contact_count + $promise_pending_count;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>ড্যাশবোর্ড - ঋণ আদায় ব্যবস্থাপনা</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
:root {
    --bg:#f4f7fb;
    --surface:#ffffff;
    --surface-soft:#f8fafc;
    --ink:#0f172a;
    --muted:#64748b;
    --line:#e2e8f0;
    --blue:#2563eb;
    --blue-soft:#dbeafe;
    --green:#16a34a;
    --green-soft:#dcfce7;
    --red:#dc2626;
    --red-soft:#fee2e2;
    --amber:#f59e0b;
    --amber-soft:#fef3c7;
    --violet:#7c3aed;
    --violet-soft:#ede9fe;
    --shadow:0 20px 45px rgba(15,23,42,.08);
    --shadow-soft:0 10px 26px rgba(15,23,42,.06);
}

* { box-sizing:border-box; }

body {
    margin:0;
    font-family:Arial, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(37,99,235,.13), transparent 34%),
        linear-gradient(180deg,#f8fbff 0%, var(--bg) 48%, #eef3f9 100%);
    color:var(--ink);
}

a { color:inherit; }

.topbar {
    position:sticky;
    top:0;
    z-index:50;
    background:rgba(255,255,255,.86);
    backdrop-filter:blur(18px);
    border-bottom:1px solid rgba(226,232,240,.8);
    padding:14px 22px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:12px;
}

.brand-wrap {
    display:flex;
    align-items:center;
    gap:12px;
}

.logo-mark {
    width:44px;
    height:44px;
    border-radius:16px;
    display:grid;
    place-items:center;
    color:#fff;
    font-weight:800;
    background:linear-gradient(135deg,#0f172a,#2563eb);
    box-shadow:0 12px 26px rgba(37,99,235,.25);
}

.brand {
    font-size:19px;
    font-weight:800;
    letter-spacing:-.2px;
}

.top-sub {
    font-size:13px;
    color:var(--muted);
    margin-top:3px;
}

.top-actions {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.top-actions a {
    text-decoration:none;
    padding:9px 13px;
    background:#fff;
    border:1px solid var(--line);
    border-radius:999px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:14px;
    color:#334155;
    box-shadow:0 6px 18px rgba(15,23,42,.04);
}

.container {
    max-width:1480px;
    margin:auto;
    padding:24px;
}

.hero {
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 54%,#2563eb 100%);
    color:#fff;
    border-radius:30px;
    padding:28px;
    margin-bottom:18px;
    box-shadow:var(--shadow);
    display:grid;
    grid-template-columns:1.3fr .7fr;
    gap:24px;
}

.hero:after {
    content:"";
    position:absolute;
    width:360px;
    height:360px;
    border-radius:999px;
    right:-110px;
    top:-150px;
    background:rgba(255,255,255,.11);
}

.hero h2 {
    margin:0 0 8px;
    font-size:32px;
    letter-spacing:-.8px;
}

.hero p {
    margin:0;
    color:#dbeafe;
    line-height:1.55;
}

.hero-actions {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
}

.hero-actions a,
.hero-badge {
    text-decoration:none;
    border-radius:999px;
    padding:10px 14px;
    font-size:14px;
    font-weight:700;
}

.hero-actions a {
    background:rgba(255,255,255,.13);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
}

.hero-badge {
    align-self:start;
    justify-self:end;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.24);
    color:#fff;
}

.hero-kpis {
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

.hero-kpi {
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);
    border-radius:20px;
    padding:15px;
}

.hero-kpi span {
    display:block;
    color:#dbeafe;
    font-size:13px;
    margin-bottom:8px;
}

.hero-kpi strong {
    font-size:23px;
}

/* Overview strip */
.overview-strip {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:18px;
}

.metric-tile {
    background:rgba(255,255,255,.88);
    border:1px solid rgba(226,232,240,.9);
    border-radius:24px;
    padding:18px;
    box-shadow:var(--shadow-soft);
    display:flex;
    gap:14px;
    align-items:center;
}

.metric-icon {
    width:48px;
    height:48px;
    border-radius:17px;
    display:grid;
    place-items:center;
    font-size:22px;
    flex:0 0 auto;
}

.metric-blue { background:var(--blue-soft); color:var(--blue); }
.metric-red { background:var(--red-soft); color:var(--red); }
.metric-green { background:var(--green-soft); color:var(--green); }
.metric-amber { background:var(--amber-soft); color:#b45309; }
.metric-violet { background:var(--violet-soft); color:var(--violet); }

.metric-tile span {
    color:var(--muted);
    font-size:13px;
}

.metric-tile strong {
    display:block;
    margin-top:4px;
    font-size:22px;
    letter-spacing:-.4px;
}

/* Dashboard cards */
.cards {
    display:grid;
    grid-template-columns:repeat(12,1fr);
    gap:16px;
    margin-bottom:22px;
}

.card-link {
    text-decoration:none;
    color:inherit;
    grid-column:span 3;
}

.card-link.wide {
    grid-column:span 6;
}

.card {
    min-height:158px;
    height:100%;
    border-radius:26px;
    padding:19px;
    position:relative;
    overflow:hidden;
    background:#fff;
    border:1px solid rgba(226,232,240,.9);
    box-shadow:var(--shadow-soft);
    transition:.22s ease;
}

.card:hover {
    transform:translateY(-4px);
    box-shadow:0 20px 42px rgba(15,23,42,.1);
    border-color:#bfdbfe;
}

.card-top {
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    margin-bottom:14px;
}

.card-title {
    color:#475569;
    font-size:14px;
    font-weight:700;
}

.card-value {
    margin:0;
    color:#0f172a;
    font-size:28px;
    font-weight:900;
    letter-spacing:-.8px;
}

.card-note {
    display:block;
    margin-top:9px;
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.pill {
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}

.pill-blue { background:var(--blue-soft); color:#1d4ed8; }
.pill-red { background:var(--red-soft); color:#b91c1c; }
.pill-green { background:var(--green-soft); color:#15803d; }
.pill-amber { background:var(--amber-soft); color:#b45309; }
.pill-violet { background:var(--violet-soft); color:#6d28d9; }
.pill-dark { background:#e2e8f0; color:#334155; }

.card-visual {
    margin-top:16px;
    height:8px;
    border-radius:999px;
    background:#e5e7eb;
    overflow:hidden;
}

.card-visual span {
    display:block;
    height:100%;
    border-radius:999px;
}

.fill-blue { background:linear-gradient(90deg,#2563eb,#60a5fa); }
.fill-red { background:linear-gradient(90deg,#dc2626,#fb7185); }
.fill-green { background:linear-gradient(90deg,#16a34a,#86efac); }
.fill-amber { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.fill-violet { background:linear-gradient(90deg,#7c3aed,#c4b5fd); }

.card-split {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    margin-top:14px;
}

.mini-box {
    background:var(--surface-soft);
    border:1px solid var(--line);
    border-radius:18px;
    padding:12px;
}

.mini-box span {
    color:var(--muted);
    font-size:12px;
}

.mini-box strong {
    display:block;
    margin-top:5px;
    font-size:16px;
}

/* Layout */
.grid {
    display:grid;
    grid-template-columns:1.25fr .75fr;
    gap:18px;
}

.panel {
    background:rgba(255,255,255,.92);
    border:1px solid rgba(226,232,240,.9);
    border-radius:26px;
    padding:22px;
    box-shadow:var(--shadow-soft);
    margin-bottom:18px;
}

.panel h3 {
    margin:0 0 6px;
    font-size:21px;
    letter-spacing:-.4px;
}

.panel-note {
    margin:0 0 18px;
    color:var(--muted);
    font-size:14px;
    line-height:1.5;
}

.action-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(118px,1fr));
    gap:10px;
}

.action-card {
    position:relative;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:flex-start;
    min-height:74px;
    text-decoration:none;
    background:linear-gradient(180deg,#fff,#f8fafc);
    border:1px solid var(--line);
    border-radius:18px;
    padding:13px;
    transition:.2s ease;
}

.action-card:hover {
    transform:translateY(-3px);
    border-color:#93c5fd;
    box-shadow:0 15px 28px rgba(37,99,235,.12);
}

.action-card strong {
    display:block;
    font-size:13px;
    margin-bottom:0;
    line-height:1.35;
}

.action-card span {
    display:none;
}

.action-card:before {
    content:"•";
    width:24px;
    height:24px;
    border-radius:9px;
    background:#dbeafe;
    color:#2563eb;
    display:grid;
    place-items:center;
    font-weight:900;
    margin-bottom:8px;
}

.action-arrow {
    display:none;
}

.quick-stack {
    display:grid;
    gap:11px;
}

.quick-item {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    padding:14px;
    border:1px solid var(--line);
    border-radius:17px;
    background:#fff;
}

.quick-item span {
    color:var(--muted);
    font-size:14px;
}

.quick-item strong {
    font-size:16px;
}

.quick-item.danger { background:linear-gradient(90deg,#fff,#fff1f2); border-color:#fecdd3; }
.quick-item.warning { background:linear-gradient(90deg,#fff,#fffbeb); border-color:#fde68a; }
.quick-item.success { background:linear-gradient(90deg,#fff,#f0fdf4); border-color:#bbf7d0; }

.report-drawer {
    margin-top:10px;
}

summary {
    cursor:pointer;
    padding:13px 15px;
    border-radius:16px;
    background:#f8fafc;
    border:1px solid var(--line);
    font-weight:800;
    color:#334155;
}

.report-links {
    margin-top:13px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:10px;
}

.report-links a {
    display:block;
    padding:11px 13px;
    border-radius:14px;
    text-decoration:none;
    background:#eef2ff;
    color:#3730a3;
    font-size:14px;
    font-weight:700;
}

.table-wrap {
    overflow-x:auto;
    margin-top:14px;
    border:1px solid var(--line);
    border-radius:20px;
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:820px;
    background:#fff;
}

thead tr {
    background:#f8fafc;
}

th {
    padding:13px 12px;
    text-align:left;
    color:#475569;
    font-size:13px;
    border-bottom:1px solid var(--line);
}

td {
    padding:12px;
    border-bottom:1px solid #eef2f7;
    font-size:14px;
}

tbody tr:hover {
    background:#f8fbff;
}

.table-action {
    background:#2563eb;
    color:#fff;
    padding:7px 11px;
    border-radius:999px;
    text-decoration:none;
    font-size:13px;
    font-weight:800;
}

.table-action.danger {
    background:#dc2626;
}

/* Popup */
.popup-overlay {
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.55);
    backdrop-filter:blur(4px);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:15px;
}

.popup-box {
    background:#fff;
    width:100%;
    max-width:540px;
    border-radius:26px;
    padding:25px;
    box-shadow:0 24px 60px rgba(0,0,0,.25);
}

.popup-actions {
    margin-top:20px;
    text-align:right;
}

.popup-actions a,
.popup-actions button {
    display:inline-block;
    padding:11px 15px;
    margin-left:8px;
    border:0;
    border-radius:999px;
    text-decoration:none;
    cursor:pointer;
    font-weight:800;
}

.popup-open { background:#2563eb; color:#fff; }
.popup-close { background:#e2e8f0; color:#334155; }

@media(max-width:1180px) {
    .overview-strip { grid-template-columns:repeat(2,1fr); }
    .card-link,
    .card-link.wide { grid-column:span 6; }
    .hero { grid-template-columns:1fr; }
    .hero-badge { justify-self:start; }
}

@media(max-width:850px) {
    .container { padding:14px; }
    .grid { grid-template-columns:1fr; }
    .overview-strip { grid-template-columns:1fr; }
    .card-link,
    .card-link.wide { grid-column:span 12; }
    .hero { border-radius:24px; padding:22px; }
    .hero h2 { font-size:27px; }
    .topbar { position:relative; }
}
</style>
</head>

<body>

<?php if ($unread_message_count > 0 && $latest_unread_message) { ?>
<div class="popup-overlay" id="messagePopup">
    <div class="popup-box">
        <h3>নতুন বার্তা আছে</h3>
        <p>আপনার মোট <strong><?php echo $unread_message_count; ?></strong> টি অপঠিত বার্তা আছে।</p>
        <p><strong>সর্বশেষ বার্তা:</strong><br><?php echo htmlspecialchars($latest_unread_message['subject']); ?></p>

        <div class="popup-actions">
            <button class="popup-close" onclick="document.getElementById('messagePopup').style.display='none'">পরে দেখবো</button>
            <a class="popup-open" href="message_view.php?id=<?php echo intval($latest_unread_message['id']); ?>">বার্তা খুলুন</a>
        </div>
    </div>
</div>
<?php } ?>

<div class="topbar">
    <div class="brand-wrap">
        <div class="logo-mark">ঋ</div>
        <div>
            <div class="brand">ঋণ আদায় ব্যবস্থাপনা</div>
            <div class="top-sub">
                স্বাগতম, <?php echo htmlspecialchars($_SESSION['name']); ?> —
                <?php echo htmlspecialchars($role); ?>
            </div>
        </div>
    </div>

    <div class="top-actions">
        <a href="my_messages.php">বার্তা <?php if($unread_message_count > 0) echo '(' . $unread_message_count . ')'; ?></a>
        <a href="change_password.php">পাসওয়ার্ড</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <section class="hero">
        <div>
            <div class="hero-badge"><?php echo $is_admin ? 'Admin Monitoring View' : 'Officer Daily View'; ?></div>
            <h2>Loan Recovery Command Center</h2>
            <p>আজকের আদায়, ঝুঁকিপূর্ণ গ্রাহক, বকেয়া ফলো-আপ এবং CL performance এক জায়গায় দেখুন।</p>
            <div class="hero-actions">
                <a href="due_followups.php">আজকের Follow-up</a>
                <a href="report_no_contact.php">No Contact List</a>
                <a href="report_recovery.php">Recovery Report</a>
            </div>
        </div>

        <div class="hero-kpis">
            <div class="hero-kpi">
                <span>মোট বকেয়া</span>
                <strong>৳ <?php echo number_format($total_outstanding, 2); ?></strong>
            </div>
            <div class="hero-kpi">
                <span>আজকের আদায়</span>
                <strong>৳ <?php echo number_format($today_recovery, 2); ?></strong>
            </div>
            <div class="hero-kpi">
                <span>Urgent Task</span>
                <strong><?php echo number_format($urgent_tasks); ?></strong>
            </div>
            <div class="hero-kpi">
                <span>Recovery Rate</span>
                <strong><?php echo number_format($recovery_rate, 2); ?>%</strong>
            </div>
        </div>
    </section>

    <section class="overview-strip">
        <a class="metric-tile" href="customers.php" style="text-decoration:none;">
            <div class="metric-icon metric-blue">👥</div>
            <div><span>মোট গ্রাহক</span><strong><?php echo number_format($total_customers); ?></strong></div>
        </a>

        <a class="metric-tile" href="report_top_defaulters.php" style="text-decoration:none;">
            <div class="metric-icon metric-red">৳</div>
            <div><span>Outstanding Ratio</span><strong><?php echo number_format($outstanding_percent, 2); ?>%</strong></div>
        </a>

        <a class="metric-tile" href="report_recovery.php" style="text-decoration:none;">
            <div class="metric-icon metric-green">↗</div>
            <div><span>মোট আদায়</span><strong>৳ <?php echo number_format($total_recovery, 2); ?></strong></div>
        </a>

        <a class="metric-tile" href="due_followups.php" style="text-decoration:none;">
            <div class="metric-icon metric-amber">⏱</div>
            <div><span>বকেয়া ফলো-আপ</span><strong><?php echo number_format($due_followups); ?></strong></div>
        </a>
    </section>

    <!-- Dashboard Cards -->
    <section class="cards">

        <a class="card-link wide" href="report_top_defaulters.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">মোট বকেয়া / Old CL Outstanding</div>
                    <div class="pill pill-red">Risk Monitor</div>
                </div>
                <h3 class="card-value">৳ <?php echo number_format($total_outstanding, 2); ?></h3>
                <small class="card-note">
                    Total CL ৳ <?php echo number_format($total_cl_start_balance, 2); ?> এর মধ্যে প্রায় <?php echo number_format($outstanding_percent, 2); ?>% এখনো outstanding।
                </small>
                <div class="card-visual"><span class="fill-red" style="width:<?php echo min(100, $outstanding_percent); ?>%"></span></div>
            </div>
        </a>

        <a class="card-link wide" href="new_cl_customers.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">নতুন CL Customer</div>
                    <div class="pill pill-violet">New Risk</div>
                </div>
                <h3 class="card-value"><?php echo number_format($new_cl_count); ?></h3>
                <div class="card-split">
                    <div class="mini-box">
                        <span>Outstanding</span>
                        <strong>৳ <?php echo number_format($new_cl_outstanding, 2); ?></strong>
                    </div>
                    <div class="mini-box">
                        <span>Action</span>
                        <strong>Review Now</strong>
                    </div>
                </div>
            </div>
        </a>

        <?php if ($is_admin) { ?>
        <a class="card-link" href="officers.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">মোট অফিসার</div>
                    <div class="pill pill-blue">Team</div>
                </div>
                <h3 class="card-value"><?php echo number_format($total_officers); ?></h3>
                <small class="card-note">Officer performance and assignment tracking.</small>
                <div class="card-visual"><span class="fill-blue" style="width:74%"></span></div>
            </div>
        </a>
        <?php } ?>

        <a class="card-link" href="standard_risky_customers.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">Standard Risky</div>
                    <div class="pill pill-amber">Watchlist</div>
                </div>
                <h3 class="card-value"><?php echo number_format($standard_risky_count); ?></h3>
                <small class="card-note">Risky standard customers under monitoring.</small>
                <div class="card-visual"><span class="fill-amber" style="width:58%"></span></div>
            </div>
        </a>

        <a class="card-link" href="report_recovery.php?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">আজকের আদায়</div>
                    <div class="pill pill-green">Today</div>
                </div>
                <h3 class="card-value">৳ <?php echo number_format($today_recovery, 2); ?></h3>
                <small class="card-note">আজকের recovery collection summary.</small>
                <div class="card-visual"><span class="fill-green" style="width:66%"></span></div>
            </div>
        </a>

        <a class="card-link" href="report_no_recovery.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">কোনো আদায় হয়নি</div>
                    <div class="pill pill-red">Priority</div>
                </div>
                <h3 class="card-value"><?php echo number_format($no_recovery_count); ?></h3>
                <small class="card-note">Immediate follow-up required.</small>
                <div class="card-visual"><span class="fill-red" style="width:82%"></span></div>
            </div>
        </a>

        <a class="card-link" href="report_no_contact.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">৭+ দিন যোগাযোগ নেই</div>
                    <div class="pill pill-amber">Gap</div>
                </div>
                <h3 class="card-value"><?php echo number_format($no_contact_count); ?></h3>
                <small class="card-note">যোগাযোগ gap দ্রুত close করুন।</small>
                <div class="card-visual"><span class="fill-amber" style="width:72%"></span></div>
            </div>
        </a>

        <a class="card-link" href="report_promise_to_pay.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">অপেক্ষমাণ প্রতিশ্রুতি</div>
                    <div class="pill pill-amber">Promise</div>
                </div>
                <h3 class="card-value"><?php echo number_format($promise_pending_count); ?></h3>
                <small class="card-note">Promise to Pay follow-up pending.</small>
                <div class="card-visual"><span class="fill-violet" style="width:64%"></span></div>
            </div>
        </a>

        <a class="card-link" href="my_messages.php">
            <div class="card">
                <div class="card-top">
                    <div class="card-title">অপঠিত বার্তা</div>
                    <div class="pill pill-dark">Inbox</div>
                </div>
                <h3 class="card-value"><?php echo number_format($unread_message_count); ?></h3>
                <small class="card-note">নতুন বার্তা ও নির্দেশনা দেখুন।</small>
                <div class="card-visual"><span class="fill-blue" style="width:45%"></span></div>
            </div>
        </a>

    </section>

    <div class="grid">
        <div>
            <div class="panel">
                <h3>প্রধান অপারেশন</h3>
                <p class="panel-note">সবচেয়ে জরুরি মেনুগুলো।</p>

                <div class="report-links">
                    <?php if ($role === 'officer') { ?>
                        <a href="customers.php">আমার গ্রাহক</a>
                        <a href="add_contact.php">যোগাযোগ যোগ</a>
                        <a href="add_recovery.php">রিকভারি যোগ</a>
                        <a href="my_messages.php">আমার বার্তা</a>
                
                    <?php } elseif ($role === 'admin') { ?>
                        <a href="customers.php">গ্রাহক ব্যবস্থাপনা</a>
                        <a href="add_customer.php">নতুন গ্রাহক</a>
                        <a href="officers.php">অফিসার ব্যবস্থাপনা</a>
                        <a href="add_officer.php">নতুন অফিসার</a>
                        <a href="send_message.php">বার্তা পাঠান</a>
                        <a href="my_messages.php">আমার বার্তা</a>
                
                    <?php } elseif ($role === 'zone_admin' || $role === 'circle_admin') { ?>
                        <a href="customers.php">গ্রাহক ব্যবস্থাপনা</a>
                        <a href="officers.php">অফিসার ব্যবস্থাপনা</a>
                        <a href="add_officer.php">Officer/User Add</a>
                        <a href="branches.php">শাখা ব্যবস্থাপনা</a>
                        <a href="import_customers.php">গ্রাহক Import</a>
                        <a href="send_message.php">বার্তা পাঠান</a>
                        <a href="my_messages.php">আমার বার্তা</a>
                
                    <?php } else { ?>
                        <a href="report_zone_dashboard.php">Zone/Circle Dashboard</a>
                        <a href="customers.php">গ্রাহক ব্যবস্থাপনা</a>
                        <a href="officers.php">Officer</a>
                        <a href="circles.php">সার্কেল</a>
                        <a href="zones.php">জোন</a>
                        <a href="branches.php">শাখা</a>
                        <a href="import_customers.php">গ্রাহক Import</a>
                        <a href="message_templates.php">মেসেজ টেমপ্লেট</a>
                        <a href="send_message.php">Messaging</a>
                        <a href="backup.php">Backup</a>
                        <a href="activity_logs.php">Activity Log</a>
                        <a href="users.php">Create Admin</a>
                    <?php } ?>
                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'super_admin'): ?>
                        <div class="panel" style="border: 2px solid #7c3aed;">
                            <h3 style="color: #7c3aed;">Super Admin Controls</h3>
                            <p class="panel-note">পুরো সিস্টেম ম্যানেজ করার জন্য নিচের বাটনে ক্লিক করুন।</p>
                            <div class="report-links">
                                <a href="super_admin.php" style="background: #7c3aed; color: #fff;">SaaS User Management</a>
                            </div>
                        </div>
                    <?php endif; ?>
                
                    <a href="change_password.php">পাসওয়ার্ড পরিবর্তন</a>
                </div>
            </div>
            

            <div class="panel">
                <h3>অতিরিক্ত রিপোর্ট</h3>
                <p class="panel-note">Detailed report, logs এবং performance tracking।</p>

                <details class="report-drawer">
                    <summary>More Reports / Logs খুলুন</summary>
                    <div class="report-links">
                        <a href="report_recovery.php">রিকভারি রিপোর্ট</a>
                        <a href="report_customer.php">গ্রাহক রিপোর্ট</a>
                        <a href="report_officer_performance.php">অফিসার পারফরম্যান্স</a>
                        <a href="report_top_defaulters.php">শীর্ষ খেলাপি</a>
                        <a href="report_no_recovery.php">রিকভারি হয়নি</a>
                        <a href="report_no_contact.php">যোগাযোগ হয়নি</a>
                        <a href="report_promise_to_pay.php">পরিশোধের প্রতিশ্রুতি</a>
                        <a href="report_no_response.php">সাড়া পাওয়া যায়নি</a>
                        <a href="report_call_priority.php">কল অগ্রাধিকার</a>
                        <a href="contacts.php">যোগাযোগ লগ</a>
                        <a href="recoveries.php">রিকভারি তালিকা</a>
                        <a href="my_messages.php">আমার বার্তা</a>
                    </div>
                </details>
            </div>
        </div>

        <aside class="panel">
            <h3>আজকের দ্রুত পর্যবেক্ষণ</h3>
            <p class="panel-note">যেগুলোতে এখনই action নেওয়া দরকার।</p>

            <div class="quick-stack">
                <div class="quick-item success">
                    <span>আজকের আদায়</span>
                    <strong>৳ <?php echo number_format($today_recovery, 2); ?></strong>
                </div>
                <div class="quick-item warning">
                    <span>Due Follow-up</span>
                    <strong><?php echo number_format($due_followups); ?></strong>
                </div>
                <div class="quick-item danger">
                    <span>No Recovery Customer</span>
                    <strong><?php echo number_format($no_recovery_count); ?></strong>
                </div>
                <div class="quick-item warning">
                    <span>No Contact 7+ Days</span>
                    <strong><?php echo number_format($no_contact_count); ?></strong>
                </div>
                <div class="quick-item warning">
                    <span>Promise Pending</span>
                    <strong><?php echo number_format($promise_pending_count); ?></strong>
                </div>
                <div class="quick-item">
                    <span>Unread Message</span>
                    <strong><?php echo number_format($unread_message_count); ?></strong>
                </div>
            </div>
        </aside>
    </div>

    <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
            <div>
                <h3 style="margin-bottom:4px;">CL Date ভিত্তিক সারাংশ</h3>
                <div style="color:#64748b; font-size:13px;">
                    First default date অনুযায়ী CL amount, outstanding এবং recovery ratio
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>CL Date</th>
                        <th style="text-align:center;">Customer</th>
                        <th style="text-align:right;">CL Amount</th>
                        <th style="text-align:right;">Outstanding</th>
                        <th style="text-align:right;">Recovered</th>
                        <th style="text-align:right;">Recovery %</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $total_customer = 0;
                $total_cl = 0;
                $total_outstanding = 0;

                while ($row = $result->fetch_assoc()) {
                    $is_empty_date = empty($row['cl_date']) || $row['cl_date'] == '0000-00-00';

                    $customer_count = intval($row['customer_count']);
                    $cl_amount = floatval($row['cl_amount']);
                    $outstanding = floatval($row['outstanding']);
                    $recovered = $cl_amount - $outstanding;

                    $percent = $cl_amount > 0
                        ? round(($recovered / $cl_amount) * 100, 2)
                        : 0;

                    $total_customer += $customer_count;
                    $total_cl += $cl_amount;
                    $total_outstanding += $outstanding;
                ?>
                    <tr style="background:<?php echo $is_empty_date ? '#fff1f2' : '#ffffff'; ?>;">
                        <td style="font-weight:700;">
                            <?php if ($is_empty_date) { ?>
                                <span class="pill pill-red">⚠ Date Not Set</span>
                            <?php } else { ?>
                                <?php echo htmlspecialchars($row['cl_date']); ?>
                            <?php } ?>
                        </td>

                        <td style="text-align:center;">
                            <?php echo number_format($customer_count); ?>
                        </td>

                        <td style="text-align:right;">
                            <?php echo number_format($cl_amount, 2); ?>
                        </td>

                        <td style="text-align:right;">
                            <?php echo number_format($outstanding, 2); ?>
                        </td>

                        <td style="text-align:right;">
                            <?php echo number_format($recovered, 2); ?>
                        </td>

                        <td style="text-align:right; font-weight:800; color:<?php echo ($percent < 20 ? '#dc2626' : '#16a34a'); ?>;">
                            <?php echo $percent; ?>%
                        </td>

                        <td style="text-align:center;">
                            <?php if ($is_empty_date) { ?>
                                <a href="customers.php?missing_first_default_date=1" class="table-action danger">View Missing</a>
                            <?php } else { ?>
                                <a href="customers.php?first_default_date=<?php echo urlencode($row['cl_date']); ?>" class="table-action">View</a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>

                <?php
                $total_recovered = $total_cl - $total_outstanding;
                $total_percent = $total_cl > 0
                    ? round(($total_recovered / $total_cl) * 100, 2)
                    : 0;
                ?>

                    <tr style="background:#e2e8f0; font-weight:800;">
                        <td>Total</td>
                        <td style="text-align:center;"><?php echo number_format($total_customer); ?></td>
                        <td style="text-align:right;"><?php echo number_format($total_cl, 2); ?></td>
                        <td style="text-align:right;"><?php echo number_format($total_outstanding, 2); ?></td>
                        <td style="text-align:right;"><?php echo number_format($total_recovered, 2); ?></td>
                        <td style="text-align:right;"><?php echo $total_percent; ?>%</td>
                        <td style="text-align:center;">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
