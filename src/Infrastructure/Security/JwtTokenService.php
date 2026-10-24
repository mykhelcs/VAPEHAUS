<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Security;

final class JwtTokenService implements TokenServiceInterface
{
    private string $secret;

    public function __construct(string $secret = 'vapehaus_secret_jwt_key_2026')
    {
        $this->secret = $secret;
    }

    public function generateToken(array $claims, int $ttlSeconds = 86400): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $issuedAt = time();
        $payload = array_merge($claims, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttlSeconds,
        ]);

        $base64UrlHeader = $this->base64UrlEncode((string) json_encode($header));
        $base64UrlPayload = $this->base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSignature}";
    }

    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        $expectedSig = $this->base64UrlEncode(hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", $this->secret, true));
        if (!hash_equals($expectedSig, $base64UrlSignature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($base64UrlPayload);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && time() > $payload['exp']) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }
}
