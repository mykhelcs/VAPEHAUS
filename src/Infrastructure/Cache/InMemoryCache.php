<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Cache;

final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: int, tags: array<int, string>}>
     */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->storage[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 300, array $tags = []): bool
    {
        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttlSeconds,
            'tags' => array_values(array_unique($tags)),
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }

        return false;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        if (time() >= $this->storage[$key]['expires_at']) {
            unset($this->storage[$key]);
            return false;
        }

        return true;
    }

    public function remember(string $key, int $ttlSeconds, callable $callback, array $tags = []): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttlSeconds, $tags);

        return $value;
    }

    public function invalidateTags(array $tags): void
    {
        $tagSet = array_flip($tags);

        foreach ($this->storage as $key => $item) {
            foreach ($item['tags'] as $tag) {
                if (isset($tagSet[$tag])) {
                    unset($this->storage[$key]);
                    break;
                }
            }
        }
    }
}
