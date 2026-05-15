<?php
include 'config.php';
include 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'free';

if ($user_type !== 'free') {
    header("Location: dashboard.php"); // Paid user goes to normal dashboard
    exit;
}

// Count Free user customers
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM customers WHERE branch_id=0 AND assigned_officer=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_customers = $stmt->get_result()->fetch_assoc()['total'];
$customer_limit = 20;

// Optional: recent activity log
$recent_activities = [];
$stmt_log = $conn->prepare("SELECT activity, created_at FROM log_activity WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$stmt_log->bind_param("i", $user_id);
$stmt_log->execute();
$res_log = $stmt_log->get_result();
while ($row = $res_log->fetch_assoc()) $recent_activities[] = $row;

?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Free User Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f4f7fb; }
.navbar { border-radius:10px; }
.card { border-radius:12px; box-shadow:0 5px 20px rgba(0,0,0,0.08); }
.btn-upgrade { background:#28a745; color:#fff; }
.display-number { font-size:2rem; font-weight:bold; }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Loan Recovery</a>
    <div class="d-flex align-items-center">
      <span class="navbar-text text-white me-3"><?php echo htmlspecialchars($user_name); ?> (Free)</span>
      <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container">

<h3 class="mb-3">স্বাগতম, <?php echo htmlspecialchars($user_name); ?>!</h3>
<p class="text-muted">আপনি Free Version ব্যবহার করছেন। সর্বোচ্চ <?php echo $customer_limit; ?> জন কাস্টমার এ্যাড করতে পারবেন।</p>

<div class="row g-3 mt-3">
  <!-- Total Customers -->
  <div class="col-md-3">
    <div class="card p-3 text-center bg-light">
      <h6>Total Customers</h6>
      <p class="display-number"><?php echo $total_customers; ?> / <?php echo $customer_limit; ?></p>
    </div>
  </div>

  <!-- Add Customer -->
  <div class="col-md-3">
    <div class="card p-3 text-center bg-light">
      <h6>Add Customer</h6>
      <a href="add_customer.php" class="btn btn-primary w-100 mt-2">Add</a>
    </div>
  </div>

  <!-- Manage Customers -->
  <div class="col-md-3">
    <div class="card p-3 text-center bg-light">
      <h6>Manage Customers</h6>
      <a href="my_customers.php" class="btn btn-secondary w-100 mt-2">Manage</a>
    </div>
  </div>

  <!-- Upgrade -->
  <div class="col-md-3">
    <div class="card p-3 text-center bg-light">
      <h6>Upgrade</h6>
      <p>Unlimit customers & premium features</p>
      <a href="upgrade.php" class="btn btn-upgrade w-100 mt-2">Go Paid</a>
    </div>
  </div>
</div>

<!-- Recent Activity -->
<div class="row mt-4">
  <div class="col-12">
    <div class="card p-3 bg-light">
      <h6>Recent Activity</h6>
      <?php if (count($recent_activities) === 0): ?>
        <p class="text-muted">কোনো activity নেই।</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($recent_activities as $act): ?>
          <li class="list-group-item"><?php echo htmlspecialchars($act['activity']); ?> <small class="text-muted float-end"><?php echo $act['created_at']; ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Info / Notes -->
<div class="row mt-3">
  <div class="col-12">
    <div class="card p-3 bg-light">
      <h6>Free Version Notes:</h6>
      <ul>
        <li>আপনি কেবল নিজের কাস্টমার দেখতে এবং এডিট করতে পারবেন।</li>
        <li>ডিলিট করলে নতুন কাস্টমার এড করার সুযোগ পাবেন।</li>
        <li>Paid version এ Branch/Officer hierarchy এবং unlimited customer সুবিধা থাকবে।</li>
      </ul>
    </div>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>