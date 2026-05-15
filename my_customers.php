<?php
include 'config.php';
include 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_type = $_SESSION['user_type'] ?? 'free';

if($user_type !== 'free') {
    header("Location: dashboard.php");
    exit;
}

// Free user এর কাস্টমারগুলো
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
</body>
</html>