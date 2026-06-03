<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Helper;
use App\Enums\Toggle;

final class Page extends Model
{
    protected static string $table = 'pages';

    public static function findBySlug(string $slug): ?self
    {
        return self::findBy('slug', $slug);
    }

    public static function navItems(): array
    {
        return self::query(
            'SELECT id, title, slug FROM pages WHERE is_nav = ' . Toggle::On->value . ' ORDER BY sort ASC, id ASC'
        );
    }

    public function getUrl(): string
    {
        return Helper::url('/page/' . $this->slug . '.html');
    }
}
