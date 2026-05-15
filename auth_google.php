<?php
include 'config.php';

if (isset($_GET['code'])) {
    // ১. এক্সচেঞ্জ কোড ফর টোকেন
    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URL,
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code']
    ];

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        // ২. ইউজারের তথ্য আনা
        $user_info_url = 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $data['access_token'];
        $user_info = json_decode(file_get_contents($user_info_url), true);

        if (isset($user_info['sub'])) {
            $email = $user_info['email'];
            $google_id = $user_info['sub'];
            $name = $user_info['name'];

            // ৩. ডাটাবেসে ইউজার চেক এবং আপডেট
            $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR username = ?");
            $stmt->bind_param("ss", $google_id, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (empty($user['google_id'])) {
                    $upd = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                    $upd->bind_param("si", $google_id, $user['id']);
                    $upd->execute();
                }
            } else {
                // নতুন ফ্রি ইউজার তৈরি
                $stmt_ins = $conn->prepare("INSERT INTO users (name, username, google_id, user_type, status, role) VALUES (?, ?, ?, 'free', 'Active', 'officer')");
                $stmt_ins->bind_param("sss", $name, $email, $google_id);
                $stmt_ins->execute();
                
                $new_id = $conn->insert_id;
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $new_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            }

            // ৪. সেশন সেটআপ
            $_SESSION['user_id']   = intval($user['id']);
            $_SESSION['name']      = $user['name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'] ?? 'officer';
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['branch_id'] = intval($user['branch_id']);
            
            header("Location: dashboard.php");
            exit;
        }
    }
    header("Location: login.php?error=auth_failed");
    exit;
} else {
    // সরাসরি এক্সেস করলে গুগল লগইনে পাঠিয়ে দেওয়া
    $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'scope'         => 'email profile',
        'redirect_uri'  => GOOGLE_REDIRECT_URL,
        'response_type' => 'code',
        'client_id'     => GOOGLE_CLIENT_ID,
        'access_type'   => 'online'
    ]);
    header("Location: " . $url);
    exit;
}
?>