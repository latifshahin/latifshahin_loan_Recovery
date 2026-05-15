<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$circle_id = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

$is_admin = in_array($role, ['admin', 'ho_admin', 'circle_admin', 'zone_admin']);

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

/*
Scope condition for customer-based dashboard data
*/

$scope_join = "";
$scope_where = "1=1";

if ($role === 'ho_admin') {
    $scope_join = "";
    $scope_where = "1=1";
} elseif ($role === 'circle_admin') {
    $scope_join = " LEFT JOIN branches b ON c.branch_id = b.id
                    LEFT JOIN zones z ON b.zone_id = z.id ";
    $scope_where = "z.circle_id = $circle_id";
} elseif ($role === 'zone_admin') {
    $scope_join = " LEFT JOIN branches b ON c.branch_id = b.id ";
    $scope_where = "b.zone_id = $zone_id";
} elseif ($role === 'admin') {
    $scope_join = "";
    $scope_where = "c.branch_id = $branch_id";
} else {
    $scope_join = "";
    $scope_where = "c.branch_id = $branch_id AND c.assigned_officer = $officer_id";
}

/*
Dashboard counts
*/

$res1 = $conn->query("SELECT COUNT(*) AS total FROM customers c $scope_join WHERE $scope_where");
$total_customers = $res1 ? intval($res1->fetch_assoc()['total']) : 0;

