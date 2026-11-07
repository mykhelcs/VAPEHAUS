<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Response;

use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;

final class ApiResponse
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $headers
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200, array $headers = []): Response
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return Response::json($payload, $status, $headers);
    }

    /**
     * Return a standardized success response equipped with HTTP Cache Headers
     * (Cache-Control, ETag, Last-Modified) and handles conditional 304 Not Modified.
     *
     * @param array<string, mixed> $meta
     * @param array<string, string> $extraHeaders
     */
    public static function cached(
        Request $request,
        mixed $data = null,
        array $meta = [],
        int $maxAgeSeconds = 120,
        array $extraHeaders = []
    ): Response {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        $jsonBody = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"' . sha1($jsonBody) . '"';
        $lastModified = gmdate('D, d M Y H:i:s') . ' GMT';

        $cacheHeaders = array_merge([
            'Cache-Control' => "public, max-age={$maxAgeSeconds}, stale-while-revalidate=60",
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ], $extraHeaders);

        // Check conditional request header: If-None-Match
        $clientEtag = $request->getHeader('if-none-match');
        if ($clientEtag !== null) {
            $normalizedClient = trim($clientEtag);
            $normalizedServer = trim($etag);
            if ($normalizedClient === $normalizedServer || trim($normalizedClient, '"') === trim($normalizedServer, '"')) {
                return new Response(304, $cacheHeaders, '');
            }
        }

        return Response::json($payload, 200, $cacheHeaders);
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    public static function error(string $code, string $message, array $details = [], int $status = 400): Response
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];

        return Response::json($payload, $status);
    }
}
