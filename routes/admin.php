<?php
declare(strict_types=1);

/**
 * 后台路由
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\PageController;
use App\Controllers\Admin\AttachmentController;
use App\Controllers\Admin\LinkController;
use App\Controllers\Admin\CommentController;
use App\Controllers\Admin\ShuoshuoController;
use App\Controllers\Admin\StatController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\ProfileController;
use App\Middleware\AdminAuth;
use App\Middleware\CsrfMiddleware;

// 公开
$router->get('/admin/login',        [AuthController::class, 'loginForm']);
$router->post('/admin/login',       [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/admin/logout',       [AuthController::class, 'logout']);

// 受保护
$router->group('/admin', function ($r) {
    $r->get('/',                       [DashboardController::class, 'index']);

    // 文章
    $r->get('/posts',                  [PostController::class, 'index']);
    $r->get('/posts/create',           [PostController::class, 'create']);
    $r->post('/posts/create',          [PostController::class, 'store'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/posts/import',           [PostController::class, 'importForm']);
    $r->post('/posts/import',          [PostController::class, 'importMarkdown'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/preview',         [PostController::class, 'preview'], [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/posts/{id}/edit',        [PostController::class, 'edit']);
    $r->post('/posts/{id}/edit',       [PostController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/{id}/delete',     [PostController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/bulk',            [PostController::class, 'bulk'],   [\App\Middleware\CsrfMiddleware::class]);

    // 分类
    $r->get('/categories',             [CategoryController::class, 'index']);
    $r->post('/categories/save',       [CategoryController::class, 'save'],   [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/categories/delete',     [CategoryController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 标签功能已彻底移除(2026-06):Tag 模型 / Controller / 表 / 路由 全部清理

    // 页面
    $r->get('/pages',                  [PageController::class, 'index']);
    $r->get('/pages/create',           [PageController::class, 'create']);
    $r->post('/pages/create',          [PageController::class, 'store'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/pages/{id}/edit',        [PageController::class, 'edit']);
    $r->post('/pages/{id}/edit',       [PageController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/pages/delete',          [PageController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 附件
    $r->get('/attachments',            [AttachmentController::class, 'index']);
    $r->post('/attachments/upload',    [AttachmentController::class, 'upload'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/delete',    [AttachmentController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 友情链接
    $r->get('/links',                  [LinkController::class, 'index']);
    $r->post('/links/save',            [LinkController::class, 'save'],    [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/links/delete',          [LinkController::class, 'destroy'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/links/refresh',         [LinkController::class, 'refresh'], [\App\Middleware\CsrfMiddleware::class]);

    // 评论
    $r->get('/comments',               [CommentController::class, 'index']);
    $r->post('/comments/approve',      [CommentController::class, 'approve'],[\App\Middleware\CsrfMiddleware::class]);
    $r->post('/comments/spam',         [CommentController::class, 'spam'],   [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/comments/delete',       [CommentController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 说说
    $r->get('/shuoshuo',               [ShuoshuoController::class, 'index']);
    $r->get('/shuoshuo/create',        [ShuoshuoController::class, 'create']);
    $r->post('/shuoshuo/create',       [ShuoshuoController::class, 'store'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/shuoshuo/{id}/edit',     [ShuoshuoController::class, 'edit']);
    $r->post('/shuoshuo/{id}/edit',    [ShuoshuoController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/shuoshuo/delete',       [ShuoshuoController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 统计
    $r->get('/stats',                  [StatController::class, 'index']);

    // 设置
    $r->get('/settings',               [SettingController::class, 'index']);
    $r->post('/settings/save',         [SettingController::class, 'save'],   [\App\Middleware\CsrfMiddleware::class]);

    // 个人资料
    $r->get('/profile',                [ProfileController::class, 'index']);
    $r->post('/profile',               [ProfileController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/profile/password',      [ProfileController::class, 'password'],[\App\Middleware\CsrfMiddleware::class]);

}, [AdminAuth::class]);
