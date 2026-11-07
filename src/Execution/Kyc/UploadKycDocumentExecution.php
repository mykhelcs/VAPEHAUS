<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Kyc;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Infrastructure\Storage\StorageInterface;
use Vapehaus\Infrastructure\Storage\SupabaseStorageAdapter;

final class UploadKycDocumentExecution
{
    private StorageInterface $storage;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->storage = $storage ?? new SupabaseStorageAdapter();
    }

    /**
     * @param array{image_data?: string, data?: string, filename?: string, mime_type?: string} $payload
     * @return array{url: string, filename: string, size: int}
     */
    public function execute(string $userId, array $payload): array
    {
        if (trim($userId) === '') {
            throw new ValidationException('Authentication required to upload identification document.');
        }

        $imageData = $payload['image_data'] ?? $payload['data'] ?? '';
        if ($imageData === '') {
            throw new ValidationException('No document image data provided for upload.');
        }

        $extension = 'jpg';
        $mimeType = 'image/jpeg';

        // Check if data URI scheme is present
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            $ext = strtolower($matches[1]);
            $extension = match ($ext) {
                'jpeg', 'jpg' => 'jpg',
                'png' => 'png',
                'webp' => 'webp',
                default => throw new ValidationException("Unsupported image format: {$ext}. Allowed: JPG, PNG, WEBP."),
            };
            $mimeType = match ($extension) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                default => 'image/webp',
            };
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
        }

        $decoded = base64_decode($imageData, true);
        if ($decoded === false || strlen($decoded) === 0) {
            throw new ValidationException('Invalid base64 encoded document image payload.');
        }

        $fileSize = strlen($decoded);
        if ($fileSize > 5 * 1024 * 1024) {
            throw new ValidationException('Identification document exceeds the maximum allowed size of 5MB.');
        }

        // Verify magic bytes header
        $isJpeg = str_starts_with($decoded, "\xFF\xD8\xFF");
        $isPng = str_starts_with($decoded, "\x89PNG\x0D\x0A\x1A\x0A");
        $isWebp = str_starts_with($decoded, "RIFF") && substr($decoded, 8, 4) === "WEBP";

        if (!$isJpeg && !$isPng && !$isWebp) {
            throw new ValidationException('Uploaded file is not a valid JPEG, PNG, or WEBP image.');
        }

        // Determine matching extension from signature
        if ($isPng) {
            $extension = 'png';
            $mimeType = 'image/png';
        } elseif ($isWebp) {
            $extension = 'webp';
            $mimeType = 'image/webp';
        } else {
            $extension = 'jpg';
            $mimeType = 'image/jpeg';
        }

        $cleanUserId = preg_replace('/[^a-zA-Z0-9_]/', '', $userId);
        $filename = 'id_' . $cleanUserId . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        // Upload to kyc-documents bucket
        $result = $this->storage->upload($decoded, $filename, $mimeType, 'kyc-documents');

        return [
            'url' => $result['url'],
            'filename' => $result['filename'],
            'size' => $fileSize,
        ];
    }
}
