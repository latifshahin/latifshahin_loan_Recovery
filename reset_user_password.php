<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$message = "";
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT id, name, username FROM users WHERE id = ? AND branch_id = ?");
$stmt->bind_param("ii", $user_id, $_SESSION['branch_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $message = "Password does not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $hash, $user_id);

        if ($update->execute()) {
            $message = "Password reset successfully.";
        } else {
            $message = "Password reset failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { max-width:600px; margin:30px auto; background:#fff; padding:25px; border-radius:10px; }
        input { width:100%; padding:12px; margin:8px 0 15px; box-sizing:border-box; }
        button { background:#007bff; color:#fff; border:0; padding:12px 18px; border-radius:6px; }
        .msg { font-weight:bold; color:green; margin-bottom:15px; }
    </style>
</head>
<body>

<div class="topbar">
    Password Reset
    <a href="dashboard.php">Dashboard</a>
</div>

<div class="container">
    <h3>Reset Password</h3>
    <p><strong>User:</strong> <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)</p>

    <?php if ($message != "") { ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Reset Password</button>
    </form>
</div>

</body>
</html>