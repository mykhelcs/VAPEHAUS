<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Cache;

final class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim(
            $directory ?? (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '.cache'),
            DIRECTORY_SEPARATOR
        );

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getFilePath($key);
        if (!file_exists($path)) {
            return $default;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $default;
        }

        $data = @json_decode($raw, true);
        if (!is_array($data) || !isset($data['expires_at'], $data['value'])) {
            @unlink($path);
            return $default;
        }

        if (time() >= $data['expires_at']) {
            @unlink($path);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 300, array $tags = []): bool
    {
        $path = $this->getFilePath($key);
        $payload = json_encode([
            'key' => $key,
            'expires_at' => time() + $ttlSeconds,
            'tags' => array_values(array_unique($tags)),
            'value' => $value,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return false;
        }

        $tempPath = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tempPath, $payload, LOCK_EX) !== false) {
            return @rename($tempPath, $path);
        }

        return false;
    }

    public function delete(string $key): bool
    {
        $path = $this->getFilePath($key);
        if (file_exists($path)) {
            return @unlink($path);
        }

        return false;
    }

    public function clear(): bool
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
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
        if (empty($tags)) {
            return;
        }

        $tagSet = array_flip($tags);
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }

            $data = @json_decode($raw, true);
            if (is_array($data) && isset($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tag) {
                    if (isset($tagSet[$tag])) {
                        @unlink($file);
                        break;
                    }
                }
            }
        }
    }
}
