<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Middleware;

use Closure;
use Vapehaus\Domain\Repository\SessionRepositoryInterface;
use Vapehaus\Infrastructure\Security\TokenServiceInterface;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    private TokenServiceInterface $tokenService;
    private bool $optional;
    private ?SessionRepositoryInterface $sessionRepository;

    public function __construct(
        TokenServiceInterface $tokenService,
        bool $optional = false,
        ?SessionRepositoryInterface $sessionRepository = null
    ) {
        $this->tokenService = $tokenService;
        $this->optional = $optional;
        $this->sessionRepository = $sessionRepository;
    }

    public function process(Request $request, Closure $next): Response
    {
        $token = $request->getBearerToken();

        if ($token === null) {
            if ($this->optional) {
                return $next($request);
            }
            return ApiResponse::error('UNAUTHENTICATED', 'Missing Bearer token in Authorization header.', [], 401);
        }

        $claims = $this->tokenService->validateToken($token);
        if ($claims === null) {
            if ($this->optional) {
                return $next($request);
            }
            return ApiResponse::error('UNAUTHENTICATED', 'Token is expired or invalid.', [], 401);
        }

        // Verify session in database if session repository is present
        if ($this->sessionRepository !== null) {
            $session = $this->sessionRepository->findByToken($token);
            if ($session === null || $session->isExpired()) {
                if ($this->optional) {
                    return $next($request);
                }
                return ApiResponse::error('UNAUTHENTICATED', 'Session has expired or was revoked.', [], 401);
            }
        }

        $request = $request
            ->withAttribute('userId', $claims['sub'] ?? null)
            ->withAttribute('userEmail', $claims['email'] ?? null)
            ->withAttribute('userRole', $claims['role'] ?? 'customer');

        return $next($request);
    }
}
