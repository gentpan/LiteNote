<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;

final class Category extends Model
{
    protected static string $table = 'categories';

    public static function allEnabled(): array
    {
        return self::query('SELECT * FROM categories ORDER BY sort ASC, id ASC');
    }

    /** 仅返回勾选了「在菜单栏显示」的分类(用于前台导航下拉)。 */
    public static function navList(): array
    {
        return self::query('SELECT * FROM categories WHERE show_in_nav = 1 ORDER BY sort ASC, id ASC');
    }

    /**
     * 分类配色索引(0-5),按 id 稳定取色 —— 下拉菜单与分类页 Hero 共用,
     * 保证同一分类在任何位置颜色一致(不随排序变化)。
     */
    public function colorIndex(): int
    {
        // 优先用手动指定的 color(0-5);未指定则按 id 稳定取色
        $color = $this->color;
        if ($color !== null && $color !== '') {
            return ((int) $color) % 6;
        }
        return ((int) $this->id) % 6;
    }

    /**
     * 安全的 FontAwesome 图标类名(白名单过滤,空/非法时回退)。
     */
    public function iconClass(string $fallback = 'fa-regular fa-folder'): string
    {
        $icon = trim((string) ($this->icon ?? ''));
        if ($icon === '' || !preg_match('/^[a-zA-Z0-9 _-]+$/', $icon)) {
            return $fallback;
        }
        return $icon;
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::findBy('slug', $slug);
    }

    public function postCount(): int
    {
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM posts WHERE category_id = ? AND status = ?',
            [$this->id, PostStatus::Published->value]
        );
    }
}
