<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Services\PermalinkService;

class ArchiveController
{
    public function index(): string
    {
        $posts = Post::archives();
        foreach ($posts as &$post) {
            $post['url'] = PermalinkService::postUrlFromParts(
                (int)($post['id'] ?? 0),
                (string)($post['slug'] ?? ''),
                (string)($post['category_slug'] ?? '')
            );
        }
        unset($post);

        $stats = $this->buildStats($posts);
        $heatmap = $this->buildHeatmap($posts);
        $categoryCards = $this->buildCategoryCards($posts);
        $years = [];
        foreach ($posts as $p) {
            $date = (string)($p['published_at'] ?? '');
            $year = substr($date, 0, 4) ?: '未归档';
            $month = substr($date, 5, 2) ?: '00';
            $years[$year]['total'] = ($years[$year]['total'] ?? 0) + 1;
            $years[$year]['months'][$month]['total'] = ($years[$year]['months'][$month]['total'] ?? 0) + 1;
            $years[$year]['months'][$month]['items'][] = $p;
        }

        return View::render('archive.index', [
            'years' => $years,
            'stats' => $stats,
            'heatmap' => $heatmap,
            'categoryCards' => $categoryCards,
            'total' => count($posts),
            'pageTitle' => '归档',
            'activeNav' => 'archives',
        ], 'layouts.front');
    }

    private function buildStats(array $posts): array
    {
        $firstTs = null;
        $words = 0;
        $comments = 0;

        foreach ($posts as $row) {
            $ts = strtotime((string)($row['published_at'] ?? '')) ?: null;
            if ($ts && ($firstTs === null || $ts < $firstTs)) {
                $firstTs = $ts;
            }
            $comments += (int)($row['comments_count'] ?? 0);
            $post = new Post($row);
            $words += $this->wordCount($post->markdown() ?: (string)($row['summary'] ?? ''));
        }

        $days = $firstTs ? max(1, (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $firstTs))) / 86400) + 1) : 0;

        return [
            'articles' => count($posts),
            'days' => $days,
            'words' => $words,
            'comments' => $comments,
        ];
    }

    private function wordCount(string $markdown): int
    {
        $plain = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $plain = preg_replace('/!\[(.*?)\]\((.*?)\)/', '$1', $plain) ?? $plain;
        $plain = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $plain) ?? $plain;
        $plain = preg_replace('/[#>*_`\-\[\](){ }|~!]+/u', ' ', $plain) ?? $plain;
        $plain = trim(preg_replace('/\s+/u', '', strip_tags($plain)) ?? '');
        return $plain === '' ? 0 : mb_strlen($plain);
    }

    private function buildHeatmap(array $posts): array
    {
        $counts = [];
        foreach ($posts as $row) {
            $day = substr((string)($row['published_at'] ?? ''), 0, 10);
            if ($day !== '') {
                $counts[$day] = ($counts[$day] ?? 0) + 1;
            }
        }

        $today = strtotime(date('Y-m-d')) ?: time();
        $rangeStart = strtotime('-364 days', $today) ?: $today;
        $startDow = (int)date('w', $rangeStart);
        $gridStart = strtotime("-{$startDow} days", $rangeStart) ?: $rangeStart;
        $endDow = (int)date('w', $today);
        $gridEnd = strtotime('+' . (6 - $endDow) . ' days', $today) ?: $today;

        $max = 0;
        foreach ($counts as $date => $count) {
            $ts = strtotime($date) ?: 0;
            if ($ts >= $rangeStart && $ts <= $today) {
                $max = max($max, $count);
            }
        }
        $days = [];
        $months = [];
        $monthSeen = [];
        $i = 0;
        for ($ts = $gridStart; $ts <= $gridEnd; $ts += 86400, $i++) {
            $date = date('Y-m-d', $ts);
            $count = $counts[$date] ?? 0;
            $inRange = $ts >= $rangeStart && $ts <= $today;
            $level = 0;
            if ($count > 0 && $max > 0) {
                $level = max(1, min(4, (int)ceil(($count / $max) * 4)));
            }
            $week = intdiv($i, 7) + 1;
            if ($inRange) {
                $monthKey = date('Y-m', $ts);
                if (!isset($monthSeen[$monthKey]) && ((int)date('j', $ts) <= 7 || $date === date('Y-m-d', $rangeStart))) {
                    $monthSeen[$monthKey] = true;
                    $months[] = ['label' => date('n月', $ts), 'week' => $week];
                }
            }
            $days[] = [
                'date' => $date,
                'count' => $inRange ? $count : 0,
                'level' => $inRange ? $level : 0,
                'muted' => !$inRange,
            ];
        }

        return [
            'days' => $days,
            'months' => $months,
            'weeks' => max(1, (int)ceil(count($days) / 7)),
        ];
    }

    private function buildCategoryCards(array $posts): array
    {
        $categories = Category::allEnabled();
        $cards = [];
        foreach ($categories as $cat) {
            $items = array_values(array_filter($posts, static fn(array $p): bool => (int)($p['category_id'] ?? 0) === (int)$cat->id));
            $latest = $items[0] ?? null;
            $cards[] = [
                'id' => (int)$cat->id,
                'name' => (string)$cat->name,
                'slug' => (string)$cat->slug,
                'description' => (string)($cat->description ?: '这个分类还没有描述。'),
                'icon' => $cat->iconClass(),
                'color' => $cat->colorIndex(),
                'count' => count($items),
                'latestTitle' => $latest['title'] ?? '暂无文章',
                'latestAt' => !empty($latest['published_at']) ? Helper::humanDate((string)$latest['published_at']) : '',
            ];
        }
        return $cards;
    }
}
