<?php
declare(strict_types=1);

/**
 * LiteNote admin entry.
 */
define('APP_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('IS_ADMIN', true);

require BASE_PATH . '/core/app/bootstrap.php';

$request = new \App\Core\Request();
$router = new \App\Core\Router();

require BASE_PATH . '/core/routes/admin.php';

$router->dispatch($request);
