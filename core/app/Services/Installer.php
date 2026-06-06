<?php
declare(strict_types=1);

/**
 * 数据库安装 / 升级脚本
 * CLI/setup service for creating and upgrading the local SQLite schema.
 *
 * 性能考虑：所有 SQL 用 CREATE TABLE IF NOT EXISTS，
 * 可重复执行而不出错
 */

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\Toggle;

final class Installer
{
    public static function isInstalled(): bool
    {
        $path = Config::get('database.sqlite');
        if (!is_file($path)) return false;
        try {
            $row = Database::getInstance()->fetchOne(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='settings'"
            );
            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function install(): array
    {
        $log = [];
        $db = Database::getInstance();

        // 用户
        // socials: JSON 数组,每个元素 {key, url, icon}
        //   key  平台名(github / x / email / website ... 自由取)
        //   url  链接地址
        //   icon fontawesome 图标 HTML,例如 <i class="fa-brands fa-github"></i>
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            nickname VARCHAR(50),
            avatar VARCHAR(255),
            socials TEXT,
            role VARCHAR(20) DEFAULT 'admin',
            last_login_at DATETIME,
            last_login_ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 分类
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(50) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            description VARCHAR(255),
            parent_id INTEGER DEFAULT 0,
            sort INTEGER DEFAULT 0,
            post_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 文章
        // 注意:status 字面量 'published' 与 App\Enums\PostStatus::Published->value 共享
        // is_top/is_recommend 字面量 0/1 与 App\Enums\Toggle 共享
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            summary TEXT,
            content TEXT NOT NULL,
            markdown_content TEXT,
            cover VARCHAR(255),
            category_id INTEGER DEFAULT 0,
            user_id INTEGER DEFAULT 1,
            views INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            is_top INTEGER DEFAULT 0,
            is_recommend INTEGER DEFAULT 0,
            status VARCHAR(20) DEFAULT 'published',
            published_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 页面
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT NOT NULL,
            markdown_content TEXT,
            url VARCHAR(255),
            icon VARCHAR(80),
            views INTEGER DEFAULT 0,
            is_nav INTEGER DEFAULT 0,
            is_system INTEGER DEFAULT 0,
            sort INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 附件
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            filepath VARCHAR(500) NOT NULL,
            fileurl VARCHAR(500) NOT NULL,
            filetype VARCHAR(50),
            filesize INTEGER DEFAULT 0,
            mime_type VARCHAR(100),
            user_id INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 评论
        // status 字面量 'pending' 与 App\Enums\CommentStatus::Pending->value 共享
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER DEFAULT 0,
            page_id INTEGER DEFAULT 0,
            parent_id INTEGER DEFAULT 0,
            talk_id INTEGER DEFAULT 0,
            music_id INTEGER DEFAULT 0,
            nickname VARCHAR(50) NOT NULL,
            email VARCHAR(100),
            website VARCHAR(255),
            content TEXT NOT NULL,
            ip VARCHAR(45),
            ua VARCHAR(255),
            geo_country_code VARCHAR(2),
            geo_country VARCHAR(64),
            geo_region VARCHAR(80),
            geo_city VARCHAR(80),
            geo_data TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 友情链接
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(50) NOT NULL,
            url VARCHAR(255) NOT NULL,
            logo VARCHAR(255),
            description VARCHAR(255),
            rss_url VARCHAR(255),
            contact_email VARCHAR(255),
            request_type VARCHAR(20) DEFAULT 'admin',
            previous_url VARCHAR(255),
            sort INTEGER DEFAULT 0,
            is_enabled INTEGER DEFAULT 1,
            submitted_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME
        )
        SQL);

        // 滔客
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS talk (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            images TEXT,
            music_id INTEGER DEFAULT 0,
            music TEXT,
            mood VARCHAR(20),
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            is_public INTEGER DEFAULT 1,
            post_type VARCHAR(20) DEFAULT 'talk',
            tweet_id VARCHAR(40),
            tweet_url TEXT,
            tweet_author_name VARCHAR(120),
            tweet_author_handle VARCHAR(120),
            tweet_author_avatar TEXT,
            tweet_author_verified INTEGER DEFAULT 0,
            tweet_posted_at DATETIME,
            tweet_likes_count INTEGER DEFAULT 0,
            tweet_reposts_count INTEGER DEFAULT 0,
            tweet_data TEXT,
            published_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 音乐
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS music (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(160) NOT NULL,
            artist VARCHAR(120),
            album VARCHAR(160),
            audio_url TEXT NOT NULL,
            cover_url TEXT,
            lyrics TEXT,
            lyrics_url TEXT,
            description TEXT,
            mood VARCHAR(60),
            duration VARCHAR(20),
            play_count INTEGER DEFAULT 0,
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            sort INTEGER DEFAULT 0,
            is_public INTEGER DEFAULT 1,
            published_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 站点设置
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            k VARCHAR(100) UNIQUE NOT NULL,
            v TEXT,
            type VARCHAR(20) DEFAULT 'string',
            label VARCHAR(100),
            group_name VARCHAR(50) DEFAULT 'basic',
            sort INTEGER DEFAULT 0
        )
        SQL);

        // Passkey / WebAuthn 凭证
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS webauthn_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL DEFAULT 1,
            credential_id TEXT NOT NULL UNIQUE,
            public_key TEXT NOT NULL,
            counter INTEGER DEFAULT 0,
            device_name VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME
        )
        SQL);

        // 邮件日志
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS mail_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider VARCHAR(40),
            mail_type VARCHAR(60),
            recipient VARCHAR(160),
            subject VARCHAR(255),
            status VARCHAR(24),
            error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 邮件退订
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS mail_unsubscribes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(160) NOT NULL,
            mail_type VARCHAR(60) NOT NULL DEFAULT 'all',
            token VARCHAR(80) NOT NULL,
            ip VARCHAR(45),
            ua VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(email, mail_type)
        )
        SQL);

        // 索引
        $db->query('CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_posts_published_at ON posts(published_at)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_comments_post ON comments(post_id)');
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_comments_talk ON comments(talk_id)');
        } catch (\Throwable) {
            // 老库会在 selfUpgrade() 加列后再建一次索引
        }
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_comments_music ON comments(music_id)');
        } catch (\Throwable) {
            // 老库会在 selfUpgrade() 加列后再建一次索引
        }
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_talk_music ON talk(music_id)');
        } catch (\Throwable) {
            // 老库会在 selfUpgrade() 加列后再建一次索引
        }
        $db->query('CREATE INDEX IF NOT EXISTS idx_comments_status ON comments(status)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_music_public_sort ON music(is_public, sort, id)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_music_public_published ON music(is_public, published_at, sort, id)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_webauthn_user ON webauthn_credentials(user_id)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_mail_logs_created ON mail_logs(created_at)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_mail_logs_status ON mail_logs(status)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_mail_unsub_email ON mail_unsubscribes(email)');

        $log[] = '数据表创建完成';

        // 默认数据：管理员
        $exists = $db->fetchOne('SELECT id FROM users LIMIT 1');
        if (!$exists) {
            $db->insert('users', [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'email'    => 'admin@example.com',
                'nickname' => '管理员',
                'role'     => 'admin',
            ]);
            $log[] = '默认管理员创建完成（admin / admin123）';
        }

        // 默认设置
        $exists = $db->fetchOne('SELECT id FROM settings LIMIT 1');
        if (!$exists) {
            $settings = [
                ['k' => 'title',         'v' => '我的个人博客',         'label' => '站点标题', 'group_name' => 'basic',   'sort' => 1],
                ['k' => 'subtitle',      'v' => '记录、分享、思考',     'label' => '站点副标题','group_name' => 'basic',   'sort' => 2],
                ['k' => 'description',   'v' => '一个用 PHP 8.5 写的个人博客', 'label' => '站点描述', 'group_name' => 'basic',   'sort' => 3],
                ['k' => 'keywords',      'v' => 'PHP,博客,个人',       'label' => '关键词',   'group_name' => 'basic',   'sort' => 4],
                ['k' => 'beian',         'v' => '',                     'label' => '备案号',   'group_name' => 'basic',   'sort' => 5],
                ['k' => 'site_avatar_url','v' => '',                     'label' => '站点头像地址', 'group_name' => 'basic', 'sort' => 8],
                ['k' => 'comment_need_audit',    'v' => '1', 'type' => 'bool', 'label' => '评论需要审核', 'group_name' => 'comment', 'sort' => 6],
                ['k' => 'comment_captcha',       'v' => '0', 'type' => 'bool', 'label' => '启用验证码', 'group_name' => 'comment', 'sort' => 7],
                ['k' => 'home_feed_mode',        'v' => 'mixed', 'type' => 'select', 'label' => '首页展示模式', 'group_name' => 'reading', 'sort' => 1],
                ['k' => 'home_total_limit',      'v' => '12', 'type' => 'number', 'label' => '首页总数量', 'group_name' => 'reading', 'sort' => 2],
                ['k' => 'home_post_limit',       'v' => '8', 'type' => 'number', 'label' => '首页文章读取数', 'group_name' => 'reading', 'sort' => 3],
                ['k' => 'home_talk_limit',       'v' => '8', 'type' => 'number', 'label' => '首页说说读取数', 'group_name' => 'reading', 'sort' => 4],
                ['k' => 'home_fixed_posts',      'v' => '', 'type' => 'textarea', 'label' => '固定显示文章', 'group_name' => 'reading', 'sort' => 5],
                ['k' => 'home_fixed_talks',      'v' => '', 'type' => 'textarea', 'label' => '固定显示说说', 'group_name' => 'reading', 'sort' => 6],
                ['k' => 'post_list_per_page',    'v' => '5', 'type' => 'number', 'label' => '文章列表每页数量', 'group_name' => 'reading', 'sort' => 7],
                ['k' => 'permalink_mode', 'v' => 'default', 'type' => 'select', 'label' => '文章链接模式', 'group_name' => 'permalink', 'sort' => 1],
                ['k' => 'permalink_numeric_prefix', 'v' => 'post', 'label' => '数字链接前缀', 'group_name' => 'permalink', 'sort' => 10],
                ['k' => 'permalink_numeric_source', 'v' => 'six', 'type' => 'select', 'label' => '数字来源', 'group_name' => 'permalink', 'sort' => 11],
                ['k' => 'permalink_numeric_suffix', 'v' => '.html', 'type' => 'select', 'label' => '数字链接后缀', 'group_name' => 'permalink', 'sort' => 12],
                ['k' => 'site_icp',           'v' => '',                 'label' => 'ICP 备案',  'group_name' => 'basic',   'sort' => 7],
                ['k' => 'mail_enabled',       'v' => '0',                'type' => 'bool', 'label' => '启用邮件', 'group_name' => 'mail', 'sort' => 1],
                ['k' => 'mail_driver',        'v' => 'sendflare',         'label' => '邮件驱动', 'group_name' => 'mail', 'sort' => 2],
                ['k' => 'mail_from',          'v' => 'noreply@example.com','label' => '发件邮箱', 'group_name' => 'mail', 'sort' => 3],
                ['k' => 'mail_from_name',     'v' => 'LiteNote',          'label' => '发件名称', 'group_name' => 'mail', 'sort' => 4],
            ];
            foreach ($settings as $s) {
                $db->insert('settings', $s);
            }
            $log[] = '默认设置创建完成';
        }

        // 默认分类
        $exists = $db->fetchOne('SELECT id FROM categories LIMIT 1');
        if (!$exists) {
            $db->insert('categories', [
                'name' => '默认分类', 'slug' => 'default',
                'description' => '默认分类', 'sort' => 1,
            ]);
            $log[] = '默认分类创建完成';
        }

        // 默认欢迎文章
        $exists = $db->fetchOne('SELECT id FROM posts LIMIT 1');
        if (!$exists) {
            $now = date('Y-m-d H:i:s');
            $welcomeMarkdown = "欢迎使用 **LiteNote**！这是你的第一篇文章。\n\n你可以在后台编辑或删除它。";
            $db->insert('posts', [
                'title'        => '欢迎使用我的博客',
                'slug'         => 'welcome',
                'summary'      => '这是第一篇示例文章',
                'content'      => '',
                'markdown_content' => '',
                'category_id'  => 1,
                'is_top'       => Toggle::On->value,
                'status'       => PostStatus::Published->value,
                'published_at' => $now,
            ]);
            PostContentStorage::write('welcome', $welcomeMarkdown);
            $log[] = '示例文章创建完成';
        }

        // 关于页面
        $exists = $db->fetchOne('SELECT id FROM pages LIMIT 1');
        if (!$exists) {
            $db->insert('pages', [
                'title'        => '关于我',
                'slug'         => 'about',
                'content'      => '<p>这是关于页面，介绍你自己。</p>',
                'is_nav'       => Toggle::On->value,
                'sort'         => 1,
            ]);
            $db->insert('pages', [
                'title'        => '友情链接',
                'slug'         => 'friends',
                'content'      => '<p>欢迎互换友链。联系方式在关于页。</p>',
                'is_nav'       => Toggle::On->value,
                'sort'         => 2,
            ]);
            $log[] = '默认页面创建完成';
        }

        // 写一个 install.lock
        file_put_contents(Config::get('database.sqlite') . '.installed', date('c'));

        // ====== 增量 schema 升级(对已存在的老库) ======
        // 每次 install() 都尝试 ALTER,失败 swallow。SQLite 下安全且幂等。
        self::selfUpgrade($db, $log);
        \App\Models\Page::ensureSystemPages();
        $log[] = '系统页面导航初始化完成';

        return $log;
    }

    /**
     * 增量 schema 升级。已存在则忽略,新增则 ALTER。
     * 调用场景:新装 / 升级 / 修复,统一入口。
     */
    private static function selfUpgrade(Database $db, array &$log): void
    {
        self::ensureMusicTable($db);

        $upgrades = [
            ['users',    'socials', 'TEXT'],         // 2026-06: 个人资料社交链接
            ['users',    'reset_token', 'VARCHAR(64)'],   // 2026-06: 密码找回 token(sha256)
            ['users',    'reset_expires_at', 'DATETIME'], // 2026-06: 密码找回 token 过期时间
            ['categories', 'icon', 'VARCHAR(64)'],          // 2026-06: 分类菜单图标(fontawesome)
            ['categories', 'show_in_nav', 'INTEGER DEFAULT 1'], // 2026-06: 是否在导航菜单显示
            ['categories', 'color', 'INTEGER'],             // 2026-06: 分类配色 0-5(空则按 id 取色)
            ['pages', 'url', 'VARCHAR(255)'],
            ['pages', 'icon', 'VARCHAR(80)'],
            ['pages', 'is_system', 'INTEGER DEFAULT 0'],
            ['comments', 'talk_id', 'INTEGER DEFAULT 0'],
            ['comments', 'music_id', 'INTEGER DEFAULT 0'],
            ['comments', 'geo_country_code', 'VARCHAR(2)'],
            ['comments', 'geo_country', 'VARCHAR(64)'],
            ['comments', 'geo_region', 'VARCHAR(80)'],
            ['comments', 'geo_city', 'VARCHAR(80)'],
            ['comments', 'geo_data', 'TEXT'],
            ['talk', 'music_id', 'INTEGER DEFAULT 0'],
            ['talk', 'music', 'TEXT'],
            ['talk', 'music_cover', 'VARCHAR(255)'],     // 2026-06: 音乐卡片封面
            ['talk', 'music_title', 'VARCHAR(120)'],     // 2026-06: 音乐卡片标题
            ['talk', 'music_artist', 'VARCHAR(120)'],    // 2026-06: 音乐卡片歌手
            ['talk', 'likes_count', 'INTEGER DEFAULT 0'],
            ['talk', 'comments_count', 'INTEGER DEFAULT 0'],
            ['talk', 'post_type', "VARCHAR(20) DEFAULT 'talk'"],
            ['talk', 'tweet_id', 'VARCHAR(40)'],
            ['talk', 'tweet_url', 'TEXT'],
            ['talk', 'tweet_author_name', 'VARCHAR(120)'],
            ['talk', 'tweet_author_handle', 'VARCHAR(120)'],
            ['talk', 'tweet_author_avatar', 'TEXT'],
            ['talk', 'tweet_author_verified', 'INTEGER DEFAULT 0'],
            ['talk', 'tweet_posted_at', 'DATETIME'],
            ['talk', 'tweet_likes_count', 'INTEGER DEFAULT 0'],
            ['talk', 'tweet_reposts_count', 'INTEGER DEFAULT 0'],
            ['talk', 'tweet_data', 'TEXT'],
            ['talk', 'published_at', 'DATETIME'],
        ];
        foreach ($upgrades as [$table, $col, $type]) {
            try {
                $db->query("ALTER TABLE {$table} ADD COLUMN {$col} {$type}");
                $log[] = "升级: ALTER TABLE {$table} ADD COLUMN {$col}";
            } catch (\Throwable) {
                // 列已存在,忽略
            }
        }

        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_comments_talk ON comments(talk_id)');
        } catch (\Throwable) {
            // ignore
        }
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_comments_music ON comments(music_id)');
        } catch (\Throwable) {
            // ignore
        }
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_talk_music ON talk(music_id)');
        } catch (\Throwable) {
            // ignore
        }
        try {
            $db->query("UPDATE talk SET post_type = 'talk' WHERE post_type IS NULL OR post_type = ''");
            $db->query('UPDATE talk SET published_at = created_at WHERE published_at IS NULL');
            $db->query('CREATE INDEX IF NOT EXISTS idx_talk_published ON talk(is_public, published_at, id)');
        } catch (\Throwable) {
            // ignore
        }

        self::migrateTalkMusicToMusic($db, $log);
        self::seedMusic($db, $log);
        self::migratePostMarkdownFiles($db, $log);
        self::ensurePasskeyTable($db);
        self::ensureMailTables($db);
    }

    private static function ensurePasskeyTable(Database $db): void
    {
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS webauthn_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL DEFAULT 1,
            credential_id TEXT NOT NULL UNIQUE,
            public_key TEXT NOT NULL,
            counter INTEGER DEFAULT 0,
            device_name VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME
        )
        SQL);

        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_webauthn_user ON webauthn_credentials(user_id)');
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function ensureMailTables(Database $db): void
    {
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS mail_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider VARCHAR(40),
            mail_type VARCHAR(60),
            recipient VARCHAR(160),
            subject VARCHAR(255),
            status VARCHAR(24),
            error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS mail_unsubscribes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(160) NOT NULL,
            mail_type VARCHAR(60) NOT NULL DEFAULT 'all',
            token VARCHAR(80) NOT NULL,
            ip VARCHAR(45),
            ua VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(email, mail_type)
        )
        SQL);
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_mail_logs_created ON mail_logs(created_at)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_mail_logs_status ON mail_logs(status)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_mail_unsub_email ON mail_unsubscribes(email)');
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function ensureMusicTable(Database $db): void
    {
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS music (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(160) NOT NULL,
            artist VARCHAR(120),
            album VARCHAR(160),
            audio_url TEXT NOT NULL,
            cover_url TEXT,
            lyrics TEXT,
            description TEXT,
            mood VARCHAR(60),
            duration VARCHAR(20),
            play_count INTEGER DEFAULT 0,
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            sort INTEGER DEFAULT 0,
            is_public INTEGER DEFAULT 1,
            published_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        $columns = [
            ['music', 'album', 'VARCHAR(160)'],
            ['music', 'cover_url', 'TEXT'],
            ['music', 'lyrics', 'TEXT'],
            ['music', 'lyrics_url', 'TEXT'],
            ['music', 'description', 'TEXT'],
            ['music', 'mood', 'VARCHAR(60)'],
            ['music', 'duration', 'VARCHAR(20)'],
            ['music', 'play_count', 'INTEGER DEFAULT 0'],
            ['music', 'likes_count', 'INTEGER DEFAULT 0'],
            ['music', 'comments_count', 'INTEGER DEFAULT 0'],
            ['music', 'sort', 'INTEGER DEFAULT 0'],
            ['music', 'is_public', 'INTEGER DEFAULT 1'],
            ['music', 'published_at', 'DATETIME'],
            ['music', 'updated_at', 'DATETIME'],
        ];
        foreach ($columns as [$table, $col, $type]) {
            try {
                $db->query("ALTER TABLE {$table} ADD COLUMN {$col} {$type}");
            } catch (\Throwable) {
                // 已存在则忽略
            }
        }

        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_music_public_sort ON music(is_public, sort, id)');
        } catch (\Throwable) {
            // ignore
        }
        try {
            $db->query(
                "UPDATE music
                 SET published_at = COALESCE(NULLIF(TRIM(published_at), ''), created_at, updated_at, CURRENT_TIMESTAMP)
                 WHERE published_at IS NULL OR TRIM(published_at) = ''"
            );
        } catch (\Throwable) {
            // ignore
        }
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_music_public_published ON music(is_public, published_at, sort, id)');
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function migrateTalkMusicToMusic(Database $db, array &$log): void
    {
        try {
            $rows = $db->fetchAll(
                "SELECT id, content, music, music_cover, music_title, music_artist, mood, is_public, created_at
                 FROM talk
                 WHERE music IS NOT NULL AND TRIM(music) <> ''"
            );
        } catch (\Throwable) {
            return;
        }

        $count = 0;
        foreach ($rows as $row) {
            $audioUrl = trim((string)($row['music'] ?? ''));
            if ($audioUrl === '') {
                continue;
            }
            $path = (string)(parse_url($audioUrl, PHP_URL_PATH) ?? $audioUrl);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'], true)) {
                continue;
            }
            $exists = $db->fetchOne('SELECT id FROM music WHERE audio_url = ? LIMIT 1', [$audioUrl]);
            if ($exists) {
                try {
                    $db->query('UPDATE talk SET music_id = ? WHERE id = ? AND COALESCE(music_id, 0) = 0', [(int)$exists['id'], (int)$row['id']]);
                } catch (\Throwable) {
                    // ignore
                }
                continue;
            }

            $title = trim((string)($row['music_title'] ?? ''));
            if ($title === '') {
                $title = pathinfo($path, PATHINFO_FILENAME) ?: '未命名音乐';
            }

            $musicId = $db->insert('music', [
                'title'       => $title,
                'artist'      => trim((string)($row['music_artist'] ?? '')),
                'album'       => '',
                'audio_url'   => $audioUrl,
                'cover_url'   => trim((string)($row['music_cover'] ?? '')),
                'lyrics'      => '',
                'description' => '从说说音乐拆分导入。',
                'mood'        => trim((string)($row['mood'] ?? '')),
                'duration'    => '',
                'play_count'  => 0,
                'likes_count' => 0,
                'sort'        => $count,
                'is_public'   => (int)($row['is_public'] ?? 1),
                'published_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
                'created_at'  => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            try {
                $db->query('UPDATE talk SET music_id = ? WHERE id = ? AND COALESCE(music_id, 0) = 0', [(int)$musicId, (int)$row['id']]);
            } catch (\Throwable) {
                // ignore
            }
            $count++;
        }

        if ($count > 0) {
            $log[] = "升级: 已将 {$count} 条说说音乐拆分到音乐模块";
        }
    }

    private static function seedMusic(Database $db, array &$log): void
    {
        try {
            $exists = $db->fetchOne('SELECT id FROM music LIMIT 1');
        } catch (\Throwable) {
            return;
        }
        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $songs = [
            [
                'title' => '阴雨额度',
                'artist' => 'LiteNote FM',
                'album' => '慢速生活样本',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                'cover_url' => 'https://picsum.photos/seed/litenote-music-rain/640/640',
                'lyrics' => "雨停在窗沿\n给今天留一点安静\n旧歌在房间里转弯\n把心事慢慢放轻",
                'description' => '给阴天预留一点循环播放的空间。',
                'mood' => '阴雨额度',
                'duration' => '5:44',
                'sort' => 0,
            ],
            [
                'title' => '山路汤圆',
                'artist' => 'Gentle Loop',
                'album' => '夜行备忘录',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                'cover_url' => 'https://picsum.photos/seed/litenote-music-road/640/640',
                'lyrics' => "山风经过耳机\n远处灯火一格一格亮起\n把路写成旋律\n也把疲惫留在身后",
                'description' => '适合路上听的一首测试曲。',
                'mood' => '路上',
                'duration' => '6:12',
                'sort' => 1,
            ],
            [
                'title' => '低频热可可',
                'artist' => 'Default Ember',
                'album' => '室内温度',
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                'cover_url' => 'https://picsum.photos/seed/litenote-music-cocoa/640/640',
                'lyrics' => "杯口冒着白雾\n桌面摊开没写完的句子\n低频在墙角轻轻跳\n夜晚因此慢了下来",
                'description' => '默认主题下的一点暖色声音。',
                'mood' => '夜读',
                'duration' => '4:58',
                'sort' => 2,
            ],
        ];

        foreach ($songs as $song) {
            $db->insert('music', $song + [
                'play_count' => 0,
                'likes_count' => 0,
                'is_public' => 1,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $log[] = '示例音乐创建完成';
    }

    /**
     * 2026-06:文章正文改为 Markdown 文件存储。
     * 老库里如果还有 markdown_content,自动导出到 storage/posts/{slug}.md。
     */
    private static function migratePostMarkdownFiles(Database $db, array &$log): void
    {
        try {
            $rows = $db->fetchAll(
                "SELECT slug, markdown_content FROM posts WHERE markdown_content IS NOT NULL AND LENGTH(markdown_content) > 0"
            );
        } catch (\Throwable) {
            return;
        }

        $count = 0;
        foreach ($rows as $row) {
            $slug = (string)($row['slug'] ?? '');
            $markdown = (string)($row['markdown_content'] ?? '');
            if ($slug === '' || $markdown === '' || PostContentStorage::read($slug) !== '') {
                continue;
            }
            PostContentStorage::write($slug, $markdown);
            $count++;
        }

        if ($count > 0) {
            $log[] = "升级: 已导出 {$count} 篇文章 Markdown 文件";
        }
    }
}
