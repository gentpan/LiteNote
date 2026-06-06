<?php
declare(strict_types=1);

/**
 * 前台路由
 */

use App\Controllers\Front\HomeController;
use App\Controllers\Front\PostController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\TalkController;
use App\Controllers\Front\MusicController;
use App\Controllers\Front\ArchiveController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\FriendController;
use App\Controllers\Front\CommentController;
use App\Controllers\Front\CaptchaController;
use App\Controllers\Front\FeedController;
use App\Controllers\Front\ActivityController;
use App\Controllers\Front\MailController;
use App\Controllers\Api\ActivityApiController;
// 首页
$router->get('/',                 [HomeController::class, 'index']);

// 文章
$router->get('/posts',            [HomeController::class, 'posts']);
$router->get('/readers',          [HomeController::class, 'readers']);
$router->get('/post/{slug}.html', [PostController::class, 'show']);

// 分类
$router->get('/category/{slug}',  [CategoryController::class, 'show']);

// 滔客
$router->get('/activity',         [ActivityController::class, 'index']);
$router->get('/talk',             [TalkController::class, 'index']);
$router->post('/talk/publish',    [TalkController::class, 'publish']);
$router->post('/talk/{id}/like',  [TalkController::class, 'like']);

// 音乐
$router->get('/music',            [MusicController::class, 'index']);
$router->get('/music/lyrics/meting', [MusicController::class, 'metingLyrics']);
$router->post('/music/{id}/like', [MusicController::class, 'like']);
$router->post('/music/{id}/play', [MusicController::class, 'play']);

// 归档
$router->get('/archives',         [ArchiveController::class, 'index']);

// 搜索
$router->get('/search',           [SearchController::class, 'index']);

// 友链页
$router->get('/links',            [FriendController::class, 'links']);
$router->get('/subscribe',        [FriendController::class, 'subscribe']);
$router->post('/links/apply',     [FriendController::class, 'apply']);
$router->post('/links/modify',    [FriendController::class, 'modify']);

// 评论提交
$router->post('/comment/submit',  [CommentController::class, 'submit']);

// 评论验证码图片
$router->get('/captcha',          [CaptchaController::class, 'image']);

// RSS（/rss.xml 为正式地址,旧 /feed 301 重定向）
$router->get('/rss.xml',          [FeedController::class, 'feed']);
$router->get('/feed',             [FeedController::class, 'feedRedirect']);

// 邮件退订
$router->get('/mail/unsubscribe', [MailController::class, 'unsubscribe']);

// llms.txt（AI / 大模型收录索引）
$router->get('/llms.txt',         [FeedController::class, 'llms']);

$router->get('/api/v1/activity/feed', [ActivityApiController::class, 'feed']);
$router->get('/api/v1/activity/summary', [ActivityApiController::class, 'summary']);
$router->get('/api/v1/activity/stats/today', [ActivityApiController::class, 'today']);
$router->get('/api/v1/activity/stats/{date}', [ActivityApiController::class, 'stat']);

// 插件前台路由:必须在下面的 catch-all 短链接之前注册,否则会被 /{slug} 抢先匹配。
\App\Services\Plugins\Registry::applyRoutes($router, 'web');

// 自定义页面短链接,必须放在最后,避免覆盖上面的固定路由。
$router->get('/{category}/{slug}', [PostController::class, 'show']);
$router->get('/{slug}',           [PageController::class, 'show']);
