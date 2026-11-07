<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Middleware;

use Closure;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;

final class HttpCacheMiddleware implements MiddlewareInterface
{
    private int $maxAgeSeconds;

    public function __construct(int $maxAgeSeconds = 120)
    {
        $this->maxAgeSeconds = $maxAgeSeconds;
    }

    public function process(Request $request, Closure $next): Response
    {
        // Only cache GET and HEAD requests
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        // Only attach cache headers to successful 200 responses
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $etag = '"' . sha1($response->getBody()) . '"';
        $lastModified = gmdate('D, d M Y H:i:s') . ' GMT';

        $cacheHeaders = [
            'Cache-Control' => "public, max-age={$this->maxAgeSeconds}, stale-while-revalidate=60",
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ];

        // Conditional GET: check If-None-Match
        $clientEtag = $request->getHeader('if-none-match');
        if ($clientEtag !== null) {
            $clientTrim = trim($clientEtag, " \t\n\r\0\x0B\"");
            $serverTrim = trim($etag, " \t\n\r\0\x0B\"");
            if ($clientTrim === $serverTrim) {
                return new Response(304, array_merge($response->getHeaders(), $cacheHeaders), '');
            }
        }

        return $response->withHeaders($cacheHeaders);
    }
}
