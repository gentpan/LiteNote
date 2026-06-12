<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Response;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Services\Gravatar;
use App\Services\ActivityCacheService;
use App\Services\ReadingSettingsService;

/**
 * 首页（改进版）
 * 变更点：
 * 1. 使用 View Composer 注入共享侧边栏数据，消除每个 Controller 重复传递 categories / recentPosts。
 * 2. 使用 Post::paginatePublished() 的预加载能力，消除 N+1。
 */
class HomeController
{
    public function __construct()
    {
        // 注册前台布局的 View Composer，自动注入共享数据
        View::composer('layouts.front', function (array $data): array {
            return array_merge($data, [
                'categories'  => Category::allEnabled(),
                'recentPosts' => Post::recent(5),
            ]);
        });
    }

    public function index(): string
    {
        $limit = 10;
        $feedItems = ReadingSettingsService::homeFeedItems(0, $limit);

        return View::render('home.index', [
            'feedItems' => $feedItems,
            'homeFeedHasMore' => ReadingSettingsService::homeFeedHasMore(0, count($feedItems)),
            // 读 5 分钟文件缓存快照,避免每次访问都实时聚合 + 重复建表。
            'activitySummary' => (new ActivityCacheService())->snapshot()['summary'] ?? null,
            'pageTitle' => null,
            'activeNav' => 'home',
        ]);
    }

    public function feed(): never
    {
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
        $feedItems = ReadingSettingsService::homeFeedItems($offset, $limit);
        $html = View::render('partials.home-feed-items', [
            'feedItems' => $feedItems,
        ]);

        Response::json([
            'code' => 0,
            'html' => $html,
            'count' => count($feedItems),
            'nextOffset' => $offset + count($feedItems),
            'hasMore' => count($feedItems) > 0 && ReadingSettingsService::homeFeedHasMore($offset, count($feedItems)),
        ]);
    }

    public function posts(): string
    {
        // 第 1 页:3 篇带图(feature) + 10 篇长条(compact) = 13;之后每页 10 篇。
        $featured = 3;
        $perPage = 10;
        $firstPageSize = $featured + $perPage; // 13
        $page = max(1, (int)($_GET['page'] ?? 1));

        $offset = $page === 1 ? 0 : $firstPageSize + ($page - 2) * $perPage;
        $limit = $page === 1 ? $firstPageSize : $perPage;
        ['items' => $posts, 'total' => $total] = Post::paginatePublishedRange($offset, $limit);

        // 总页数:第 1 页装 13,其余每页 10。
        $totalPages = $total <= 0 ? 0 : ($total <= $firstPageSize ? 1 : 1 + (int) ceil(($total - $firstPageSize) / $perPage));

        return View::render('home.posts', [
            'posts'     => $posts,
            'total'     => $total,
            'heatmap'   => $this->postsHeatmap(),
            'page'      => $page,
            'perPage'   => $perPage,
            'paginator' => Helper::loadMore($page, $totalPages, 1, Helper::url('/posts')),
            'pageTitle' => '文章',
            'activeNav' => 'posts',
        ]);
    }

