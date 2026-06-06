<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Helper;
use App\Enums\Toggle;

final class Page extends Model
{
    protected static string $table = 'pages';
    protected static array $sortable = ['id', 'slug', 'sort', 'views', 'is_nav', 'is_system', 'created_at', 'updated_at'];
    private static bool $systemChecked = false;

    /**
     * 系统功能页也放进页面管理,用于统一控制前台菜单显示。
     */
    public static function systemDefinitions(): array
    {
        return [
            'activity' => [
                'title' => '动态',
                'url' => '/activity',
                'icon' => 'fa-solid fa-chart-simple',
                'sort' => 15,
            ],
            'talk' => [
                'title' => '滔客',
                'url' => '/talk',
                'icon' => 'fa-regular fa-comments',
                'sort' => 20,
            ],
            'x' => [
                'title' => 'X',
                'url' => '/x',
                'icon' => 'fa-brands fa-x-twitter',
                'sort' => 25,
            ],
            'music' => [
                'title' => '音乐',
                'url' => '/music',
                'icon' => 'fa-solid fa-music',
                'sort' => 30,
            ],
            'archives' => [
                'title' => '归档',
                'url' => '/archives',
                'icon' => 'fa-solid fa-box-archive',
                'sort' => 40,
            ],
            'friends' => [
                'title' => '友链',
                'url' => '/links',
                'icon' => 'fa-solid fa-link',
                'sort' => 50,
            ],
            'feeds' => [
                'title' => '订阅',
                'url' => '/subscribe',
                'icon' => 'fa-solid fa-square-rss',
                'sort' => 60,
            ],
        ];
    }

    public static function ensureSystemPages(): void
    {
        if (self::$systemChecked) {
            return;
        }
        self::$systemChecked = true;

        $db = self::db();
        foreach ([
            ['url', 'VARCHAR(255)'],
            ['icon', 'VARCHAR(80)'],
            ['is_system', 'INTEGER DEFAULT 0'],
        ] as [$col, $type]) {
            try {
                $db->query("ALTER TABLE pages ADD COLUMN {$col} {$type}");
            } catch (\Throwable) {
                // 已存在则忽略
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::systemDefinitions() as $slug => $def) {
            $row = $db->fetchOne('SELECT id, title, is_nav, sort, is_system FROM pages WHERE slug = ? LIMIT 1', [$slug]);
            if ($row) {
                $wasSystem = (int)($row['is_system'] ?? 0) === Toggle::On->value;
                $updates = [
                    'url' => $def['url'],
                    'icon' => $def['icon'],
                    'is_system' => Toggle::On->value,
                    'updated_at' => $now,
                ];
                $currentTitle = trim((string)($row['title'] ?? ''));
                if ($currentTitle === '' || (!$wasSystem && $slug === 'friends' && $currentTitle === '友情链接')) {
                    $updates['title'] = $def['title'];
                }
                if (!$wasSystem) {
                    $updates['sort'] = $def['sort'];
                }
                $db->update('pages', $updates, 'id = :id', [':id' => (int)$row['id']]);
                continue;
            }

            $db->insert('pages', [
                'title' => $def['title'],
                'slug' => $slug,
                'content' => '',
                'markdown_content' => '',
                'url' => $def['url'],
                'icon' => $def['icon'],
                'views' => 0,
                'is_nav' => Toggle::On->value,
                'is_system' => Toggle::On->value,
                'sort' => $def['sort'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public static function findBySlug(string $slug): ?self
    {
        self::ensureSystemPages();
        return self::findBy('slug', $slug);
    }

    public static function navItems(): array
    {
        self::ensureSystemPages();
        return self::query(
            'SELECT id, title, slug, url, icon, is_system, sort FROM pages WHERE is_nav = ' . Toggle::On->value . ' ORDER BY sort ASC, id ASC'
        );
    }

    public function getUrl(): string
    {
        $url = trim((string)($this->url ?? ''));
        if ($url !== '') {
            return $url;
        }
        return Helper::url('/' . ltrim((string) $this->slug, '/'));
    }

    public function iconClass(): string
    {
        $icon = trim((string)($this->icon ?? ''));
        return $icon !== '' && preg_match('/^[a-zA-Z0-9 _-]+$/', $icon)
            ? $icon
            : 'fa-regular fa-bookmark';
    }

    public function isSystem(): bool
    {
        return (int)($this->is_system ?? 0) === Toggle::On->value;
    }
}
