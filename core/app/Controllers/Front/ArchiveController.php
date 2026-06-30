<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\FileCache;
use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Models\Talk;
use App\Services\HeatmapBuilder;
use App\Services\PermalinkService;

class ArchiveController
{
    public function index(): string
    {
        $cache = new FileCache();
        $data = $cache->remember('archives.page', 3600, function (): array {
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
            $talks = $this->publicTalks();
            $heatmap = $this->buildHeatmap($posts, $talks);
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

            return [
                'years' => $years,
                'stats' => $stats,
                'heatmap' => $heatmap,
                'categoryCards' => $categoryCards,
                'total' => count($posts),
            ];
        });

        return View::render('archive.index', [
            'years' => $data['years'],
            'stats' => $data['stats'],
            'heatmap' => $data['heatmap'],
            'categoryCards' => $data['categoryCards'],
            'total' => $data['total'],
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
            $words += HeatmapBuilder::wordCount($post->markdown() ?: (string)($row['summary'] ?? ''));
        }

        $days = $firstTs ? max(1, (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $firstTs))) / 86400) + 1) : 0;

        return [
            'articles' => count($posts),
            'days' => $days,
            'words' => $words,
            'comments' => $comments,
        ];
    }

    private function buildHeatmap(array $posts, array $talks = []): array
    {
        $wordsByDay = [];
        $articlesByDay = [];
        foreach ($posts as $row) {
            $day = substr((string)($row['published_at'] ?? ''), 0, 10);
            if ($day !== '') {
                $post = new Post($row);
                $wordsByDay[$day] = ($wordsByDay[$day] ?? 0) + HeatmapBuilder::wordCount($post->markdown() ?: (string)($row['summary'] ?? ''));
                $articlesByDay[$day] = ($articlesByDay[$day] ?? 0) + 1;
            }
        }

        $talksByDay = [];
        foreach ($talks as $row) {
            $day = substr((string)($row['published_at'] ?? $row['created_at'] ?? ''), 0, 10);
            if ($day !== '') {
                $wordsByDay[$day] = ($wordsByDay[$day] ?? 0) + HeatmapBuilder::wordCount((string)($row['content'] ?? ''));
                $talksByDay[$day] = ($talksByDay[$day] ?? 0) + 1;
            }
        }

        $grid = HeatmapBuilder::buildWordGrid(
            $wordsByDay,
            364,
            static function (string $date, int $words, bool $inRange) use ($articlesByDay, $talksByDay): array {
                $articles = $inRange ? ($articlesByDay[$date] ?? 0) : 0;
                $talkCount = $inRange ? ($talksByDay[$date] ?? 0) : 0;
                return [
                    'count' => $inRange ? $words : 0,
                    'words' => $inRange ? $words : 0,
                    'articles' => $articles,
                    'talks' => $talkCount,
                ];
            }
        );

        return [
            'days' => $grid['days'],
            'months' => $grid['months'],
            'weeks' => $grid['weeks'],
        ];
    }

    private function publicTalks(): array
    {
        try {
            return Talk::db()->fetchAll(
                "SELECT content, created_at, published_at FROM talk WHERE is_public = 1 AND COALESCE(post_type, 'talk') = 'talk'"
            );
        } catch (\Throwable) {
            return [];
        }
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
