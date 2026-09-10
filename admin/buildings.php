<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $name = trim($_POST["building_name"] ?? "");

    if ($action === "add") {
        if ($name === "") {
            $error = "Building name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO buildings (building_name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) $message = "Building added successfully.";
            else $error = "Unable to add building.";
            $stmt->close();
        }
    }

    if ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM buildings WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Building deleted.";
        else $error = "Cannot delete this building. Remove its meters first.";
        $stmt->close();
    }
}

$result = $conn->query("SELECT id, building_name, created_at FROM buildings ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buildings</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5f7;margin:0}
header{background:#1677ff;color:white;padding:18px 25px}
main{padding:25px}
.card{background:white;padding:22px;border-radius:12px;margin-bottom:20px;max-width:900px}
input{padding:10px;width:280px;max-width:100%;border:1px solid #ccc;border-radius:6px}
button{padding:10px 14px;border:0;border-radius:6px;cursor:pointer}
.add{background:#1677ff;color:white}.delete{background:#d33;color:white}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #ddd}
.msg{padding:10px;background:#e7f7e7;margin-bottom:12px}.err{padding:10px;background:#ffe7e7;margin-bottom:12px}
a{color:#1677ff}
</style>
</head>
<body>
<header><strong>Building Management</strong></header>
<main>
<div class="card">
<a href="dashboard.php">← Admin Dashboard</a>
<h2>Add Building</h2>
<?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST">
    <input type="hidden" name="action" value="add">
    <input type="text" name="building_name" placeholder="Building name" required>
    <button class="add" type="submit">Add Building</button>
</form>
</div>

<div class="card">
<h2>Buildings</h2>
<table>
<tr><th>ID</th><th>Building</th><th>Created</th><th>Action</th></tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
<td><?= (int)$row["id"] ?></td>
<td><?= htmlspecialchars($row["building_name"]) ?></td>
<td><?= htmlspecialchars($row["created_at"]) ?></td>
<td>
<form method="POST" onsubmit="return confirm('Delete this building?');">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int)$row["id"] ?>">
<button class="delete" type="submit">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>
</main>
</body>
</html>
