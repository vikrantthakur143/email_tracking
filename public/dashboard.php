<?php

/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
*/


/**
 * dashboard.php
 *
 * Very basic protected view of open events. Put a real auth layer in front
 * of this in production (e.g. HTTP basic auth via .htaccess, or your app's
 * existing login system) - the $DASHBOARD_PASSWORD check below is a minimal
 * placeholder, not a substitute for that.
 */

declare(strict_types=1);

$DASHBOARD_PASSWORD = 'change-me'; // <-- set a real secret, or better, remove this and use proper auth

session_start();

if (($_POST['password'] ?? null) === $DASHBOARD_PASSWORD) {
    $_SESSION['authed'] = true;
}

if (empty($_SESSION['authed'])) {
    echo '<form method="post"><input type="password" name="password" placeholder="Password">
          <button type="submit">Login</button></form>';
    exit;
}

$config = require __DIR__ . '/config.php';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db_host'], $config['db_name'], $config['db_charset']);
$pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$rows = $pdo->query(
    'SELECT te.recipient, te.subject, te.campaign, eo.ip_address, eo.user_agent,
            eo.referer, eo.accept_lang, eo.opened_at
     FROM email_opens eo
     JOIN tracked_emails te ON te.tracking_id = eo.tracking_id
     ORDER BY eo.opened_at DESC
     LIMIT 500'
)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Email Open Tracking</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; }
        table { border-collapse: collapse; width: 100%; font-size: 0.85rem; }
        th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
        th { background: #f4f4f4; }
        tr:nth-child(even) { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Recent Email Opens</h1>
    <table>
        <tr>
            <th>Opened At</th><th>Recipient</th><th>Subject</th><th>Campaign</th>
            <th>IP</th><th>User Agent</th><th>Referer</th><th>Language</th>
        </tr>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['opened_at']) ?></td>
            <td><?= htmlspecialchars($r['recipient'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['subject'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['campaign'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['ip_address']) ?></td>
            <td><?= htmlspecialchars($r['user_agent'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['referer'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['accept_lang'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
