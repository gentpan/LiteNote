<?php
declare(strict_types=1);

namespace App\Core;

final class FileCache
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?: (string)Config::get('cache.path', dirname(__DIR__, 3) . '/runtime/storage/cache'), '/');
        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null, ?int $ttl = null): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }

        if ($ttl !== null && $ttl > 0 && filemtime($path) + $ttl < time()) {
            @unlink($path);
            return $default;
        }

        $payload = json_decode((string)file_get_contents($path), true);
        return is_array($payload) && array_key_exists('value', $payload) ? $payload['value'] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode([
                'created_at' => date('Y-m-d H:i:s'),
                'value' => $value,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key, null, $ttl);
        if ($cached !== null) {
            return $cached;
        }

        $lockPath = $this->path($key) . '.lock';
        $dir = dirname($lockPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            $value = $callback();
            $this->set($key, $value);
            return $value;
        }

        try {
            if (!@flock($lock, LOCK_EX)) {
                fclose($lock);
                $value = $callback();
                $this->set($key, $value);
                return $value;
            }

            // 拿到锁后再检查一次，防止多个进程重复重建
            $cached = $this->get($key, null, $ttl);
            if ($cached !== null) {
                return $cached;
            }

            $value = $callback();
            $this->set($key, $value);
            return $value;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
                @unlink($lockPath);
            }
        }
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function age(string $key): ?int
    {
        $path = $this->path($key);
        return is_file($path) ? max(0, time() - (int)filemtime($path)) : null;
    }

    public function path(string $key): string
    {
        $safe = trim($key, '/');
        $safe = preg_replace('/[^a-zA-Z0-9_.\/-]+/', '_', $safe) ?: 'cache';
        $safe = str_replace(['..', '\\'], '', $safe);
        $path = rtrim($this->basePath, '/') . '/' . ltrim($safe, '/') . '.json';
        $base = realpath($this->basePath);
        if ($base !== false) {
            $parent = dirname($path);
            if (!is_dir($parent)) {
                @mkdir($parent, 0775, true);
            }
            $realParent = realpath($parent);
            if ($realParent !== false && !str_starts_with($realParent . '/', $base . '/')) {
                return $base . '/cache.json';
            }
        }
        return $path;
    }
}
