<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
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
        return View::render('home.index', [
            'feedItems' => ReadingSettingsService::homeFeedItems(),
            // 读 5 分钟文件缓存快照,避免每次访问都实时聚合 + 重复建表。
            'activitySummary' => (new ActivityCacheService())->snapshot()['summary'] ?? null,
            'pageTitle' => null,
            'activeNav' => 'home',
        ]);
    }

    public function posts(): string
    {
        $perPage = ReadingSettingsService::postsPerPage();
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $posts, 'total' => $total] = Post::paginatePublished($page, $perPage);

        return View::render('home.posts', [
            'posts'     => $posts,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/posts')),
            'pageTitle' => '文章',
            'activeNav' => 'posts',
        ]);
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
                'email' => (string)($row['email'] ?? ''),
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
