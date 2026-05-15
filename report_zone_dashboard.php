<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}


$branch_id = intval($_SESSION['branch_id']);

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-01-01');
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

/*
Note:
Current user is branch admin, but this report is structural.
For now admin can see all circles/zones/branches in system.
Later role-based HO/Zone/Circle access can be added.
*/

$circle_sql = "
SELECT
    c.id AS circle_id,
    c.name AS circle_name,
    COUNT(DISTINCT b.id) AS total_branches,
    COUNT(DISTINCT cu.id) AS total_customers,
    COALESCE(SUM(cu.outstanding), 0) AS total_outstanding,
    COALESCE((
        SELECT SUM(r.amount)
        FROM recoveries r
        INNER JOIN customers cu2 ON r.customer_id = cu2.id
        INNER JOIN branches b2 ON cu2.branch_id = b2.id
        INNER JOIN zones z2 ON b2.zone_id = z2.id
        WHERE z2.circle_id = c.id
        AND r.recovery_date BETWEEN ? AND ?
    ), 0) AS total_recovery
FROM circles c
LEFT JOIN zones z ON c.id = z.circle_id
LEFT JOIN branches b ON z.id = b.zone_id
LEFT JOIN customers cu ON b.id = cu.branch_id
WHERE c.status = 'Active'
GROUP BY c.id, c.name
ORDER BY total_outstanding DESC
";

$stmtCircle = $conn->prepare($circle_sql);
$stmtCircle->bind_param("ss", $from, $to);
$stmtCircle->execute();
$circle_result = $stmtCircle->get_result();


$zone_sql = "
SELECT
    z.id AS zone_id,
    z.name AS zone_name,
    c.name AS circle_name,
    COUNT(DISTINCT b.id) AS total_branches,
    COUNT(DISTINCT cu.id) AS total_customers,
    COALESCE(SUM(cu.outstanding), 0) AS total_outstanding,
    COALESCE((
        SELECT SUM(r.amount)
        FROM recoveries r
        INNER JOIN customers cu2 ON r.customer_id = cu2.id
        INNER JOIN branches b2 ON cu2.branch_id = b2.id
        WHERE b2.zone_id = z.id
        AND r.recovery_date BETWEEN ? AND ?
    ), 0) AS total_recovery
FROM zones z
LEFT JOIN circles c ON z.circle_id = c.id
LEFT JOIN branches b ON z.id = b.zone_id
LEFT JOIN customers cu ON b.id = cu.branch_id
WHERE z.status = 'Active'
GROUP BY z.id, z.name, c.name
ORDER BY total_outstanding DESC
";

$stmtZone = $conn->prepare($zone_sql);
$stmtZone->bind_param("ss", $from, $to);
$stmtZone->execute();
$zone_result = $stmtZone->get_result();


$summary_sql = "
SELECT
    COUNT(DISTINCT c.id) AS total_circles,
    COUNT(DISTINCT z.id) AS total_zones,
    COUNT(DISTINCT b.id) AS total_branches,
    COUNT(DISTINCT cu.id) AS total_customers,
    COALESCE(SUM(cu.outstanding), 0) AS total_outstanding,
    COALESCE((
        SELECT SUM(r.amount)
        FROM recoveries r
        INNER JOIN customers cu2 ON r.customer_id = cu2.id
        INNER JOIN branches b2 ON cu2.branch_id = b2.id
        WHERE r.recovery_date BETWEEN ? AND ?
    ), 0) AS total_recovery
FROM circles c
LEFT JOIN zones z ON c.id = z.circle_id
LEFT JOIN branches b ON z.id = b.zone_id
LEFT JOIN customers cu ON b.id = cu.branch_id
WHERE c.status = 'Active'
";

$stmtSummary = $conn->prepare($summary_sql);
$stmtSummary->bind_param("ss", $from, $to);
$stmtSummary->execute();
$summary = $stmtSummary->get_result()->fetch_assoc();

