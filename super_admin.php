<?php
include 'config.php';

// নিরাপত্তা চেক: শুধুমাত্র সুপার অ্যাডমিন ঢুকতে পারবে
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$message = "";

// ইউজার টাইপ পরিবর্তন করার লজিক (Free to Paid)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = intval($_GET['id']);
    $new_type = ($_GET['action'] === 'make_paid') ? 'paid' : 'free';
    
    $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE id = ?");
    $stmt->bind_param("si", $new_type, $target_id);
    if ($stmt->execute()) {
        $message = "ইউজার টাইপ সফলভাবে আপডেট হয়েছে।";
    }
}

// সকল ইউজারের তালিকা এবং তাদের কাস্টমার সংখ্যা আনা
$sql = "SELECT u.id, u.name, u.username, u.user_type, u.status, u.created_at,
        (SELECT COUNT(*) FROM customers WHERE assigned_officer = u.id AND branch_id = 0) as free_customers
        FROM users u 
        WHERE u.user_type != 'super_admin'
        ORDER BY u.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Panel - SaaS Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { margin-top: 30px; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">Super Admin Dashboard</span>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">মূল ড্যাশবোর্ড</a>
    </div>
</nav>

<div class="container">
    <?php if($message != ""): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card p-4">
                <h4 class="mb-4">ব্যবহারকারী ব্যবস্থাপনা (Free & Paid Users)</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>নাম</th>
                                <th>ইউজারনেম/ইমেইল</th>
                                <th>টাইপ</th>
                                <th>ফ্রি কাস্টমার</th>
                                <th>স্ট্যাটাস</th>
                                <th>যোগদানের তারিখ</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($row['user_type'] === 'paid') ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo strtoupper($row['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $row['free_customers']; ?> / <?php echo $free_user_customer_limit; ?></td>
                                <td><span class="text-success"><?php echo $row['status']; ?></span></td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <?php if($row['user_type'] === 'free'): ?>
                                        <a href="?action=make_paid&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Make Paid</a>
                                    <?php else: ?>
                                        <a href="?action=make_free&id=<?php echo $row['id']; ?>" class="btn btn-outline-danger btn-sm">Demote to Free</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>