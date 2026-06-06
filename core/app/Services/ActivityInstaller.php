<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ActivityDatabase;

final class ActivityInstaller
{
    /** 进程内"只建表一次"守卫:同一请求里被调多次时,只有首次真正执行 DDL。 */
    private static bool $done = false;

    public static function install(): array
    {
        if (self::$done) {
            return [];
        }
        self::$done = true;

        $db = ActivityDatabase::getInstance();
        $log = [];

        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            action TEXT NOT NULL,
            source TEXT NOT NULL,
            external_id TEXT,
            title TEXT NOT NULL,
            content TEXT,
            url TEXT,
            icon TEXT,
            metadata TEXT,
            visibility TEXT NOT NULL DEFAULT 'public',
            happened_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
        SQL);

        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS daily_activity_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL UNIQUE,
            coding_seconds INTEGER DEFAULT 0,
            music_seconds INTEGER DEFAULT 0,
            video_seconds INTEGER DEFAULT 0,
            ai_input_tokens INTEGER DEFAULT 0,
            ai_output_tokens INTEGER DEFAULT 0,
            ai_total_tokens INTEGER DEFAULT 0,
            github_events INTEGER DEFAULT 0,
            blog_posts INTEGER DEFAULT 0,
            social_events INTEGER DEFAULT 0,
            manual_events INTEGER DEFAULT 0,
            total_events INTEGER DEFAULT 0,
            active_types INTEGER DEFAULT 0,
            metadata TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
        SQL);

        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS activity_integrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT NOT NULL UNIQUE,
            access_token TEXT,
            refresh_token TEXT,
            expires_at TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            last_synced_at TEXT,
            metadata TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
        SQL);

        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS activity_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source TEXT NOT NULL,
            external_id TEXT NOT NULL,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            subtitle TEXT,
            year TEXT,
            cover_url TEXT,
            local_cover TEXT,
            url TEXT,
            metadata TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(source, external_id)
        )
        SQL);

        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS activity_sync_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT NOT NULL,
            status TEXT NOT NULL,
            message TEXT,
            metadata TEXT,
            started_at TEXT NOT NULL,
            finished_at TEXT
        )
        SQL);

        $db->query('CREATE INDEX IF NOT EXISTS idx_activities_time ON activities(visibility, happened_at, id)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_activities_type ON activities(type, happened_at)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_activities_source ON activities(source, happened_at)');
        $db->query('CREATE UNIQUE INDEX IF NOT EXISTS uniq_activities_external ON activities(source, external_id) WHERE external_id IS NOT NULL AND external_id <> ""');
        $db->query('CREATE INDEX IF NOT EXISTS idx_activity_stats_date ON daily_activity_stats(date)');

        $log[] = 'Activity 数据表创建完成';
        return $log;
    }
}
