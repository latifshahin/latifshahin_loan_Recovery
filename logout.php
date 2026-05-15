<?php
include 'config.php';
include 'log_activity.php';

logActivity($conn, "Logout", "User logged out");

session_unset();
session_destroy();

header("Location: login.php");
exit;
?>