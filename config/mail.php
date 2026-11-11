<?php
/**
 * SMTP Mail Configuration & Lightweight Pure Vanilla PHP Socket Mailer
 * Connects securely to Google Gmail SMTP (smtp.gmail.com:465 over SSL)
 */

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
if (!defined('SMTP_USER')) define('SMTP_USER', 'albardeen07@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'ftxzncxkuotoztiu');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'albardeen07@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'VendFlow Platform');

/**
 * Pure Vanilla PHP SSL Socket SMTP client for sending HTML emails without third-party dependencies.
 *
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $subject Email subject line
 * @param string $htmlBody HTML email content
 * @param string $altBody Plain-text fallback content
 * @return bool True if sent successfully, false otherwise
 */
function send_smtp_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool {
    $timeout = 15;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $socket = @stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        error_log("VendFlow Mailer: Connection error: {$errstr} ({$errno})");
        return false;
    }

    $readResponse = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $sendCommand = function(string $cmd, array $expectedCodes = [250]) use ($socket, $readResponse): bool {
        fwrite($socket, $cmd . "\r\n");
        $res = $readResponse();
        $code = (int)substr($res, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            error_log("VendFlow Mailer: Command [{$cmd}] failed with response: {$res}");
            return false;
        }
        return true;
    };

    $greeting = $readResponse();
    if ((int)substr($greeting, 0, 3) !== 220) {
        fclose($socket);
        return false;
    }

    $clientHost = gethostname() ?: 'localhost';
    if (!$sendCommand("EHLO {$clientHost}", [250])) {
        fclose($socket);
        return false;
    }

    if (!$sendCommand("AUTH LOGIN", [334])) {
        fclose($socket);
        return false;
    }

    if (!$sendCommand(base64_encode(SMTP_USER), [334])) {
        fclose($socket);
        return false;
    }

    if (!$sendCommand(base64_encode(SMTP_PASS), [235])) {
        fclose($socket);
        return false;
    }

    if (!$sendCommand("MAIL FROM:<" . SMTP_FROM_EMAIL . ">", [250])) {
        fclose($socket);
        return false;
    }

    if (!$sendCommand("RCPT TO:<" . $toEmail . ">", [250, 251])) {
        fclose($socket);
        return false;
    }

    if (!$sendCommand("DATA", [354])) {
        fclose($socket);
        return false;
    }

    $boundary = '=_vf_' . bin2hex(random_bytes(16));
    $headers = [
        'Date: ' . date('r'),
        'From: "' . addslashes(SMTP_FROM_NAME) . '" <' . SMTP_FROM_EMAIL . '>',
        'To: "' . addslashes($toName) . '" <' . $toEmail . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: VendFlow Pure Vanilla PHP Engine'
    ];

    $body = "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($altBody ?: strip_tags($htmlBody))) . "\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($htmlBody)) . "\r\n" .
            "--{$boundary}--\r\n";

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

    fwrite($socket, $message . "\r\n");
    $dataRes = $readResponse();
    $sent = ((int)substr($dataRes, 0, 3) === 250);

    $sendCommand("QUIT", [221]);
    fclose($socket);

    return $sent;
}

/**
 * Resolves the application base URL dynamically from the current HTTP request
 * so verification links work over both localhost and LAN/Wi-Fi IP addresses.
 */
function get_app_base_url(): string {
    $rawHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($rawHost === '') {
        return 'http://localhost/IM%20FINAL';
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';

    $host = preg_match('/^(\[[0-9a-fA-F:]+\]|[a-zA-Z0-9.\-]+)(:\d{1,5})?$/', $rawHost)
        ? $rawHost
        : 'localhost';

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $trimmedDir = trim($scriptDir, '/');
    if ($trimmedDir === '' || $trimmedDir === '.') {
        return "{$scheme}://{$host}";
    }

    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $trimmedDir)));
    return "{$scheme}://{$host}/{$encodedPath}";
}

/**
 * Builds and sends the official PostHog-styled email verification message.
 */
function send_verification_email(string $userEmail, string $userName, string $token): bool {
    $baseUrl = get_app_base_url();
    $verifyUrl = $baseUrl . '/verify-email.php?token=' . urlencode($token);

    $subject = 'Verify your VendFlow operator account';

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f4f5; margin: 0; padding: 24px; color: #18181b; }
    .card { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .header { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
    .logo-badge { background: #f54e00; color: #fff; font-weight: 900; font-size: 14px; padding: 4px 10px; border-radius: 6px; }
    .title { font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 12px; }
    .lead { font-size: 15px; color: #4b5563; line-height: 1.6; margin: 0 0 24px; }
    .btn { display: inline-block; background: #f54e00; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 15px; padding: 12px 28px; border-radius: 8px; box-shadow: 0 2px 4px rgba(245,78,0,0.25); }
    .alt-link { font-size: 12px; color: #71717a; margin-top: 24px; word-break: break-all; }
    .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e4e4e7; font-size: 12px; color: #a1a1aa; }
</style>
</head>
<body>
    <div class="card">
        <div class="header">
            <span class="logo-badge">⚡ VendFlow</span>
            <span style="font-weight: 700; font-size: 14px; color: #71717a;">Operator Verification</span>
        </div>
        <h1 class="title">Verify your email address</h1>
        <p class="lead">
            Hi <strong>{$userName}</strong>,<br><br>
            Thank you for creating your VendFlow operator workspace. To activate your account and start managing your vending inventory, please verify your email address by clicking the button below:
        </p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{$verifyUrl}" class="btn">Verify Operator Account &rarr;</a>
        </p>
        <p class="lead" style="font-size: 13px; color: #71717a;">
            This link will remain active for 24 hours. Once verified, you will immediately unlock access to your digital machines ledger and visual SKU cards.
        </p>
        <div class="alt-link">
            Or copy and paste this link in your browser:<br>
            <a href="{$verifyUrl}" style="color: #f54e00;">{$verifyUrl}</a>
        </div>
        <div class="footer">
            VendFlow &bull; Digital Vending Machine Inventory Recordkeeping<br>
            If you didn't create this account, you can safely ignore this email.
        </div>
    </div>
</body>
</html>
HTML;

    $altText = "Hi {$userName},\n\nPlease verify your VendFlow operator account by visiting:\n{$verifyUrl}\n\nVendFlow Team";

    return send_smtp_mail($userEmail, $userName, $subject, $html, $altText);
}