/* Officer count by scope */
if ($role === 'ho_admin') {
    $res2 = $conn->query("SELECT COUNT(*) AS total FROM officers");
} elseif ($role === 'circle_admin') {
    $res2 = $conn->query("SELECT COUNT(*) AS total
                          FROM officers o
                          INNER JOIN branches b ON o.branch_id = b.id
                          INNER JOIN zones z ON b.zone_id = z.id
                          WHERE z.circle_id = $circle_id");
} elseif ($role === 'zone_admin') {
    $res2 = $conn->query("SELECT COUNT(*) AS total
                          FROM officers o
                          INNER JOIN branches b ON o.branch_id = b.id
                          WHERE b.zone_id = $zone_id");
} elseif ($role === 'admin') {
    $res2 = $conn->query("SELECT COUNT(*) AS total FROM officers WHERE branch_id = $branch_id");
} else {
    $res2 = false;
}
$total_officers = $res2 ? intval($res2->fetch_assoc()['total']) : 0;

$res3 = $conn->query("SELECT COALESCE(SUM(c.outstanding),0) AS total
                      FROM customers c
                      $scope_join
                      WHERE $scope_where");
$total_outstanding = $res3 ? floatval($res3->fetch_assoc()['total']) : 0;

$res4 = $conn->query("SELECT COALESCE(SUM(r.amount),0) AS total
                      FROM recoveries r
                      INNER JOIN customers c ON r.customer_id = c.id
                      $scope_join
                      WHERE $scope_where");
$total_recovery = $res4 ? floatval($res4->fetch_assoc()['total']) : 0;

$res5 = $conn->query("SELECT COUNT(*) AS total
                      FROM contacts ct
                      INNER JOIN customers c ON ct.customer_id = c.id
                      $scope_join
                      WHERE $scope_where
                      AND ct.next_followup IS NOT NULL
                      AND ct.next_followup <= CURDATE()");
$due_followups = $res5 ? intval($res5->fetch_assoc()['total']) : 0;

$res6 = $conn->query("SELECT COALESCE(SUM(r.amount),0) AS total
                      FROM recoveries r
                      INNER JOIN customers c ON r.customer_id = c.id
                      $scope_join
                      WHERE $scope_where
                      AND r.recovery_date = CURDATE()");
$today_recovery = $res6 ? floatval($res6->fetch_assoc()['total']) : 0;

$res7 = $conn->query("SELECT c.id
                      FROM customers c
                      $scope_join
                      LEFT JOIN recoveries r ON c.id = r.customer_id
                      WHERE $scope_where
                      GROUP BY c.id
                      HAVING COALESCE(SUM(r.amount),0) = 0");
$no_recovery_count = $res7 ? $res7->num_rows : 0;

$res8 = $conn->query("SELECT c.id
                      FROM customers c
                      $scope_join
                      WHERE $scope_where
                      AND (
                          (SELECT MAX(ct.created_at) FROM contacts ct WHERE ct.customer_id = c.id) IS NULL
                          OR DATE((SELECT MAX(ct.created_at) FROM contacts ct WHERE ct.customer_id = c.id)) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      )");
$no_contact_count = $res8 ? $res8->num_rows : 0;

$res9 = $conn->query("SELECT COUNT(*) AS total
                      FROM contacts ct
                      INNER JOIN customers c ON ct.customer_id = c.id
                      $scope_join
                      WHERE $scope_where
                      AND ct.action_result = 'Promise to Pay'
                      AND NOT EXISTS (
                          SELECT 1 FROM recoveries r
                          WHERE r.customer_id = ct.customer_id
                          AND r.recovery_date >= DATE(ct.created_at)
                      )");
$promise_pending_count = $res9 ? intval($res9->fetch_assoc()['total']) : 0;

/*
Unread message popup
For HO/Circle/Zone users branch_id may be 0/NULL, so allow branch_id=0/global plus own branch messages.
*/

if ($branch_id > 0) {
    $msg_stmt = $conn->prepare("SELECT m.*
                                FROM messages m
                                LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = ?
                                WHERE mr.id IS NULL
                                AND (m.branch_id = ? OR m.branch_id = 0)
                                AND (
                                    m.recipient_type = 'all'
                                    OR m.recipient_type = ?
                                    OR (m.recipient_type = 'specific' AND m.recipient_user_id = ?)
                                )
                                ORDER BY m.id DESC
                                LIMIT 1");
    $msg_stmt->bind_param("iisi", $user_id, $branch_id, $role, $user_id);

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total
                                  FROM messages m
                                  LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = ?
                                  WHERE mr.id IS NULL
                                  AND (m.branch_id = ? OR m.branch_id = 0)
                                  AND (
                                      m.recipient_type = 'all'
                                      OR m.recipient_type = ?
                                      OR (m.recipient_type = 'specific' AND m.recipient_user_id = ?)
                                  )");
    $count_stmt->bind_param("iisi", $user_id, $branch_id, $role, $user_id);
} else {
    $msg_stmt = $conn->prepare("SELECT m.*
                                FROM messages m
                                LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = ?
                                WHERE mr.id IS NULL
                                AND (
                                    m.recipient_type = 'all'
                                    OR m.recipient_type = ?
                                    OR (m.recipient_type = 'specific' AND m.recipient_user_id = ?)
                                )
                                ORDER BY m.id DESC
                                LIMIT 1");
    $msg_stmt->bind_param("isi", $user_id, $role, $user_id);

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total
                                  FROM messages m
                                  LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id = ?
                                  WHERE mr.id IS NULL
                                  AND (
                                      m.recipient_type = 'all'
                                      OR m.recipient_type = ?
                                      OR (m.recipient_type = 'specific' AND m.recipient_user_id = ?)
                                  )");
    $count_stmt->bind_param("isi", $user_id, $role, $user_id);
}

$msg_stmt->execute();
$latest_unread_message = $msg_stmt->get_result()->fetch_assoc();

$count_stmt->execute();
$unread_message_count = intval($count_stmt->get_result()->fetch_assoc()['total']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ড্যাশবোর্ড - ঋণ আদায় ব্যবস্থাপনা</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef2f7;
            color: #1f2937;
        }

        .topbar {
            background: linear-gradient(135deg, #1f2937, #111827);
            color: white;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 4px 18px rgba(0,0,0,0.15);
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
        }

        .top-actions a {
            color: #fff;
            text-decoration: none;
            margin-left: 10px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.12);
            border-radius: 8px;
            font-size: 14px;
        }

        .top-actions a:hover {
            background: rgba(255,255,255,0.22);
        }

        .container {
            padding: 22px;
            max-width: 1400px;
            margin: auto;
        }

        .hero {
            background: white;
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .hero h2 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        .hero p {
            margin: 0;
            color: #64748b;
        }

        .hero-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 14px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .card {
            min-height: 135px;
            padding: 20px;
            border-radius: 18px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(15,23,42,0.12);
            transition: all 0.22s ease;
        }

        .card::after {
            content: "";
            position: absolute;
            width: 110px;
            height: 110px;
            right: -30px;
            bottom: -30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
        }

        .card-link:hover .card {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(15,23,42,0.18);
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 16px;
            font-weight: 600;
        }

        .card p {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }

        .card small {
            display: block;
            margin-top: 12px;
            color: rgba(255,255,255,0.92);
            font-size: 12px;
        }

        .blue { background: linear-gradient(135deg, #2563eb, #60a5fa); }
        .green { background: linear-gradient(135deg, #15803d, #4ade80); }
        .red { background: linear-gradient(135deg, #dc2626, #fb7185); }
        .orange { background: linear-gradient(135deg, #f97316, #fbbf24); }
        .purple { background: linear-gradient(135deg, #7c3aed, #a78bfa); }
        .dark { background: linear-gradient(135deg, #334155, #64748b); }

        .grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 18px;
        }

        .menu-box {
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            margin-bottom: 18px;
        }

        .menu-box h3 {
            margin: 0 0 5px;
            font-size: 20px;
        }

        .section-note {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .menu-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .menu-links a {
            display: inline-block;
            padding: 10px 13px;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
        }

        .admin-menu .menu-links a { background: #7c3aed; }
        .officer-menu .menu-links a { background: #15803d; }
        .common-menu .menu-links a { background: #2563eb; }

        .menu-links a:hover {
            filter: brightness(0.92);
            transform: translateY(-1px);
        }

        .quick-panel {
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
        }

        .quick-panel h3 {
            margin: 0 0 12px;
        }

        .quick-item {
            display: flex;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid #e5e7eb;
            gap: 10px;
        }

        .quick-item:last-child {
            border-bottom: none;
        }

        .quick-item span {
            color: #64748b;
            font-size: 14px;
        }

        .quick-item strong {
            font-size: 15px;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 15px;
        }

        .popup-box {
            background: #fff;
            width: 100%;
            max-width: 540px;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 14px 35px rgba(0,0,0,0.25);
        }

        .popup-box h3 {
            margin-top: 0;
        }

        .popup-actions {
            margin-top: 20px;
            text-align: right;
        }

        .popup-actions a,
        .popup-actions button {
            display: inline-block;
            padding: 10px 14px;
            margin-left: 8px;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
        }

        .popup-open {
            background: #2563eb;
            color: #fff;
        }

        .popup-close {
            background: #64748b;
            color: #fff;
        }

        @media (max-width: 850px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
            }

            .top-actions a {
                margin-left: 0;
                margin-right: 6px;
                display: inline-block;
                margin-top: 6px;
            }

            .container {
                padding: 14px;
            }

            .hero {
                padding: 18px;
            }
        }
        
        .menu-section {
    background: #fff;
    padding: 22px;
    border-radius: 18px;
    box-shadow: 0 6px 20px rgba(15,23,42,0.06);
    margin-bottom: 18px;
}

.menu-section h3 {
    margin: 0 0 6px;
    font-size: 22px;
}

.section-note {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 18px;
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 14px;
}

.module-card {
    display: block;
    text-decoration: none;
    color: #1f2937;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px;
    transition: 0.22s ease;
    min-height: 118px;
    position: relative;
    overflow: hidden;
}

.module-card:hover {
    transform: translateY(-4px);
    background: #fff;
    border-color: #93c5fd;
    box-shadow: 0 10px 24px rgba(37,99,235,0.12);
}

.module-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: #fff;
    font-size: 20px;
    margin-bottom: 12px;
}

.module-card h4 {
    margin: 0 0 6px;
    font-size: 16px;
}

.module-card p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    line-height: 1.45;
}

.module-card.purple .module-icon { background:#7c3aed; }
.module-card.green .module-icon { background:#16a34a; }
.module-card.orange .module-icon { background:#f97316; }
.module-card.red .module-icon { background:#dc2626; }
.module-card.dark .module-icon { background:#475569; }

@media (max-width: 650px) {
    .module-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .module-card {
        padding: 14px;
        min-height: 112px;
    }

    .module-icon {
        width: 36px;
        height: 36px;
        font-size: 17px;
        margin-bottom: 10px;
    }

    .module-card h4 {
        font-size: 14px;
    }

    .module-card p {
        font-size: 12px;
    }
}
.module-card:hover .module-icon {
    transform: scale(1.1);
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
        <p><strong>Priority:</strong> <?php echo htmlspecialchars($latest_unread_message['priority']); ?></p>

        <div class="popup-actions">
            <button class="popup-close" onclick="document.getElementById('messagePopup').style.display='none'">পরে দেখবো</button>
            <a class="popup-open" href="message_view.php?id=<?php echo $latest_unread_message['id']; ?>">বার্তা খুলুন</a>
        </div>
    </div>
</div>
<?php } ?>

<div class="topbar">
    <div>
        <div class="brand">ঋণ আদায় ব্যবস্থাপনা</div>
        <div style="font-size:13px; color:#cbd5e1; margin-top:4px;">
            স্বাগতম, <?php echo htmlspecialchars($_SESSION['name']); ?> —
            <?php echo htmlspecialchars($_SESSION['role']); ?>
        </div>
    </div>

    <div class="top-actions">
        <a href="my_messages.php">আমার বার্তা <?php if($unread_message_count > 0) echo '(' . $unread_message_count . ')'; ?></a>
        <a href="change_password.php">পাসওয়ার্ড পরিবর্তন</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <div class="hero">
        <div>
            <h2>ড্যাশবোর্ড</h2>
            <p>আজকের কাজ, আদায়, ফলো-আপ এবং ঝুঁকিপূর্ণ গ্রাহকদের দ্রুত দেখুন।</p>
        </div>
        <div class="hero-badge">
            <?php echo $is_admin ? 'Admin View' : 'Officer View'; ?>
        </div>
    </div>

    <div class="cards">

        <a class="card-link" href="customers.php">
            <div class="card blue">
                <h3>মোট গ্রাহক</h3>
                <p><?php echo $total_customers; ?></p>
                <small>গ্রাহক তালিকা দেখতে ক্লিক করুন</small>
            </div>
        </a>

        <?php if (in_array($_SESSION['role'], ['admin','zone_admin','circle_admin','ho_admin'])) { ?>
        <a class="card-link" href="officers.php">
            <div class="card purple">
                <h3>মোট অফিসার</h3>
                <p><?php echo $total_officers; ?></p>
                <small>অফিসার তালিকা দেখতে ক্লিক করুন</small>
            </div>
        </a>
        <?php } ?>

        <a class="card-link" href="report_top_defaulters.php">
            <div class="card red">
                <h3>মোট বকেয়া</h3>
                <p><?php echo number_format($total_outstanding, 2); ?></p>
                <small>শীর্ষ খেলাপি দেখতে ক্লিক করুন</small>
            </div>
        </a>

        <a class="card-link" href="report_recovery.php">
            <div class="card green">
                <h3>মোট আদায়</h3>
                <p><?php echo number_format($total_recovery, 2); ?></p>
                <small>রিকভারি রিপোর্ট দেখতে ক্লিক করুন</small>
            </div>
        </a>

        <a class="card-link" href="due_followups.php">
            <div class="card orange">
                <h3>বকেয়া ফলো-আপ</h3>
                <p><?php echo $due_followups; ?></p>
                <small>আজ ও আগের ফলো-আপ দেখুন</small>
            </div>
        </a>

        <a class="card-link" href="report_recovery.php?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>">
            <div class="card green">
                <h3>আজকের আদায়</h3>
                <p><?php echo number_format($today_recovery, 2); ?></p>
                <small>আজকের রিকভারি রিপোর্ট</small>
            </div>
        </a>

        <a class="card-link" href="report_no_recovery.php">
            <div class="card red">
                <h3>কোনো আদায় হয়নি</h3>
                <p><?php echo $no_recovery_count; ?></p>
                <small>Priority follow-up দরকার</small>
            </div>
        </a>

        <a class="card-link" href="report_no_contact.php">
            <div class="card orange">
                <h3>৭+ দিন যোগাযোগ নেই</h3>
                <p><?php echo $no_contact_count; ?></p>
                <small>যোগাযোগ gap দেখুন</small>
            </div>
        </a>

        <a class="card-link" href="report_promise_to_pay.php">
            <div class="card orange">
                <h3>অপেক্ষমাণ প্রতিশ্রুতি</h3>
                <p><?php echo $promise_pending_count; ?></p>
                <small>Promise follow-up দেখুন</small>
            </div>
        </a>

        <a class="card-link" href="my_messages.php">
            <div class="card dark">
                <h3>অপঠিত বার্তা</h3>
                <p><?php echo $unread_message_count; ?></p>
                <small>আমার বার্তা দেখুন</small>
            </div>
        </a>

    </div>

    <div class="grid">
        <div>
            <?php if ($is_admin) { ?>
<div class="menu-section">
    <h3>এডমিন মডিউল</h3>
    <div class="section-note">ব্যবস্থাপনা, import, backup, structure এবং monitoring</div>

    <div class="module-grid">
        <a class="module-card purple" href="report_zone_dashboard.php">
            <div class="module-icon">🌍</div>
            <h4>Zone/Circle Dashboard</h4>
            <p>Circle, zone, branch wise outstanding এবং recovery summary</p>
        </a>
        <a class="module-card purple" href="officers.php">
            <div class="module-icon">👥</div>
            <h4>অফিসার ব্যবস্থাপনা</h4>
            <p>অফিসার তালিকা, status এবং branch wise monitoring</p>
        </a>

        <a class="module-card purple" href="add_officer.php">
            <div class="module-icon">➕</div>
            <h4>নতুন অফিসার</h4>
            <p>নতুন recovery officer তৈরি করুন</p>
        </a>

        <a class="module-card green" href="customers.php">
            <div class="module-icon">🏦</div>
            <h4>গ্রাহক ব্যবস্থাপনা</h4>
            <p>সব গ্রাহক, দায়স্থিতি, status এবং action দেখুন</p>
        </a>

        <a class="module-card green" href="add_customer.php">
            <div class="module-icon">➕</div>
            <h4>নতুন গ্রাহক</h4>
            <p>একক গ্রাহক manually যোগ করুন</p>
        </a>

        <a class="module-card dark" href="users.php">
            <div class="module-icon">🔐</div>
            <h4>ইউজার ব্যবস্থাপনা</h4>
            <p>Admin/Officer user এবং password reset</p>
        </a>

        <a class="module-card dark" href="branches.php">
            <div class="module-icon">🏢</div>
            <h4>শাখা ব্যবস্থাপনা</h4>
            <p>Branch list, code এবং zone mapping</p>
        </a>

        <a class="module-card orange" href="import_customers.php">
            <div class="module-icon">⬆️</div>
            <h4>গ্রাহক Import</h4>
            <p>CSV থেকে validated customer upload</p>
        </a>

        <a class="module-card orange" href="import_recoveries.php">
            <div class="module-icon">💰</div>
            <h4>রিকভারি Import</h4>
            <p>Excel/CSV recovery data bulk upload</p>
        </a>

        <a class="module-card green" href="send_message.php">
            <div class="module-icon">✉️</div>
            <h4>বার্তা পাঠান</h4>
            <p>সবাই, officer বা নির্দিষ্ট user-কে message পাঠান</p>
        </a>

        <a class="module-card green" href="message_templates.php">
            <div class="module-icon">💬</div>
            <h4>মেসেজ টেমপ্লেট</h4>
            <p>SMS/WhatsApp template এবং variable message</p>
        </a>

        <a class="module-card purple" href="circles.php">
            <div class="module-icon">🌐</div>
            <h4>সার্কেল</h4>
            <p>Circle list এবং structure setup</p>
        </a>

        <a class="module-card purple" href="zones.php">
            <div class="module-icon">📍</div>
            <h4>জোন</h4>
            <p>Zone list এবং circle mapping</p>
        </a>

        <a class="module-card purple" href="assign_branch_zone.php">
            <div class="module-icon">🧭</div>
            <h4>Branch-Zone Mapping</h4>
            <p>Branch কে নির্দিষ্ট zone-এর সাথে যুক্ত করুন</p>
        </a>

        <a class="module-card red" href="backup.php">
            <div class="module-icon">💾</div>
            <h4>ডাটাবেজ Backup</h4>
            <p>বর্তমান database download backup নিন</p>
        </a>

        <a class="module-card red" href="restore.php">
            <div class="module-icon">♻️</div>
            <h4>ডাটাবেজ Restore</h4>
            <p>Backup file থেকে database restore করুন</p>
        </a>

        <a class="module-card dark" href="activity_logs.php">
            <div class="module-icon">🧾</div>
            <h4>Activity Log</h4>
            <p>কে কখন কী action করেছে তা দেখুন</p>
        </a>

    </div>
</div>
<?php } else { ?>
<div class="menu-section">
    <h3>অফিসার মডিউল</h3>
    <div class="section-note">নিজের assigned customer ও দৈনিক recovery action</div>

    <div class="module-grid">
        <a class="module-card green" href="customers.php">
            <div class="module-icon">🏦</div>
            <h4>আমার গ্রাহক</h4>
            <p>নিজের দায়িত্বপ্রাপ্ত গ্রাহক তালিকা দেখুন</p>
        </a>

        <a class="module-card orange" href="due_followups.php">
            <div class="module-icon">⏰</div>
            <h4>Due Follow-up</h4>
            <p>আজ ও আগের বকেয়া follow-up দেখুন</p>
        </a>

        <a class="module-card green" href="add_contact.php">
            <div class="module-icon">📞</div>
            <h4>যোগাযোগ যোগ</h4>
            <p>Call/Visit/Follow-up entry সংরক্ষণ করুন</p>
        </a>

        <a class="module-card green" href="add_recovery.php">
            <div class="module-icon">💰</div>
            <h4>রিকভারি যোগ</h4>
            <p>গ্রাহকের recovery amount entry করুন</p>
        </a>
    </div>
</div>
<?php } ?>

<div class="menu-section">
    <h3>রিপোর্ট ও অপারেশন</h3>
    <div class="section-note">সকল লগইন ইউজারের জন্য প্রয়োজনীয় report ও history</div>

    <div class="module-grid">

        <a class="module-card green" href="report_recovery.php">
            <div class="module-icon">📊</div>
            <h4>রিকভারি রিপোর্ট</h4>
            <p>Date wise recovery summary ও officer contribution</p>
        </a>

        <a class="module-card green" href="report_customer.php">
            <div class="module-icon">👤</div>
            <h4>গ্রাহক রিপোর্ট</h4>
            <p>Customer wise recovery, outstanding ও last activity</p>
        </a>

        <a class="module-card purple" href="report_officer_performance.php">
            <div class="module-icon">🏅</div>
            <h4>অফিসার পারফরম্যান্স</h4>
            <p>Officer wise customer, recovery, contact ও follow-up</p>
        </a>

        <a class="module-card red" href="report_top_defaulters.php">
            <div class="module-icon">🔥</div>
            <h4>শীর্ষ খেলাপি</h4>
            <p>বেশি outstanding থাকা customer priority list</p>
        </a>

        <a class="module-card red" href="report_no_recovery.php">
            <div class="module-icon">⚠️</div>
            <h4>রিকভারি হয়নি</h4>
            <p>যাদের এখনো কোনো recovery entry নেই</p>
        </a>

        <a class="module-card orange" href="report_no_contact.php">
            <div class="module-icon">📵</div>
            <h4>যোগাযোগ হয়নি</h4>
            <p>দীর্ঘদিন contact না হওয়া customer list</p>
        </a>

        <a class="module-card orange" href="report_promise_to_pay.php">
            <div class="module-icon">🤝</div>
            <h4>পরিশোধের প্রতিশ্রুতি</h4>
            <p>Promise to Pay এবং pending recovery follow-up</p>
        </a>

        <a class="module-card orange" href="report_no_response.php">
            <div class="module-icon">☎️</div>
            <h4>সাড়া পাওয়া যায়নি</h4>
            <p>No Response, Switch Off, Wrong Number cases</p>
        </a>

        <a class="module-card red" href="report_call_priority.php">
            <div class="module-icon">🎯</div>
            <h4>কল অগ্রাধিকার</h4>
            <p>High priority calling list with SMS/WhatsApp</p>
        </a>

        <a class="module-card dark" href="contacts.php">
            <div class="module-icon">📝</div>
            <h4>যোগাযোগ লগ</h4>
            <p>সব contact/follow-up history দেখুন</p>
        </a>

        <a class="module-card dark" href="recoveries.php">
            <div class="module-icon">💳</div>
            <h4>রিকভারি তালিকা</h4>
            <p>সব recovery transaction history</p>
        </a>

        <a class="module-card purple" href="adjustments.php">
            <div class="module-icon">➕</div>
            <h4>Loan Adjustments</h4>
            <p>Interest, charge, waiver এবং liability adjustment</p>
        </a>

        <a class="module-card dark" href="message_send_logs.php">
            <div class="module-icon">📨</div>
            <h4>মেসেজ সেন্ড লগ</h4>
            <p>SMS/WhatsApp/Call click tracking</p>
        </a>

        <a class="module-card green" href="my_messages.php">
            <div class="module-icon">🔔</div>
            <h4>আমার বার্তা</h4>
            <p>Admin থেকে পাঠানো notice/message দেখুন</p>
        </a>

        <a class="module-card dark" href="change_password.php">
            <div class="module-icon">🔑</div>
            <h4>পাসওয়ার্ড পরিবর্তন</h4>
            <p>নিজের login password update করুন</p>
        </a>

    </div>
</div>
        </div>

        <div class="quick-panel">
            <h3>আজকের দ্রুত পর্যবেক্ষণ</h3>

            <div class="quick-item">
                <span>আজকের আদায়</span>
                <strong><?php echo number_format($today_recovery, 2); ?></strong>
            </div>

            <div class="quick-item">
                <span>Due Follow-up</span>
                <strong><?php echo $due_followups; ?></strong>
            </div>

            <div class="quick-item">
                <span>No Recovery Customer</span>
                <strong><?php echo $no_recovery_count; ?></strong>
            </div>

            <div class="quick-item">
                <span>No Contact 7+ Days</span>
                <strong><?php echo $no_contact_count; ?></strong>
            </div>

            <div class="quick-item">
                <span>Promise Pending</span>
                <strong><?php echo $promise_pending_count; ?></strong>
            </div>

            <div class="quick-item">
                <span>Unread Message</span>
                <strong><?php echo $unread_message_count; ?></strong>
            </div>
        </div>
    </div>

</div>

</body>
</html>