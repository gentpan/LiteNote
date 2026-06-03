<?php
declare(strict_types=1);

/**
 * 后台入口
 */
define('APP_START', microtime(true));
define('BASE_PATH', __DIR__ . '/../..');
define('IS_ADMIN', true);

require BASE_PATH . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;
use App\Middleware\AdminAuth;

$request = new Request();
$router  = new Router();

// 加载后台路由
require BASE_PATH . '/routes/admin.php';

$router->dispatch($request);
