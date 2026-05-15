<?php
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$branch_id = intval($_SESSION['branch_id']);
$user_id = intval($_SESSION['user_id']);

$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$channel = isset($_POST['channel']) ? trim($_POST['channel']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

if ($customer_id <= 0 || $channel == '') {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$check = $conn->prepare("SELECT id FROM customers WHERE id = ? AND branch_id = ?");
$check->bind_param("ii", $customer_id, $branch_id);
$check->execute();
$customer = $check->get_result()->fetch_assoc();

if (!$customer) {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO message_send_logs 
    (branch_id, customer_id, user_id, channel, phone, message_text)
    VALUES (?, ?, ?, ?, ?, ?)");

$stmt->bind_param("iiisss", $branch_id, $customer_id, $user_id, $channel, $phone, $message_text);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Log failed']);
}