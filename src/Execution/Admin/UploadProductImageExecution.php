<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Infrastructure\Storage\StorageInterface;
use Vapehaus\Infrastructure\Storage\SupabaseStorageAdapter;

final class UploadProductImageExecution
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
    public function execute(array $payload): array
    {
        $imageData = $payload['image_data'] ?? $payload['data'] ?? '';
        if ($imageData === '') {
            throw new ValidationException('No image data provided for upload.');
        }

        // Support base64 data URI format (e.g., data:image/webp;base64,...)
        $extension = 'webp';
        $mimeType = 'image/webp';
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            $ext = strtolower($matches[1]);
            $extension = match ($ext) {
                'jpeg', 'jpg' => 'jpg',
                'png' => 'png',
                'webp' => 'webp',
                default => throw new ValidationException("Unsupported image format: {$ext}"),
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
            throw new ValidationException('Invalid base64 encoded image payload.');
        }

        $fileSize = strlen($decoded);
        if ($fileSize > 5 * 1024 * 1024) {
            throw new ValidationException('Image exceeds the maximum allowed size of 5MB.');
        }

        // Verify image magic bytes header or format
        $isJpeg = str_starts_with($decoded, "\xFF\xD8\xFF");
        $isPng = str_starts_with($decoded, "\x89PNG\x0D\x0A\x1A\x0A");
        $isWebp = (str_starts_with($decoded, "RIFF") && str_contains(substr($decoded, 0, 16), "WEBP")) || str_contains($decoded, 'webp');

        if (!$isJpeg && !$isPng && !$isWebp) {
            throw new ValidationException('Uploaded file is not a valid JPEG, PNG, or WEBP image.');
        }

        $rawFilename = $payload['filename'] ?? null;
        if (!empty($rawFilename)) {
            $pathInfo = pathinfo($rawFilename);
            $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pathInfo['filename'] ?? 'prod');
            $filename = ($baseName !== '' ? $baseName : 'prod') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        } else {
            $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $extension;
        }

        // Upload directly to remote Supabase object storage (zero local disk writes)
        $result = $this->storage->upload($decoded, $filename, $mimeType, 'products');

        return [
            'url' => $result['url'],
            'filename' => $result['filename'],
            'size' => $fileSize,
        ];
    }
}
