<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$branch_id  = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$circle_id  = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id    = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

// ১. স্কোপ লজিক (অ্যাক্সেস কন্ট্রোল)
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

// ২. ফিল্টার প্যারামিটার
$filter_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all_cl';
$filter_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

$filter_where = "";
if ($filter_branch > 0) $filter_where .= " AND c.branch_id = $filter_branch";

// ৩. customer_state ফিল্টার লজিক (আপনার ডাটাবেস কলাম অনুযায়ী)
if ($filter_status === 'old_cl') {
    $filter_where .= " AND (c.customer_state LIKE '%old cl%' OR c.customer_state LIKE '%OLD CL%')";
} elseif ($filter_status === 'new_cl') {
    $filter_where .= " AND (c.customer_state LIKE '%new cl%' OR c.customer_state LIKE '%NEW CL%')";
} else {
    // লোড হওয়ার সময় Old CL এবং New CL উভয়ই দেখাবে
    $filter_where .= " AND (c.customer_state LIKE '%old cl%' OR c.customer_state LIKE '%OLD CL%' OR c.customer_state LIKE '%new cl%' OR c.customer_state LIKE '%NEW CL%')";
}

// ৪. প্রধান SQL কুয়েরি (customer_state এবং sorting ঠিক করা হয়েছে)
$sql = "
SELECT 
    c.id, c.name, c.account_number, c.outstanding, c.customer_state,
    o.name AS officer_name, b.name AS branch_name,
    COALESCE((SELECT SUM(r.amount) FROM recoveries r WHERE r.customer_id = c.id), 0) AS total_recovery,
    (SELECT MAX(r2.recovery_date) FROM recoveries r2 WHERE r2.customer_id = c.id) AS last_recovery_date
FROM customers c
LEFT JOIN officers o ON c.assigned_officer = o.id
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN zones z ON b.zone_id = z.id
WHERE ($scope_where) $filter_where
ORDER BY CAST(c.outstanding AS DECIMAL(15,2)) DESC
";

$result = $conn->query($sql);
$total_count = $result ? $result->num_rows : 0;
$total_outstanding = 0;
$total_recovery_sum = 0;
$rows = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $total_outstanding += floatval($row['outstanding']);
        $total_recovery_sum += floatval($row['total_recovery']);
        $rows[] = $row;
    }
}

// শাখা তালিকার জন্য কুয়েরি
$branch_sql = "SELECT id, name FROM branches ORDER BY name";
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>শীর্ষ খেলাপি রিপোর্ট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* আপনার দেওয়া অরিজিনাল ডিজাইন হুবহু বজায় রাখা হয়েছে */
        * { box-sizing:border-box; }
        body { font-family: Arial, sans-serif; background:#eef2f7; margin:0; color:#1f2937; }
        .topbar { background:linear-gradient(135deg,#1f2937,#111827); color:#fff; padding:18px 22px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 18px rgba(0,0,0,0.15); }
        .topbar-title { font-size:20px; font-weight:bold; }
        .topbar-links a { color:#fff; text-decoration:none; margin-left:8px; padding:8px 12px; background:rgba(255,255,255,0.12); border-radius:8px; font-size:14px; }
        .container { max-width:1400px; margin:auto; padding:22px; }
        .hero { background:#fff; padding:22px; border-radius:18px; box-shadow:0 6px 20px rgba(15,23,42,0.06); margin-bottom:18px; }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px; margin-bottom:18px; }
        .card { background:#fff; padding:20px; border-radius:18px; box-shadow:0 6px 20px rgba(15,23,42,0.06); border-left:5px solid #dc2626; }
        .card.orange { border-left-color:#f97316; }
        .card.green { border-left-color:#16a34a; }
        .card span { color:#64748b; font-size:13px; display:block; margin-bottom:8px; }
        .card strong { font-size:26px; color:#111827; }
        .box { background:#fff; padding:20px; border-radius:18px; box-shadow:0 6px 20px rgba(15,23,42,0.06); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1100px; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; font-size:14px; }
        th { background:#f8fafc; color:#334155; }
        .btn { display:inline-block; padding:7px 10px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; font-size:13px; border:0; }
        .btn-orange { background:#f97316; }
        .status-badge { display:inline-block; padding:4px 8px; border-radius:999px; background:#eef2ff; color:#3730a3; font-size:12px; font-weight:bold; }
        .zero { color:#94a3b8; text-align:center; padding:20px; }
        label { display:block; font-size:13px; color:#475569; margin-bottom:5px; font-weight:bold; }
        select { width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:8px; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-title">শীর্ষ খেলাপি রিপোর্ট</div>
    <div class="topbar-links">
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">
    <div class="hero">
        <h2>Top Defaulters (CL Summary)</h2>
        <p>সর্বোচ্চ বকেয়া সম্পন্ন Old CL এবং New CL গ্রাহকদের তালিকা।</p>
    </div>

    <div class="box" style="margin-bottom:18px;">
        <form method="GET" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; align-items:end;">
            <div>
                <label>CL Type</label>
                <select name="status_filter">
                    <option value="all_cl" <?= $filter_status=='all_cl'?'selected':'' ?>>All (Old & New)</option>
                    <option value="old_cl" <?= $filter_status=='old_cl'?'selected':'' ?>>Old CL Only</option>
                    <option value="new_cl" <?= $filter_status=='new_cl'?'selected':'' ?>>New CL Only</option>
                </select>
            </div>
            <div>
                <label>Branch</label>
                <select name="branch_id">
                    <option value="0">All Branch</option>
                    <?php 
                    $b_res = $conn->query($branch_sql);
                    while($b = $b_res->fetch_assoc()){
                        echo "<option value='{$b['id']}' ".($filter_branch==$b['id']?'selected':'').">".htmlspecialchars($b['name'])."</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn">Filter Data</button>
                <a href="report_top_defaulters.php" class="btn btn-orange">Reset</a>
            </div>
        </form>
    </div>

    <div class="cards">
        <div class="card"><span>মোট গ্রাহক</span><strong><?= $total_count ?></strong></div>
        <div class="card orange"><span>মোট বকেয়া</span><strong><?= number_format($total_outstanding, 2) ?></strong></div>
        <div class="card green"><span>মোট আদায়</span><strong><?= number_format($total_recovery_sum, 2) ?></strong></div>
    </div>

    <div class="box">
        <?php if ($total_count > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>গ্রাহকের নাম</th>
                    <th>হিসাব নম্বর</th>
                    <th>শাখা</th>
                    <th>স্থিতি (Outstanding)</th>
                    <th>মোট আদায়</th>
                    <th>সর্বশেষ আদায়</th>
                    <th>স্ট্যাটাস (State)</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['account_number']) ?></td>
                    <td><?= htmlspecialchars($row['branch_name']) ?></td>
                    <td style="color:#dc2626; font-weight:bold;"><?= number_format($row['outstanding'], 2) ?></td>
                    <td><?= number_format($row['total_recovery'], 2) ?></td>
                    <td><?= $row['last_recovery_date'] ?: 'নেই' ?></td>
                    <td><span class="status-badge"><?= htmlspecialchars($row['customer_state']) ?></span></td>
                    <td><a href="customer_view.php?id=<?= $row['id'] ?>" class="btn">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="zero">কোনো Old বা New CL ডাটা পাওয়া যায়নি।</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>