<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Middleware;

use Closure;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class RateLimiterMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    /** @var array<string, array<int, int>> */
    private static array $requests = [];

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function process(Request $request, Closure $next): Response
    {
        $ip = $request->getHeader('x-forwarded-for') ?? $request->getHeader('remote-addr') ?? '127.0.0.1';
        $key = md5($ip . ':' . $request->getPath());
        $now = time();

        if (!isset(self::$requests[$key])) {
            self::$requests[$key] = [];
        }

        // Clean out timestamps older than window
        self::$requests[$key] = array_filter(
            self::$requests[$key],
            fn (int $timestamp) => ($now - $timestamp) < $this->windowSeconds
        );

        if (count(self::$requests[$key]) >= $this->maxRequests) {
            return ApiResponse::error(
                'RATE_LIMIT_EXCEEDED',
                "Too many requests. Please wait before trying again.",
                ['retry_after_seconds' => $this->windowSeconds],
                429
            );
        }

        self::$requests[$key][] = $now;

        return $next($request);
    }

    public static function reset(): void
    {
        self::$requests = [];
    }
}
