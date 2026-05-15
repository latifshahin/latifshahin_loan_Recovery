<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$is_admin = ($_SESSION['role'] === 'admin');
$officer_id = isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0;

if ($is_admin) {
    $sql = "SELECT 
                c.id, c.name, c.account_number, c.phone, c.outstanding, c.status,
                o.name AS officer_name,
                ct.action_result,
                ct.created_at AS last_contact
            FROM contacts ct
            INNER JOIN customers c ON ct.customer_id = c.id
            LEFT JOIN officers o ON ct.officer_id = o.id
            WHERE c.branch_id = ?
            AND ct.action_result IN ('No Response', 'Switch Off', 'Wrong Number')
            AND ct.id = (
                SELECT MAX(ct2.id)
                FROM contacts ct2
                WHERE ct2.customer_id = c.id
            )
            ORDER BY ct.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch_id);
} else {
    $sql = "SELECT 
                c.id, c.name, c.account_number, c.phone, c.outstanding, c.status,
                o.name AS officer_name,
                ct.action_result,
                ct.created_at AS last_contact
            FROM contacts ct
            INNER JOIN customers c ON ct.customer_id = c.id
            LEFT JOIN officers o ON ct.officer_id = o.id
            WHERE c.branch_id = ?
            AND ct.officer_id = ?
            AND ct.action_result IN ('No Response', 'Switch Off', 'Wrong Number')
            AND ct.id = (
                SELECT MAX(ct2.id)
                FROM contacts ct2
                WHERE ct2.customer_id = c.id
            )
            ORDER BY ct.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $branch_id, $officer_id);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>সাড়া পাওয়া যায়নি রিপোর্ট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; margin-left:15px; text-decoration:none; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border:1px solid #ddd; }
        th { background:#f1f1f1; }
        .danger { background:#fff3f3; }
    </style>
</head>
<body>

<div class="topbar">
    সাড়া পাওয়া যায়নি / ফোন বন্ধ রিপোর্ট
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <table>
            <tr>
                <th>গ্রাহক</th>
                <th>হিসাব নম্বর</th>
                <th>মোবাইল</th>
                <th>অফিসার</th>
                <th>সর্বশেষ যোগাযোগ</th>
                <th>ফলাফল</th>
                <th>বকেয়া</th>
                <th>স্ট্যাটাস</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr class="danger">
                <td><a href="customer_view.php?id=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['officer_name']); ?></td>
                <td><?php echo htmlspecialchars($row['last_contact']); ?></td>
                <td><?php echo htmlspecialchars($row['action_result']); ?></td>
                <td><?php echo number_format($row['outstanding'], 2); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

</body>
</html>