<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 配置加载器
 */
final class Config
{
    private static array $items = [];
    private static bool $loaded = false;
    private static bool $environmentLoaded = false;

    public static function load(string $file): void
    {
        if (!self::$loaded) {
            self::loadEnvironment();
            $path = dirname(__DIR__, 3) . '/' . $file . '.php';
            if (!is_file($path)) {
                throw new \RuntimeException("Config file not found: {$file}");
            }
            self::$items = require $path;
            self::$loaded = true;
        }
    }

    /**
     * Load the project-local .env before config.php reads process values.
     * Existing process/server variables always win over file values.
     */
    private static function loadEnvironment(): void
    {
        if (self::$environmentLoaded) {
            return;
        }
        self::$environmentLoaded = true;

        $envPath = dirname(__DIR__, 3) . '/.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (function_exists('putenv')) {
                putenv($key . '=' . $value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = self::$items;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &self::$items;
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $ref[$k] = $value;
            } else {
                if (!isset($ref[$k]) || !is_array($ref[$k])) {
                    $ref[$k] = [];
                }
                $ref = &$ref[$k];
            }
        }
    }

    public static function all(): array
    {
        return self::$items;
    }
}
