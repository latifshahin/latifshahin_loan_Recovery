<?php
include 'config.php';

$admin_roles = ['admin', 'ho_admin'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $admin_roles)) {
    header("Location: dashboard.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file']['tmp_name'];

    if ($file) {
        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === "") {
            $message = "Backup file empty or unreadable.";
        } else {
            if ($conn->multi_query($sql)) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());

                if ($conn->errno) {
                    $message = "Restore failed: " . $conn->error;
                } else {
                    $message = "Database restored successfully.";
                }
            } else {
                $message = "Restore failed: " . $conn->error;
            }
        }
    } else {
        $message = "Please select a backup file.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Restore Database</title>
</head>
<body>

<h2>Database Restore</h2>

<?php if ($message) { ?>
    <p><?php echo htmlspecialchars($message); ?></p>
<?php } ?>

<form method="POST" enctype="multipart/form-data">
    <label>Select Backup File (.sql)</label><br><br>
    <input type="file" name="backup_file" accept=".sql" required><br><br>
    <button type="submit" onclick="return confirm('Restore করলে current database replace হবে। Continue?')">Restore Now</button>
</form>

<p><a href="dashboard.php">Back to Dashboard</a></p>

</body>
</html>