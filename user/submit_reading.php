<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";
date_default_timezone_set("Asia/Kuala_Lumpur");

$meter_id = (int)($_GET["meter_id"] ?? $_POST["meter_id"] ?? 0);
$error = "";
$success = "";

$stmt = $conn->prepare(
    "SELECT m.id, m.meter_name, m.usage_limit, b.building_name
     FROM meters m
     INNER JOIN buildings b ON b.id = m.building_id
     WHERE m.id = ?"
);
$stmt->bind_param("i", $meter_id);
$stmt->execute();
$meter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meter) {
    die("Meter not found.");
}

$today = date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reading_raw = trim($_POST["reading"] ?? "");

    if ($reading_raw === "" || !is_numeric($reading_raw) || (float)$reading_raw < 0) {
        $error = "Please enter a valid meter reading.";
    } elseif (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Please capture a live meter photo before submitting.";
    } else {
        $reading = (float)$reading_raw;

        $check = $conn->prepare(
            "SELECT id FROM meter_readings WHERE meter_id = ? AND submission_date = ? LIMIT 1"
        );
        $check->bind_param("is", $meter_id, $today);
        $check->execute();
        $already = $check->get_result()->fetch_assoc();
        $check->close();

        if ($already) {
            $error = "A reading for this meter has already been submitted today.";
        } else {
            $previous_stmt = $conn->prepare(
                "SELECT reading
                 FROM meter_readings
                 WHERE meter_id = ? AND submission_date < ?
                 ORDER BY submission_date DESC, submission_time DESC
                 LIMIT 1"
            );
            $previous_stmt->bind_param("is", $meter_id, $today);
            $previous_stmt->execute();
            $previous_row = $previous_stmt->get_result()->fetch_assoc();
            $previous_stmt->close();

            $daily_usage = null;

            if ($previous_row !== null) {
                $previous_reading = (float)$previous_row["reading"];

                if ($reading < $previous_reading) {
                    $error = "Current reading cannot be lower than the previous reading (" .
                             $previous_reading . " kWh).";
                } else {
                    $daily_usage = $reading - $previous_reading;
                }
            }

            if ($error === "") {
                $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "meter";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }

                $allowed = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                $mime = mime_content_type($_FILES["photo"]["tmp_name"]);
                $max_size = 5 * 1024 * 1024;

                if (!isset($allowed[$mime])) {
                    $error = "Only JPG, PNG or WEBP images are allowed.";
                } elseif ($_FILES["photo"]["size"] > $max_size) {
                    $error = "Photo must be 5 MB or smaller.";
                } else {
                    $filename = "meter_" . $meter_id . "_" . date("Ymd_His") . "_" .
                                bin2hex(random_bytes(4)) . "." . $allowed[$mime];
                    $target = $upload_dir . DIRECTORY_SEPARATOR . $filename;

                    if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target)) {
                        $error = "Unable to save the photo.";
                    } else {
                        $submission_date = date("Y-m-d");
                        $submission_time = date("H:i:s");
                        $photo_path = "uploads/meter/" . $filename;

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
                            $submission_date,
                            $submission_time,
                            $daily_usage
                        );

                        if ($insert->execute()) {
                            $success = "Reading submitted successfully.";
                        } else {
                            @unlink($target);
                            $error = "Unable to save the reading.";
                        }
                        $insert->close();
                    }
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
<title>Submit Meter Reading</title>
<style>
body{font-family:Arial,sans-serif;margin:0;background:#f2f5f7}
header{background:#1677ff;color:white;padding:18px 25px}
main{padding:30px}.card{background:white;padding:25px;border-radius:12px;max-width:700px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
label{display:block;margin-top:16px;margin-bottom:7px;font-weight:bold}
input[type=number]{box-sizing:border-box;width:100%;padding:11px;border:1px solid #ccc;border-radius:7px}
button{margin-top:14px;padding:12px 18px;background:#1677ff;color:white;border:0;border-radius:7px;cursor:pointer;font-size:16px}
button:disabled{background:#9aa7b5;cursor:not-allowed}
.error{padding:11px;background:#ffe7e7;color:#a00000;border-radius:7px;margin-bottom:12px}
.success{padding:11px;background:#e5f7e5;color:#176b17;border-radius:7px;margin-bottom:12px}
.info{background:#f0f4ff;padding:12px;border-radius:7px;margin-bottom:16px}
.camera{background:#111;border-radius:10px;overflow:hidden;max-width:620px}
video{display:block;width:100%;max-height:430px;object-fit:cover;background:#111}
.preview{display:none;width:100%;max-height:430px;object-fit:contain;background:#111}
.camera-controls{display:flex;gap:10px;flex-wrap:wrap;padding:12px;background:#222}
.camera-controls button{margin:0}
.status{margin-top:10px;font-size:14px;color:#555}
.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#ffe6b3;color:#754c00;font-weight:bold}
a{color:#1677ff}
</style>
</head>
<body>
<header><strong>TNB Meter Monitoring — Submit Reading</strong></header>
<main>
<div class="card">
<a href="dashboard.php">← Back to User Dashboard</a>
<h2>Submit Meter Reading</h2>

<?php if ($success): ?>
<div class="success"><?= htmlspecialchars($success) ?></div>
<p><strong>Building:</strong> <?= htmlspecialchars($meter["building_name"]) ?></p>
<p><strong>Meter:</strong> <?= htmlspecialchars($meter["meter_name"]) ?></p>
<p>Your reading has been recorded with Malaysia date and time.</p>
<?php else: ?>

<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="info">
<strong>Building:</strong> <?= htmlspecialchars($meter["building_name"]) ?><br>
<strong>Meter:</strong> <?= htmlspecialchars($meter["meter_name"]) ?><br>
<strong>Daily Limit:</strong> <?= htmlspecialchars($meter["usage_limit"]) ?> kWh
</div>

<form method="POST" enctype="multipart/form-data" id="readingForm">
<input type="hidden" name="meter_id" value="<?= (int)$meter_id ?>">

<label for="reading">Meter Reading (kWh)</label>
<input id="reading" name="reading" type="number" min="0" step="0.01" required>

<label>Live Meter Photo</label>
<div class="camera">
    <video id="camera" autoplay playsinline></video>
    <img id="preview" class="preview" alt="Captured meter photo preview">
    <div class="camera-controls">
        <button type="button" id="startCamera">Start Camera</button>
        <button type="button" id="capturePhoto" disabled>Capture Photo</button>
        <button type="button" id="retakePhoto" style="display:none">Retake Photo</button>
    </div>
</div>
<canvas id="canvas" style="display:none"></canvas>
<input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" hidden>
<div class="status" id="cameraStatus"><span class="badge">Photo not captured</span></div>

<button type="submit" id="submitButton" disabled>Submit Reading</button>
</form>
<?php endif; ?>
</div>
</main>

<script>
const video = document.getElementById('camera');
const preview = document.getElementById('preview');
const canvas = document.getElementById('canvas');
const photoInput = document.getElementById('photo');
const startButton = document.getElementById('startCamera');
const captureButton = document.getElementById('capturePhoto');
const retakeButton = document.getElementById('retakePhoto');
const submitButton = document.getElementById('submitButton');
const statusBox = document.getElementById('cameraStatus');
const form = document.getElementById('readingForm');
let stream = null;
let captured = false;

function setStatus(text, good=false) {
    statusBox.innerHTML = '<span class="badge" style="background:' + (good ? '#dff5df' : '#ffe6b3') + ';color:' + (good ? '#176b17' : '#754c00') + '">' + text + '</span>';
}

async function startCamera() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Camera access is not supported by this browser.');
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false
        });
        video.srcObject = stream;
        video.style.display = 'block';
        preview.style.display = 'none';
        captureButton.disabled = false;
        retakeButton.style.display = 'none';
        captured = false;
        submitButton.disabled = true;
        setStatus('Camera ready — capture a live photo');
    } catch (err) {
        setStatus('Camera permission/access failed: ' + err.message);
    }
}

captureButton.addEventListener('click', () => {
    if (!stream) return;

    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 720;
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, width, height);

    canvas.toBlob(blob => {
        if (!blob) {
            setStatus('Unable to capture photo. Please try again.');
            return;
        }

        const file = new File([blob], 'live_meter_photo.jpg', {type: 'image/jpeg'});
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        photoInput.files = dataTransfer.files;

        preview.src = URL.createObjectURL(blob);
        preview.style.display = 'block';
        video.style.display = 'none';
        captureButton.disabled = true;
        retakeButton.style.display = 'inline-block';
        captured = true;
        submitButton.disabled = false;
        setStatus('Live photo captured ✓', true);

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    }, 'image/jpeg', 0.90);
});

retakeButton.addEventListener('click', startCamera);
startButton.addEventListener('click', startCamera);

form.addEventListener('submit', e => {
    if (!captured || !photoInput.files.length) {
        e.preventDefault();
        setStatus('Please capture a live meter photo first.');
        return;
    }
    submitButton.disabled = true;
    submitButton.textContent = 'Submitting...';
});

window.addEventListener('beforeunload', () => {
    if (stream) stream.getTracks().forEach(track => track.stop());
});
</script>
</body>
</html>
