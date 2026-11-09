<?php

class Mailer
{
    private static ?array $config = null;

    /**
     * Load configuration from config/mail.php
     */
    public static function getConfig(): array
    {
        if (self::$config === null) {
            $configFile = __DIR__ . "/../config/mail.php";
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [
                    'smtp_host'       => 'smtp.gmail.com',
                    'smtp_port'       => 587,
                    'smtp_user'       => '',
                    'smtp_pass'       => '',
                    'smtp_encryption' => 'tls',
                    'smtp_timeout'    => 10,
                    'from_email'      => 'noreply@lasustech.edu.ng',
                    'from_name'       => 'LASUSTECH Secure Voting System',
                    'delivery_mode'   => 'auto',
                    'otp_expiry_minutes' => 10,
                    'otp_max_attempts' => 5,
                    'resend_cooldown_seconds' => 60,
                    'enforce_student_roster' => false,
                    'allowed_email_domains' => [],
                ];
            }
        }
        return self::$config;
    }

    /**
     * Send OTP Verification Email
     */
    public static function sendVerificationCode(
        string $recipientEmail,
        string $recipientName,
        string $otpCode,
        int $expiresMinutes = 10
    ): array {
        $config = self::getConfig();
        $subject = "Your Verification Code: {$otpCode} - LASUSTECH Secure Voting";

        $htmlBody = self::buildVerificationEmailTemplate($recipientName, $otpCode, $expiresMinutes);
        $plainText = "Hello " . ($recipientName ?: "Student") . ",\n\n"
            . "Your confirmation code for student voter registration is: {$otpCode}\n\n"
            . "This code will expire in {$expiresMinutes} minutes.\n\n"
            . "If you did not request this registration, please disregard this email.\n\n"
            . "-- LASUSTECH Secure Online Voting Portal";

        return self::send($recipientEmail, $recipientName, $subject, $htmlBody, $plainText, $otpCode);
    }

    /**
     * Send email via configured delivery mode
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainText,
        ?string $debugOtp = null
    ): array {
        $config = self::getConfig();
        $mode = $config['delivery_mode'] ?? 'auto';

        $hasSmtpCredentials = !empty($config['smtp_host']) && !empty($config['smtp_user']) && !empty($config['smtp_pass']);

        // Check if explicitly log mode or if auto mode with empty credentials
        if ($mode === 'log' || ($mode === 'auto' && !$hasSmtpCredentials)) {
            $logged = self::logEmail($toEmail, $toName, $subject, $otpCode ?? $debugOtp, $htmlBody);
            return [
                'success'   => true,
                'mode'      => 'log',
                'message'   => 'Verification code generated (development simulation mode). Check logs/mail.log or development banner.',
                'debug_otp' => $debugOtp,
            ];
        }

        // Try SMTP first
        try {
            self::sendViaSmtp($toEmail, $toName, $subject, $htmlBody, $plainText);
            return [
                'success' => true,
                'mode'    => 'smtp',
                'message' => 'Verification code sent to your email.',
            ];
        } catch (Exception $e) {
            error_log("SMTP Send Failed: " . $e->getMessage());

            // If in auto mode, fallback to PHP mail() or local log
            if ($mode === 'auto') {
                if (function_exists('mail') && @self::sendViaPhpMail($toEmail, $toName, $subject, $htmlBody, $plainText)) {
                    return [
                        'success' => true,
                        'mode'    => 'mail',
                        'message' => 'Verification code sent to your email (via system mailer).',
                    ];
                }

                // If remote network is not reachable (e.g. offline testing or blocked sandbox), log locally
                self::logEmail($toEmail, $toName, $subject, $debugOtp, $htmlBody);
                return [
                    'success'   => true,
                    'mode'      => 'fallback_log',
                    'message'   => 'Verification code generated. SMTP server unreachable in current environment; logged to system.',
                    'debug_otp' => $debugOtp,
                ];
            }

            return [
                'success' => false,
                'mode'    => 'smtp_error',
                'message' => 'Could not deliver email: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Pure PHP SMTP Implementation using stream sockets
     */
    private static function sendViaSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainText
    ): void {
        $config = self::getConfig();
        $host = $config['smtp_host'];
        $port = (int)($config['smtp_port'] ?? 587);
        $user = $config['smtp_user'] ?? '';
        $pass = $config['smtp_pass'] ?? '';
        $encryption = strtolower($config['smtp_encryption'] ?? 'tls');
        $timeout = (int)($config['smtp_timeout'] ?? 10);
        $fromEmail = $config['from_email'] ?? 'noreply@lasustech.edu.ng';
        $fromName = $config['from_name'] ?? 'LASUSTECH Secure Voting System';

        $socketHost = ($encryption === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        $socket = @stream_socket_client(
            "{$socketHost}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new Exception("Connection to SMTP server {$host}:{$port} failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        self::expectReply($socket, [220]);

        $localhost = gethostname() ?: 'localhost';
        self::sendCommand($socket, "EHLO {$localhost}", [250]);

        if ($encryption === 'tls') {
            self::sendCommand($socket, "STARTTLS", [220]);

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $cryptoSuccess = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            if (!$cryptoSuccess) {
                throw new Exception("STARTTLS encryption negotiation failed with {$host}");
            }

            self::sendCommand($socket, "EHLO {$localhost}", [250]);
        }

        if (!empty($user) && !empty($pass)) {
            self::sendCommand($socket, "AUTH LOGIN", [334]);
            self::sendCommand($socket, base64_encode($user), [334]);
            self::sendCommand($socket, base64_encode($pass), [235]);
        }

        self::sendCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);
        self::sendCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
        self::sendCommand($socket, "DATA", [354]);

        $boundary = "==Multipart_Boundary_x" . md5((string)microtime()) . "x";

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date("r"),
            "X-Mailer: LASUSTECH-Voting-Portal-PHP/" . phpversion(),
        ];

        $content = implode("\r\n", $headers) . "\r\n\r\n";

        // Plain Text part
        $content .= "--{$boundary}\r\n";
        $content .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $content .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $content .= chunk_split(base64_encode($plainText)) . "\r\n";

        // HTML part
        $content .= "--{$boundary}\r\n";
        $content .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $content .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $content .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $content .= "--{$boundary}--\r\n";
        $content .= "\r\n.\r\n";

        fwrite($socket, $content);
        self::expectReply($socket, [250]);

        self::sendCommand($socket, "QUIT", [221]);
        fclose($socket);
    }

    /**
     * Send using PHP mail() function
     */
    private static function sendViaPhpMail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainText
    ): bool {
        $config = self::getConfig();
        $fromEmail = $config['from_email'] ?? 'noreply@lasustech.edu.ng';
        $fromName = $config['from_name'] ?? 'LASUSTECH Secure Voting System';

        $boundary = "==Multipart_Boundary_x" . md5((string)microtime()) . "x";

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "X-Mailer: PHP/" . phpversion(),
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=\"UTF-8\"\r\n\r\n"
            . $plainText . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=\"UTF-8\"\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--{$boundary}--";

        return mail($toEmail, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Log email to file for local testing and records
     */
    public static function logEmail(
        string $toEmail,
        string $toName,
        string $subject,
        ?string $otpCode,
        string $htmlBody
    ): bool {
        $logsDir = __DIR__ . "/../logs";
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0777, true);
        }

        $logFile = $logsDir . "/mail.log";
        $timestamp = date("Y-m-d H:i:s");
        $entry = "====================================================\n"
            . "[{$timestamp}] EMAIL SENT\n"
            . "To: {$toName} <{$toEmail}>\n"
            . "Subject: {$subject}\n"
            . ($otpCode ? "OTP CODE: {$otpCode}\n" : "")
            . "----------------------------------------------------\n"
            . "Content summary: " . strip_tags(substr($htmlBody, 0, 500)) . "...\n"
            . "====================================================\n\n";

        return (bool)file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send command to SMTP socket and check expected status codes
     */
    private static function sendCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expectReply($socket, $expectedCodes);
    }

    /**
     * Read and validate SMTP response code
     */
    private static function expectReply($socket, array $expectedCodes): string
    {
        $response = "";
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // SMTP multi-line responses have hyphen as 4th char (e.g. 250-something)
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new Exception("SMTP Unexpected response [{$code}]: " . trim($response));
        }

        return $response;
    }

    /**
     * Beautiful, modern HTML email template
     */
    public static function buildVerificationEmailTemplate(
        string $recipientName,
        string $otpCode,
        int $expiresMinutes = 10
    ): string {
        $nameSafe = htmlspecialchars($recipientName ?: "Student", ENT_QUOTES, "UTF-8");
        $codeDigits = str_split($otpCode);
        $digitsHtml = "";
        foreach ($codeDigits as $d) {
            $digitsHtml .= "<span style=\"display:inline-block; width:38px; height:46px; line-height:46px; margin:0 4px; font-size:24px; font-weight:800; color:#102541; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; text-align:center; box-shadow:0 2px 4px rgba(0,0,0,0.04);\">{$d}</span>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Voter Registration Confirmation Code</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f9; padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:540px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 10px 25px rgba(16,37,65,0.06);">
                    
                    <!-- Top Brand Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg, #102541 0%, #153e6c 100%); padding:28px 30px; text-align:left;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td>
                                        <div style="font-size:11px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; color:#14b8a6; margin-bottom:4px;">
                                            LASUSTECH ELECTION PORTAL
                                        </div>
                                        <div style="font-size:20px; font-weight:750; color:#ffffff; letter-spacing:-0.4px;">
                                            Secure Online Voting System
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:32px 30px 24px 30px;">
                            <h1 style="margin:0 0 12px 0; font-size:21px; font-weight:750; color:#0f172a; letter-spacing:-0.3px;">
                                Verify your student registration
                            </h1>
                            <p style="margin:0 0 18px 0; font-size:14px; line-height:1.6; color:#475569;">
                                Hello <strong>{$nameSafe}</strong>,
                            </p>
                            <p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#475569;">
                                You recently initiated registration for a student voter account. Use the 6-digit confirmation code below to verify your email and complete your account creation:
                            </p>

                            <!-- OTP Box -->
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:24px 16px; text-align:center; margin:0 0 24px 0;">
                                <div style="font-size:11px; font-weight:750; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:12px;">
                                    Confirmation Code
                                </div>
                                <div style="margin:0 auto 10px auto;">
                                    {$digitsHtml}
                                </div>
                                <div style="font-size:12px; font-weight:600; color:#0d9488;">
                                    ⏳ Expires in {$expiresMinutes} minutes
                                </div>
                            </div>

                            <!-- Security Notes -->
                            <div style="background:#eff6ff; border-left:4px solid #2563eb; border-radius:6px; padding:12px 14px; margin-bottom:24px;">
                                <div style="font-size:12px; font-weight:700; color:#1e40af; margin-bottom:2px;">
                                    Security Notice
                                </div>
                                <div style="font-size:12px; line-height:1.5; color:#1e3a8a;">
                                    Never share this verification code with anyone. University administrators and election officials will never ask for your code.
                                </div>
                            </div>

                            <p style="margin:0; font-size:12px; line-height:1.6; color:#64748b;">
                                If you did not attempt to register on the student election portal, you can safely ignore this email. No account will be created without confirming this code.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 30px; background-color:#f8fafc; border-top:1px solid #e2e8f0; text-align:center;">
                            <div style="font-size:11px; color:#94a3b8; line-height:1.5;">
                                Lagos State University of Science and Technology · Student Election Portal<br>
                                Secure Cryptographic Voting System
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

