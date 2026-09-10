<?php
session_start();
require_once "config/database.php";

if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter username and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TNB Meter Monitoring - Login</title>
<style>
body{
    font-family:Arial,sans-serif;
    background:#f2f5f7;
    margin:0;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}
.login-box{
    width:360px;
    background:white;
    padding:30px;
    border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,.12);
}
h1{text-align:center;margin-top:0}
label{display:block;margin:14px 0 6px}
input{
    width:100%;
    box-sizing:border-box;
    padding:11px;
    border:1px solid #ccc;
    border-radius:7px;
}
button{
    width:100%;
    margin-top:20px;
    padding:12px;
    border:0;
    border-radius:7px;
    cursor:pointer;
    background:#1677ff;
    color:white;
    font-size:16px;
}
.error{
    background:#ffe8e8;
    color:#b00020;
    padding:10px;
    border-radius:7px;
    margin-bottom:12px;
}
</style>
</head>
<body>
<div class="login-box">
    <h1>TNB Meter Monitoring</h1>

    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
