<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class RuntimeRequirements
{
    private const DEFAULT_APP_KEY = 'change-me-32-bytes-random-secret!!';

    public static function verify(): void
    {
        self::assertPhpVersion('8.5.0');
        self::assertExtensions([
            'pdo' => 'PDO',
            'pdo_sqlite' => 'PDO SQLite',
            'sqlite3' => 'SQLite3',
        ]);
        self::warnIfMissing('gd', 'GD 未启用，验证码与部分图片处理可能不可用');
        self::warnIfFts5Missing();
        self::assertAppKey();
    }

    private static function assertPhpVersion(string $minimum): void
    {
        if (version_compare(PHP_VERSION, $minimum, '>=')) {
            return;
        }
        self::fail('LiteNote 需要 PHP ' . $minimum . ' 或更高版本，当前为 ' . PHP_VERSION);
    }

    /**
     * @param array<string, string> $extensions
     */
    private static function assertExtensions(array $extensions): void
    {
        $missing = [];
        foreach ($extensions as $ext => $label) {
            if (!extension_loaded($ext)) {
                $missing[] = $label . ' (' . $ext . ')';
            }
        }
        if ($missing !== []) {
            self::fail('LiteNote 需要以下 PHP 扩展：' . implode('、', $missing));
        }
    }

    private static function warnIfMissing(string $ext, string $message): void
    {
        if (!extension_loaded($ext)) {
            error_log('LiteNote: ' . $message);
        }
    }

    private static function warnIfFts5Missing(): void
    {
        try {
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE VIRTUAL TABLE IF NOT EXISTS __fts_probe USING fts5(x)');
        } catch (\Throwable) {
            error_log('LiteNote: SQLite FTS5 不可用，全文搜索将降级为 LIKE 查询（较慢）');
        }
    }

    private static function assertAppKey(): void
    {
        if (Config::get('app.debug', false)) {
            return;
        }
        $key = trim((string) Config::get('app.key', ''));
        if ($key === '' || $key === self::DEFAULT_APP_KEY || strlen($key) < 32) {
            self::fail('生产环境请在 .env 中设置至少 32 字符的 APP_KEY');
        }
    }

    private static function fail(string $message): never
    {
        error_log($message);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit(1);
    }
}
