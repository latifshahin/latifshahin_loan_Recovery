<?php
include 'config.php';
include 'log_activity.php';
$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}


$branch_id = intval($_SESSION['branch_id']);
$message = "";
$errors = [];

function normalize_number($value) {
    return preg_replace('/\D+/', '', trim($value));
}

function valid_account_number($account) {
    return preg_match('/^02\d{11}$/', $account);
}

function valid_mobile_number($mobile) {
    return preg_match('/^01\d{9}$/', $mobile);
}

if (isset($_POST['upload'])) {
    if (!empty($_FILES['file']['name'])) {

        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'csv') {
            $message = "শুধু CSV ফাইল গ্রহণযোগ্য।";
        } else {
            $file = fopen($file_tmp, "r");

            if ($file === false) {
                $message = "আপলোড করা ফাইল খোলা যায়নি।";
            } else {
                fgetcsv($file);

                $inserted = 0;
                $skipped = 0;
                $row_no = 1;

                while (($data = fgetcsv($file, 2000, ",")) !== false) {
                    $row_no++;

                    if (count($data) < 7) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: ৭টি কলাম পাওয়া যায়নি।";
                        continue;
                    }

                    $name = trim($data[0]);
                    $account = normalize_number($data[1]);
                    $cl_class = trim($data[2]);
                    $outstanding = trim($data[3]);
                    $phone = normalize_number($data[4]);
                    $officer_name = trim($data[5]);
                    $status = trim($data[6]);
                    $customer_state = isset($data[7]) ? trim($data[7]) : 'Old CL';
                    $allowed_states = ['Old CL','New CL','Standard Risky','Fully Recovered'];
                    if (!in_array($customer_state, $allowed_states)) { $customer_state = 'Old CL'; }

                    if ($status === '') {
                        $status = 'New Defaulter';
                    }

                    if ($name === '') {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: গ্রাহকের নাম খালি।";
                        continue;
                    }

                    if (!valid_account_number($account)) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: হিসাব নম্বর সঠিক নয় ({$account})। 02 দিয়ে শুরু এবং 13 সংখ্যা হতে হবে।";
                        continue;
                    }

                    if (!valid_mobile_number($phone)) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: মোবাইল নম্বর সঠিক নয় ({$phone})। 01 দিয়ে শুরু এবং 11 সংখ্যা হতে হবে।";
                        continue;
                    }

                    if ($outstanding === '' || !is_numeric($outstanding)) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: বকেয়ার পরিমাণ সঠিক নয়।";
                        continue;
                    }

                    $outstanding = floatval($outstanding);

                    if ($outstanding < 0) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: বকেয়ার পরিমাণ ঋণাত্মক হতে পারবে না।";
                        continue;
                    }

                    $checkCustomer = $conn->prepare("SELECT id FROM customers WHERE account_number = ? AND branch_id = ?");
                    $checkCustomer->bind_param("si", $account, $branch_id);
                    $checkCustomer->execute();
                    $existingCustomer = $checkCustomer->get_result()->fetch_assoc();

                    if ($existingCustomer) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: একই হিসাব নম্বর ({$account}) আগে থেকেই আছে।";
                        continue;
                    }

                    $stmtOfficer = $conn->prepare("SELECT id FROM officers WHERE name = ? AND branch_id = ? LIMIT 1");
                    $stmtOfficer->bind_param("si", $officer_name, $branch_id);
                    $stmtOfficer->execute();
                    $officer = $stmtOfficer->get_result()->fetch_assoc();

                    if (!$officer) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: অফিসার পাওয়া যায়নি ({$officer_name})।";
                        continue;
                    }

                    $officer_id = $officer['id'];

                    $stmt = $conn->prepare("INSERT INTO customers (name, account_number, cl_class, outstanding, cl_start_balance, customer_state, phone, assigned_officer, status, branch_id)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $cl_start_balance = $outstanding;
                    $stmt->bind_param("sssddssisi", $name, $account, $cl_class, $outstanding, $cl_start_balance, $customer_state, $phone, $officer_id, $status, $branch_id);

                    if ($stmt->execute()) {
                        $inserted++;
                    } else {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: হিসাব নম্বর ({$account}) সংরক্ষণ করা যায়নি।";
                    }
                }

                fclose($file);
                logActivity($conn, "Import Customers", "Bulk upload done");
                $message = "ইমপোর্ট সম্পন্ন। মোট সংরক্ষিত: {$inserted} | বাদ: {$skipped}";
            }
        }
    } else {
        $message = "অনুগ্রহ করে একটি CSV ফাইল নির্বাচন করুন।";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>গ্রাহক ইমপোর্ট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .subnav { background:#fff; padding:12px 20px; border-bottom:1px solid #ddd; }
        .subnav a {
            display:inline-block;
            margin-right:10px;
            padding:8px 12px;
            background:#007bff;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
            font-size:14px;
        }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); max-width:1000px; margin:0 auto; }
        .msg { margin-bottom:15px; font-weight:bold; color:green; }
        .errbox { margin-top:20px; background:#fff3f3; padding:15px; border-radius:8px; border:1px solid #f5c2c2; }
        .errbox h3 { margin-top:0; color:#b02a37; }
        input[type=file] { margin-bottom:15px; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; cursor:pointer; }
        pre { background:#f8f9fa; padding:12px; border-radius:6px; overflow:auto; }
        ul { line-height:1.6; }
    </style>
</head>
<body>

<div class="topbar">
    গ্রাহক ইমপোর্ট
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="subnav">
    <a href="dashboard.php">ড্যাশবোর্ড</a>
    <a href="customers.php">গ্রাহক তালিকা</a>
    <a href="import_recoveries.php">রিকভারি ইমপোর্ট</a>
</div>

<div class="container">
    <div class="box">
        <h2>গ্রাহক ইমপোর্ট (CSV)</h2>

        <?php if ($message != "") { ?>
            <div class="msg"><?php echo htmlspecialchars($message); ?></div>
        <?php } ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" accept=".csv" required>
            <br>
            <button type="submit" name="upload">আপলোড করুন</button>
        </form>

        <p><strong>CSV ফরম্যাট:</strong></p>
        <pre>Name,Account,CL Class,Outstanding,Phone,Officer Name,Status,Customer State</pre>

        <p><strong>নিয়মাবলি:</strong></p>
        <ul>
            <li>হিসাব নম্বর <strong>02</strong> দিয়ে শুরু হতে হবে এবং <strong>13 সংখ্যা</strong> হতে হবে</li>
            <li>মোবাইল নম্বর <strong>01</strong> দিয়ে শুরু হতে হবে এবং <strong>11 সংখ্যা</strong> হতে হবে</li>
            <li>বকেয়ার পরিমাণ numeric হতে হবে এবং ঋণাত্মক হতে পারবে না</li>
            <li>অফিসারের নাম একই শাখায় আগে থেকে থাকতে হবে</li>
            <li>একই শাখায় duplicate account number হলে row বাদ যাবে</li>
        </ul>

        <?php if (!empty($errors)) { ?>
            <div class="errbox">
                <h3>বাদ পড়া সারি / ত্রুটি</h3>
                <ul>
                    <?php foreach ($errors as $err) { ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>
</div>

</body>
</html>