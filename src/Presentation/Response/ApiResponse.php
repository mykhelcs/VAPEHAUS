<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Response;

use Vapehaus\Presentation\Http\Response;

final class ApiResponse
{
    /**
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): Response
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = array_merge([
                'timestamp' => time(),
            ], $meta);
        }

        return Response::json($payload, $status);
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
