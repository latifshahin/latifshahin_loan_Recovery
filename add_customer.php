<?php
include 'config.php';
include 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_type = $_SESSION['user_type']; // 'free' or 'paid'
$message = "";
$message_type = ""; // success or danger

// --- নতুন লজিক: ফ্রি ইউজার লিমিট চেক ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if ($user_type === 'free') {
        // চেক করা হচ্ছে এই ইউজার অলরেডি কয়টি কাস্টমার এ্যাড করেছে
        // (যেহেতু ফ্রি ইউজারদের নির্দিষ্ট ব্রাঞ্চ নেই, তাই তাদের তৈরি করা কাস্টমার চেক করা হচ্ছে)
        $check_sql = "SELECT COUNT(*) as total FROM customers WHERE branch_id = 0 AND assigned_officer = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $total_count = $stmt_check->get_result()->fetch_assoc()['total'];

        if ($total_count >= $free_user_customer_limit) {
            $message = "আপনি ফ্রি ভার্সন ব্যবহার করছেন। সর্বোচ্চ $free_user_customer_limit জন কাস্টমার এ্যাড করার সীমা অতিক্রম করেছেন। আনলিমিটেড এ্যাড করতে পেইড ভার্সন নিন।";
            $message_type = "danger";
        }
    }

    // যদি কোনো এরর মেসেজ না থাকে, তবেই সেভ হবে
    if ($message === "") {
        $name = trim($_POST['name']);
        $account_number = trim($_POST['account_number']);
        $cl_class = trim($_POST['cl_class']);
        $outstanding = floatval($_POST['outstanding']);
        $phone = trim($_POST['phone']);
        $customer_state = trim($_POST['customer_state'] ?? 'Old CL');
        
        // পেইড ইউজার হলে সিলেক্ট করা ব্রাঞ্চ এবং অফিসার, ফ্রি হলে ডিফল্ট
        $branch_id = ($user_type === 'free') ? 0 : intval($_SESSION['branch_id']);
        $assigned_officer = ($user_type === 'free') ? $user_id : intval($_POST['assigned_officer']);
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("INSERT INTO customers (name, account_number, cl_class, outstanding, cl_start_balance, customer_state, phone, branch_id, assigned_officer, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $cl_start_balance = $outstanding;
        $stmt->bind_param("sssddssiis", $name, $account_number, $cl_class, $outstanding, $cl_start_balance, $customer_state, $phone, $branch_id, $assigned_officer, $status);

        if ($stmt->execute()) {
            logActivity($conn, "Add Customer", "Customer: $name ($account_number)");
            $message = "কাস্টমার সফলভাবে যোগ করা হয়েছে।";
            $message_type = "success";
        } else {
            $message = "ভুল হয়েছে: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// অফিসার ড্রপডাউনের জন্য ডেটা আনা (পেইড ইউজারদের জন্য)
$officers = [];
if ($user_type !== 'free') {
    $branch_id = intval($_SESSION['branch_id']);
    $res = $conn->query("SELECT id, name FROM officers WHERE status='Active' AND branch_id = $branch_id ORDER BY name ASC");
    while($row = $res->fetch_assoc()) $officers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Customer - Recovery App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; padding-top: 20px; }
        .container { max-width: 700px; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>নতুন গ্রাহক যোগ করুন</h3>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">ড্যাশবোর্ড</a>
    </div>

    <?php if($message !== ""): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">গ্রাহকের নাম</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">হিসাব নম্বর</label>
                <input type="text" name="account_number" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">শ্রেণি (CL Class)</label>
                <input type="text" name="cl_class" class="form-control">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">বর্তমান বকেয়া</label>
            <input type="number" step="0.01" name="outstanding" class="form-control" value="0">
        </div>
        <div class="mb-3">
            <label class="form-label">মোবাইল নম্বর</label>
            <input type="text" name="phone" class="form-control">
        </div>

        <?php if($user_type !== 'free'): ?>
        <div class="mb-3">
            <label class="form-label">দায়িত্বপ্রাপ্ত অফিসার</label>
            <select name="assigned_officer" class="form-select" required>
                <option value="">অফিসার নির্বাচন করুন</option>
                <?php foreach($officers as $off): ?>
                    <option value="<?php echo $off['id']; ?>"><?php echo $off['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="mb-4">
            <label class="form-label">বর্তমান অবস্থা (Status)</label>
            <select name="status" class="form-select">
                <option value="New Defaulter">New Defaulter</option>
                <option value="Under Follow-up">Under Follow-up</option>
                <option value="Legal Notice">Legal Notice</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary w-100 p-2">সংরক্ষণ করুন</button>
    </form>
</div>
</body>
</html>