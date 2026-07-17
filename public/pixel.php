<?php
/**
 * pixel.php
 *
 * Embed in emails as:
 *   <img src="https://yourserver.com/pixel.php?id=TRACKING_UUID" width="1" height="1" alt="" style="display:none;">
 *
 * On each request this:
 *   1. Reads the tracking id + request metadata (IP, user agent, referer, etc.)
 *   2. Inserts a row into email_opens (only if the tracking id exists in tracked_emails)
 *   3. Returns a real 1x1 transparent GIF so the image "loads" normally in the email client
 *
 * Never throws visible errors to the client - a broken pixel should still just
 * return the image, so tracking failures don't show up as a broken image icon.
 */

declare(strict_types=1);

// ---- 1. Load config -------------------------------------------------------
$config = require __DIR__ . '/config.php';

// ---- 2. Always send the pixel image, no matter what happens below --------
// Register this first so even a fatal error later still returns a valid image.
register_shutdown_function(function () {
    if (!headers_sent()) {
        send_pixel_headers();
    }
});

function send_pixel_headers(): void
{
    header('Content-Type: image/gif');
    header('Content-Length: 43');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// 1x1 transparent GIF, raw bytes (43 bytes)
function pixel_bytes(): string
{
    return base64_decode(
        'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7'
    );
}

// ---- 3. Gather request data -----------------------------------------------
$trackingId = $_GET['id'] ?? '';

// Validate as a UUID (v1-v5 format) to avoid junk / injection attempts.
$isValidUuid = (bool) preg_match(
    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
    $trackingId
);

if ($isValidUuid) {
    $ip = get_client_ip();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    $referer   = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512);
    $acceptLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 255);

    try {
        $pdo = get_pdo($config);

        $stmt = $pdo->prepare(
            'INSERT INTO email_opens (tracking_id, ip_address, user_agent, referer, accept_lang)
             SELECT :tid, :ip, :ua, :ref, :lang
             WHERE EXISTS (SELECT 1 FROM tracked_emails WHERE tracking_id = :tid2)'
        );

        $stmt->execute([
            ':tid'  => $trackingId,
            ':tid2' => $trackingId,
            ':ip'   => $ip,
            ':ua'   => $userAgent,
            ':ref'  => $referer,
            ':lang' => $acceptLang,
        ]);
    } catch (Throwable $e) {
        // Log server-side only; never expose DB errors to the client.
        error_log('[pixel.php] tracking insert failed: ' . $e->getMessage());
    }
}

// ---- 4. Output the image ---------------------------------------------------
send_pixel_headers();
echo pixel_bytes();
exit;

// ---- Helpers ----------------------------------------------------------------

function get_pdo(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_name'],
        $config['db_charset']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function get_client_ip(): string
{
    // Trust X-Forwarded-For only if you control the proxy in front of this server
    // (e.g. your own load balancer / Cloudflare). Otherwise use REMOTE_ADDR directly
    // to avoid clients spoofing their IP via the header.
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $value) {
        if (!$value) {
            continue;
        }
        // X-Forwarded-For can be a comma-separated list; take the first entry.
        $ip = trim(explode(',', $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}
