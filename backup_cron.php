<?php
include 'config.php';

$dir = __DIR__ . "/backups/";

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$file = $dir . "backup_" . date("Y-m-d_H-i-s") . ".sql";

$cmd = "mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 -h " . escapeshellarg($host) .
       " -u " . escapeshellarg($dbuser) .
       " -p" . escapeshellarg($dbpass) . " " .
       escapeshellarg($dbname) .
       " > " . escapeshellarg($file);

system($cmd, $result);

if ($result === 0 && file_exists($file) && filesize($file) > 0) {
    echo "Backup OK: " . basename($file) . "\n";
} else {
    echo "Backup FAILED\n";
}

$files = glob($dir . "*.sql");
$now = time();

foreach ($files as $f) {
    if (is_file($f) && ($now - filemtime($f) >= 7 * 24 * 60 * 60)) {
        unlink($f);
    }
}