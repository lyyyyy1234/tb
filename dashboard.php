<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"];
$role = $_SESSION["role"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TNB Meter Monitoring - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f2f5f7; }
        header {
            background: #1677ff; color: white; padding: 18px 25px;
            display: flex; justify-content: space-between; align-items: center;
        }
        main { padding: 30px; }
        .card {
            background: white; padding: 25px; border-radius: 12px;
            max-width: 700px; box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }
        a { color: white; text-decoration: none; }
    </style>
</head>
<body>
<header>
    <strong>TNB Meter Monitoring</strong>
    <span><?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>) |
        <a href="logout.php">Logout</a>
    </span>
</header>

<main>
    <div class="card">
        <h2>Welcome, <?= htmlspecialchars($username) ?>!</h2>
        <p>You are logged in successfully.</p>
        <p>Role: <strong><?= htmlspecialchars($role) ?></strong></p>

        <?php if ($role === "admin"): ?>
            <h3>Admin Dashboard</h3>
            <p>Admin functions will be added next.</p>
        <?php else: ?>
            <h3>User Dashboard</h3>
            <p>Meter reading submission will be added next.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
