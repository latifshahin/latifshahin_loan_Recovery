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
    $stmt = $conn->prepare("SELECT a.*, c.name AS customer_name, c.account_number, u.name AS created_by_name
                            FROM loan_adjustments a
                            LEFT JOIN customers c ON a.customer_id = c.id
                            LEFT JOIN users u ON a.created_by = u.id
                            WHERE a.branch_id = ?
                            ORDER BY a.id DESC");
    $stmt->bind_param("i", $branch_id);
} else {
    $stmt = $conn->prepare("SELECT a.*, c.name AS customer_name, c.account_number, u.name AS created_by_name
                            FROM loan_adjustments a
                            LEFT JOIN customers c ON a.customer_id = c.id
                            LEFT JOIN users u ON a.created_by = u.id
                            WHERE a.branch_id = ?
                            AND c.assigned_officer = ?
                            ORDER BY a.id DESC");
    $stmt->bind_param("ii", $branch_id, $officer_id);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Loan Adjustment List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body { font-family: Arial, sans-serif; background:#eef2f7; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:14px; box-shadow:0 0 8px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; }
        .add { color:#16a34a; font-weight:bold; }
        .sub { color:#dc2626; font-weight:bold; }
        .badge { display:inline-block; padding:4px 8px; background:#eef2ff; border-radius:999px; font-size:12px; }
    </style>
</head>
<body>

<div class="topbar">
    Loan Adjustment List
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">
    <div class="box">
        <table>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Account</th>
                <th>Type</th>
                <th>Effect</th>
                <th>Amount</th>
                <th>Note</th>
                <th>Created By</th>
                <th>Created At</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['effective_date']); ?></td>
                <td>
                    <a href="customer_view.php?id=<?php echo $row['customer_id']; ?>">
                        <?php echo htmlspecialchars($row['customer_name']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars($row['adjustment_type']); ?></span></td>
                <td class="<?php echo $row['effect'] == 'Add' ? 'add' : 'sub'; ?>">
                    <?php echo htmlspecialchars($row['effect']); ?>
                </td>
                <td><?php echo number_format($row['amount'], 2); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['note'])); ?></td>
                <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

</body>
</html>