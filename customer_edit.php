<?php
include 'config.php';
include 'role_scope.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = currentRole();
$ids = roleIds();

if (!in_array($role, ['admin', 'zone_admin', 'circle_admin', 'ho_admin'])) {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Customer ID missing.");
}

$id = intval($_GET['id']);
$message = "";

$customer = getScopedCustomer($conn, $id);
if (!$customer) {
    die("Customer not found or outside your permitted scope.");
}

$officers = scopedOfficers($conn, $id);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $account_number = trim($_POST['account_number']);
    $cl_class = trim($_POST['cl_class']);
    $outstanding = floatval($_POST['outstanding']);
    $customer_state = trim($_POST['customer_state'] ?? 'Old CL');
    $allowed_states = ['Old CL','New CL','Standard Risky','Fully Recovered'];
    if (!in_array($customer_state, $allowed_states)) { $customer_state = 'Old CL'; }
    $phone = trim($_POST['phone']);
    $first_default_date = $_POST['first_default_date'];
    $assigned_officer = intval($_POST['assigned_officer']);
    $status = trim($_POST['status']);
    $last_note = trim($_POST['last_note']);

    if (!canAccessCustomer($conn, $id)) {
        die("এই গ্রাহক আপনার অনুমোদিত scope-এর ভিতরে নেই।");
    }

    $checkOfficer = $conn->prepare("SELECT id FROM officers WHERE id = ? AND branch_id = ? LIMIT 1");
    $checkOfficer->bind_param("ii", $assigned_officer, $customer['branch_id']);
    $checkOfficer->execute();
    $validOfficer = $checkOfficer->get_result()->fetch_assoc();

    if (!$validOfficer) {
        $message = "নির্বাচিত অফিসার এই গ্রাহকের শাখার অধীনে নেই।";
    } else {
        $update = $conn->prepare("UPDATE customers SET name=?, account_number=?, cl_class=?, outstanding=?, customer_state=?, phone=?, first_default_date=?, assigned_officer=?, status=?, last_note=? WHERE id=?");
        $update->bind_param("sssdsssissi", $name, $account_number, $cl_class, $outstanding, $customer_state, $phone, $first_default_date, $assigned_officer, $status, $last_note, $id);

        if ($update->execute()) {
            $message = "Customer updated successfully.";
            $customer = getScopedCustomer($conn, $id);
            $officers = scopedOfficers($conn, $id);
        } else {
            $message = "Error updating customer.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Customer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:850px; margin:30px auto; background:#fff; padding:25px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); }
        input, select, textarea { width:100%; padding:12px; margin:8px 0 15px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        textarea { min-height:100px; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; cursor:pointer; }
        .msg { margin-bottom:15px; color:green; font-weight:bold; }
    </style>
</head>
<body>
<div class="topbar">
    Edit Customer
    <a href="logout.php">Logout</a>
    <a href="customers.php">Customers</a>
    <a href="dashboard.php">Dashboard</a>
</div>

<div class="container">
    <?php if($message != "") { echo '<div class="msg">'.htmlspecialchars($message).'</div>'; } ?>

    <form method="POST">
        <label>Customer Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>

        <label>Account Number</label>
        <input type="text" name="account_number" value="<?php echo htmlspecialchars($customer['account_number']); ?>" required>

        <label>CL Class</label>
        <input type="text" name="cl_class" value="<?php echo htmlspecialchars($customer['cl_class']); ?>">

        <label>Outstanding Amount</label>
        <input type="number" step="0.01" name="outstanding" value="<?php echo htmlspecialchars($customer['outstanding']); ?>">

        <label>CL Start Balance (not changeable)</label>
        <input type="number" step="0.01" value="<?php echo htmlspecialchars($customer['cl_start_balance'] ?? 0); ?>" readonly style="background:#f1f5f9;">

        <label>Customer State</label>
        <select name="customer_state" required>
            <?php
            $states = ["Old CL","New CL","Standard Risky","Fully Recovered"];
            foreach($states as $state) {
                $selected = (($customer["customer_state"] ?? "Old CL") == $state) ? "selected" : "";
                echo "<option value=\"$state\" $selected>$state</option>";
            }
            ?>
        </select>

        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>">

        <label>First Default Date</label>
        <input type="date" name="first_default_date" value="<?php echo htmlspecialchars($customer['first_default_date']); ?>">

        <label>Assigned Officer</label>
        <select name="assigned_officer" required>
            <option value="">Select Officer</option>
            <?php while($officer = $officers->fetch_assoc()) { ?>
                <option value="<?php echo intval($officer['id']); ?>" <?php if($customer['assigned_officer'] == $officer['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($officer['name']); ?>
                </option>
            <?php } ?>
        </select>

        <label>Status</label>
        <select name="status" required>
            <?php
            $statuses = ["New Defaulter","Under Follow-up","Promise to Pay","Partial Recovery","Regularized","Legal Notice","Litigation","Unreachable"];
            foreach($statuses as $s) {
                $selected = ($customer['status'] == $s) ? 'selected' : '';
                echo "<option value=\"$s\" $selected>$s</option>";
            }
            ?>
        </select>

        <label>Last Note</label>
        <textarea name="last_note"><?php echo htmlspecialchars($customer['last_note']); ?></textarea>

        <button type="submit">Update Customer</button>
    </form>
</div>
</body>
</html>
