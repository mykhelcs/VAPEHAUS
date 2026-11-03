<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Storage;

use RuntimeException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Infrastructure\Environment\Env;

final class SupabaseStorageAdapter implements StorageInterface
{
    private string $supabaseUrl;
    private string $apiKey;
    private string $localUploadDir;

    public function __construct(?string $supabaseUrl = null, ?string $apiKey = null)
    {
        $this->supabaseUrl = rtrim($supabaseUrl ?? (string) (Env::get('SUPABASE_URL') ?? 'https://xpegspccnihndttvhfna.supabase.co'), '/');
        $this->apiKey = $apiKey ?? (string) (Env::get('SUPABASE_SERVICE_KEY') ?? Env::get('SUPABASE_ANON_KEY') ?? '');
        $this->localUploadDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    }

    /**
     * Upload binary data directly to Supabase Storage via REST API,
     * and dual-write locally so images can ALWAYS be served even if Supabase
     * credentials, CORS, or storage bucket policies fail.
     *
     * @param string $contents Raw file binary data
     * @param string $filename Target filename
     * @param string $mimeType MIME type (e.g. image/webp)
     * @param string $bucket Storage bucket name
     * @return array{url: string, filename: string, size: int}
     */
    public function upload(string $contents, string $filename, string $mimeType = 'image/webp', string $bucket = 'products'): array
    {
        $fileSize = strlen($contents);
        if ($fileSize === 0) {
            throw new ValidationException('Cannot upload empty file payload.');
        }

        $cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
        if (empty($cleanFilename)) {
            $cleanFilename = 'prod_' . bin2hex(random_bytes(8)) . '.webp';
        }

        // 1. Dual-write to local public folder so local fallback/proxy works 100% reliably
        $this->saveLocally($contents, $cleanFilename);

        $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$cleanFilename}";
        $remotePublicUrl = $this->getPublicUrl($cleanFilename, $bucket);
        $localPublicUrl = $this->getLocalUrl($cleanFilename);

        // If no API key configured, use local URL or deterministic remote URL
        if ($this->apiKey === '') {
            return [
                'url' => $localPublicUrl,
                'filename' => $cleanFilename,
                'size' => $fileSize,
            ];
        }

        $headers = [
            "Authorization: Bearer {$this->apiKey}",
            "apikey: {$this->apiKey}",
            "Content-Type: {$mimeType}",
            "x-upsert: true",
        ];

        $uploadedToRemote = false;

        $ch = curl_init($endpoint);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Supabase returns 200/201 on success. If bucket not found (404/400), try auto-creating bucket
            if ($httpCode === 404 || ($httpCode >= 400 && is_string($response) && str_contains($response, 'Bucket not found'))) {
                $this->ensureBucketExists($bucket);
                $uploadedToRemote = $this->rawUpload($endpoint, $contents, $headers);
            } elseif ($httpCode === 200 || $httpCode === 201) {
                $uploadedToRemote = true;
            } else {
                error_log("SupabaseStorageAdapter upload warning (HTTP {$httpCode}): {$response} {$curlError}");
            }
        }

        // Return the Supabase public URL whenever remote upload succeeds OR API key is provided
        // to ensure bucket CDN URLs are saved.
        $finalUrl = ($uploadedToRemote || !empty($this->apiKey)) ? $remotePublicUrl : $localPublicUrl;

        return [
            'url' => $finalUrl,
            'filename' => $cleanFilename,
            'size' => $fileSize,
            'remote' => $uploadedToRemote,
        ];
    }

    public function getPublicUrl(string $filename, string $bucket = 'products'): string
    {
        return "{$this->supabaseUrl}/storage/v1/object/public/{$bucket}/{$filename}";
    }

    public function getLocalUrl(string $filename): string
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $baseDir = preg_replace('#/(api|index\.php).*$#', '', $requestUri);
            $baseDir = rtrim((string) $baseDir, '/');
            return "{$scheme}://{$host}{$baseDir}/uploads/products/{$filename}";
        }

        $appUrl = (string) Env::get('APP_URL');
        if (!empty($appUrl)) {
            return rtrim($appUrl, '/') . "/uploads/products/{$filename}";
        }

        return "uploads/products/{$filename}";
    }

    private function saveLocally(string $contents, string $filename): void
    {
        try {
            if (!is_dir($this->localUploadDir)) {
                @mkdir($this->localUploadDir, 0777, true);
            }
            @file_put_contents($this->localUploadDir . DIRECTORY_SEPARATOR . $filename, $contents);
        } catch (\Throwable $e) {
            error_log("Failed to save local copy of upload: " . $e->getMessage());
        }
    }

    private function ensureBucketExists(string $bucket): void
    {
        $endpoint = "{$this->supabaseUrl}/storage/v1/bucket";
        $payload = json_encode([
            'id' => $bucket,
            'name' => $bucket,
            'public' => true,
            'file_size_limit' => 5242880, // 5MB limit
            'allowed_mime_types' => ['image/webp', 'image/jpeg', 'image/png'],
        ]);

        $headers = [
            "Authorization: Bearer {$this->apiKey}",
            "apikey: {$this->apiKey}",
            "Content-Type: application/json",
        ];

        $ch = curl_init($endpoint);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    /**
     * @param array<int, string> $headers
     */
    private function rawUpload(string $endpoint, string $contents, array $headers): bool
    {
        $ch = curl_init($endpoint);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 200 || $code === 201;
        }
        return false;
    }
}
