<?php
declare(strict_types=1);

namespace LiteNotePlugin\X;

use App\Core\Router;
use App\Models\Page;
use App\Services\Plugins\PluginContext;
use App\Services\Plugins\PluginInterface;
use LiteNotePlugin\X\Adapters\XBookmarksAdapter;
use LiteNotePlugin\X\Controllers\XAdminController;
use LiteNotePlugin\X\Controllers\XmarksController;
use LiteNotePlugin\X\Controllers\XOAuthController;
use LiteNotePlugin\X\Models\XTweet;

/**
 * X(Twitter)集成插件入口。
 *
 * 线A(书签):X 书签同步 —— Activity Provider + Adapter + OAuth 授权 + /xmarks 页面 + 导航页。
 * 线B(X 卡片):说说推文分享 —— 见阶段3 追加(独立 x_tweets 表 + 首页时间线贡献 + 推文卡片视图)。
 */
final class Plugin implements PluginInterface
{
    public function register(PluginContext $ctx): void
    {
        // ---- 线A:书签(X 书签同步) ----

        // Activity Provider 配置(自核心 ActivityIntegration::PROVIDERS 迁出)。
        $ctx->activityProvider('x_bookmarks', [
            'label' => 'X 书签',
            'icon' => 'fa-solid fa-bookmark',
            'description' => '同步我的 X 书签，允许保存别人的公开内容。',
            'default_interval_minutes' => 1440,
            'token_label' => 'OAuth 用户 Access Token',
            'token_hint' => '需要 OAuth 2.0 用户授权，scope 至少包含 tweet.read、users.read、bookmark.read。',
            'refresh_label' => '',
            'fields' => [
                'username' => ['label' => '用户名', 'placeholder' => 'gentpan'],
                'user_id' => ['label' => '用户 ID', 'placeholder' => '可选；填写后不再按用户名查询'],
                'limit' => ['label' => '每次拉取条数', 'placeholder' => '10'],
                'pages' => ['label' => '最多检查页数', 'placeholder' => '10；开启只补新增时，遇到已有书签会提前停止'],
                'sync_new_only' => ['label' => '只补新增书签', 'placeholder' => '1 开启 / 0 关闭；遇到本地已有书签即停止'],
            ],
        ]);

        // Activity 适配器(同步书签写入 activities 表 source=x_bookmarks)。
        $ctx->activityAdapter(new XBookmarksAdapter());

        // 后台 OAuth 授权路由(applyRoutes 时自动套 /admin 前缀 + AdminAuth)。
        $ctx->adminRoutes(static function (Router $r): void {
            $r->get('/oauth/x/start', [XOAuthController::class, 'start']);
            $r->get('/oauth/x/callback', [XOAuthController::class, 'callback']);
        });

        // 前台书签列表页。
        $ctx->webRoutes(static function (Router $r): void {
            $r->get('/xmarks', [XmarksController::class, 'index']);
        });

        // 前台导航页(取代核心 systemDefinitions 里硬编码的 xmarks)。
        $ctx->navPage('xmarks', [
            'title' => '书签',
            'url' => '/xmarks',
            'icon' => 'fa-solid fa-bookmark',
            'sort' => 25,
        ]);

        // 插件视图目录:提供 pages/x.php 等默认视图(主题放同名文件即可覆盖)。
        $ctx->viewDir(__DIR__ . '/views');

        // ---- 线B:X 卡片(说说里分享推文 → 独立 x_tweets 表) ----

        // 后台推文管理(取代原 talk-form 的"分享 X"标签)。
        $ctx->adminMenu([
            'label' => 'X 推文',
            'href' => '/admin/x/tweets',
            'icon' => 'fa-brands fa-x-twitter',
            'group' => '内容',
            'sort' => 60,
        ]);
        $ctx->adminRoutes(static function (Router $r): void {
            $r->get('/x/tweets', [XAdminController::class, 'index']);
            $r->post('/x/tweets/store', [XAdminController::class, 'store'], [\App\Middleware\CsrfMiddleware::class]);
            $r->post('/x/tweets/refresh', [XAdminController::class, 'refresh'], [\App\Middleware\CsrfMiddleware::class]);
            $r->post('/x/tweets/delete', [XAdminController::class, 'destroy'], [\App\Middleware\CsrfMiddleware::class]);
        });

        // 前台推文本地点赞(独立端点,不与核心 /talk/{id}/like 冲突)。
        $ctx->webRoutes(static function (Router $r): void {
            $r->post('/x/tweet/{id}/like', [XmarksController::class, 'like']);
            $r->post('/xmarks/{id}/like', [XmarksController::class, 'likeBookmark']);
        });

        // 把推文混入首页时间线(核心 homeFeedItems 合并各贡献者产出后按时间排序)。
        $ctx->homeFeedContributor(static function (): array {
            try {
                ['items' => $items] = XTweet::paginate(1, 200, 'published_at DESC, created_at DESC, id DESC', 'is_public = 1');
            } catch (\Throwable) {
                return [];
            }
            $out = [];
            foreach ($items as $xt) {
                $xt->loadComments();
                $out[] = [
                    'type' => 'x_tweet',
                    'partial' => 'partials.x-card',
                    'time' => strtotime((string)$xt->publishedAt()) ?: 0,
                    'item' => $xt,
                    'fixed' => false,
                ];
            }
            return $out;
        });

        // 前台书签页样式和推文卡片本地点赞脚本(插件自包含,不依赖首页 home.css)。
        $frontCss = '/plugins/x/assets/front.css';
        $frontCssFile = __DIR__ . '/assets/front.css';
        $frontCssVersion = is_file($frontCssFile) ? (string)filemtime($frontCssFile) : '1';
        $ctx->frontHead('<link rel="stylesheet" href="' . $frontCss . '?v=' . $frontCssVersion . '">');
        $ctx->frontHead(<<<'HTML'
<script>
(function(){
  function bind(btn, endpoint){
    if(btn.dataset.xLikeBound){return;} btn.dataset.xLikeBound='1';
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-id'); if(!id){return;}
      fetch(endpoint+encodeURIComponent(id)+'/like', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();})
        .then(function(d){ if(d && typeof d.likes !== 'undefined'){ var c=btn.querySelector('.like-count'); if(c){c.textContent=d.likes;} btn.classList.add('liked'); } })
        .catch(function(){});
    });
  }
  function init(){
    document.querySelectorAll('.x-tweet-like-btn[data-id]').forEach(function(b){ bind(b, '/x/tweet/'); });
    document.querySelectorAll('.x-bookmark-like-btn[data-id]').forEach(function(b){ bind(b, '/xmarks/'); });
  }
  if(document.readyState!=='loading'){ init(); } else { document.addEventListener('DOMContentLoaded', init); }
})();
</script>
HTML);
    }

    public function migrate(): void
    {
        $db = \App\Core\Database::getInstance();

        // 线A:数据走核心 activities 表(由 ActivityInstaller 建表),无需额外建表。

        // 线B:独立推文表 x_tweets(与核心 talk 表平级,同在主库)。幂等。
        $db->query(<<<SQL
            CREATE TABLE IF NOT EXISTS x_tweets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tweet_id VARCHAR(40),
                tweet_url TEXT,
                tweet_author_name VARCHAR(120),
                tweet_author_handle VARCHAR(120),
                tweet_author_avatar TEXT,
                tweet_author_verified INTEGER DEFAULT 0,
                tweet_posted_at DATETIME,
                tweet_likes_count INTEGER DEFAULT 0,
                tweet_reposts_count INTEGER DEFAULT 0,
                tweet_data TEXT,
                content TEXT,
                images TEXT,
                is_public INTEGER DEFAULT 1,
                likes_count INTEGER DEFAULT 0,
                comments_count INTEGER DEFAULT 0,
                published_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $db->query('CREATE INDEX IF NOT EXISTS idx_x_tweets_public ON x_tweets(is_public, published_at, id)');

        // 确保核心 comments 表有 x_tweet_id 维度(不依赖核心 Installer 的执行时序)。
        try {
            $db->query('ALTER TABLE comments ADD COLUMN x_tweet_id INTEGER DEFAULT 0');
        } catch (\Throwable) {
            // 列已存在。
        }

        // 数据迁移:把核心 talk 表里的推文搬入 x_tweets,并把其本地评论改挂到 x_tweet_id,
        // 然后从 talk 删除。可重入(再次 enable 时 talk 已无推文行,rows 为空)。
        try {
            $rows = $db->fetchAll(
                "SELECT * FROM talk WHERE post_type = 'tweet' OR COALESCE(tweet_id, '') <> '' OR COALESCE(tweet_url, '') <> ''"
            );
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $row) {
            $oldId = (int)($row['id'] ?? 0);
            if ($oldId <= 0) {
                continue;
            }
            $newId = (int)$db->insert('x_tweets', [
                'tweet_id' => (string)($row['tweet_id'] ?? ''),
                'tweet_url' => (string)($row['tweet_url'] ?? ''),
                'tweet_author_name' => (string)($row['tweet_author_name'] ?? ''),
                'tweet_author_handle' => (string)($row['tweet_author_handle'] ?? ''),
                'tweet_author_avatar' => (string)($row['tweet_author_avatar'] ?? ''),
                'tweet_author_verified' => (int)($row['tweet_author_verified'] ?? 0),
                'tweet_posted_at' => $row['tweet_posted_at'] ?? null,
                'tweet_likes_count' => (int)($row['tweet_likes_count'] ?? 0),
                'tweet_reposts_count' => (int)($row['tweet_reposts_count'] ?? 0),
                'tweet_data' => (string)($row['tweet_data'] ?? ''),
                'content' => (string)($row['content'] ?? ''),
                'images' => (string)($row['images'] ?? ''),
                'is_public' => (int)($row['is_public'] ?? 1),
                'likes_count' => (int)($row['likes_count'] ?? 0),
                'comments_count' => (int)($row['comments_count'] ?? 0),
                'published_at' => $row['published_at'] ?? ($row['created_at'] ?? null),
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            try {
                $db->query('UPDATE comments SET x_tweet_id = ?, talk_id = 0 WHERE talk_id = ?', [$newId, $oldId]);
            } catch (\Throwable) {
            }
            $db->delete('talk', 'id = ?', [$oldId]);
        }
    }

    public function uninstall(): void
    {
        // 清理前台导航页:删除 pages 表中 slug='xmarks' 的系统页,避免禁用后残留死链。
        try {
            Page::db()->delete('pages', 'slug = ?', ['xmarks']);
        } catch (\Throwable) {
            // pages 表不存在或删除失败时忽略。
        }
    }
}
