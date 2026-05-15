<?php
// Error reporting বন্ধ রাখা (Pro-level Security)
error_reporting(0);
ini_set('display_errors', 0);
session_start();

// Database Configuration
$host = "localhost";
$dbname = "vwdiuanb_mobileapp";
$dbuser = "vwdiuanb_wp685";
$dbpass = "}5}Q#vmyLs3($2DH";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Google OAuth Configuration (এখানে আপনার গুগল আইডি বসাবেন)
define('GOOGLE_CLIENT_ID', '765643586437-c6tevibhre9oqidtictm0r3h59lfbmu4.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URL', 'https://latifshahin.com/bkup/auth_google.php');

// Application Settings
$free_user_customer_limit = 20; // ফ্রি ইউজারদের কাস্টমার লিমিট
?>