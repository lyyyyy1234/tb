<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$total_buildings = (int)$conn->query("SELECT COUNT(*) AS total FROM buildings")->fetch_assoc()["total"];
$total_meters = (int)$conn->query("SELECT COUNT(*) AS total FROM meters")->fetch_assoc()["total"];
$total_readings = (int)$conn->query("SELECT COUNT(*) AS total FROM meter_readings")->fetch_assoc()["total"];

$over_usage = $conn->query(
    "SELECT COUNT(*) AS total
     FROM meter_readings r
     INNER JOIN meters m ON m.id = r.meter_id
     WHERE r.daily_usage IS NOT NULL
       AND r.daily_usage > m.usage_limit"
)->fetch_assoc()["total"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - TNB Meter Monitoring</title>
<style>
body{font-family:Arial,sans-serif;margin:0;background:#f2f5f7}
header{background:#1677ff;color:#fff;padding:18px 25px;display:flex;justify-content:space-between;align-items:center}
header a{color:#fff;text-decoration:none}
main{padding:30px}
.card{background:#fff;padding:25px;border-radius:12px;max-width:1000px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin:20px 0}
.stat{background:#f7f9fc;border-radius:10px;padding:18px}
.stat h3{margin:0 0 8px;font-size:14px}
.stat p{margin:0;font-size:28px;font-weight:bold}
.menu{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
.menu a{padding:12px 18px;background:#1677ff;color:#fff;text-decoration:none;border-radius:7px}
.menu a.secondary{background:#555}
@media(max-width:700px){.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<header>
    <strong>TNB Meter Monitoring — Admin</strong>
    <span><?= htmlspecialchars($_SESSION["username"]) ?> | <a href="../logout.php">Logout</a></span>
</header>
<main>
<div class="card">
    <h2>Admin Dashboard</h2>
    <p>Manage buildings, meters, daily usage limits and meter readings.</p>

    <div class="stats">
        <div class="stat"><h3>Buildings</h3><p><?= $total_buildings ?></p></div>
        <div class="stat"><h3>Meters</h3><p><?= $total_meters ?></p></div>
        <div class="stat"><h3>Readings</h3><p><?= $total_readings ?></p></div>
        <div class="stat"><h3>Over Usage</h3><p><?= (int)$over_usage ?></p></div>
    </div>

    <div class="menu">
        <a href="buildings.php">Manage Buildings</a>
        <a href="meters.php">Manage Meters</a>
        <a href="readings.php">Meter Reading Records</a>
        <a class="secondary" href="../dashboard.php">Main Dashboard</a>
    </div>
</div>
</main>
</body>
</html>
