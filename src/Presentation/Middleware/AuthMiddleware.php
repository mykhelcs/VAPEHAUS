<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Middleware;

use Closure;
use Vapehaus\Infrastructure\Security\TokenServiceInterface;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    private TokenServiceInterface $tokenService;
    private bool $optional;

    public function __construct(TokenServiceInterface $tokenService, bool $optional = false)
    {
        $this->tokenService = $tokenService;
        $this->optional = $optional;
    }

    public function process(Request $request, Closure $next): Response
    {
        $token = $request->getBearerToken();

        if ($token === null) {
            if ($this->optional) {
                return $next($request);
            }
            return ApiResponse::error('AUTHENTICATION_REQUIRED', 'Missing Bearer token in Authorization header.', [], 401);
        }

        $claims = $this->tokenService->validateToken($token);
        if ($claims === null) {
            return ApiResponse::error('INVALID_TOKEN', 'Token is expired or invalid.', [], 401);
        }

        $request = $request
            ->withAttribute('userId', $claims['sub'] ?? null)
            ->withAttribute('userEmail', $claims['email'] ?? null)
            ->withAttribute('userRole', $claims['role'] ?? 'customer');

        return $next($request);
    }
}
