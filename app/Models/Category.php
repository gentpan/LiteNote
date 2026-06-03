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
