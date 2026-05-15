<?php
include 'config.php';
include 'log_activity.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');
$session_officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

if (!isset($_GET['id'])) {
    die("গ্রাহক আইডি পাওয়া যায়নি।");
}

$id = intval($_GET['id']);
$message = "";

$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
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
    $status = trim($_POST['status']);
    $last_note = trim($_POST['last_note']);
    $user_id = $_SESSION['user_id'];
    $old_status = $customer['status'];

    $update = $conn->prepare("UPDATE customers SET status=?, last_note=? WHERE id=?");
    $update->bind_param("ssi", $status, $last_note, $id);

    if ($update->execute()) {

        if ($old_status !== $status || $last_note !== '') {
            $history = $conn->prepare("INSERT INTO customer_status_history (customer_id, old_status, new_status, remarks, changed_by_user_id)
                                       VALUES (?, ?, ?, ?, ?)");
            $history->bind_param("isssi", $id, $old_status, $status, $last_note, $user_id);
            $history->execute();
        }
        logActivity($conn, "Status Update", "Customer ID: $customer_id, Status: $status");
        $message = "স্ট্যাটাস সফলভাবে আপডেট হয়েছে।";
        $customer['status'] = $status;
        $customer['last_note'] = $last_note;
    } else {
        $message = "স্ট্যাটাস আপডেট করা যায়নি।";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>স্ট্যাটাস আপডেট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:700px; margin:30px auto; background:#fff; padding:25px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); }
        select, textarea { width:100%; padding:12px; margin:8px 0 15px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        textarea { min-height:100px; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; cursor:pointer; }
        .msg { margin-bottom:15px; color:green; font-weight:bold; }
    </style>
</head>
<body>
<div class="topbar">
    স্ট্যাটাস আপডেট
    <a href="logout.php">লগআউট</a>
    <a href="customers.php">গ্রাহক তালিকা</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <h3><?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['account_number']); ?>)</h3>

    <?php if($message != "") { echo '<div class="msg">'.$message.'</div>'; } ?>

    <form method="POST">
        <label>স্ট্যাটাস</label>
        <select name="status" required>
            <?php
            $statuses = ["New Defaulter","Under Follow-up","Promise to Pay","Partial Recovery","Regularized","Legal Notice","Litigation","Unreachable"];
            foreach($statuses as $s) {
                $selected = ($customer['status'] == $s) ? 'selected' : '';
                echo "<option value=\"$s\" $selected>$s</option>";
            }
            ?>
        </select>

        <label>মন্তব্য / Remarks</label>
        <textarea name="last_note"><?php echo htmlspecialchars($customer['last_note']); ?></textarea>

        <button type="submit">আপডেট করুন</button>
    </form>
</div>
</body>
</html>