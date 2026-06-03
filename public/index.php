<?php
declare(strict_types=1);

/**
 * 前台入口
 */
define('APP_START', microtime(true));
define('BASE_PATH', __DIR__ . '/..');

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

// 统计访问
\App\Services\StatService::record($request);

$router->dispatch($request);
