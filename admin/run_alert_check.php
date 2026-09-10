<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/alert_config.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

/* Create the alert table automatically for this project. */
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

function sendWhatsAppMessage(string $message): array
{
    if (!WHATSAPP_ENABLED) {
        return [false, "disabled"];
    }

    if (
        WHATSAPP_PHONE_NUMBER_ID === "" ||
        WHATSAPP_ACCESS_TOKEN === "" ||
        WHATSAPP_ADMIN_NUMBER === ""
    ) {
        return [false, "not_configured"];
    }

    $url = "https://graph.facebook.com/" .
           WHATSAPP_GRAPH_API_VERSION . "/" .
           WHATSAPP_PHONE_NUMBER_ID . "/messages";

    $payload = [
        "messaging_product" => "whatsapp",
        "to" => WHATSAPP_ADMIN_NUMBER,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $message
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . WHATSAPP_ACCESS_TOKEN,
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error !== "") {
        return [false, "curl_error"];
    }

    if ($http_code >= 200 && $http_code < 300) {
        return [true, "sent"];
    }

    return [false, "api_error_" . $http_code];
}

$target_date = $_GET["date"] ?? date("Y-m-d");
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    $target_date = date("Y-m-d");
}

$now = date("H:i:s");
$messages = [];

/* Check every meter. */
$meters = $conn->query(
    "SELECT m.id, m.meter_name, m.usage_limit, b.building_name
     FROM meters m
     INNER JOIN buildings b ON b.id = m.building_id
     ORDER BY m.id"
);

while ($meter = $meters->fetch_assoc()) {
    $meter_id = (int)$meter["id"];

    /* 1. Over-usage alert for the target date. */
    $reading_stmt = $conn->prepare(
        "SELECT r.reading, r.daily_usage, r.submission_date
         FROM meter_readings r
         WHERE r.meter_id = ? AND r.submission_date = ?
         LIMIT 1"
    );
    $reading_stmt->bind_param("is", $meter_id, $target_date);
    $reading_stmt->execute();
    $reading = $reading_stmt->get_result()->fetch_assoc();
    $reading_stmt->close();

    if ($reading && $reading["daily_usage"] !== null) {
        $usage = (float)$reading["daily_usage"];
        $limit = (float)$meter["usage_limit"];

        if ($usage > $limit) {
            $exceeded = $usage - $limit;

            $message =
                "TNB Electricity Usage Alert\n\n" .
                "Building: " . $meter["building_name"] . "\n" .
                "Meter: " . $meter["meter_name"] . "\n" .
                "Date: " . $target_date . "\n" .
                "Daily Usage: " . $usage . " kWh\n" .
                "Limit: " . $limit . " kWh\n" .
                "Exceeded by: " . $exceeded . " kWh";

            $insert = $conn->prepare(
                "INSERT IGNORE INTO alerts
                 (meter_id, alert_type, alert_date, message, whatsapp_status)
                 VALUES (?, 'over_usage', ?, ?, 'not_sent')"
            );
            $insert->bind_param("iss", $meter_id, $target_date, $message);
            $insert->execute();
            $created = $insert->affected_rows > 0;
            $insert->close();

            if ($created) {
                [$sent, $status] = sendWhatsAppMessage($message);

                $update = $conn->prepare(
                    "UPDATE alerts
                     SET whatsapp_status = ?
                     WHERE meter_id = ? AND alert_type = 'over_usage' AND alert_date = ?"
                );
                $update->bind_param("sis", $status, $meter_id, $target_date);
                $update->execute();
                $update->close();

                $messages[] = "Over-usage alert created for " .
                              $meter["building_name"] . " / " .
                              $meter["meter_name"] . " — " . $status;
            }
        }
    }

    /*
     * 2. Missing-reading reminder.
     * For a real daily run, the checker should run after the configured
     * deadline. A manual test can use ?date=YYYY-MM-DD.
     */
    $reading_exists_stmt = $conn->prepare(
        "SELECT id FROM meter_readings
         WHERE meter_id = ? AND submission_date = ?
         LIMIT 1"
    );
    $reading_exists_stmt->bind_param("is", $meter_id, $target_date);
    $reading_exists_stmt->execute();
    $reading_exists = $reading_exists_stmt->get_result()->fetch_assoc();
    $reading_exists_stmt->close();

    $deadline_reached = ($target_date < date("Y-m-d")) ||
                        ($target_date === date("Y-m-d") && $now >= METER_SUBMISSION_DEADLINE);

    if (!$reading_exists && $deadline_reached) {
        $message =
            "TNB Meter Reading Reminder\n\n" .
            "No meter reading found for " . $target_date . ".\n" .
            "Building: " . $meter["building_name"] . "\n" .
            "Meter: " . $meter["meter_name"];

        $insert = $conn->prepare(
            "INSERT IGNORE INTO alerts
             (meter_id, alert_type, alert_date, message, whatsapp_status)
             VALUES (?, 'missing_reading', ?, ?, 'not_sent')"
        );
        $insert->bind_param("iss", $meter_id, $target_date, $message);
        $insert->execute();
        $created = $insert->affected_rows > 0;
        $insert->close();

        if ($created) {
            [$sent, $status] = sendWhatsAppMessage($message);

            $update = $conn->prepare(
                "UPDATE alerts
                 SET whatsapp_status = ?
                 WHERE meter_id = ? AND alert_type = 'missing_reading' AND alert_date = ?"
            );
            $update->bind_param("sis", $status, $meter_id, $target_date);
            $update->execute();
            $update->close();

            $messages[] = "Missing-reading alert created for " .
                          $meter["building_name"] . " / " .
                          $meter["meter_name"] . " — " . $status;
        }
    }
}

$_SESSION["alert_check_messages"] = $messages;

header("Location: alerts.php");
exit;
?>
