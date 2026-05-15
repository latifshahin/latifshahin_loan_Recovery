<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];
$is_admin = in_array($_SESSION['role'], $admin_roles);
$can_manage_customer = in_array($_SESSION['role'], ['admin', 'ho_admin']);
$can_send_message = in_array($_SESSION['role'], ['officer', 'admin', 'ho_admin', 'circle_admin', 'zone_admin']);

$session_officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;
$branch_id = intval($_SESSION['branch_id']);

if (!isset($_GET['id'])) {
    die("গ্রাহক আইডি পাওয়া যায়নি।");
}

$id = intval($_GET['id']);

$where = "customers.id = ?";

if ($_SESSION['role'] === 'ho_admin') {
    $sql = "SELECT customers.*, officers.name AS officer_name
            FROM customers
            LEFT JOIN officers ON customers.assigned_officer = officers.id
            WHERE $where";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

} elseif ($_SESSION['role'] === 'circle_admin') {
    $sql = "SELECT customers.*, officers.name AS officer_name
            FROM customers
            LEFT JOIN officers ON customers.assigned_officer = officers.id
            LEFT JOIN branches b ON customers.branch_id = b.id
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE $where AND z.circle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $_SESSION['circle_id']);

} elseif ($_SESSION['role'] === 'zone_admin') {
    $sql = "SELECT customers.*, officers.name AS officer_name
            FROM customers
            LEFT JOIN officers ON customers.assigned_officer = officers.id
            LEFT JOIN branches b ON customers.branch_id = b.id
            WHERE $where AND b.zone_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $_SESSION['zone_id']);

} elseif ($_SESSION['role'] === 'admin') {
    $sql = "SELECT customers.*, officers.name AS officer_name
            FROM customers
            LEFT JOIN officers ON customers.assigned_officer = officers.id
            WHERE $where AND customers.branch_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $_SESSION['branch_id']);

} else {
    $sql = "SELECT customers.*, officers.name AS officer_name
            FROM customers
            LEFT JOIN officers ON customers.assigned_officer = officers.id
            WHERE $where 
            AND customers.branch_id = ? 
            AND customers.assigned_officer = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $id, $_SESSION['branch_id'], $_SESSION['officer_id']);
}
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    die("গ্রাহক পাওয়া যায়নি।");
}

include 'log_activity.php';
logActivity($conn, "View Customer", "Viewed: " . $customer['name'] . " (" . $customer['account_number'] . ")");

if ($_SESSION['role'] === 'officer' && intval($customer['assigned_officer']) !== $session_officer_id) {
    header("Location: dashboard.php");
    exit;
}

