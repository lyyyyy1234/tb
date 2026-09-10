<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'synergy1_lawlifang_tb');
define('DB_USER', 'synergy1_yenping');
define('DB_PASS', 'R.zb0ZwEuGZ}*fW2');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
