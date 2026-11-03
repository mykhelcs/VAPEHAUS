<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Mailer;

use Vapehaus\Infrastructure\Environment\Env;

final class SmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $encryption = null,
        ?string $username = null,
        ?string $password = null,
        ?string $fromEmail = null,
        ?string $fromName = null
    ) {
        $this->host = trim($host ?? (string) (Env::get('SMTP_HOST') ?? ''));
        $this->port = $port ?? (int) (Env::get('SMTP_PORT') ?? 587);
        $this->encryption = strtolower(trim((string) ($encryption ?? Env::get('SMTP_ENCRYPTION') ?? 'tls')));
        $this->username = trim($username ?? (string) (Env::get('SMTP_USERNAME') ?? ''));
        $this->password = trim($password ?? (string) (Env::get('SMTP_PASSWORD') ?? ''));
        $this->fromEmail = trim($fromEmail ?? (string) (Env::get('SMTP_FROM_EMAIL') ?? 'noreply@vapehaus.ph'));
        $this->fromName = trim($fromName ?? (string) (Env::get('SMTP_FROM_NAME') ?? 'VAPEHAUS'));
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->port > 0;
    }

    /**
     * Send an email using raw SMTP socket (TLS/STARTTLS/SSL).
     * Zero external dependencies.
     */
    public function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (!$this->isConfigured()) {
            error_log("SmtpMailer: SMTP is not configured in .env. Skipping email to {$toEmail}.");
            return false;
        }

        $timeout = 15;
        $remote = $this->encryption === 'ssl' ? "ssl://{$this->host}:{$this->port}" : "tcp://{$this->host}:{$this->port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            error_log("SmtpMailer connection error: [{$errno}] {$errstr}");
            return false;
        }

        try {
            $this->expectResponse($socket, '220');

            // EHLO
            $hostname = gethostname() ?: 'localhost';
            $this->sendCommand($socket, "EHLO {$hostname}");
            $ehloResponse = $this->readResponse($socket);

            // STARTTLS if port 587 or tls
            if ($this->encryption === 'tls' || ($this->encryption === 'starttls' || str_contains($ehloResponse, 'STARTTLS'))) {
                $this->sendCommand($socket, "STARTTLS");
                $this->expectResponse($socket, '220');

                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    throw new \RuntimeException("Failed to enable STARTTLS encryption with SMTP server.");
                }

                // Send EHLO again after TLS handshake
                $this->sendCommand($socket, "EHLO {$hostname}");
                $this->readResponse($socket);
            }

            // Authentication
            if ($this->username !== '' && $this->password !== '') {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->expectResponse($socket, '334');

                $this->sendCommand($socket, base64_encode($this->username));
                $this->expectResponse($socket, '334');

                $this->sendCommand($socket, base64_encode($this->password));
                $this->expectResponse($socket, '235');
            }

            // MAIL FROM
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->expectResponse($socket, '250');

            // RCPT TO
            $this->sendCommand($socket, "RCPT TO:<{$toEmail}>");
            $this->expectResponse($socket, '250');

            // DATA
            $this->sendCommand($socket, "DATA");
            $this->expectResponse($socket, '354');

            // Headers & Body
            $boundary = "----=_NextPart_" . md5(uniqid((string) mt_rand(), true));
            $headers = [
                "MIME-Version: 1.0",
                "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>",
                "To: <{$toEmail}>",
                "Date: " . date('r'),
                "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            ];

            $text = $textBody ?? strip_tags($htmlBody);

            $message = implode("\r\n", $headers) . "\r\n\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($text)) . "\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $message .= "--{$boundary}--\r\n";
            $message .= ".\r\n";

            fwrite($socket, $message);
            $this->expectResponse($socket, '250');

            // QUIT
            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            error_log("SmtpMailer exception sending to {$toEmail}: " . $e->getMessage());
            if (is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }

    private function sendCommand($socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function expectResponse($socket, string $expectedCode): string
    {
        $resp = $this->readResponse($socket);
        if (!str_starts_with(trim($resp), $expectedCode)) {
            throw new \RuntimeException("SMTP error expected code [{$expectedCode}] but got: {$resp}");
        }
        return $resp;
    }
}
