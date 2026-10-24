<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Middleware;

use Closure;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;

interface MiddlewareInterface
{
    /**
     * @param Closure(Request): Response $next
     */
    public function process(Request $request, Closure $next): Response;
}
