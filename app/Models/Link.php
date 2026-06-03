<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Toggle;

final class Link extends Model
{
    protected static string $table = 'links';

    public static function enabled(): array
    {
        return self::query('SELECT * FROM links WHERE is_enabled = ' . Toggle::On->value . ' ORDER BY sort ASC, id ASC');
    }

    public static function withRss(): array
    {
        return self::query(
            'SELECT * FROM links WHERE is_enabled = ' . Toggle::On->value .
            ' AND rss_url IS NOT NULL AND rss_url <> "" ORDER BY sort ASC, id ASC'
        );
    }
}
