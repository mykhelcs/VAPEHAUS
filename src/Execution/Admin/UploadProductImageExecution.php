<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\ValidationException;

final class UploadProductImageExecution
{
    private string $uploadDir;

    public function __construct(?string $uploadDir = null)
    {
        $this->uploadDir = $uploadDir ?? (dirname(__DIR__, 3) . '/public/uploads/products');
    }

    /**
     * @param array{image_data?: string, filename?: string, mime_type?: string} $payload
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
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            $ext = strtolower($matches[1]);
            $extension = match ($ext) {
                'jpeg', 'jpg' => 'jpg',
                'png' => 'png',
                'webp' => 'webp',
                default => throw new ValidationException("Unsupported image format: {$ext}"),
            };
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
        }

        $decoded = base64_decode($imageData, true);
        if ($decoded === false) {
            throw new ValidationException('Invalid base64 encoded image payload.');
        }

        $fileSize = strlen($decoded);
        if ($fileSize > 5 * 1024 * 1024) {
            throw new ValidationException('Image exceeds the maximum allowed size of 5MB.');
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $this->uploadDir . '/' . $filename;

        if (file_put_contents($targetPath, $decoded) === false) {
            throw new ValidationException('Failed to write image to disk.');
        }

        return [
            'url' => 'uploads/products/' . $filename,
            'filename' => $filename,
            'size' => $fileSize,
        ];
    }
}
