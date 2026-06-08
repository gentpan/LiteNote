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
use App\Controllers\Admin\TalkController;
use App\Controllers\Admin\MusicController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\PasskeyController;
use App\Controllers\Admin\ActivityController;
use App\Controllers\Admin\MailController;
use App\Controllers\Admin\TelegramController;
use App\Controllers\Admin\ThemeController;
use App\Controllers\Admin\PluginController;
use App\Middleware\AdminAuth;
use App\Middleware\CsrfMiddleware;

// 公开
$router->get('/admin/login',        [AuthController::class, 'loginForm']);
$router->post('/admin/login',       [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/admin/logout',       [AuthController::class, 'logout']);
$router->get('/admin/forgot',       [AuthController::class, 'forgotForm']);
$router->post('/admin/forgot',      [AuthController::class, 'forgot'], [CsrfMiddleware::class]);
$router->get('/admin/reset',        [AuthController::class, 'resetForm']);
$router->post('/admin/reset',       [AuthController::class, 'reset'], [CsrfMiddleware::class]);
$router->get('/admin/passkey/login-options', [PasskeyController::class, 'loginOptions']);
$router->post('/admin/passkey/login',        [PasskeyController::class, 'login'], [CsrfMiddleware::class]);

// 受保护
$router->get('/admin', [DashboardController::class, 'index'], [AdminAuth::class]);
$router->group('/admin', function ($r) {
    $r->get('/',                       [DashboardController::class, 'index']);

    // 文章
    $r->get('/posts',                  [PostController::class, 'index']);
    $r->get('/posts/create',           [PostController::class, 'create']);
    $r->post('/posts/create',          [PostController::class, 'store'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/posts/import',           [PostController::class, 'importForm']);
    $r->post('/posts/import',          [PostController::class, 'importMarkdown'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/preview',         [PostController::class, 'preview'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/upload-image',    [PostController::class, 'uploadImage'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/summary',         [PostController::class, 'generateSummary'], [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/posts/{id}/edit',        [PostController::class, 'edit']);
    $r->post('/posts/{id}/edit',       [PostController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/{id}/toggle',     [PostController::class, 'toggleFlag'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/{id}/delete',     [PostController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);
    $r->post('/posts/bulk',            [PostController::class, 'bulk'],   [\App\Middleware\CsrfMiddleware::class]);

    // 分类
    $r->get('/categories',             [CategoryController::class, 'index']);
    $r->post('/categories/save',       [CategoryController::class, 'save'],   [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/categories/toggle',     [CategoryController::class, 'toggleNav'],[\App\Middleware\CsrfMiddleware::class]);
    $r->post('/categories/delete',     [CategoryController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 标签功能已彻底移除(2026-06):Tag 模型 / Controller / 表 / 路由 全部清理

    // 页面
    $r->get('/pages',                  [PageController::class, 'index']);
    $r->get('/pages/create',           [PageController::class, 'create']);
    $r->post('/pages/create',          [PageController::class, 'store'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/pages/toggle',          [PageController::class, 'toggleNav'], [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/pages/{id}/edit',        [PageController::class, 'edit']);
    $r->post('/pages/{id}/edit',       [PageController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/pages/delete',          [PageController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 附件
    $r->get('/attachments',            [AttachmentController::class, 'index']);
    $r->post('/attachments/settings',  [AttachmentController::class, 'saveSettings'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/s3-test',   [AttachmentController::class, 'testS3'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/s3-clear',  [AttachmentController::class, 'clearS3'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/s3-command', [AttachmentController::class, 's3ClearCommand'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/backup-now', [AttachmentController::class, 'backupNow'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/upload',    [AttachmentController::class, 'upload'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/attachments/delete',    [AttachmentController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 主题 / 插件
    $r->get('/themes',                 [ThemeController::class, 'index']);
    $r->post('/themes/activate',       [ThemeController::class, 'activate'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/themes/delete',         [ThemeController::class, 'delete'],   [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/plugins',                [PluginController::class, 'index']);
    $r->post('/plugins/{key}/toggle',  [PluginController::class, 'toggle'], [\App\Middleware\CsrfMiddleware::class]);

    // 友情链接
    $r->get('/links',                  [LinkController::class, 'index']);
    $r->post('/links/save',            [LinkController::class, 'save'],    [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/links/delete',          [LinkController::class, 'destroy'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/links/bulk-delete',     [LinkController::class, 'bulkDelete'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/links/refresh',         [LinkController::class, 'refresh'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/links/refresh-aggregate', [LinkController::class, 'refreshAggregate'], [\App\Middleware\CsrfMiddleware::class]);

    // 评论
    $r->get('/comments',               [CommentController::class, 'index']);
    $r->post('/comments/settings',      [CommentController::class, 'saveSettings'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/comments/approve',      [CommentController::class, 'approve'],[\App\Middleware\CsrfMiddleware::class]);
    $r->post('/comments/spam',         [CommentController::class, 'spam'],   [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/comments/delete',       [CommentController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 滔客
    $r->get('/activities',         [ActivityController::class, 'index']);
    $r->get('/activities/integrations', [ActivityController::class, 'integrations']);
    $r->get('/activities/integrations/{provider}/edit', [ActivityController::class, 'editIntegration']);
    $r->post('/activities/integrations/{provider}/save', [ActivityController::class, 'saveIntegration'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/activities/integrations/{provider}/sync', [ActivityController::class, 'syncIntegration'], [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/activities/{id}/edit', [ActivityController::class, 'edit']);
    $r->post('/activities/{id}/edit', [ActivityController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/activities/delete', [ActivityController::class, 'destroy'], [\App\Middleware\CsrfMiddleware::class]);

    // 滔客
    $r->get('/talk',               [TalkController::class, 'index']);
    $r->get('/talk/{id}/edit',     [TalkController::class, 'edit']);
    $r->post('/talk/{id}/edit',    [TalkController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/talk/{id}/toggle',  [TalkController::class, 'togglePublic'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/talk/delete',       [TalkController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 音乐
    $r->get('/music',              [MusicController::class, 'index']);
    $r->get('/music/meting/search', [MusicController::class, 'metingSearch']);
    $r->get('/music/meting/song',   [MusicController::class, 'metingSong']);
    $r->get('/music/online',       [MusicController::class, 'online']);
    $r->get('/music/create',       [MusicController::class, 'create']);
    $r->post('/music/create',      [MusicController::class, 'store'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/music/{id}/share',  [MusicController::class, 'share'],  [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/music/{id}/edit',    [MusicController::class, 'edit']);
    $r->post('/music/{id}/edit',   [MusicController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/music/delete',      [MusicController::class, 'destroy'],[\App\Middleware\CsrfMiddleware::class]);

    // 设置
    $r->get('/settings',               [SettingController::class, 'index']);
    $r->get('/settings/comments',      [SettingController::class, 'comments']);
    $r->get('/settings/permalinks',    [SettingController::class, 'permalinks']);
    $r->get('/settings/attachments',   [AttachmentController::class, 'settings']);
    $r->post('/settings/save',         [SettingController::class, 'save'],   [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/settings/favicon',      [SettingController::class, 'uploadFavicon'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/settings/site-logo',    [SettingController::class, 'uploadSiteLogo'], [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/settings/mail',          [MailController::class, 'index']);
    $r->post('/settings/mail/save',    [MailController::class, 'save'],      [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/settings/mail/test',    [MailController::class, 'test'],      [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/settings/telegram',      [TelegramController::class, 'index']);
    $r->post('/settings/telegram/save', [TelegramController::class, 'save'], [\App\Middleware\CsrfMiddleware::class]);
    $r->get('/mail',                   static function (): never {
        \App\Core\Response::redirect('/admin/settings/mail');
    });
    $r->post('/mail/save',             [MailController::class, 'save'],      [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/mail/test',             [MailController::class, 'test'],      [\App\Middleware\CsrfMiddleware::class]);

    // 个人资料
    $r->get('/profile',                [ProfileController::class, 'index']);
    $r->post('/profile',               [ProfileController::class, 'update'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/profile/avatar',        [ProfileController::class, 'uploadAvatar'], [\App\Middleware\CsrfMiddleware::class]);
    $r->post('/profile/password',      [ProfileController::class, 'password'],[\App\Middleware\CsrfMiddleware::class]);

    // Passkey
    $r->get('/passkey/register-options',  [PasskeyController::class, 'registerOptions']);
    $r->post('/passkey/register',         [PasskeyController::class, 'register'], [\App\Middleware\CsrfMiddleware::class]);

}, [AdminAuth::class]);

// 插件后台路由:统一套 /admin 前缀与 AdminAuth(见 Registry::applyRoutes)。
\App\Services\Plugins\Registry::applyRoutes($router, 'admin');
