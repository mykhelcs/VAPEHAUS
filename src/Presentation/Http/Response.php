<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Http;

final class Response
{
    private int $statusCode;
    /** @var array<string, string> */
    private array $headers;
    private string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $statusCode = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self($statusCode, $headers, (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;
        return new self($this->statusCode, $headers, $this->body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $merged = array_merge($this->headers, $headers);
        return new self($this->statusCode, $merged, $this->body);
    }

    public function getHeader(string $name): ?string
    {
        $lowerName = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lowerName) {
                return $v;
            }
        }
        return null;
    }

    public function hasHeader(string $name): bool
    {
        return $this->getHeader($name) !== null;
    }

    public function withStatus(int $statusCode): self
    {
        return new self($statusCode, $this->headers, $this->body);
    }

    public function withBody(string $body): self
    {
        return new self($this->statusCode, $this->headers, $body);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
