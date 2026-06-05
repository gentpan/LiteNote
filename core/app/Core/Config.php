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

    public static function load(string $file): void
    {
        if (!self::$loaded) {
            $path = dirname(__DIR__, 3) . '/' . $file . '.php';
            if (!is_file($path)) {
                throw new \RuntimeException("Config file not found: {$file}");
            }
            self::$items = require $path;
            self::$loaded = true;
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
