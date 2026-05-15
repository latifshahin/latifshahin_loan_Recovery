<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$dir = "backups/";

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$file = $dir . "backup_" . date("Y-m-d_H-i-s") . ".sql";

$cmd = "mysqldump -h " . escapeshellarg($host) .
       " -u " . escapeshellarg($dbuser) .
       " -p" . escapeshellarg($dbpass) . " " .
       escapeshellarg($dbname) .
       " > " . escapeshellarg($file);

system($cmd, $result);

if ($result === 0 && file_exists($file)) {
    echo "Backup saved successfully: " . htmlspecialchars($file);
} else {
    echo "Backup failed.";
}
?>