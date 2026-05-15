<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;
$branch_id = intval($_SESSION['branch_id']);

if ($is_admin) {
    $stmt = $conn->prepare("SELECT contacts.*, customers.name AS customer_name, customers.account_number, officers.name AS officer_name
                            FROM contacts
                            LEFT JOIN customers ON contacts.customer_id = customers.id
                            LEFT JOIN officers ON contacts.officer_id = officers.id
                            WHERE customers.branch_id = ?
                            ORDER BY contacts.id DESC");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare("SELECT contacts.*, customers.name AS customer_name, customers.account_number, officers.name AS officer_name
                            FROM contacts
                            LEFT JOIN customers ON contacts.customer_id = customers.id
                            LEFT JOIN officers ON contacts.officer_id = officers.id
                            WHERE contacts.officer_id = ?
                              AND customers.branch_id = ?
                            ORDER BY contacts.id DESC");
    $stmt->bind_param("ii", $officer_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>যোগাযোগ লগ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); overflow-x:auto; }
        .btn { display:inline-block; margin-bottom:15px; padding:10px 15px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
        table { width:100%; border-collapse:collapse; }
        table th, table td { padding:12px; border:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f1f1f1; }
    </style>
</head>
<body>
<div class="topbar">
    যোগাযোগ লগ
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <a class="btn" href="add_contact.php">নতুন যোগাযোগ যোগ করুন</a>

        <table>
            <tr>
                <th>আইডি</th>
                <th>গ্রাহক</th>
                <th>অফিসার</th>
                <th>ধরন</th>
                <th>ফলাফল</th>
                <th>প্রতিশ্রুতির পরিমাণ</th>
                <th>মন্তব্য</th>
                <th>পরবর্তী ফলো-আপ</th>
                <th>তৈরির সময়</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td>
                    <?php echo htmlspecialchars($row['customer_name']); ?><br>
                    <small><?php echo htmlspecialchars($row['account_number']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                <td><?php echo htmlspecialchars($row['contact_type']); ?></td>
                <td><?php echo htmlspecialchars($row['action_result']); ?></td>
                <td><?php echo $row['commitment_amount'] !== null ? number_format($row['commitment_amount'], 2) : ''; ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['note'])); ?></td>
                <td><?php echo htmlspecialchars($row['next_followup']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>
</body>
</html>