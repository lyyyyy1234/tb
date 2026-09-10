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

    if ($action === "add") {
        $building_id = (int)($_POST["building_id"] ?? 0);
        $meter_name = trim($_POST["meter_name"] ?? "");
        $usage_limit = (float)($_POST["usage_limit"] ?? 0);

        if ($building_id <= 0 || $meter_name === "" || $usage_limit < 0) {
            $error = "Please enter valid meter details.";
        } else {
            $stmt = $conn->prepare("INSERT INTO meters (building_id, meter_name, usage_limit) VALUES (?, ?, ?)");
            $stmt->bind_param("isd", $building_id, $meter_name, $usage_limit);
            if ($stmt->execute()) $message = "Meter added successfully.";
            else $error = "Unable to add meter.";
            $stmt->close();
        }
    }

    if ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM meters WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Meter deleted.";
        else $error = "Cannot delete this meter if reading records already reference it.";
        $stmt->close();
    }
}

$buildings = $conn->query("SELECT id, building_name FROM buildings ORDER BY building_name");
$meters = $conn->query(
    "SELECT m.id, m.meter_name, m.usage_limit, b.building_name, m.created_at
     FROM meters m
     INNER JOIN buildings b ON b.id = m.building_id
     ORDER BY m.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meter Management</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5f7;margin:0}
header{background:#1677ff;color:white;padding:18px 25px}
main{padding:25px}
.card{background:white;padding:22px;border-radius:12px;margin-bottom:20px;max-width:1000px}
input,select{padding:10px;border:1px solid #ccc;border-radius:6px;margin:4px}
button{padding:10px 14px;border:0;border-radius:6px;cursor:pointer}
.add{background:#1677ff;color:white}.delete{background:#d33;color:white}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #ddd}
.msg{padding:10px;background:#e7f7e7;margin-bottom:12px}.err{padding:10px;background:#ffe7e7;margin-bottom:12px}
a{color:#1677ff}
</style>
</head>
<body>
<header><strong>Meter Management</strong></header>
<main>
<div class="card">
<a href="dashboard.php">← Admin Dashboard</a>
<h2>Add Meter</h2>
<?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($buildings->num_rows === 0): ?>
<p>Please add a building first.</p>
<?php else: ?>
<form method="POST">
    <input type="hidden" name="action" value="add">
    <select name="building_id" required>
        <option value="">Select Building</option>
        <?php while ($b = $buildings->fetch_assoc()): ?>
            <option value="<?= (int)$b["id"] ?>"><?= htmlspecialchars($b["building_name"]) ?></option>
        <?php endwhile; ?>
    </select>
    <input type="text" name="meter_name" placeholder="Meter name" required>
    <input type="number" name="usage_limit" placeholder="Daily limit (kWh)" min="0" step="0.01" required>
    <button class="add" type="submit">Add Meter</button>
</form>
<?php endif; ?>
</div>

<div class="card">
<h2>Meters</h2>
<table>
<tr><th>ID</th><th>Building</th><th>Meter</th><th>Daily Limit (kWh)</th><th>Action</th></tr>
<?php while ($row = $meters->fetch_assoc()): ?>
<tr>
<td><?= (int)$row["id"] ?></td>
<td><?= htmlspecialchars($row["building_name"]) ?></td>
<td><?= htmlspecialchars($row["meter_name"]) ?></td>
<td><?= htmlspecialchars($row["usage_limit"]) ?></td>
<td>
<form method="POST" onsubmit="return confirm('Delete this meter?');">
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
