<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$role = $_SESSION['role'];
$can_manage_template = ($role === 'ho_admin');

$message = "";

/* Delete */
if (isset($_GET['delete']) && $can_manage_template) {
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM message_templates WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Template deleted successfully.";
    } else {
        $message = "Template delete failed.";
    }
}

/* Add / Update */
if ($_SERVER["REQUEST_METHOD"] == "POST" && $can_manage_template) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = trim($_POST['title']);
    $template_text = trim($_POST['template_text']);
    $status = trim($_POST['status']);

    if ($title == "" || $template_text == "") {
        $message = "Title and template text required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE message_templates
                SET title = ?, template_text = ?, status = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssi", $title, $template_text, $status, $id);

            if ($stmt->execute()) {
                $message = "Template updated successfully.";
            } else {
                $message = "Template update failed.";
            }
        } else {
            /*
              Global template.
              branch_id = 0 means all branches/officers/admins can use it.
            */
            $branch_id = 0;

            $stmt = $conn->prepare("
                INSERT INTO message_templates (branch_id, title, template_text, status)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $branch_id, $title, $template_text, $status);

            if ($stmt->execute()) {
                $message = "Template saved successfully.";
            } else {
                $message = "Template save failed.";
            }
        }
    }
}

/* Edit data */
$edit_data = null;
if (isset($_GET['edit']) && $can_manage_template) {
    $id = intval($_GET['edit']);

    $stmt = $conn->prepare("SELECT * FROM message_templates WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
}

/* Show all global templates */
$result = $conn->query("
    SELECT *
    FROM message_templates
    WHERE branch_id = 0 OR branch_id IS NULL
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>মেসেজ টেমপ্লেট</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .topbar { background:#343a40; color:#fff; padding:15px 20px; }
        .topbar a { color:#fff; float:right; text-decoration:none; margin-left:15px; }
        .container { padding:20px; }
        .box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 8px rgba(0,0,0,0.05); margin-bottom:20px; }
        input, textarea, select { width:100%; padding:12px; margin:8px 0 15px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        textarea { min-height:100px; }
        button, .btn { background:#007bff; color:#fff; border:0; padding:8px 12px; border-radius:6px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-danger { background:#dc3545; }
        .btn-warning { background:#ffc107; color:#000; }
        .msg { color:green; font-weight:bold; margin-bottom:15px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border:1px solid #ddd; text-align:left; vertical-align:top; }
        th { background:#f1f1f1; }
    </style>
</head>
<body>

<div class="topbar">
    মেসেজ টেমপ্লেট
    <a href="logout.php">লগআউট</a>
    <a href="dashboard.php">ড্যাশবোর্ড</a>
</div>

<div class="container">

<p style="background:#f8f9fa; padding:10px; border-radius:6px;">
    ব্যবহারযোগ্য variables:
    <br>
    <code>{name}</code>,
    <code>{account_number}</code>,
    <code>{outstanding}</code>,
    <code>{phone}</code>,
    <code>{status}</code>,
    <code>{officer_name}</code>,
    <code>{actual_liability}</code>
</p>

<?php if($message != "") { ?>
    <div class="msg"><?php echo htmlspecialchars($message); ?></div>
<?php } ?>

<?php if ($can_manage_template) { ?>
<div class="box">
    <h3><?php echo $edit_data ? "টেমপ্লেট আপডেট" : "নতুন টেমপ্লেট তৈরি"; ?></h3>

    <form method="POST">
        <input type="hidden" name="id" value="<?php echo $edit_data ? intval($edit_data['id']) : 0; ?>">

        <label>Template Title</label>
        <input type="text" name="title" required
               value="<?php echo $edit_data ? htmlspecialchars($edit_data['title']) : ''; ?>">

        <label>Template Text</label>
        <textarea name="template_text" required><?php echo $edit_data ? htmlspecialchars($edit_data['template_text']) : ''; ?></textarea>

        <label>Status</label>
        <select name="status">
            <option value="Active" <?php echo ($edit_data && $edit_data['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
            <option value="Inactive" <?php echo ($edit_data && $edit_data['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>

        <button type="submit">
            <?php echo $edit_data ? "আপডেট করুন" : "সংরক্ষণ করুন"; ?>
        </button>

        <?php if ($edit_data) { ?>
            <a href="message_templates.php" class="btn">Cancel</a>
        <?php } ?>
    </form>
</div>
<?php } ?>

<div class="box">
    <h3>টেমপ্লেট তালিকা</h3>

    <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Text</th>
            <th>Status</th>

            <?php if ($can_manage_template) { ?>
                <th>Update</th>
                <th>Delete</th>
            <?php } ?>
        </tr>

        <?php while($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo intval($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['title']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($row['template_text'])); ?></td>
            <td><?php echo htmlspecialchars($row['status']); ?></td>

            <?php if ($can_manage_template) { ?>
                <td>
                    <a class="btn btn-warning" href="message_templates.php?edit=<?php echo intval($row['id']); ?>">
                        Update
                    </a>
                </td>
                <td>
                    <a class="btn btn-danger"
                       href="message_templates.php?delete=<?php echo intval($row['id']); ?>"
                       onclick="return confirm('Are you sure you want to delete this template?');">
                        Delete
                    </a>
                </td>
            <?php } ?>
        </tr>
        <?php } ?>
    </table>
</div>

</div>
</body>
</html>