<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/alert_config.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

$conn->query(
    "CREATE TABLE IF NOT EXISTS alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meter_id INT NOT NULL,
        alert_type VARCHAR(50) NOT NULL,
        alert_date DATE NOT NULL,
        message TEXT NOT NULL,
        whatsapp_status VARCHAR(30) NOT NULL DEFAULT 'not_sent',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_meter_alert (meter_id, alert_type, alert_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$run_messages = $_SESSION["alert_check_messages"] ?? [];
unset($_SESSION["alert_check_messages"]);

$result = $conn->query(
    "SELECT a.id, a.alert_type, a.alert_date, a.message,
            a.whatsapp_status, a.created_at,
            b.building_name, m.meter_name
     FROM alerts a
     INNER JOIN meters m ON m.id = a.meter_id
     INNER JOIN buildings b ON b.id = m.building_id
     ORDER BY a.created_at DESC, a.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alerts - TNB Meter Monitoring</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5f7;margin:0}
header{background:#1677ff;color:white;padding:18px 25px;display:flex;justify-content:space-between;align-items:center}
header a{color:white;text-decoration:none}
main{padding:25px}.card{background:white;padding:22px;border-radius:12px;margin-bottom:20px;box-shadow:0 3px 12px rgba(0,0,0,.08);overflow-x:auto}
button{padding:10px 15px;background:#1677ff;color:white;border:0;border-radius:7px;cursor:pointer}
input{padding:10px;border:1px solid #ccc;border-radius:7px}
.msg{padding:10px;background:#e7f7e7;margin:8px 0;border-radius:7px}
.note{padding:12px;background:#f0f4ff;border-radius:7px;margin-top:15px}
table{width:100%;border-collapse:collapse;min-width:850px}
th,td{text-align:left;padding:10px;border-bottom:1px solid #ddd;vertical-align:top}
.status{font-weight:bold}
a{color:#1677ff}
</style>
</head>
<body>
<header>
<strong>TNB Meter Monitoring — Alerts</strong>
<span><?= htmlspecialchars($_SESSION["username"]) ?> | <a href="../logout.php">Logout</a></span>
</header>
<main>
<div class="card">
<a href="dashboard.php">← Admin Dashboard</a>
<h2>Alert Checker</h2>

<?php foreach ($run_messages as $message): ?>
<div class="msg"><?= htmlspecialchars($message) ?></div>
<?php endforeach; ?>

<form method="GET" action="run_alert_check.php">
<label>Check date:
<input type="date" name="date" value="<?= htmlspecialchars(date("Y-m-d")) ?>" required>
</label>
<button type="submit">Run Alert Check</button>
</form>

<div class="note">
<strong>Current WhatsApp mode:</strong>
<?= WHATSAPP_ENABLED ? "Enabled" : "Disabled for local testing" ?><br>
When disabled, the system still creates and records alerts. Real WhatsApp sending requires the WhatsApp Business/Cloud API values in <code>config/alert_config.php</code>.
</div>
</div>

<div class="card">
<h2>Alert History</h2>
<?php if ($result->num_rows === 0): ?>
<p>No alerts yet.</p>
<?php else: ?>
<table>
<tr>
<th>Date</th><th>Type</th><th>Building</th><th>Meter</th>
<th>Message</th><th>WhatsApp</th><th>Created</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row["alert_date"]) ?></td>
<td><?= htmlspecialchars($row["alert_type"]) ?></td>
<td><?= htmlspecialchars($row["building_name"]) ?></td>
<td><?= htmlspecialchars($row["meter_name"]) ?></td>
<td><?= nl2br(htmlspecialchars($row["message"])) ?></td>
<td class="status"><?= htmlspecialchars($row["whatsapp_status"]) ?></td>
<td><?= htmlspecialchars($row["created_at"]) ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
