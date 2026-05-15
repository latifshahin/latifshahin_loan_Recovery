<?php
include 'config.php';
include 'log_activity.php';
include 'role_scope.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = currentRole();
$ids = roleIds();
$message = "";

$selected_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$selected_officer_id = isset($_GET['officer_id']) ? intval($_GET['officer_id']) : 0;

if ($selected_customer_id > 0 && !canAccessCustomer($conn, $selected_customer_id)) {
    die("এই গ্রাহক আপনার অনুমোদিত scope-এর ভিতরে নেই।");
}

$selectedCustomer = null;
if ($selected_customer_id > 0) {
    $selectedCustomer = getScopedCustomer($conn, $selected_customer_id);
    if (!$selectedCustomer) {
        die("Customer not found.");
    }
    if ($selected_officer_id <= 0) {
        $selected_officer_id = intval($selectedCustomer['assigned_officer']);
    }
}

if ($role === 'officer') {
    $selected_officer_id = $ids['officer_id'];
}

$customers = scopedCustomers($conn);
$officers = scopedOfficers($conn, $selected_customer_id);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = intval($_POST['customer_id']);
    $officer_id = intval($_POST['officer_id']);
    $contact_type = trim($_POST['contact_type']);
    $action_result = trim($_POST['action_result']);
    $commitment_amount = ($_POST['commitment_amount'] !== '') ? floatval($_POST['commitment_amount']) : null;
    $note = trim($_POST['note']);
    $next_followup = $_POST['next_followup'];

    if (!canAccessCustomer($conn, $customer_id)) {
        $message = "এই গ্রাহক আপনার অনুমোদিত scope-এর ভিতরে নেই।";
    } else {
        $cust = getScopedCustomer($conn, $customer_id);
        if ($role === 'officer') {
            $officer_id = $ids['officer_id'];
        }

        $checkOfficer = $conn->prepare("SELECT id FROM officers WHERE id = ? AND branch_id = ? LIMIT 1");
        $checkOfficer->bind_param("ii", $officer_id, $cust['branch_id']);
        $checkOfficer->execute();
        $validOfficer = $checkOfficer->get_result()->fetch_assoc();

        if (!$validOfficer) {
            $message = "নির্বাচিত অফিসার এই গ্রাহকের শাখার অধীনে নেই।";
        } else {
            $stmt = $conn->prepare("INSERT INTO contacts (customer_id, officer_id, contact_type, action_result, commitment_amount, note, next_followup) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdss", $customer_id, $officer_id, $contact_type, $action_result, $commitment_amount, $note, $next_followup);

            if ($stmt->execute()) {
                logActivity($conn, "Add Contact", "Customer ID: $customer_id");
                header("Location: customer_view.php?id=" . $customer_id);
                exit;
            } else {
                $message = "যোগাযোগ লগ সংরক্ষণ করা যায়নি।";
            }
        }
    }

    $selected_customer_id = $customer_id;
    $selected_officer_id = $officer_id;
    $selectedCustomer = getScopedCustomer($conn, $selected_customer_id);
    $customers = scopedCustomers($conn);
    $officers = scopedOfficers($conn, $selected_customer_id);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ফলো-আপ যোগ করুন</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:800px; margin:30px auto; background:#fff; padding:25px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); }
        input, select, textarea { width:100%; padding:12px; margin:8px 0 15px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        textarea { min-height:100px; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; cursor:pointer; }
        .msg { margin-bottom:15px; color:red; font-weight:bold; }
    </style>
</head>
<body>
<div class="topbar">
    ফলো-আপ যোগ করুন
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <?php if($message != "") { echo '<div class="msg">'.htmlspecialchars($message).'</div>'; } ?>

    <form method="POST">
        <label>গ্রাহক</label>
        <?php if ($selected_customer_id > 0 && $selectedCustomer) { ?>
            <input type="text" value="<?php echo htmlspecialchars($selectedCustomer['name']) . ' (' . htmlspecialchars($selectedCustomer['account_number']) . ')'; ?>" readonly>
            <input type="hidden" name="customer_id" value="<?php echo intval($selected_customer_id); ?>">
        <?php } else { ?>
            <select name="customer_id" required>
                <option value="">গ্রাহক নির্বাচন করুন</option>
                <?php while($customer = $customers->fetch_assoc()) { ?>
                    <option value="<?php echo intval($customer['id']); ?>" <?php if($selected_customer_id == $customer['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($customer['name']) . " (" . htmlspecialchars($customer['account_number']) . ")"; ?>
                    </option>
                <?php } ?>
            </select>
        <?php } ?>

        <label>অফিসার</label>
        <?php if ($role === 'officer') { ?>
            <?php
            $stmtOfficer = $conn->prepare("SELECT name FROM officers WHERE id = ? LIMIT 1");
            $stmtOfficer->bind_param("i", $ids['officer_id']);
            $stmtOfficer->execute();
            $officerRow = $stmtOfficer->get_result()->fetch_assoc();
            ?>
            <input type="text" value="<?php echo htmlspecialchars($officerRow['name'] ?? ''); ?>" readonly>
            <input type="hidden" name="officer_id" value="<?php echo intval($ids['officer_id']); ?>">
        <?php } else { ?>
            <select name="officer_id" required>
                <option value="">অফিসার নির্বাচন করুন</option>
                <?php while($officer = $officers->fetch_assoc()) { ?>
                    <option value="<?php echo intval($officer['id']); ?>" <?php if($selected_officer_id == $officer['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($officer['name']); ?>
                    </option>
                <?php } ?>
            </select>
        <?php } ?>

        <label>যোগাযোগের ধরন</label>
        <select name="contact_type" required>
            <option value="Call">কল</option>
            <option value="Visit">সাক্ষাৎ</option>
            <option value="SMS">এসএমএস</option>
            <option value="Meeting">মিটিং</option>
            <option value="Notice">নোটিশ</option>
        </select>

        <label>ফলাফল</label>
        <select name="action_result" required>
            <option value="Promise to Pay">পরিশোধের প্রতিশ্রুতি</option>
            <option value="No Response">সাড়া পাওয়া যায়নি</option>
            <option value="Switch Off">ফোন বন্ধ</option>
            <option value="Visited">সাক্ষাৎ করা হয়েছে</option>
            <option value="Call Failed">কল ব্যর্থ</option>
            <option value="Paid Today">আজ পরিশোধ করেছে</option>
            <option value="Partial Paid">আংশিক পরিশোধ</option>
            <option value="Rescheduled">পুনঃনির্ধারিত</option>
            <option value="Wrong Number">ভুল নম্বর</option>
            <option value="Not Interested">আগ্রহী নয়</option>
        </select>

        <label>প্রতিশ্রুতির পরিমাণ (যদি থাকে)</label>
        <input type="number" step="0.01" name="commitment_amount">

        <label>মন্তব্য</label>
        <textarea name="note" required></textarea>

        <label>পরবর্তী ফলো-আপ তারিখ</label>
        <input type="date" name="next_followup" value="<?php echo date('Y-m-d'); ?>">

        <button type="submit">সংরক্ষণ করুন</button>
    </form>
</div>
</body>
</html>
