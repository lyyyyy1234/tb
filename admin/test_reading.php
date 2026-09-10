<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set("Asia/Kuala_Lumpur");

$message = "";
$error = "";

$meters = $conn->query(
    "SELECT m.id, m.meter_name, b.building_name, m.usage_limit
     FROM meters m
     INNER JOIN buildings b ON b.id = m.building_id
     ORDER BY b.building_name, m.meter_name"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $meter_id = (int)($_POST["meter_id"] ?? 0);
    $reading = (float)($_POST["reading"] ?? 0);
    $test_date = $_POST["test_date"] ?? "";

    if ($meter_id <= 0 || $reading < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $test_date)) {
        $error = "Please enter valid test data.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, meter_name FROM meters WHERE id = ?"
        );
        $stmt->bind_param("i", $meter_id);
        $stmt->execute();
        $meter = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$meter) {
            $error = "Meter not found.";
        } else {
            $check = $conn->prepare(
                "SELECT id FROM meter_readings WHERE meter_id = ? AND submission_date = ? LIMIT 1"
            );
            $check->bind_param("is", $meter_id, $test_date);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existing) {
                $error = "A reading already exists for this meter on " . $test_date . ".";
            } else {
                $test_time = "12:00:00";
                $photo_path = "";
                $daily_usage = null;

                $insert = $conn->prepare(
                    "INSERT INTO meter_readings
                    (meter_id, user_id, reading, photo, submission_date, submission_time, daily_usage)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->bind_param(
                    "iidsssd",
                    $meter_id,
                    $_SESSION["user_id"],
                    $reading,
                    $photo_path,
                    $test_date,
                    $test_time,
                    $daily_usage
                );

                if ($insert->execute()) {
                    $insert->close();

                    // Recalculate the next reading after the inserted test reading.
                    $next = $conn->prepare(
                        "SELECT id, reading
                         FROM meter_readings
                         WHERE meter_id = ? AND submission_date > ?
                         ORDER BY submission_date ASC, submission_time ASC, id ASC
                         LIMIT 1"
                    );
                    $next->bind_param("is", $meter_id, $test_date);
                    $next->execute();
                    $next_row = $next->get_result()->fetch_assoc();
                    $next->close();

                    if ($next_row) {
                        $usage = (float)$next_row["reading"] - $reading;

                        if ($usage >= 0) {
                            $update = $conn->prepare(
                                "UPDATE meter_readings SET daily_usage = ? WHERE id = ?"
                            );
                            $update->bind_param("di", $usage, $next_row["id"]);
                            $update->execute();
                            $update->close();
                        }
                    }

                    $message = "Test reading added successfully. The next reading's daily usage was recalculated.";
                } else {
                    $insert->close();
                    $error = "Unable to add test reading.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Test Reading - TNB Meter Monitoring</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5f7;margin:0}
header{background:#1677ff;color:white;padding:18px 25px}
main{padding:25px}.card{background:white;padding:25px;border-radius:12px;max-width:700px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
label{display:block;margin:14px 0 6px;font-weight:bold}
input,select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ccc;border-radius:7px}
button{margin-top:18px;padding:12px 18px;border:0;border-radius:7px;background:#1677ff;color:white;cursor:pointer}
.error{background:#ffe7e7;color:#a00000;padding:10px;border-radius:7px;margin-bottom:12px}
.success{background:#e5f7e5;color:#176b17;padding:10px;border-radius:7px;margin-bottom:12px}
.note{background:#f0f4ff;padding:12px;border-radius:7px;margin-top:15px}
a{color:#1677ff}
</style>
</head>
<body>
<header><strong>TNB Meter Monitoring — Test Reading</strong></header>
<main>
<div class="card">
<a href="dashboard.php">← Admin Dashboard</a>
<h2>Add Test Previous Reading</h2>

<p>Use this only for local testing. It simulates an earlier meter reading so Daily Usage can be tested.</p>

<?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
<label for="meter_id">Meter</label>
<select id="meter_id" name="meter_id" required>
<option value="">Select Meter</option>
<?php while ($m = $meters->fetch_assoc()): ?>
<option value="<?= (int)$m["id"] ?>">
<?= htmlspecialchars($m["building_name"] . " — " . $m["meter_name"] . " (Limit: " . $m["usage_limit"] . " kWh)") ?>
</option>
<?php endwhile; ?>
</select>

<label for="reading">Previous Reading (kWh)</label>
<input id="reading" name="reading" type="number" min="0" step="0.01" value="500" required>

<label for="test_date">Previous Date</label>
<input id="test_date" name="test_date" type="date" value="<?= htmlspecialchars(date("Y-m-d", strtotime("-1 day"))) ?>" required>

<div class="note">
For your current test: enter <strong>500 kWh</strong> and yesterday's date.
Your existing reading is <strong>600 kWh</strong>, so the next reading should become
<strong>100 kWh daily usage</strong>, which exceeds the <strong>80 kWh</strong> limit.
</div>

<button type="submit">Add Test Reading</button>
</form>
</div>
</main>
</body>
</html>
