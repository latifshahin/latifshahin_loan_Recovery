<?php
include 'config.php';

$res = $conn->query("
    SELECT *
    FROM officers
    WHERE user_id IS NULL
");

while ($o = $res->fetch_assoc()) {
    $name = $conn->real_escape_string($o['name']);
    $mobile = $conn->real_escape_string($o['mobile']);
    $branch_id = intval($o['branch_id']);
    $status = $conn->real_escape_string($o['status']);

    $username = 'officer' . intval($o['id']);
    $password = md5('123456');

    $conn->query("
        INSERT INTO users
        (name, username, password, role, branch_id, status, created_at)
        VALUES
        ('$name', '$username', '$password', 'officer', $branch_id, '$status', NOW())
    ");

    $user_id = intval($conn->insert_id);
    $officer_id = intval($o['id']);

    $conn->query("
        UPDATE officers
        SET user_id = $user_id
        WHERE id = $officer_id
    ");

    echo "Linked Officer ID $officer_id with username $username / password 123456<br>";
}