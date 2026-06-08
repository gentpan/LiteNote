<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

final class BackupService
{
    private const SETTING_DEFAULTS = [
        'attachment_backup_enabled' => '0',
        'attachment_backup_s3_enabled' => '1',
        'attachment_backup_time' => '00:00',
        'attachment_backup_retention_days' => '15',
        'attachment_backup_keep_versions' => '10',
        'attachment_backup_last_run_date' => '',
        'attachment_backup_last_status' => '',
    ];

    public static function defaults(): array
    {
        return self::SETTING_DEFAULTS;
    }

    public static function runDueSafely(): void
    {
        try {
            $settings = self::settings();
            if ($settings['attachment_backup_enabled'] !== '1') {
                return;
            }
            $today = date('Y-m-d');
            if ($settings['attachment_backup_last_run_date'] === $today) {
                return;
            }
            if (date('H:i') < self::backupTime($settings)) {
                return;
            }
            (new self())->run($settings, false);
        } catch (\Throwable $e) {
            try {
                Setting::setMany([
                    'attachment_backup_last_run_date' => date('Y-m-d'),
                    'attachment_backup_last_status' => '自动备份失败：' . $e->getMessage() . '（' . date('Y-m-d H:i:s') . '）',
                ]);
            } catch (\Throwable) {
            }
        }
    }

    public function run(?array $settings = null, bool $force = true): array
    {
        $settings = $settings ?? self::settings();
        if (!$force && $settings['attachment_backup_enabled'] !== '1') {
            return ['skipped' => true, 'message' => '备份未启用'];
        }

        $createdAt = date('Ymd-His');
        $backupDir = self::backupDir();
        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('备份目录创建失败');
        }

        $files = [];
        $jsonPath = $backupDir . '/litenote-data-' . $createdAt . '.json';
        $this->writeJsonBackup($jsonPath, $createdAt);
        $files[] = $jsonPath;

        foreach (self::databasePaths() as $name => $path) {
            if (!is_file($path)) {
                continue;
            }
            $target = $backupDir . '/litenote-' . $name . '-' . $createdAt . '.sqlite';
            if (!copy($path, $target)) {
                throw new \RuntimeException('数据库备份失败：' . $name);
            }
            $files[] = $target;
        }

        $uploaded = [];
        if ($settings['attachment_backup_s3_enabled'] === '1') {
            $uploaded = $this->uploadFiles($files, $settings);
        }

        $localDeleted = $this->cleanupLocal($settings);
        $remoteDeleted = $settings['attachment_backup_s3_enabled'] === '1'
            ? $this->cleanupRemote($settings)
            : 0;

        $message = '备份完成：本地 ' . count($files) . ' 个文件';
        if ($uploaded !== []) {
            $message .= '，远端同步 ' . count($uploaded) . ' 个对象';
        }
        if ($localDeleted > 0 || $remoteDeleted > 0) {
            $message .= '，清理本地 ' . $localDeleted . ' 个、远端 ' . $remoteDeleted . ' 个';
        }

        Setting::setMany([
            'attachment_backup_last_run_date' => date('Y-m-d'),
            'attachment_backup_last_status' => $message . '（' . date('Y-m-d H:i:s') . '）',
        ]);

