<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Http;

final class Request
{
    private string $method;
    private string $path;
    /** @var array<string, mixed> */
    private array $queryParams;
    /** @var array<string, mixed> */
    private array $body;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<string, mixed> */
    private array $attributes = [];
    private string $rawBody;

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $body = [],
        array $headers = [],
        string $rawBody = ''
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? '/', '/');
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->rawBody = $rawBody;

        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower(str_replace('_', '-', $k))] = (string) $v;
        }
        $this->headers = $normalizedHeaders;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = (string) $value;
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $body = [];
        if (!empty($rawBody)) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } elseif (!empty($_POST)) {
            $body = $_POST;
        }

        return new self($method, $uri, $_GET ?? [], $body, $headers, $rawBody);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getBodyParam(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBearerToken(): ?string
    {
        $authHeader = $this->getHeader('authorization');
        if ($authHeader !== null && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function withBody(array $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
