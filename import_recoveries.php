<?php
include 'config.php';
include 'log_activity.php';

$admin_roles = ['admin', 'ho_admin', 'circle_admin', 'zone_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0;
$circle_id = isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0;
$zone_id = isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0;

$message = "";
$errors = [];

function normalize_number($value) {
    return preg_replace('/\D+/', '', trim($value));
}

function valid_account_number($account) {
    return preg_match('/^02\d{11}$/', $account);
}

function valid_date_ymd($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (isset($_POST['upload'])) {

    if (empty($_FILES['file']['name'])) {
        $message = "অনুগ্রহ করে একটি CSV ফাইল নির্বাচন করুন।";
    } else {

        $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        if ($file_ext !== 'csv') {
            $message = "শুধু CSV ফাইল গ্রহণযোগ্য।";
        } else {

            $file = fopen($_FILES['file']['tmp_name'], "r");

            if ($file === false) {
                $message = "আপলোড করা ফাইল খোলা যায়নি।";
            } else {

                fgetcsv($file);

                $inserted = 0;
                $skipped = 0;
                $row_no = 1;

                while (($data = fgetcsv($file, 5000, ",")) !== false) {
                    $row_no++;

                    if (count($data) < 4) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: প্রয়োজনীয় ৪টি কলাম পাওয়া যায়নি।";
                        continue;
                    }

                    $account = normalize_number($data[0]);
                    $amount_raw = trim($data[1]);
                    $date_raw = trim($data[2]);
                    $note = trim($data[3]);

                    if (!valid_account_number($account)) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: হিসাব নম্বর সঠিক নয় ({$account})। 02 দিয়ে শুরু এবং 13 সংখ্যা হতে হবে।";
                        continue;
                    }

                    if ($amount_raw === '' || !is_numeric($amount_raw)) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: রিকভারির পরিমাণ সঠিক নয়।";
                        continue;
                    }

                    $amount = floatval($amount_raw);

                    if ($amount <= 0) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: রিকভারির পরিমাণ শূন্যের বেশি হতে হবে।";
                        continue;
                    }

                    $date = date('Y-m-d', strtotime($date_raw));

                    if (!valid_date_ymd($date)) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: তারিখ সঠিক নয়। Format হবে YYYY-MM-DD।";
                        continue;
                    }

                    if ($role === 'ho_admin') {
                        $stmtC = $conn->prepare("SELECT c.id, c.assigned_officer, c.branch_id
                                                 FROM customers c
                                                 WHERE c.account_number = ?
                                                 LIMIT 1");
                        $stmtC->bind_param("s", $account);
                    } elseif ($role === 'circle_admin') {
                        $stmtC = $conn->prepare("SELECT c.id, c.assigned_officer, c.branch_id
                                                 FROM customers c
                                                 INNER JOIN branches b ON c.branch_id = b.id
                                                 INNER JOIN zones z ON b.zone_id = z.id
                                                 WHERE c.account_number = ?
                                                 AND z.circle_id = ?
                                                 LIMIT 1");
                        $stmtC->bind_param("si", $account, $circle_id);
                    } elseif ($role === 'zone_admin') {
                        $stmtC = $conn->prepare("SELECT c.id, c.assigned_officer, c.branch_id
                                                 FROM customers c
                                                 INNER JOIN branches b ON c.branch_id = b.id
                                                 WHERE c.account_number = ?
                                                 AND b.zone_id = ?
                                                 LIMIT 1");
                        $stmtC->bind_param("si", $account, $zone_id);
                    } else {
                        $stmtC = $conn->prepare("SELECT c.id, c.assigned_officer, c.branch_id
                                                 FROM customers c
                                                 WHERE c.account_number = ?
                                                 AND c.branch_id = ?
                                                 LIMIT 1");
                        $stmtC->bind_param("si", $account, $branch_id);
                    }

                    $stmtC->execute();
                    $customer = $stmtC->get_result()->fetch_assoc();

                    if (!$customer) {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: আপনার scope-এর মধ্যে গ্রাহক পাওয়া যায়নি ({$account})।";
                        continue;
                    }

                    $customer_id = intval($customer['id']);
                    $officer_id = intval($customer['assigned_officer']);

                    $stmt = $conn->prepare("INSERT INTO recoveries 
                                            (customer_id, officer_id, amount, recovery_date, note)
                                            VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iidss", $customer_id, $officer_id, $amount, $date, $note);

                    if ($stmt->execute()) {
                        $update = $conn->prepare("UPDATE customers
                                                  SET outstanding = GREATEST(outstanding - ?, 0),
                                                      customer_state = CASE WHEN GREATEST(outstanding - ?, 0) <= 0 THEN 'Fully Recovered' ELSE customer_state END
                                                  WHERE id = ?");
                        $update->bind_param("ddi", $amount, $amount, $customer_id);
                        $update->execute();

                        $inserted++;
                    } else {
                        $skipped++;
                        $errors[] = "সারি {$row_no}: রিকভারি সংরক্ষণ করা যায়নি ({$account})।";
                    }
                }

                fclose($file);

                logActivity($conn, "Import Recovery", "Inserted: {$inserted}, Skipped: {$skipped}");

                $message = "রিকভারি ইমপোর্ট সম্পন্ন। সংরক্ষিত: {$inserted} | বাদ: {$skipped}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>রিকভারি ইমপোর্ট</title>
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
        .msg { color:green; font-weight:bold; margin-bottom:10px; }
        .err { background:#fff3f3; padding:15px; border-radius:8px; margin-top:20px; border:1px solid #f5c2c2; }
        .note { background:#fff3cd; padding:12px; border-radius:8px; margin-bottom:15px; line-height:1.6; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; cursor:pointer; }
        pre { background:#f8f9fa; padding:12px; border-radius:6px; overflow:auto; }
    </style>
</head>
<body>

<div class="topbar">
    রিকভারি ইমপোর্ট
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="subnav">
    <a href="dashboard.php">ড্যাশবোর্ড</a>
    <a href="recoveries.php">রিকভারি তালিকা</a>
    <a href="import_customers.php">গ্রাহক ইমপোর্ট</a>
</div>

<div class="container">
    <div class="box">
        <h2>রিকভারি ইমপোর্ট (CSV)</h2>

        <div class="note">
            <strong>রিকভারি ফাইল নির্দেশনা:</strong><br>
            CSV ফাইলে অবশ্যই এই ৪টি কলাম থাকবে:<br>
            <code>account_number, amount, recovery_date, note</code><br><br>
            <b>account_number:</b> 02 দিয়ে শুরু, 13 digit<br>
            <b>amount:</b> numeric এবং শূন্যের বেশি<br>
            <b>recovery_date:</b> YYYY-MM-DD format<br>
            <b>note:</b> optional, যেমন Cash, BEFTN, Collection, Partial payment<br><br>
            একই দিনে একই customer একাধিক recovery দিতে পারবে। Duplicate block করা হবে না।
        </div>

        <?php if($message) { ?>
            <div class="msg"><?php echo htmlspecialchars($message); ?></div>
        <?php } ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" accept=".csv" required>
            <br><br>
            <button type="submit" name="upload">আপলোড করুন</button>
        </form>

        <p><strong>CSV Example:</strong></p>
        <pre>account_number,amount,recovery_date,note
0201234567890,5000,2026-04-26,Cash collection
0201234567891,2500,2026-04-26,Partial payment</pre>

        <?php if (!empty($errors)) { ?>
            <div class="err">
                <b>বাদ পড়া সারি / ত্রুটি:</b>
                <ul>
                    <?php foreach ($errors as $e) { ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>
</div>

</body>
</html>