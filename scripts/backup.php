<?php
declare(strict_types=1);

/**
 * LiteNote 备份 CLI
 *
 * 用法：
 *   php scripts/backup.php          # 按设置条件执行一次到期备份（同旧版 bootstrap 行为）
 *   php scripts/backup.php --force  # 忽略条件，立即执行一次备份
 *
 * 建议通过系统 cron 定时调用：
 *   0 1 * * * cd /path/to/litenote && php scripts/backup.php >> /var/log/litenote-backup.log 2>&1
 */

$basePath = dirname(__DIR__);
require $basePath . '/core/app/bootstrap.php';

use App\Services\BackupService;

$force = in_array('--force', $argv, true);

try {
    if ($force) {
        $result = (new BackupService())->run(null, true);
        echo "[OK] " . $result['message'] . PHP_EOL;
        if (!empty($result['files'])) {
            echo "     files: " . implode(', ', $result['files']) . PHP_EOL;
        }
        exit(0);
    }

    BackupService::runDueSafely();
    $settings = BackupService::settings();
    if ($settings['attachment_backup_enabled'] !== '1') {
        echo "[SKIP] 备份未启用。" . PHP_EOL;
        exit(0);
    }
    echo "[OK] 备份检查完成。" . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo "[ERR] " . $e->getMessage() . PHP_EOL;
    exit(1);
}
