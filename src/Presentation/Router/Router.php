<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Router;

use Closure;
use Throwable;
use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\DomainException;
use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Middleware\MiddlewareInterface;
use Vapehaus\Presentation\Response\ApiResponse;

final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     pattern: string,
     *     paramNames: array<int, string>,
     *     handler: callable,
     *     middlewares: array<int, MiddlewareInterface>
     * }>
     */
    private array $routes = [];

    /**
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function addRoute(string $method, string $path, callable $handler, array $middlewares = []): self
    {
        $normalizedPath = '/' . trim($path, '/');

        // Extract parameters like {id} or {slug}
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $normalizedPath);

        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $regex,
            'paramNames' => $paramNames,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];

        return $this;
    }

    /**
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function get(string $path, callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function post(string $path, callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function put(string $path, callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function patch(string $path, callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    /**
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function delete(string $path, callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function dispatch(Request $request): Response
    {
        $reqMethod = $request->getMethod();
        $reqPath = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $reqMethod) {
                continue;
            }

            if (preg_match($route['pattern'], $reqPath, $matches)) {
                array_shift($matches); // Remove full match

                $params = [];
                foreach ($route['paramNames'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                    $request = $request->withAttribute($name, $matches[$index] ?? null);
                }

                // Execute middleware pipeline
                $handler = $route['handler'];
                $pipeline = array_reverse($route['middlewares']);

                $runner = function (Request $req) use ($handler, $params): Response {
                    try {
                        return $handler($req, $params);
                    } catch (ValidationException $e) {
                        return ApiResponse::error($e->getErrorCode(), $e->getMessage(), $e->getDetails(), 422);
                    } catch (UnderageException $e) {
                        return ApiResponse::error($e->getErrorCode(), $e->getMessage(), [], 403);
                    } catch (InsufficientStockException $e) {
                        return ApiResponse::error($e->getErrorCode(), $e->getMessage(), [], 422);
                    } catch (EntityNotFoundException $e) {
                        return ApiResponse::error($e->getErrorCode(), $e->getMessage(), [], 404);
                    } catch (AuthenticationException $e) {
                        return ApiResponse::error($e->getErrorCode(), $e->getMessage(), [], 401);
                    } catch (DomainException $e) {
                        return ApiResponse::error($e->getErrorCode(), $e->getMessage(), [], 400);
                    } catch (Throwable $e) {
                        return ApiResponse::error('SERVER_ERROR', $e->getMessage(), [], 500);
                    }
                };

                foreach ($pipeline as $middleware) {
                    $next = $runner;
                    $runner = fn (Request $req) => $middleware->process($req, $next);
                }

                return $runner($request);
            }
        }

        return ApiResponse::error('NOT_FOUND', "Endpoint {$reqMethod} {$reqPath} was not found on this server.", [], 404);
    }
}
