<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * 按文件名顺序执行 core/database/migrations/*.sql。
 */
final class MigrationRunner
{
  private static bool $done = false;

  public static function run(): void
  {
    if (self::$done) {
      return;
    }
    self::$done = true;

    if (!is_file((string)Config::get('database.sqlite'))) {
      return;
    }

    $db = Database::getInstance();
    $db->query(
      'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )'
    );

    $dir = dirname(__DIR__, 2) . '/database/migrations';
    if (!is_dir($dir)) {
      return;
    }

    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
      $name = basename($file);
      $applied = $db->fetchColumn(
        'SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1',
        [$name]
      );
      if ($applied) {
        continue;
      }

      $sql = (string)file_get_contents($file);
      if (trim($sql) === '') {
        continue;
      }

      if (!self::canApply($name, $db)) {
        continue;
      }

      try {
        $db->pdo()->exec($sql);
        $db->insert('schema_migrations', ['migration' => $name]);
      } catch (\Throwable $e) {
        error_log('LiteNote migration failed (' . $name . '): ' . $e->getMessage());
      }
    }
  }

  private static function canApply(string $name, Database $db): bool
  {
    if ($name === '2026_06_30_activity_indexes.sql') {
      return (bool)$db->fetchColumn(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='activities' LIMIT 1"
      );
    }

    return true;
  }
}