$contacts_stmt = $conn->prepare("SELECT contacts.*, officers.name AS officer_name
                                 FROM contacts
                                 LEFT JOIN officers ON contacts.officer_id = officers.id
                                 WHERE customer_id = ?
                                 ORDER BY id DESC");
$contacts_stmt->bind_param("i", $id);
$contacts_stmt->execute();
$contacts = $contacts_stmt->get_result();

$recoveries_stmt = $conn->prepare("SELECT recoveries.*, officers.name AS officer_name
                                   FROM recoveries
                                   LEFT JOIN officers ON recoveries.officer_id = officers.id
                                   WHERE customer_id = ?
                                   ORDER BY id DESC");
$recoveries_stmt->bind_param("i", $id);
$recoveries_stmt->execute();
$recoveries = $recoveries_stmt->get_result();

$status_history_stmt = $conn->prepare("SELECT h.*, u.name AS changed_by_name
                                       FROM customer_status_history h
                                       LEFT JOIN users u ON h.changed_by_user_id = u.id
                                       WHERE h.customer_id = ?
                                       ORDER BY h.id DESC");
$status_history_stmt->bind_param("i", $id);
$status_history_stmt->execute();
$status_history = $status_history_stmt->get_result();

$template_stmt = $conn->prepare("
    SELECT *
    FROM message_templates
    WHERE status = 'Active'
    ORDER BY title ASC
");
$template_stmt->execute();
$templates = $template_stmt->get_result();

$adj_stmt = $conn->prepare("SELECT 
                            COALESCE(SUM(CASE WHEN effect='Add' THEN amount ELSE 0 END),0) AS total_add,
                            COALESCE(SUM(CASE WHEN effect='Subtract' THEN amount ELSE 0 END),0) AS total_subtract
                            FROM loan_adjustments
                            WHERE customer_id = ? AND branch_id = ?");
$adj_stmt->bind_param("ii", $id, $branch_id);
$adj_stmt->execute();
$adj_summary = $adj_stmt->get_result()->fetch_assoc();

$total_adjust_add = $adj_summary['total_add'];
$total_adjust_subtract = $adj_summary['total_subtract'];

$rec_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_recovery
                            FROM recoveries
                            WHERE customer_id = ?");
$rec_stmt->bind_param("i", $id);
$rec_stmt->execute();
$rec_summary = $rec_stmt->get_result()->fetch_assoc();

$total_recovery_customer = $rec_summary['total_recovery'];
$audit_diff = abs(floatval($customer['cl_start_balance'] ?? 0) - (floatval($customer['outstanding']) + floatval($total_recovery_customer)));
$audit_status = ($audit_diff < 0.01) ? 'OK' : 'Mismatch';

$actual_liability = floatval($customer['outstanding']) + floatval($total_adjust_add) - floatval($total_adjust_subtract);


$clean_phone = preg_replace('/\D+/', '', $customer['phone']);
$wa_phone = $clean_phone;

if (substr($wa_phone, 0, 1) == '0') {
    $wa_phone = '88' . $wa_phone;
}

$customer_name = $customer['name'];
$account_number = $customer['account_number'];
$outstanding = number_format($customer['outstanding'], 2);
$phone = $customer['phone'];
$status = $customer['status'];
$officer_name = $customer['officer_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>গ্রাহকের বিস্তারিত</title>
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

        .topbar-links a:hover {
            background: rgba(255,255,255,0.22);
        }

        .container {
            max-width: 1350px;
            margin: auto;
            padding: 22px;
        }

        .customer-hero {
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            margin-bottom: 18px;
        }

        .customer-header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .customer-header h2 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        .subtext {
            color: #64748b;
            font-size: 14px;
        }

        .status-pill {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-weight: bold;
            font-size: 13px;
        }

        .info-grid {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
        }

        .info-card span {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .info-card strong {
            font-size: 16px;
            color: #111827;
            word-break: break-word;
        }

        .outstanding-card {
            background: linear-gradient(135deg, #dc2626, #fb7185);
            color: #fff;
            border: none;
        }

        .outstanding-card span,
        .outstanding-card strong {
            color: #fff;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 18px;
            align-items: start;
        }

        .box {
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(15,23,42,0.06);
            margin-bottom: 18px;
            overflow-x: auto;
        }

        .box h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .action-panel {
            position: sticky;
            top: 14px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            margin-right: 8px;
            margin-bottom: 8px;
            border: 0;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover { filter: brightness(0.93); }

        .btn-green { background: #16a34a; }
        .btn-orange { background: #f97316; }
        .btn-red { background: #dc2626; }
        .btn-dark { background: #475569; }
        .btn-purple { background: #7c3aed; }

        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .action-group .btn {
            margin: 0;
        }

        select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            margin: 8px 0 12px;
            font-size: 14px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .hint {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        table th, table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        table th {
            background: #f8fafc;
            color: #334155;
            font-weight: bold;
        }

        table tr:hover {
            background: #f8fafc;
        }

        .muted { color: #64748b; }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .count-badge {
            background: #eef2ff;
            color: #3730a3;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .note-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 12px;
            border-radius: 12px;
            line-height: 1.5;
            margin-top: 14px;
        }

        @media (max-width: 960px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }

            .action-panel {
                position: static;
            }
        }

        @media (max-width: 650px) {
            .container {
                padding: 14px;
            }

            .customer-hero,
            .box {
                padding: 16px;
                border-radius: 14px;
            }

            .topbar-links a {
                margin-left: 0;
                margin-right: 6px;
                margin-top: 8px;
            }

            .customer-header h2 {
                font-size: 22px;
            }

            .btn {
                width: 100%;
                text-align: center;
                margin-right: 0;
            }

            .action-group {
                display: block;
            }
        }
    </style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="topbar-title">গ্রাহকের বিস্তারিত</div>
        <div style="font-size:13px; color:#cbd5e1; margin-top:4px;">
            <?php echo htmlspecialchars($_SESSION['name']); ?> — <?php echo htmlspecialchars($_SESSION['role']); ?>
        </div>
    </div>

    <div class="topbar-links">
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="customers.php">গ্রাহক তালিকা</a>
        <a href="logout.php">লগআউট</a>
    </div>
</div>

<div class="container">

    <div class="customer-hero">
        <div class="customer-header">
            <div>
                <h2><?php echo htmlspecialchars($customer['name']); ?></h2>
                <div class="subtext">
                    হিসাব নম্বর: <?php echo htmlspecialchars($customer['account_number']); ?>
                </div>
            </div>

            <div>
                <span class="status-pill">
                    <?php echo htmlspecialchars($customer['status']); ?>
                </span>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <span>শ্রেণি</span>
                <strong><?php echo htmlspecialchars($customer['cl_class']); ?></strong>
            </div>

            <div class="info-card outstanding-card">
                <span>বকেয়া</span>
                <strong><?php echo number_format($customer['outstanding'], 2); ?></strong>
            </div>

            <div class="info-card">
                <span>Customer State</span>
                <strong><?php echo htmlspecialchars($customer['customer_state'] ?? 'Old CL'); ?></strong>
            </div>

            <div class="info-card">
                <span>CL Start Balance</span>
                <strong><?php echo number_format($customer['cl_start_balance'] ?? 0, 2); ?></strong>
            </div>

            <div class="info-card">
                <span>মোট আদায়</span>
                <strong><?php echo number_format($total_recovery_customer, 2); ?></strong>
            </div>

            <div class="info-card">
                <span>Audit</span>
                <strong><?php echo htmlspecialchars($audit_status); ?></strong>
            </div>
            <div class="info-card">
                <span>যোগকৃত সুদ/চার্জ</span>
                <strong><?php echo number_format($total_adjust_add, 2); ?></strong>
            </div>
            
            <div class="info-card">
                <span>মওকুফ/কমানো দায়</span>
                <strong><?php echo number_format($total_adjust_subtract, 2); ?></strong>
            </div>
            
            <div class="info-card outstanding-card">
                <span>প্রকৃত দায়স্থিতি</span>
                <strong><?php echo number_format($actual_liability, 2); ?></strong>
            </div>
            <div class="info-card">
                <span>মোবাইল নম্বর</span>
                <strong><?php echo htmlspecialchars($customer['phone']); ?></strong>
            </div>

            <div class="info-card">
                <span>দায়িত্বপ্রাপ্ত অফিসার</span>
                <strong><?php echo htmlspecialchars($customer['officer_name']); ?></strong>
            </div>

            <div class="info-card">
                <span>প্রথম খেলাপির তারিখ</span>
                <strong><?php echo htmlspecialchars($customer['first_default_date']); ?></strong>
            </div>

            <div class="info-card">
                <span>সর্বশেষ মন্তব্য</span>
                <strong><?php echo nl2br(htmlspecialchars($customer['last_note'])); ?></strong>
            </div>
        </div>
    </div>

    <div class="layout-grid">

        <div>
            <div class="box">
                <div class="section-title">
                    <h3>যোগাযোগের ইতিহাস</h3>
                    <span class="count-badge"><?php echo $contacts->num_rows; ?> টি</span>
                </div>

                <?php if ($contacts->num_rows > 0) { ?>
                <table>
                    <tr>
                        <th>তারিখ</th>
                        <th>অফিসার</th>
                        <th>ধরন</th>
                        <th>ফলাফল</th>
                        <th>প্রতিশ্রুতির পরিমাণ</th>
                        <th>মন্তব্য</th>
                        <th>পরবর্তী ফলো-আপ</th>
                    </tr>

                    <?php while($row = $contacts->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['action_result']); ?></td>
                        <td><?php echo $row['commitment_amount'] !== null ? number_format($row['commitment_amount'], 2) : ''; ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['note'])); ?></td>
                        <td><?php echo htmlspecialchars($row['next_followup']); ?></td>
                    </tr>
                    <?php } ?>
                </table>
                <?php } else { ?>
                    <p class="muted">এখনো কোনো যোগাযোগের ইতিহাস পাওয়া যায়নি।</p>
                <?php } ?>
            </div>

            <div class="box">
                <div class="section-title">
                    <h3>রিকভারির ইতিহাস</h3>
                    <span class="count-badge"><?php echo $recoveries->num_rows; ?> টি</span>
                </div>

                <?php if ($recoveries->num_rows > 0) { ?>
                <table>
                    <tr>
                        <th>তারিখ</th>
                        <th>অফিসার</th>
                        <th>পরিমাণ</th>
                        <th>মন্তব্য</th>
                    </tr>

                    <?php while($row = $recoveries->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['recovery_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                        <td><?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['note'])); ?></td>
                    </tr>
                    <?php } ?>
                </table>
                <?php } else { ?>
                    <p class="muted">এখনো কোনো রিকভারি ইতিহাস পাওয়া যায়নি।</p>
                <?php } ?>
                            </div>
                <?php
                $adjustments_stmt = $conn->prepare("SELECT a.*, u.name AS created_by_name
                                                    FROM loan_adjustments a
                                                    LEFT JOIN users u ON a.created_by = u.id
                                                    WHERE a.customer_id = ? AND a.branch_id = ?
                                                    ORDER BY a.effective_date DESC, a.id DESC");
                $adjustments_stmt->bind_param("ii", $id, $branch_id);
                $adjustments_stmt->execute();
                $adjustments = $adjustments_stmt->get_result();
                ?>
                
                <div class="box">
                    <div class="section-title">
                        <h3>সুদ / চার্জ / দায় Adjustment History</h3>
                        <span class="count-badge"><?php echo $adjustments->num_rows; ?> টি</span>
                    </div>
                
                    <?php if ($adjustments->num_rows > 0) { ?>
                    <table>
                        <tr>
                            <th>তারিখ</th>
                            <th>ধরন</th>
                            <th>Effect</th>
                            <th>পরিমাণ</th>
                            <th>মন্তব্য</th>
                            <th>Entry By</th>
                        </tr>
                
                        <?php while($row = $adjustments->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['effective_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['adjustment_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['effect']); ?></td>
                            <td><?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($row['note'])); ?></td>
                            <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                        </tr>
                        <?php } ?>
                    </table>
                    <?php } else { ?>
                        <p class="muted">এখনো কোনো adjustment history নেই।</p>
                    <?php } ?>
                </div>
            <div class="box">
                <div class="section-title">
                    <h3>স্ট্যাটাস পরিবর্তনের ইতিহাস</h3>
                    <span class="count-badge"><?php echo $status_history->num_rows; ?> টি</span>
                </div>

                <?php if ($status_history->num_rows > 0) { ?>
                <table>
                    <tr>
                        <th>তারিখ</th>
                        <th>পরিবর্তনকারী</th>
                        <th>পুরনো স্ট্যাটাস</th>
                        <th>নতুন স্ট্যাটাস</th>
                        <th>মন্তব্য</th>
                    </tr>

                    <?php while($row = $status_history->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['changed_at']); ?></td>
                        <td><?php echo htmlspecialchars($row['changed_by_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['old_status']); ?></td>
                        <td><?php echo htmlspecialchars($row['new_status']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['remarks'])); ?></td>
                    </tr>
                    <?php } ?>
                </table>
                <?php } else { ?>
                    <p class="muted">এখনো কোনো স্ট্যাটাস ইতিহাস পাওয়া যায়নি।</p>
                <?php } ?>
            </div>
        </div>

        <div class="action-panel">

            <div class="box">
                <h3>দ্রুত অ্যাকশন</h3>

                <div class="action-group">
                    <?php if ($can_manage_customer) { ?>
                        <a class="btn btn-purple" href="customer_edit.php?id=<?php echo $customer['id']; ?>">গ্রাহক সংশোধন</a>
                    <?php } ?>

                    <a class="btn btn-dark" href="status_update.php?id=<?php echo $customer['id']; ?>">স্ট্যাটাস আপডেট</a>
                    <a class="btn btn-orange" href="add_contact.php?customer_id=<?php echo $customer['id']; ?>&officer_id=<?php echo intval($customer['assigned_officer']); ?>">ফলো-আপ যোগ</a>
                    <a class="btn btn-green" href="add_recovery.php?customer_id=<?php echo $customer['id']; ?>&officer_id=<?php echo intval($customer['assigned_officer']); ?>">রিকভারি যোগ</a>
                    <a class="btn btn-red" href="add_adjustment.php?customer_id=<?php echo $customer['id']; ?>">সুদ/চার্জ যোগ</a>
                </div>

                <div class="note-box">
                    এই প্যানেল থেকে গ্রাহকের status, follow-up, recovery এবং communication দ্রুত সম্পন্ন করা যাবে।
                </div>
            </div>
            <?php if ($can_send_message) { ?>
            <div class="box">
                <h3>SMS / WhatsApp</h3>
                <div class="hint">
                    Template নির্বাচন করলে message auto fill হবে। প্রয়োজনে পাঠানোর আগে text edit করতে পারবেন।
                </div>

                <label><strong>মেসেজ টেমপ্লেট</strong></label>
                <select id="msg_template">
                    <?php while($tpl = $templates->fetch_assoc()) { ?>
                        <option value="<?php echo htmlspecialchars($tpl['template_text']); ?>">
                            <?php echo htmlspecialchars($tpl['title']); ?>
                        </option>
                    <?php } ?>
                </select>

                <textarea id="msg_text"></textarea>

                <div class="action-group">
                    <a class="btn btn-red" href="tel:<?php echo htmlspecialchars($clean_phone); ?>" onclick="logMessageSend('Call')">কল করুন</a>
                    <a class="btn btn-orange" href="#" onclick="sendSms(); return false;">SMS পাঠান</a>
                    <a class="btn btn-green" href="#" onclick="sendWhatsApp(); return false;">WhatsApp পাঠান</a>
                </div>
            </div>
            <?php } ?>
        </div>

    </div>
</div>

<script>
    const templateSelect = document.getElementById('msg_template');
    const messageBox = document.getElementById('msg_text');

    const customerData = {
    name: <?php echo json_encode($customer_name); ?>,
    account_number: <?php echo json_encode($account_number); ?>,
    outstanding: <?php echo json_encode($outstanding); ?>,
    actual_liability: <?php echo json_encode(number_format($actual_liability, 2)); ?>,
    phone: <?php echo json_encode($phone); ?>,
    status: <?php echo json_encode($status); ?>,
    officer_name: <?php echo json_encode($officer_name); ?>
};

    function applyVariables(text) {
        return text
            .replaceAll('{name}', customerData.name)
            .replaceAll('{account_number}', customerData.account_number)
            .replaceAll('{outstanding}', customerData.outstanding)
            .replaceAll('{phone}', customerData.phone)
            .replaceAll('{status}', customerData.status)
            .replaceAll('{actual_liability}', customerData.actual_liability)
            .replaceAll('{officer_name}', customerData.officer_name);
    }

    function setTemplateText() {
        if (!templateSelect || !messageBox) return;
        const rawText = templateSelect.value || "";
        messageBox.value = applyVariables(rawText);
    }

    function logMessageSend(channel) {
        const formData = new FormData();
        formData.append('customer_id', '<?php echo intval($customer["id"]); ?>');
        formData.append('channel', channel);
        formData.append('phone', '<?php echo htmlspecialchars($clean_phone); ?>');
        formData.append('message_text', messageBox ? messageBox.value : '');

        fetch('log_message_send.php', {
            method: 'POST',
            body: formData
        }).catch(function(error) {
            console.log('Message log failed', error);
        });
    }

    function sendSms() {
        logMessageSend('SMS');

        const phone = "<?php echo htmlspecialchars($clean_phone); ?>";
        const msg = encodeURIComponent(messageBox.value);
        window.location.href = "sms:" + phone + "?body=" + msg;
    }

    function sendWhatsApp() {
        logMessageSend('WhatsApp');

        const phone = "<?php echo htmlspecialchars($wa_phone); ?>";
        const msg = encodeURIComponent(messageBox.value);
        window.open("https://wa.me/" + phone + "?text=" + msg, "_blank");
    }

    if (templateSelect) {
        templateSelect.addEventListener('change', setTemplateText);
        setTemplateText();
    }
</script>

</body>
</html>