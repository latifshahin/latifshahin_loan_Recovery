<?php
function logActivity($conn, $action, $description = "") {

    if (!isset($_SESSION['user_id'])) return;

    $user_id = intval($_SESSION['user_id']);
    $branch_id = intval($_SESSION['branch_id']);
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare("INSERT INTO activity_logs 
        (branch_id, user_id, action, description, ip_address)
        VALUES (?, ?, ?, ?, ?)");

    $stmt->bind_param("iisss", $branch_id, $user_id, $action, $description, $ip);
    $stmt->execute();
}