<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Storage;

interface StorageInterface
{
    /**
     * Upload binary or base64 file data to remote object storage.
     *
     * @param string $contents Raw file binary contents
     * @param string $filename Target filename (e.g. prod_abc123.webp)
     * @param string $mimeType MIME type (e.g. image/webp, image/jpeg)
     * @param string $bucket Target storage bucket name (default: products)
     * @return array{url: string, filename: string, size: int}
     */
    public function upload(string $contents, string $filename, string $mimeType = 'image/webp', string $bucket = 'products'): array;

    /**
     * Get the public CDN URL for a file stored in a public bucket.
     *
     * @param string $filename
     * @param string $bucket
     * @return string
     */
    public function getPublicUrl(string $filename, string $bucket = 'products'): string;
}
