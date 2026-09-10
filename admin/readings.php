<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$result = $conn->query(
    "SELECT
        r.id,
        r.reading,
        r.photo,
        r.submission_date,
        r.submission_time,
        r.daily_usage,
        u.username,
        b.building_name,
        m.meter_name,
        m.usage_limit
     FROM meter_readings r
     INNER JOIN users u ON u.id = r.user_id
     INNER JOIN meters m ON m.id = r.meter_id
     INNER JOIN buildings b ON b.id = m.building_id
     ORDER BY r.submission_date DESC, r.submission_time DESC, r.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meter Reading Records</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5f7;margin:0}
header{background:#1677ff;color:white;padding:18px 25px;display:flex;justify-content:space-between;align-items:center}
header a{color:white;text-decoration:none}
main{padding:25px}
.card{background:white;padding:22px;border-radius:12px;margin-bottom:20px;box-shadow:0 3px 12px rgba(0,0,0,.08);overflow-x:auto}
a{color:#1677ff}
table{width:100%;border-collapse:collapse;min-width:900px}
th,td{text-align:left;padding:11px;border-bottom:1px solid #ddd;vertical-align:middle}
th{background:#f7f7f7}
.status-normal{font-weight:bold}
.status-over{font-weight:bold}
.photo{width:90px;height:70px;object-fit:cover;border-radius:6px;border:1px solid #ccc}
.no-data{padding:20px;text-align:center}
</style>
</head>
<body>
<header>
<strong>TNB Meter Monitoring — Reading Records</strong>
<span><?= htmlspecialchars($_SESSION["username"]) ?> | <a href="../logout.php">Logout</a></span>
</header>

<main>
<div class="card">
<a href="dashboard.php">← Admin Dashboard</a>
<h2>Meter Reading Records</h2>

<?php if ($result->num_rows === 0): ?>
    <div class="no-data">No meter readings have been submitted yet.</div>
<?php else: ?>
<table>
<tr>
    <th>Date</th>
    <th>Time</th>
    <th>User</th>
    <th>Building</th>
    <th>Meter</th>
    <th>Reading (kWh)</th>
    <th>Daily Usage</th>
    <th>Limit</th>
    <th>Status</th>
    <th>Photo</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>
<?php
$usage = $row["daily_usage"];
$limit = (float)$row["usage_limit"];
$is_over = $usage !== null && (float)$usage > $limit;
?>
<tr>
    <td><?= htmlspecialchars($row["submission_date"]) ?></td>
    <td><?= htmlspecialchars($row["submission_time"]) ?></td>
    <td><?= htmlspecialchars($row["username"]) ?></td>
    <td><?= htmlspecialchars($row["building_name"]) ?></td>
    <td><?= htmlspecialchars($row["meter_name"]) ?></td>
    <td><?= htmlspecialchars($row["reading"]) ?></td>
    <td>
        <?= $usage === null ? "—" : htmlspecialchars($usage) . " kWh" ?>
    </td>
    <td><?= htmlspecialchars($row["usage_limit"]) ?> kWh</td>
    <td class="<?= $is_over ? 'status-over' : 'status-normal' ?>">
        <?php if ($usage === null): ?>
            Waiting
        <?php elseif ($is_over): ?>
            OVER USAGE
        <?php else: ?>
            Normal
        <?php endif; ?>
    </td>
    <td>
        <a href="../<?= htmlspecialchars($row["photo"]) ?>" target="_blank">
            <img class="photo" src="../<?= htmlspecialchars($row["photo"]) ?>" alt="Meter photo">
        </a>
    </td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
