<?php
include 'config.php';
include 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');
$branch_id = intval($_SESSION['branch_id']);
$user_id = intval($_SESSION['user_id']);
$session_officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

$message = "";

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id <= 0) {
    die("গ্রাহক নির্বাচন করা হয়নি।");
}

$stmt = $conn->prepare("SELECT c.*, o.name AS officer_name
                        FROM customers c
                        LEFT JOIN officers o ON c.assigned_officer = o.id
                        WHERE c.id = ? AND c.branch_id = ?");
$stmt->bind_param("ii", $customer_id, $branch_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    die("গ্রাহক পাওয়া যায়নি।");
}

if (!$is_admin && intval($customer['assigned_officer']) !== $session_officer_id) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $adjustment_type = trim($_POST['adjustment_type']);
    $effect = trim($_POST['effect']);
    $amount = floatval($_POST['amount']);
    $effective_date = $_POST['effective_date'];
    $note = trim($_POST['note']);

    if ($amount <= 0) {
        $message = "পরিমাণ শূন্যের বেশি হতে হবে।";
    } elseif ($adjustment_type == "") {
        $message = "Adjustment type নির্বাচন করুন।";
    } elseif ($effect != "Add" && $effect != "Subtract") {
        $message = "Effect সঠিক নয়।";
    } else {
        $stmtIns = $conn->prepare("INSERT INTO loan_adjustments 
            (branch_id, customer_id, adjustment_type, amount, effect, effective_date, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtIns->bind_param(
            "iisdsssi",
            $branch_id,
            $customer_id,
            $adjustment_type,
            $amount,
            $effect,
            $effective_date,
            $note,
            $user_id
        );

        if ($stmtIns->execute()) {
            logActivity(
                $conn,
                "Loan Adjustment",
                "Customer ID: $customer_id, Type: $adjustment_type, Effect: $effect, Amount: $amount"
            );

            header("Location: customer_view.php?id=" . $customer_id);
            exit;
        } else {
            $message = "Adjustment সংরক্ষণ করা যায়নি।";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>সুদ/চার্জ/দায় Adjustment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body { font-family: Arial, sans-serif; background:#eef2f7; margin:0; color:#1f2937; }
        .topbar { background:linear-gradient(135deg,#1f2937,#111827); color:#fff; padding:18px 22px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:12px; padding:8px 12px; background:rgba(255,255,255,0.12); border-radius:8px; }
        .container { max-width:850px; margin:30px auto; padding:0 15px; }
        .box { background:#fff; padding:24px; border-radius:18px; box-shadow:0 6px 20px rgba(15,23,42,0.06); }
        .customer-card { background:#f8fafc; border:1px solid #e5e7eb; padding:14px; border-radius:14px; margin-bottom:18px; }
        input, select, textarea { width:100%; padding:12px; margin:8px 0 15px; border:1px solid #cbd5e1; border-radius:10px; box-sizing:border-box; }
        textarea { min-height:100px; }
        button { background:#2563eb; color:#fff; border:0; padding:12px 18px; border-radius:10px; cursor:pointer; }
        .msg { color:#dc2626; font-weight:bold; margin-bottom:15px; }
        .hint { color:#64748b; font-size:13px; line-height:1.5; background:#fffbeb; border:1px solid #fde68a; padding:12px; border-radius:12px; }
    </style>
</head>
<body>

<div class="topbar">
    সুদ / চার্জ / দায় Adjustment
    <a href="logout.php">লগআউট</a>
    <a href="customer_view.php?id=<?php echo $customer_id; ?>">গ্রাহকের বিস্তারিত</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <h2>দায় Adjustment যোগ করুন</h2>

        <div class="customer-card">
            <strong><?php echo htmlspecialchars($customer['name']); ?></strong><br>
            হিসাব নম্বর: <?php echo htmlspecialchars($customer['account_number']); ?><br>
            বর্তমান সংরক্ষিত বকেয়া: <?php echo number_format($customer['outstanding'], 2); ?><br>
            অফিসার: <?php echo htmlspecialchars($customer['officer_name']); ?>
        </div>

        <?php if ($message != "") { ?>
            <div class="msg"><?php echo htmlspecialchars($message); ?></div>
        <?php } ?>

        <form method="POST">
            <label>Adjustment Type</label>
            <select name="adjustment_type" required>
                <option value="">নির্বাচন করুন</option>
                <option value="Interest">সুদ যোগ</option>
                <option value="Charge">চার্জ / খরচ যোগ</option>
                <option value="Other Liability">অন্যান্য দায় যোগ</option>
                <option value="Waiver">সুদ/চার্জ মওকুফ</option>
                <option value="Correction">Correction / সংশোধন</option>
            </select>

            <label>Effect</label>
            <select name="effect" required>
                <option value="Add">দায় বাড়াবে</option>
                <option value="Subtract">দায় কমাবে</option>
            </select>

            <label>Amount</label>
            <input type="number" step="0.01" name="amount" required>

            <label>Effective Date</label>
            <input type="date" name="effective_date" value="<?php echo date('Y-m-d'); ?>" required>

            <label>Note / Remarks</label>
            <textarea name="note" placeholder="যেমন: Q1 interest added / legal charge / waiver approval reference"></textarea>

            <div class="hint">
                Note: এই entry history হিসেবে থাকবে। পুরনো outstanding overwrite করা হবে না।
                প্রকৃত দায় হিসাব হবে: Base Outstanding + Add Adjustments - Subtract Adjustments - Recovery.
            </div>

            <br>
            <button type="submit">সংরক্ষণ করুন</button>
        </form>
    </div>
</div>

</body>
</html>