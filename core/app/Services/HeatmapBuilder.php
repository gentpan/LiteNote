<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 365 天活动热力图网格构建（供首页/归档/滔客/动态复用）。
 */
final class HeatmapBuilder
{
    /**
     * @param array<string,int> $countsByDay Y-m-d => count
     * @return array{days: array<int,array<string,mixed>>, months: array<int,array<string,mixed>>, weeks: int, weeksCount: int, activeDays: int, totalEvents: int}
     */
    public static function buildDailyGrid(array $countsByDay, int $rangeDays = 364): array
    {
        return self::buildGrid(
            $countsByDay,
            static fn(int $count, bool $inRange): int => self::levelForCount($count, $inRange),
            $rangeDays,
            static fn(string $date, int $count, bool $inRange): array => [
                'count' => $inRange ? $count : 0,
            ]
        );
    }

    /**
     * @param array<string,int> $wordsByDay Y-m-d => word count
     * @param callable(string,int,bool):array<string,mixed>|null $dayMeta
     * @return array{days: array<int,array<string,mixed>>, months: array<int,array<string,mixed>>, weeks: int, weeksCount: int, activeDays: int, totalEvents: int}
     */
    public static function buildWordGrid(array $wordsByDay, int $rangeDays = 364, ?callable $dayMeta = null): array
    {
        return self::buildGrid(
            $wordsByDay,
            static fn(int $words, bool $inRange): int => $inRange ? self::levelForWords($words) : 0,
            $rangeDays,
            $dayMeta
        );
    }

    /**
     * @param array<int,array{date?:string,total?:int|string,dominant_type?:string,types?:mixed}> $cells
     * @return array{days: array<int,array<string,mixed>>, months: array<int,array<string,mixed>>, weeks: int, weeksCount: int, activeDays: int, totalEvents: int}
     */
    public static function buildActivityGrid(array $cells): array
    {
        $byDay = [];
        foreach ($cells as $cell) {
            $date = substr((string)($cell['date'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $byDay[$date] = [
                'total' => (int)($cell['total'] ?? 0),
                'types' => is_array($cell['types'] ?? null) ? $cell['types'] : [],
                'dominant_type' => (string)($cell['dominant_type'] ?? 'none'),
            ];
        }

        $totalsByDay = [];
        foreach ($byDay as $date => $meta) {
            $totalsByDay[$date] = (int)$meta['total'];
        }

        return self::buildGrid(
            $totalsByDay,
            static fn(int $total, bool $inRange): int => $inRange && $total > 0 ? 1 : 0,
            364,
            static function (string $date, int $total, bool $inRange) use ($byDay): array {
                $dominantType = $inRange ? (string)($byDay[$date]['dominant_type'] ?? 'none') : 'none';
                if (!isset(ActivityService::TYPES[$dominantType])) {
                    $dominantType = 'none';
                }

                return [
                    'total' => $total,
                    'type' => $dominantType,
                    'type_label' => $dominantType !== 'none' ? ActivityService::typeLabel($dominantType) : '',
                ];
            }
        );
    }

    /**
     * @param array<string,int> $valuesByDay
     * @param callable(int,bool):int $levelFn
     * @param callable(string,int,bool):array<string,mixed>|null $dayMeta
     * @return array{days: array<int,array<string,mixed>>, months: array<int,array<string,mixed>>, weeks: int, weeksCount: int, activeDays: int, totalEvents: int}
     */
    public static function buildGrid(
        array $valuesByDay,
        callable $levelFn,
        int $rangeDays = 364,
        ?callable $dayMeta = null
    ): array {
        $today = new \DateTimeImmutable('today');
        $rangeStart = $today->modify('-' . max(0, $rangeDays) . ' days');
        $cursor = $rangeStart->modify('-' . (int)$rangeStart->format('w') . ' days');
        $gridEnd = $today->modify('+' . (6 - (int)$today->format('w')) . ' days');

        $days = [];
        $months = [];
        $monthSeen = [];
        $activeDays = 0;
        $totalEvents = 0;
        $i = 0;

        while ($cursor <= $gridEnd) {
            $ds = $cursor->format('Y-m-d');
            $inRange = $cursor >= $rangeStart && $cursor <= $today;
            $value = $inRange ? (int)($valuesByDay[$ds] ?? 0) : 0;
            $week = intdiv($i, 7) + 1;

            if ($inRange) {
                $monthKey = $cursor->format('Y-m');
                if (!isset($monthSeen[$monthKey]) && ((int)$cursor->format('j') <= 7 || $ds === $rangeStart->format('Y-m-d'))) {
                    $monthSeen[$monthKey] = true;
                    $months[] = ['label' => $cursor->format('n月'), 'week' => $week];
                }
                if ($value > 0) {
                    $activeDays++;
                    $totalEvents += $value;
                }
            }

            $day = [
                'date' => $ds,
                'level' => $levelFn($value, $inRange),
                'muted' => !$inRange,
            ];
            if ($dayMeta !== null) {
                $day = array_merge($day, $dayMeta($ds, $value, $inRange));
            } else {
                $day['count'] = $inRange ? $value : 0;
            }

            $days[] = $day;
            $cursor = $cursor->modify('+1 day');
            $i++;
        }

        $weeks = max(1, (int)ceil(count($days) / 7));

        return [
            'days' => $days,
            'months' => $months,
            'weeks' => $weeks,
            'weeksCount' => $weeks,
            'activeDays' => $activeDays,
            'totalEvents' => $totalEvents,
        ];
    }

    public static function wordCount(string $markdown): int
    {
        $plain = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $plain = preg_replace('/!\[(.*?)\]\((.*?)\)/', '$1', $plain) ?? $plain;
        $plain = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $plain) ?? $plain;
        $plain = preg_replace('/[#>*_`\-\[\](){ }|~!]+/u', ' ', $plain) ?? $plain;
        $plain = trim(preg_replace('/\s+/u', '', strip_tags($plain)) ?? '');
        return $plain === '' ? 0 : mb_strlen($plain);
    }

    public static function levelForWords(int $words): int
    {
        return match (true) {
            $words >= 1500 => 4,
            $words >= 1000 => 3,
            $words >= 500 => 2,
            $words > 0 => 1,
            default => 0,
        };
    }

    private static function levelForCount(int $count, bool $inRange): int
    {
        if (!$inRange || $count <= 0) {
            return 0;
        }
        if ($count === 1) {
            return 1;
        }
        if ($count <= 3) {
            return 2;
        }
        if ($count <= 6) {
            return 3;
        }
        return 4;
    }
}
