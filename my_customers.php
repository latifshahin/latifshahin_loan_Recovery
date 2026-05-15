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
    header("Location: dashboard.php");
    exit;
}

// Fetch Free user customers
$stmt = $conn->prepare("SELECT * FROM customers WHERE branch_id=0 AND assigned_officer=? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>My Customers - Free Version</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 rounded">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Loan Recovery</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="add_customer.php">Add Customer</a></li>
        <li class="nav-item"><a class="nav-link active" href="my_customers.php">My Customers</a></li>
      </ul>
      <span class="navbar-text me-3"><?php echo htmlspecialchars($user_name).' ('.ucfirst($user_type).')'; ?></span>
      <a class="btn btn-outline-light" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container">
<h3>আমার কাস্টমার (Free Version)</h3>
<a class="btn btn-primary mb-2" href="add_customer.php">Add New Customer</a>
<table class="table table-bordered table-striped">
<tr>
  <th>ID</th>
  <th>Name</th>
  <th>Account Number</th>
  <th>Outstanding</th>
  <th>Action</th>
</tr>
<?php while($row=$result->fetch_assoc()): ?>
<tr>
  <td><?php echo $row['id']; ?></td>
  <td><?php echo htmlspecialchars($row['name']); ?></td>
  <td><?php echo htmlspecialchars($row['account_number']); ?></td>
  <td><?php echo number_format($row['outstanding'],2); ?></td>
  <td>
    <a class="btn btn-sm btn-warning" href="edit_customer.php?id=<?php echo $row['id']; ?>">Edit</a>
    <a class="btn btn-sm btn-danger" href="delete_customer.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a>
  </td>
</tr>
<?php endwhile; ?>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>