<?php
include 'config.php';
include 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_type = $_SESSION['user_type'] ?? 'free';
if ($user_type !== 'free') exit('Access denied');

$customer_id = intval($_GET['id'] ?? 0);
if ($customer_id <= 0) header("Location: my_customers.php");

// Fetch customer
$stmt = $conn->prepare("SELECT * FROM customers WHERE id=? AND branch_id=0 AND assigned_officer=? LIMIT 1");
$stmt->bind_param("ii",$customer_id,$user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
if (!$customer) exit('Customer not found');

$message = "";
if ($_SERVER["REQUEST_METHOD"]=="POST") {
    $name = trim($_POST['name']);
    $account_number = trim($_POST['account_number']);
    $cl_class = trim($_POST['cl_class']);
    $outstanding = floatval($_POST['outstanding']);
    $phone = trim($_POST['phone']);
    $status = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE customers SET name=?, account_number=?, cl_class=?, outstanding=?, phone=?, status=? WHERE id=? AND branch_id=0 AND assigned_officer=?");
    $stmt->bind_param("sssdsiiii",$name,$account_number,$cl_class,$outstanding,$phone,$status,$customer_id,$user_id);
    if($stmt->execute()) {
        logActivity($conn,"Edit Customer","Customer ID: $customer_id");
        $message = "Updated successfully";
    } else {
        $message = "Update failed: ".$conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Edit Customer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
<nav class="navbar navbar-dark bg-dark mb-4 rounded">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Loan Recovery</a>
    <a class="btn btn-outline-light" href="my_customers.php">Back</a>
  </div>
</nav>

<div class="container">
<h3>Edit Customer</h3>
<?php if($message!=""): ?>
<div class="alert alert-info"><?php echo $message;?></div>
<?php endif; ?>

<form method="POST">
<div class="mb-3">
<label>Name</label>
<input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
</div>
<div class="mb-3">
<label>Account Number</label>
<input type="text" name="account_number" class="form-control" value="<?php echo htmlspecialchars($customer['account_number']); ?>" required>
</div>
<div class="mb-3">
<label>CL Class</label>
<input type="text" name="cl_class" class="form-control" value="<?php echo htmlspecialchars($customer['cl_class']); ?>">
</div>
<div class="mb-3">
<label>Outstanding</label>
<input type="number" step="0.01" name="outstanding" class="form-control" value="<?php echo number_format($customer['outstanding'],2); ?>">
</div>
<div class="mb-3">
<label>Phone</label>
<input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone']); ?>">
</div>
<div class="mb-3">
<label>Status</label>
<select name="status" class="form-select">
<option value="New Defaulter" <?php if($customer['status']=='New Defaulter') echo 'selected';?>>New Defaulter</option>
<option value="Under Follow-up" <?php if($customer['status']=='Under Follow-up') echo 'selected';?>>Under Follow-up</option>
<option value="Legal Notice" <?php if($customer['status']=='Legal Notice') echo 'selected';?>>Legal Notice</option>
</select>
</div>
<button type="submit" class="btn btn-primary">Update</button>
</form>
</div>
</body>
</html>