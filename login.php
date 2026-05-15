<?php
include 'config.php';

// ১. ইউজার অলরেডি লগইন থাকলে ড্যাশবোর্ডে রিডিরেক্ট
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error_msg = "";

// ২. ম্যানুয়াল ইউজারনেম ও পাসওয়ার্ড সাবমিট হ্যান্ডলিং
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // ডাটাবেস থেকে ইউজার চেক
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // অ্যাকাউন্ট একটিভ আছে কিনা ভেরিফিকেশন
            if (isset($user['status']) && $user['status'] !== 'Active') {
                $error_msg = "আপনার অ্যাকাউন্টটি নিষ্ক্রিয় রয়েছে!";
            } else {
                // হ্যাশ করা পাসওয়ার্ড চেক (#৫ বা password_hash এর সাথে ম্যাচিং লজিক)
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    
                    // সেশন সেটআপ (auth_google.php এর সেশন স্ট্রাকচারের সাথে হুবহু মিল রেখে)
                    $_SESSION['user_id']   = intval($user['id']);
                    $_SESSION['name']      = $user['name'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = $user['role'] ?? 'officer';
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['branch_id'] = intval($user['branch_id']);

                    // সফল লগইন হলে রিডিরেক্ট
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error_msg = "ভুল পাসওয়ার্ড! আবার চেষ্টা করুন।";
                }
            }
        } else {
            $error_msg = "এই ইউজারনেমে কোনো অ্যাকাউন্ট পাওয়া যায়নি!";
        }
    }
}

// গুগল অথেন্টিকেশন ফেইলর মেসেজ চেক (ইউআরএল প্যারামিটার থেকে)
if (isset($_GET['error']) && $_GET['error'] == 'auth_failed') {
    $error_msg = "গুগল অথেন্টিকেশন ব্যর্থ হয়েছে!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Loan Recovery Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .login-container { max-width: 400px; margin-top: 80px; }
        .card { border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .btn-google { background: #fff; border: 1px solid #dadce0; color: #3c4043; font-size: 16px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background 0.2s; }
        .btn-google:hover { background: #f8f9fa; border-color: #c6c6c6; }
    </style>
</head>
<body>
<div class="container login-container">
    <div class="card p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">ঋণ আদায় ব্যবস্থাপনা</h3>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger p-2 text-center" style="font-size: 14px; border-radius: 8px;">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <a href="auth_google.php" class="btn btn-google btn-lg w-100 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            Google দিয়ে লগইন করুন
        </a>

        <div class="text-center text-muted mb-3">অথবা</div>

        <form action="" method="POST">
            <input type="text" name="username" class="form-control mb-3" placeholder="Username / Email" required>
            <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
            <button type="submit" class="btn btn-primary w-100">প্রবেশ করুন</button>
        </form>
    </div>
</div>
</body>
</html>