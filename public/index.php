<?php
declare(strict_types=1);

/**
 * 前台入口
 */
define('APP_START', microtime(true));
define('BASE_PATH', __DIR__ . '/..');

// PHP 内置服务器或重写规则未命中静态文件时,兜底输出 public 下的安全静态资源。
// 主要处理上传图片中文/编码路径在部分环境下不能被直接静态命中的情况。
$__staticPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__staticPath = rawurldecode($__staticPath);
$__publicRoot = realpath(__DIR__);
$__staticFile = $__publicRoot ? realpath($__publicRoot . '/' . ltrim($__staticPath, '/')) : false;
$__staticExt = is_string($__staticFile) ? strtolower(pathinfo($__staticFile, PATHINFO_EXTENSION)) : '';
$__staticAllowed = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'xml', 'txt', 'woff', 'woff2', 'ttf', 'otf', 'map'];
if (
    is_string($__publicRoot)
    && is_string($__staticFile)
    && str_starts_with($__staticFile, $__publicRoot . DIRECTORY_SEPARATOR)
    && is_file($__staticFile)
    && in_array($__staticExt, $__staticAllowed, true)
) {
    $mime = mime_content_type($__staticFile) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($__staticFile));
    header('Cache-Control: public, max-age=31536000');
    readfile($__staticFile);
    exit;
}

require BASE_PATH . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$request = new Request();
$router  = new Router();

// 加载路由
$routeFile = BASE_PATH . '/routes/web.php';
if (is_file($routeFile)) {
    require $routeFile;
}

$router->dispatch($request);
