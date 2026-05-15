<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$backup_file = "backup_" . date("Y-m-d_H-i-s") . ".sql";

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backup_file . '"');

$tables = [];
$result = $conn->query("SHOW TABLES");

while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$output = "SET FOREIGN_KEY_CHECKS=0;\n\n";
$output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";
$output .= "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    $safe_table = "`" . str_replace("`", "``", $table) . "`";

    $output .= "\nDROP TABLE IF EXISTS $safe_table;\n";

    $res = $conn->query("SHOW CREATE TABLE $safe_table");
    $row = $res->fetch_row();
    $output .= $row[1] . ";\n\n";

    $res = $conn->query("SELECT * FROM $safe_table");

    while ($row = $res->fetch_assoc()) {
        $cols = array_keys($row);

        $cols = array_map(function($col) {
            return "`" . str_replace("`", "``", $col) . "`";
        }, $cols);

        $vals = array_map(function($val) use ($conn) {
            if ($val === null) {
                return "NULL";
            }
            return "'" . $conn->real_escape_string($val) . "'";
        }, array_values($row));

        $output .= "INSERT INTO $safe_table (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ");\n";
    }

    $output .= "\n";
}

$output .= "SET FOREIGN_KEY_CHECKS=1;\n";

echo $output;
exit;