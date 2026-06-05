<?php
declare(strict_types=1);

namespace App\Models;

final class DailyActivityStat
{
    public static function today(): ?array
    {
        return self::forDate(date('Y-m-d'));
    }

    public static function forDate(string $date): ?array
    {
        $row = Activity::db()->fetchOne('SELECT * FROM daily_activity_stats WHERE date = ? LIMIT 1', [$date]);
        return $row ?: null;
    }
}
