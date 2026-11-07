<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Cache;

interface CacheInterface
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache with a time-to-live in seconds.
     *
     * @param array<int, string> $tags
     */
    public function set(string $key, mixed $value, int $ttlSeconds = 300, array $tags = []): bool;

    /**
     * Delete an item from the cache.
     */
    public function delete(string $key): bool;

    /**
     * Clear all cached items.
     */
    public function clear(): bool;

    /**
     * Check if an item exists in the cache and has not expired.
     */
    public function has(string $key): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param array<int, string> $tags
     */
    public function remember(string $key, int $ttlSeconds, callable $callback, array $tags = []): mixed;

    /**
     * Invalidate all cache entries associated with any of the given tags.
     *
     * @param array<int, string> $tags
     */
    public function invalidateTags(array $tags): void;
}
