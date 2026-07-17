<?php
/**
 * create_tracking.php
 *
 * Call this from your own sending code (CLI, cron, your app's backend -
 * do NOT expose this one publicly on the internet) right before you send
 * an email, to get a tracking id + ready-to-embed <img> tag.
 *
 * Example (CLI):
 *   php create_tracking.php "user@example.com" "Weekly Newsletter" "july-2026"
 *
 * Example (as a library call from other PHP code):
 *   require 'create_tracking.php';
 *   $result = create_tracking_pixel('user@example.com', 'Weekly Newsletter', 'july-2026');
 *   // $result['pixel_tag'] -> the <img> tag to insert into the email HTML
 */

declare(strict_types=1);

function create_tracking_pixel(
    ?string $recipient,
    ?string $subject,
    ?string $campaign,
    string $pixelBaseUrl = 'https://yourserver.com/pixel.php'
): array {
    $config = require __DIR__ . '/config.php';

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_name'],
        $config['db_charset']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $trackingId = generate_uuid_v4();

    $stmt = $pdo->prepare(
        'INSERT INTO tracked_emails (tracking_id, recipient, subject, campaign)
         VALUES (:tid, :recipient, :subject, :campaign)'
    );
    $stmt->execute([
        ':tid'       => $trackingId,
        ':recipient' => $recipient,
        ':subject'   => $subject,
        ':campaign'  => $campaign,
    ]);

    $pixelUrl = $pixelBaseUrl . '?id=' . urlencode($trackingId);
    $pixelTag = sprintf(
        '<img src="%s" width="1" height="1" alt="" style="display:none;">',
        htmlspecialchars($pixelUrl, ENT_QUOTES)
    );

    return [
        'tracking_id' => $trackingId,
        'pixel_url'   => $pixelUrl,
        'pixel_tag'   => $pixelTag,
    ];
}

function generate_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Allow running directly from the command line for quick testing.
if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $recipient = $argv[1] ?? null;
    $subject   = $argv[2] ?? null;
    $campaign  = $argv[3] ?? null;

    $result = create_tracking_pixel($recipient, $subject, $campaign);

    echo "Tracking ID: {$result['tracking_id']}\n";
    echo "Pixel URL:   {$result['pixel_url']}\n";
    echo "Embed tag:   {$result['pixel_tag']}\n";
}
