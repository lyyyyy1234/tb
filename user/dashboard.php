<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$meters = $conn->query(
    "SELECT m.id, m.meter_name, b.building_name, m.usage_limit
     FROM meters m
     INNER JOIN buildings b ON b.id = m.building_id
     ORDER BY b.building_name, m.meter_name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard - TNB Meter Monitoring</title>
<style>
body{font-family:Arial,sans-serif;margin:0;background:#f2f5f7}
header{background:#1677ff;color:white;padding:18px 25px;display:flex;justify-content:space-between;align-items:center}
header a{color:white;text-decoration:none}
main{padding:30px}.card{background:white;padding:25px;border-radius:12px;max-width:850px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:11px;border-bottom:1px solid #ddd}
.button{display:inline-block;padding:10px 15px;background:#1677ff;color:white;text-decoration:none;border-radius:7px}
</style>
</head>
<body>
<header>
<strong>TNB Meter Monitoring — User</strong>
<span><?= htmlspecialchars($_SESSION["username"]) ?> | <a href="../logout.php">Logout</a></span>
</header>
<main>
<div class="card">
<h2>User Dashboard</h2>
<p>Select a meter to submit today's reading.</p>
<?php if ($meters->num_rows === 0): ?>
<p>No meter has been assigned yet. Please ask the Admin to add a meter.</p>
<?php else: ?>
<table>
<tr><th>Building</th><th>Meter</th><th>Daily Limit</th><th>Action</th></tr>
<?php while ($meter = $meters->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($meter["building_name"]) ?></td>
<td><?= htmlspecialchars($meter["meter_name"]) ?></td>
<td><?= htmlspecialchars($meter["usage_limit"]) ?> kWh</td>
<td><a class="button" href="submit_reading.php?meter_id=<?= (int)$meter["id"] ?>">Submit Reading</a></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
