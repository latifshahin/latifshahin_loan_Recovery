<?php
include 'config.php';
include 'log_activity.php';

if(!isset($_SESSION['user_id'])) header("Location: login.php");
$user_id = intval($_SESSION['user_id']);
$user_type = $_SESSION['user_type'] ?? 'free';

if($user_type !== 'free') exit('Access denied');

$customer_id = intval($_GET['id'] ?? 0);
if($customer_id>0){
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=? AND branch_id=0 AND assigned_officer=?");
    $stmt->bind_param("ii",$customer_id,$user_id);
    $stmt->execute();
    logActivity($conn,"Delete Customer","Customer ID: $customer_id");
}
header("Location: my_customers.php");
exit;
?>