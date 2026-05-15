<?php
include 'config.php';
include 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_type = $_SESSION['user_type']; // 'free' or 'paid'
$message = "";
$message_type = ""; // success or danger

// Free user limit
$free_user_customer_limit = 20;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name']);
    $account_number = trim($_POST['account_number']);
    $cl_class = trim($_POST['cl_class']);
    $outstanding = floatval($_POST['outstanding']);
    $phone = trim($_POST['phone']);
    $status = trim($_POST['status']);
    $customer_state = trim($_POST['customer_state'] ?? 'Old CL');

    if ($user_type === 'free') {
        // Check existing customer count
        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM customers WHERE branch_id = 0 AND assigned_officer = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $total_count = $stmt_check->get_result()->fetch_assoc()['total'];

        if ($total_count >= $free_user_customer_limit) {
            $message = "আপনি ফ্রি ভার্সন ব্যবহার করছেন। সর্বোচ্চ $free_user_customer_limit জন কাস্টমার এ্যাড করতে পারবেন। ডিলিট করলে নতুন কাস্টমার এড করতে পারবেন।";
            $message_type = "danger";
        }

        $branch_id = 0;
        $assigned_officer = $user_id;

    } else { // Paid
        $branch_id = intval($_POST['branch_id'] ?? 0);
        $assigned_officer = intval($_POST['assigned_officer'] ?? 0);

        // Validate Paid Office/Officer
        if ($branch_id <= 0 || $assigned_officer <= 0) {
            $message = "Paid Version এর জন্য Branch এবং Officer নির্বাচন করতে হবে।";
            $message_type = "danger";
        }
    }

    if ($message === "") {
        $stmt = $conn->prepare("INSERT INTO customers 
            (name, account_number, cl_class, outstanding, cl_start_balance, customer_state, phone, branch_id, assigned_officer, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $cl_start_balance = $outstanding;
        $stmt->bind_param("sssddssiis", $name, $account_number, $cl_class, $outstanding, $cl_start_balance, $customer_state, $phone, $branch_id, $assigned_officer, $status);

        if ($stmt->execute()) {
            logActivity($conn, "Add Customer", "Customer: $name ($account_number)");
            $message = "কাস্টমার সফলভাবে যোগ করা হয়েছে।";
            $message_type = "success";
        } else {
            $message = "ভুল হয়েছে: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// Fetch officers for Paid users
$officers = [];
$branches = [];
if ($user_type === 'paid') {
    $res = $conn->query("SELECT id, name FROM officers WHERE status='Active' ORDER BY name ASC");
    while ($row = $res->fetch_assoc()) $officers[] = $row;

    $res_branch = $conn->query("SELECT b.id, b.name, z.name as zone_name, c.name as circle_name 
        FROM branches b 
        LEFT JOIN zones z ON b.zone_id = z.id 
        LEFT JOIN circles c ON z.circle_id = c.id 
        ORDER BY c.name, z.name, b.name");
    while ($row = $res_branch->fetch_assoc()) $branches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Add Customer - Loan Recovery</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f4f7fb; padding-top:20px; }
.container { max-width: 700px; background:#fff; padding:30px; border-radius:15px; box-shadow:0 5px 20px rgba(0,0,0,0.05);}
</style>
</head>
<body>
    <?php
$user_name = $_SESSION['user_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'free';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4" style="border-radius:10px;">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Loan Recovery</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="add_customer.php">Add Customer</a></li>
        <li class="nav-item">
          <a class="nav-link" href="customers.php">Customer List</a>
        </li>
        <?php if($user_type === 'paid'): ?>
          <li class="nav-item"><a class="nav-link" href="add_officer.php">Add Officer</a></li>
          <li class="nav-item"><a class="nav-link" href="add_branch.php">Add Branch</a></li>
        <?php endif; ?>
      </ul>
      <span class="navbar-text me-3">
        <?php echo htmlspecialchars($user_name).' ('.ucfirst($user_type).')'; ?>
      </span>
      <a class="btn btn-outline-light" href="logout.php">Logout</a>
    </div>
  </div>
</nav>



<div class="container">
<h3 class="mb-3">নতুন গ্রাহক যোগ করুন</h3>

<?php if($message !== ""): ?>
<div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<form method="POST">
<div class="mb-3">
    <label>গ্রাহকের নাম</label>
    <input type="text" name="name" class="form-control" required>
</div>

<div class="row">
<div class="col-md-6 mb-3">
    <label>হিসাব নম্বর</label>
    <input type="text" name="account_number" class="form-control" required>
</div>
<div class="col-md-6 mb-3">
    <label>CL Class</label>
    <input type="text" name="cl_class" class="form-control">
</div>
</div>

<div class="mb-3">
    <label>বর্তমান বকেয়া</label>
    <input type="number" step="0.01" name="outstanding" class="form-control" value="0">
</div>

<div class="mb-3">
    <label>মোবাইল নম্বর</label>
    <input type="text" name="phone" class="form-control">
</div>

<?php if($user_type === 'paid'): ?>
<div class="mb-3">
    <label>Branch</label>
    <select name="branch_id" class="form-select" required>
        <option value="">Branch নির্বাচন করুন</option>
        <?php foreach($branches as $b): ?>
            <option value="<?php echo $b['id']; ?>"><?php echo $b['circle_name'].' - '.$b['zone_name'].' - '.$b['name']; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="mb-3">
    <label>Officer</label>
    <select name="assigned_officer" class="form-select" required>
        <option value="">Officer নির্বাচন করুন</option>
        <?php foreach($officers as $o): ?>
            <option value="<?php echo $o['id']; ?>"><?php echo $o['name']; ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="mb-4">
<label>Status</label>
<select name="status" class="form-select">
    <option value="New Defaulter">New Defaulter</option>
    <option value="Under Follow-up">Under Follow-up</option>
    <option value="Legal Notice">Legal Notice</option>
</select>
</div>

<button type="submit" class="btn btn-primary w-100">সংরক্ষণ করুন</button>
</form>
</div>
<!-- Required Bootstrap JS (add at end of body) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>