# Email Open Tracking

Tracks email opens via a 1x1 pixel image: IP address, user agent, referer,
accept-language, and timestamp — stored in MySQL.

## Files

- `schema.sql` — creates the database and two tables:
  - `tracked_emails` — one row per email you send (holds the tracking UUID)
  - `email_opens` — one row per pixel hit (the actual open event data)
- `config.php` — your DB credentials (edit this first)
- `create_tracking.php` — call this when sending an email to get a tracking
  UUID + ready-made `<img>` tag
- `pixel.php` — the public endpoint your emails hit; logs the open and
  returns a real 1x1 transparent GIF
- `dashboard.php` — minimal password-gated page to view recent opens

## Setup

1. **Create the database:**
   ```bash
   mysql -u root -p < schema.sql
   ```

2. **Edit `config.php`** with your real DB host/user/password.

3. **Upload `pixel.php` and `config.php`** to your web server (e.g.
   `https://yourserver.com/pixel.php`). Keep `create_tracking.php` and
   `dashboard.php` off the public internet, or protect them — they aren't
   meant to be hit by random visitors.

4. **When sending an email**, generate a tracking pixel first:
   ```bash
   php create_tracking.php "user@example.com" "Weekly Newsletter" "july-2026"
   ```
   or from PHP:
   ```php
   require 'create_tracking.php';
   $tracking = create_tracking_pixel('user@example.com', 'Weekly Newsletter', 'july-2026');
   $emailHtml .= $tracking['pixel_tag'];
   ```

5. **Embed the returned tag** at the bottom of the email's HTML body:
   ```html
   <img src="https://yourserver.com/pixel.php?id=550e8400-e29b-41d4-a716-446655440000"
        width="1" height="1" alt="" style="display:none;">
   ```

6. **View results** at `dashboard.php` (set a real password in that file
   first, or wrap it with proper auth / basic auth in your webserver config).

## Notes and limitations worth knowing

- **Not all opens are counted.** Many mail clients (Gmail, Apple Mail, etc.)
  block remote images by default or proxy/cache them through their own
  servers, so the IP/user-agent you see is sometimes the mail provider's
  infrastructure, not the recipient's own device. This is a known limitation
  of pixel tracking generally, not specific to this script.
- **Legal/disclosure considerations.** Depending on your jurisdiction and who
  you're emailing (e.g. GDPR in the EU, CAN-SPAM in the US, CASL in Canada),
  tracking opens may require disclosure in your privacy policy or email
  footer, and in some cases active consent. Worth checking what applies to
  your situation before rolling this out broadly.
- **IP addresses are personal data** under regulations like GDPR — treat the
  `email_opens` table with the same care as any other PII store (access
  controls, retention limits, etc.).