    private function postsHeatmap(): array
    {
        $wordsByDay = [];
        $articlesByDay = [];
        $totalWords = 0;
        $activeDays = [];
        $firstPublishedTs = null;

        foreach (Post::archives() as $row) {
            $day = substr((string)($row['published_at'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }
            $dayTs = strtotime($day);
            if ($dayTs !== false && ($firstPublishedTs === null || $dayTs < $firstPublishedTs)) {
                $firstPublishedTs = $dayTs;
            }

            $post = new Post($row);
            $words = $this->wordCount($post->markdown() ?: (string)($row['summary'] ?? ''));
            $wordsByDay[$day] = ($wordsByDay[$day] ?? 0) + $words;
            $articlesByDay[$day] = ($articlesByDay[$day] ?? 0) + 1;
            $totalWords += $words;
            $activeDays[$day] = true;
        }

        $today = strtotime(date('Y-m-d')) ?: time();
        $rangeStart = strtotime('-364 days', $today) ?: $today;
        $startDow = (int)date('w', $rangeStart);
        $gridStart = strtotime("-{$startDow} days", $rangeStart) ?: $rangeStart;
        $endDow = (int)date('w', $today);
        $gridEnd = strtotime('+' . (6 - $endDow) . ' days', $today) ?: $today;

        $days = [];
        $months = [];
        $monthSeen = [];
        $i = 0;
        for ($ts = $gridStart; $ts <= $gridEnd; $ts += 86400, $i++) {
            $date = date('Y-m-d', $ts);
            $inRange = $ts >= $rangeStart && $ts <= $today;
            $week = intdiv($i, 7) + 1;
            if ($inRange) {
                $monthKey = date('Y-m', $ts);
                if (!isset($monthSeen[$monthKey]) && ((int)date('j', $ts) <= 7 || $date === date('Y-m-d', $rangeStart))) {
                    $monthSeen[$monthKey] = true;
                    $months[] = ['label' => date('n月', $ts), 'week' => $week];
                }
            }

            $words = $wordsByDay[$date] ?? 0;
            $days[] = [
                'date' => $date,
                'words' => $inRange ? $words : 0,
                'articles' => $inRange ? ($articlesByDay[$date] ?? 0) : 0,
                'level' => $inRange ? $this->heatmapLevel($words) : 0,
                'muted' => !$inRange,
            ];
        }

        return [
            'days' => $days,
            'months' => $months,
            'weeks' => max(1, (int)ceil(count($days) / 7)),
            'articles' => array_sum($articlesByDay),
            'activeDays' => count($activeDays),
            'firstDate' => $firstPublishedTs ? date('Y-m-d', $firstPublishedTs) : null,
            'spanDays' => $firstPublishedTs ? max(1, (int)floor(($today - $firstPublishedTs) / 86400) + 1) : 0,
            'words' => $totalWords,
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

    private function heatmapLevel(int $words): int
    {
        return match (true) {
            $words >= 1500 => 4,
            $words >= 1000 => 3,
            $words >= 500 => 2,
            $words > 0 => 1,
            default => 0,
        };
    }

    public function readers(): string
    {
        $sort = ($_GET['sort'] ?? '') === 'random' ? 'random' : 'count';
        $orderBy = $sort === 'random' ? 'RANDOM()' : 'comments_count DESC, last_commented_at DESC';
        $rows = Comment::db()->fetchAll(
            "SELECT
                LOWER(TRIM(COALESCE(NULLIF(email, ''), nickname))) AS reader_key,
                MAX(nickname) AS nickname,
                MAX(email) AS email,
                MAX(website) AS website,
                COUNT(*) AS comments_count,
                MAX(created_at) AS last_commented_at
             FROM comments
             WHERE status = 'approved' AND TRIM(nickname) <> ''
             GROUP BY reader_key
             ORDER BY {$orderBy}
             LIMIT 80"
        );

        $readers = array_map(static function (array $row, int $index): array {
            $count = max(1, (int)($row['comments_count'] ?? 0));
            $website = trim((string)($row['website'] ?? ''));
            if (!preg_match('/^https?:\/\//i', $website)) {
                $website = '';
            }
            return [
                'nickname' => $row['nickname'] ?: '读者',
                'website' => $website,
                'comments_count' => $count,
                'last_commented_at' => $row['last_commented_at'] ?? null,
                'avatar' => Gravatar::url((string)($row['email'] ?? ''), 160, 'identicon'),
                'rank' => $index + 1,
                'weight' => min(4, $count),
                'tilt' => (($index % 7) - 3) * 0.35,
                'delay' => ($index % 12) * 35,
            ];
        }, $rows, array_keys($rows));

        return View::render('home.readers', [
            'readers' => $readers,
            'sort' => $sort,
            'pageTitle' => '读者墙',
            'activeNav' => 'readers',
        ]);
    }
}
