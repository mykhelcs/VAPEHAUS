<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Middleware;

use Closure;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class RequireRoleMiddleware implements MiddlewareInterface
{
    private string $requiredRole;

    public function __construct(string $requiredRole)
    {
        $this->requiredRole = strtolower($requiredRole);
    }

    public function process(Request $request, Closure $next): Response
    {
        $userRole = strtolower((string) $request->getAttribute('userRole', ''));

        if ($userRole !== $this->requiredRole) {
            return ApiResponse::error(
                'FORBIDDEN',
                "Access denied. Requires '{$this->requiredRole}' privileges.",
                [],
                403
            );
        }

        return $next($request);
    }
}
