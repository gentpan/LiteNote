<?php
declare(strict_types=1);

/**
 * 数据库安装 / 升级脚本
 * 访问 /install 执行
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
            views INTEGER DEFAULT 0,
            is_nav INTEGER DEFAULT 0,
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
            nickname VARCHAR(50) NOT NULL,
            email VARCHAR(100),
            website VARCHAR(255),
            content TEXT NOT NULL,
            ip VARCHAR(45),
            ua VARCHAR(255),
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
            sort INTEGER DEFAULT 0,
            is_enabled INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // 滔客
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS talk (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            images TEXT,
            music TEXT,
            mood VARCHAR(20),
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            is_public INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

        // 统计
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            path VARCHAR(255) NOT NULL,
            ip VARCHAR(45),
            ua VARCHAR(255),
            referer VARCHAR(500),
            day VARCHAR(10) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        $db->query('CREATE INDEX IF NOT EXISTS idx_comments_status ON comments(status)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_stats_day ON stats(day)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_stats_path ON stats(path)');

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
                ['k' => 'theme',         'v' => 'default',              'label' => '主题',     'group_name' => 'basic',   'sort' => 6],
                ['k' => 'comment_need_audit', 'v' => '1',                'label' => '评论需审核','group_name' => 'comment', 'sort' => 1],
                ['k' => 'comment_captcha',    'v' => '0',                'label' => '评论验证码','group_name' => 'comment', 'sort' => 2],
                ['k' => 'talk_enabled',   'v' => '1',                'label' => '开启滔客',  'group_name' => 'feature', 'sort' => 1],
                ['k' => 'friends_rss_enabled', 'v' => '1',               'label' => '友链 RSS',  'group_name' => 'feature', 'sort' => 2],
                ['k' => 'rss_full_text',      'v' => '1',                'label' => 'RSS 全文',  'group_name' => 'feature', 'sort' => 3],
                ['k' => 'site_icp',           'v' => '',                 'label' => 'ICP 备案',  'group_name' => 'basic',   'sort' => 7],
                ['k' => 'ai_provider',        'v' => 'deepseek',         'label' => 'AI 服务商', 'group_name' => 'ai',      'sort' => 1],
                ['k' => 'deepseek_api_key',   'v' => '',                 'type' => 'password', 'label' => 'DeepSeek API Key', 'group_name' => 'ai', 'sort' => 2],
                ['k' => 'deepseek_model',     'v' => 'deepseek-v4-flash','label' => 'DeepSeek 模型', 'group_name' => 'ai',   'sort' => 3],
                ['k' => 'deepseek_base_url',  'v' => 'https://api.deepseek.com', 'label' => 'DeepSeek Base URL', 'group_name' => 'ai', 'sort' => 4],
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
        // 每次 install() 都尝试 ALTER,失败 swallow。SQLite/MySQL 都安全。
        self::selfUpgrade($db, $log);

        return $log;
    }

    /**
     * 增量 schema 升级。已存在则忽略,新增则 ALTER。
     * 调用场景:新装 / 升级 / 修复,统一入口。
     */
    private static function selfUpgrade(Database $db, array &$log): void
    {
        $upgrades = [
            ['users',    'socials', 'TEXT'],         // 2026-06: 个人资料社交链接
            ['users',    'reset_token', 'VARCHAR(64)'],   // 2026-06: 密码找回 token(sha256)
            ['users',    'reset_expires_at', 'DATETIME'], // 2026-06: 密码找回 token 过期时间
            ['categories', 'icon', 'VARCHAR(64)'],          // 2026-06: 分类菜单图标(fontawesome)
            ['categories', 'show_in_nav', 'INTEGER DEFAULT 1'], // 2026-06: 是否在导航菜单显示
            ['categories', 'color', 'INTEGER'],             // 2026-06: 分类配色 0-5(空则按 id 取色)
            ['comments', 'talk_id', 'INTEGER DEFAULT 0'],
            ['talk', 'music', 'TEXT'],
            ['talk', 'music_cover', 'VARCHAR(255)'],     // 2026-06: 音乐卡片封面
            ['talk', 'music_title', 'VARCHAR(120)'],     // 2026-06: 音乐卡片标题
            ['talk', 'music_artist', 'VARCHAR(120)'],    // 2026-06: 音乐卡片歌手
            ['talk', 'likes_count', 'INTEGER DEFAULT 0'],
            ['talk', 'comments_count', 'INTEGER DEFAULT 0'],
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

        self::migratePostMarkdownFiles($db, $log);
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
