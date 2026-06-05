<?php
declare(strict_types=1);

/**
 * LiteNote root entry.
 */
define('APP_START', microtime(true));
define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/core/app/Core/StaticAssetServer.php';

$__requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__requestPath = rawurldecode($__requestPath);
$__adminRequest = $__requestPath === '/admin' || str_starts_with($__requestPath, '/admin/');

if ($__requestPath !== '/' && \App\Core\StaticAssetServer::serve(BASE_PATH, $__requestPath)) {
    exit;
}

if ($__adminRequest) {
    define('IS_ADMIN', true);
    require BASE_PATH . '/core/app/bootstrap.php';

    $request = new \App\Core\Request();
    $router = new \App\Core\Router();
    require BASE_PATH . '/core/routes/admin.php';
    $router->dispatch($request);
    exit;
}

require BASE_PATH . '/core/app/bootstrap.php';

$request = new \App\Core\Request();
$router = new \App\Core\Router();

$routeFile = BASE_PATH . '/core/routes/web.php';
if (is_file($routeFile)) {
    require $routeFile;
}

$router->dispatch($request);