        return [
            'message' => $message,
            'files' => array_map('basename', $files),
            'uploaded' => $uploaded,
            'local_deleted' => $localDeleted,
            'remote_deleted' => $remoteDeleted,
        ];
    }

    public static function settings(): array
    {
        $values = self::SETTING_DEFAULTS;
        foreach (array_keys($values) as $key) {
            $values[$key] = (string)Setting::get($key, $values[$key]);
        }
        return $values;
    }

    private function writeJsonBackup(string $path, string $createdAt): void
    {
        $db = \App\Models\Attachment::db();
        $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $data = [
            'app' => 'LiteNote',
            'created_at' => date('c'),
            'backup_id' => $createdAt,
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $name = (string)($table['name'] ?? '');
            if ($name === '') {
                continue;
            }
            try {
                $data['tables'][$name] = $db->fetchAll('SELECT * FROM "' . str_replace('"', '""', $name) . '"');
            } catch (\Throwable) {
                $data['tables'][$name] = [];
            }
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('JSON 备份写入失败');
        }
    }

    private function uploadFiles(array $files, array $settings): array
    {
        $s3 = new S3StorageService($settings);
        $uploaded = [];
        $basePrefix = trim((string)($settings['attachment_s3_prefix'] ?? ''), "/ \t\n\r\0\x0B");
        $prefix = ($basePrefix !== '' ? $basePrefix . '/' : '') . 'backups';

        foreach ($files as $path) {
            $name = basename((string)$path);
            $type = str_ends_with($name, '.json') ? 'application/json; charset=utf-8' : 'application/vnd.sqlite3';
            $key = $prefix . '/' . $name;
            $s3->putObject($key, (string)file_get_contents((string)$path), $type);
            $uploaded[] = $key;
        }

        return $uploaded;
    }

    private function cleanupLocal(array $settings): int
    {
        $files = self::backupFiles();
        $delete = $this->expiredBackupFiles($files, $settings);
        foreach ($delete as $file) {
            @unlink($file);
        }
        return count($delete);
    }

    private function cleanupRemote(array $settings): int
    {
        $s3 = new S3StorageService($settings);
        $basePrefix = trim((string)($settings['attachment_s3_prefix'] ?? ''), "/ \t\n\r\0\x0B");
        $prefix = ($basePrefix !== '' ? $basePrefix . '/' : '') . 'backups';
        $keys = $s3->listKeys($prefix);
        $delete = $this->expiredRemoteKeys($keys, $settings);
        return $delete !== [] ? $s3->deleteKeys($delete) : 0;
    }

    /**
     * @param array<int,string> $files
     * @return array<int,string>
     */
    private function expiredBackupFiles(array $files, array $settings): array
    {
        $groups = $this->groupByBackupId($files);
        $expiredIds = $this->expiredBackupIds(array_keys($groups), $settings);
        $delete = [];
        foreach ($expiredIds as $id) {
            $delete = array_merge($delete, $groups[$id] ?? []);
        }
        return $delete;
    }

    /**
     * @param array<int,string> $keys
     * @return array<int,string>
     */
    private function expiredRemoteKeys(array $keys, array $settings): array
    {
        $groups = $this->groupByBackupId($keys);
        $expiredIds = $this->expiredBackupIds(array_keys($groups), $settings);
        $delete = [];
        foreach ($expiredIds as $id) {
            $delete = array_merge($delete, $groups[$id] ?? []);
        }
        return $delete;
    }

    /**
     * @param array<int,string> $items
     * @return array<string,array<int,string>>
     */
    private function groupByBackupId(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $name = basename($item);
            if (!preg_match('/-(\d{8}-\d{6})\.(json|sqlite)$/', $name, $match)) {
                continue;
            }
            $groups[$match[1]][] = $item;
        }
        krsort($groups);
        return $groups;
    }

    /**
     * @param array<int,string> $ids
     * @return array<int,string>
     */
    private function expiredBackupIds(array $ids, array $settings): array
    {
        rsort($ids);
        $keepVersions = max(1, (int)$settings['attachment_backup_keep_versions']);
        $retentionDays = max(1, (int)$settings['attachment_backup_retention_days']);
        $cutoff = strtotime('-' . $retentionDays . ' days');
        $delete = [];

        foreach ($ids as $index => $id) {
            if ($index < $keepVersions) {
                continue;
            }
            $time = \DateTimeImmutable::createFromFormat('Ymd-His', $id);
            $expiredByAge = $time && $time->getTimestamp() < $cutoff;
            if ($expiredByAge) {
                $delete[] = $id;
            }
        }

        return $delete;
    }

    /**
     * @return array<int,string>
     */
    private static function backupFiles(): array
    {
        return glob(self::backupDir() . '/litenote-*-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9].{json,sqlite}', GLOB_BRACE) ?: [];
    }

    private static function backupDir(): string
    {
        return dirname(__DIR__, 3) . '/runtime/storage/backups';
    }

    /**
     * @return array<string,string>
     */
    private static function databasePaths(): array
    {
        return [
            'database' => (string)Config::get('database.sqlite'),
            'activity' => (string)Config::get('database.activity'),
        ];
    }

    private static function backupTime(array $settings): string
    {
        $time = trim((string)$settings['attachment_backup_time']);
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '00:00';
    }
}
