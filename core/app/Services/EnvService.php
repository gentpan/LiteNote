<?php
declare(strict_types=1);

namespace App\Services;

final class EnvService
{
    public static function path(): string
    {
        return (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/.env';
    }

    public static function status(): array
    {
        $path = self::path();
        $exists = is_file($path);
        $writable = $exists ? is_writable($path) : is_writable(dirname($path));

        return [
            'path' => $path,
            'exists' => $exists,
            'writable' => $writable,
        ];
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }
        if ($value !== false) {
            return (string)$value;
        }

        $path = self::path();
        if (!is_file($path)) {
            return $default;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)\s*$/', $line, $matches)) {
                continue;
            }
            return self::parseValue((string)$matches[1]);
        }
        return $default;
    }

    public static function getMany(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = self::get((string)$key);
        }
        return $values;
    }

    public static function setMany(array $values): void
    {
        $path = self::path();
        $status = self::status();
        if (!$status['writable']) {
            throw new \RuntimeException('.env 不可写，请检查项目根目录权限');
        }

        $lines = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $seen = [];

        foreach ($lines as $index => $line) {
            if (!preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches)) {
                continue;
            }
            $key = $matches[1];
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $lines[$index] = $key . '=' . self::formatValue((string)$values[$key]);
            $seen[$key] = true;
        }

        if ($lines !== [] && trim((string)end($lines)) !== '') {
            $lines[] = '';
        }

        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z0-9_]+$/', (string)$key) || isset($seen[$key])) {
                continue;
            }
            $lines[] = $key . '=' . self::formatValue((string)$value);
        }

        $body = implode(PHP_EOL, $lines) . PHP_EOL;
        if (@file_put_contents($path, $body, LOCK_EX) === false) {
            throw new \RuntimeException('.env 写入失败，请检查文件权限');
        }

        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z0-9_]+$/', (string)$key)) {
                continue;
            }
            $value = (string)$value;
            if (function_exists('putenv')) {
                putenv($key . '=' . $value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private static function parseValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
            return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }
        return $value;
    }

    private static function formatValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_@%+=:,\.\/-]+$/', $value)) {
            return $value;
        }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
