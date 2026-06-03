<?php
declare(strict_types=1);

/**
 * 前台路由
 */

use App\Controllers\Front\HomeController;
use App\Controllers\Front\PostController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\ShuoshuoController;
use App\Controllers\Front\ArchiveController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\FriendController;
use App\Controllers\Front\CommentController;
use App\Controllers\Front\FeedController;
use App\Controllers\Front\StatController;
use App\Controllers\Front\InstallController;

$router->get('/install',          [InstallController::class, 'index']);
$router->get('/install/do',       [InstallController::class, 'install']);

// 首页
$router->get('/',                 [HomeController::class, 'index']);

// 文章
$router->get('/post/{slug}',      [PostController::class, 'show']);

// 分类
$router->get('/category/{slug}',  [CategoryController::class, 'show']);

// 页面
$router->get('/page/{slug}',      [PageController::class, 'show']);

// 说说
$router->get('/shuoshuo',         [ShuoshuoController::class, 'index']);

// 归档
$router->get('/archives',         [ArchiveController::class, 'index']);

// 搜索
$router->get('/search',           [SearchController::class, 'index']);

// 友链页
$router->get('/friends',          [FriendController::class, 'index']);

// 评论提交
$router->post('/comment/submit',  [CommentController::class, 'submit']);

// RSS
$router->get('/feed',             [FeedController::class, 'feed']);
$router->get('/friends/feed',     [FeedController::class, 'friendsFeed']);

// 统计图
$router->get('/api/stats',        [StatController::class, 'summary']);