$total_outstanding = floatval($summary['total_outstanding']);
$total_recovery = floatval($summary['total_recovery']);
$recovery_ratio = $total_outstanding > 0 ? ($total_recovery / $total_outstanding) * 100 : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Zone / Circle Dashboard Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #eef2f7;
            margin: 0;
            color: #1f2937;
        }

        .topbar {
            background: linear-gradient(135deg, #1f2937, #111827);
            color: #fff;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 4px 18px rgba(0,0,0,0.15);
        }

        .topbar-title {
            font-size: 20px;
            font-weight: bold;
        }

        .topbar-links a {
            color: #fff;
            text-decoration: none;
            margin-left: 8px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.12);
            border-radius: 8px;
            display: inline-block;
            font-size: 14px;
        }

        .container {
            max-width: 1400px;
            margin: auto;
            padding: 22px;
        }

        .hero {
            background: #fff;
            padding: 22px;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
        }

        .hero h2 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        .hero p {
            margin: 0;
            color: #64748b;
        }

        .filter-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 14px;
            border-radius: 14px;
        }

        .filter-box input {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin: 4px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            margin: 4px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            border-left: 5px solid #2563eb;
        }

        .card span {
            color: #64748b;
            font-size: 13px;
            display: block;
            margin-bottom: 8px;
        }

        .card strong {
            font-size: 25px;
            color: #111827;
        }

        .card.green { border-left-color: #16a34a; }
        .card.orange { border-left-color: #f97316; }
        .card.red { border-left-color: #dc2626; }
        .card.purple { border-left-color: #7c3aed; }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .box {
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            overflow-x: auto;
            margin-bottom: 18px;
        }

        .box h3 {
            margin-top: 0;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #334155;
        }

        tr:hover {
            background: #f8fafc;
        }

        .risk-high {
            color: #dc2626;
            font-weight: bold;
        }

        .risk-medium {
            color: #f97316;
            font-weight: bold;
        }

        .risk-low {
            color: #16a34a;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 12px;
            font-weight: bold;
        }

        @media (max-width: 950px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">Zone / Circle Dashboard Report</div>
        <div style="font-size:13px; color:#cbd5e1; margin-top:4px;">
            Structural Recovery Monitoring
        </div>
    </div>

    <div class="topbar-links">
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="zones.php">জোন তালিকা</a>
        <a href="circles.php">সার্কেল তালিকা</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <div class="hero">
        <div>
            <h2>Zone / Circle Summary</h2>
            <p>Circle, Zone, Branch, Customer, Outstanding এবং Recovery monitoring</p>
        </div>

        <form method="GET" class="filter-box">
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">

            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">

            <button class="btn" type="submit">Filter</button>
            <a class="btn" href="?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>">আজ</a>
            <a class="btn" href="?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>">এই মাস</a>
        </form>
    </div>

    <div class="cards">
        <div class="card purple">
            <span>মোট সার্কেল</span>
            <strong><?php echo intval($summary['total_circles']); ?></strong>
        </div>

        <div class="card purple">
            <span>মোট জোন</span>
            <strong><?php echo intval($summary['total_zones']); ?></strong>
        </div>

        <div class="card">
            <span>মোট শাখা</span>
            <strong><?php echo intval($summary['total_branches']); ?></strong>
        </div>

        <div class="card">
            <span>মোট গ্রাহক</span>
            <strong><?php echo intval($summary['total_customers']); ?></strong>
        </div>

        <div class="card red">
            <span>মোট বকেয়া</span>
            <strong><?php echo number_format($total_outstanding, 2); ?></strong>
        </div>

        <div class="card green">
            <span>মোট আদায়</span>
            <strong><?php echo number_format($total_recovery, 2); ?></strong>
        </div>

        <div class="card orange">
            <span>Recovery Ratio</span>
            <strong><?php echo number_format($recovery_ratio, 2); ?>%</strong>
        </div>
    </div>

    <div class="grid">

        <div class="box">
            <h3>Circle Wise Summary</h3>

            <table>
                <tr>
                    <th>Circle</th>
                    <th>Branches</th>
                    <th>Customers</th>
                    <th>Outstanding</th>
                    <th>Recovery</th>
                    <th>Ratio</th>
                </tr>

                <?php while($row = $circle_result->fetch_assoc()) { 
                    $out = floatval($row['total_outstanding']);
                    $rec = floatval($row['total_recovery']);
                    $ratio = $out > 0 ? ($rec / $out) * 100 : 0;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['circle_name']); ?></strong></td>
                    <td><?php echo intval($row['total_branches']); ?></td>
                    <td><?php echo intval($row['total_customers']); ?></td>
                    <td><?php echo number_format($out, 2); ?></td>
                    <td><?php echo number_format($rec, 2); ?></td>
                    <td><span class="badge"><?php echo number_format($ratio, 2); ?>%</span></td>
                </tr>
                <?php } ?>
            </table>
        </div>

        <div class="box">
            <h3>Zone Wise Summary</h3>

            <table>
                <tr>
                    <th>Circle</th>
                    <th>Zone</th>
                    <th>Branches</th>
                    <th>Customers</th>
                    <th>Outstanding</th>
                    <th>Recovery</th>
                    <th>Risk</th>
                </tr>

                <?php while($row = $zone_result->fetch_assoc()) { 
                    $out = floatval($row['total_outstanding']);
                    $rec = floatval($row['total_recovery']);
                    $ratio = $out > 0 ? ($rec / $out) * 100 : 0;

                    if ($out > 0 && $ratio < 2) {
                        $risk = "High";
                        $risk_class = "risk-high";
                    } elseif ($out > 0 && $ratio < 5) {
                        $risk = "Medium";
                        $risk_class = "risk-medium";
                    } else {
                        $risk = "Low";
                        $risk_class = "risk-low";
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['circle_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['zone_name']); ?></strong></td>
                    <td><?php echo intval($row['total_branches']); ?></td>
                    <td><?php echo intval($row['total_customers']); ?></td>
                    <td><?php echo number_format($out, 2); ?></td>
                    <td><?php echo number_format($rec, 2); ?></td>
                    <td class="<?php echo $risk_class; ?>"><?php echo $risk; ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>

    </div>

</div>

</body>
</html>