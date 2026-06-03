<?php
declare(strict_types=1);

/**
 * PHP 内置服务器路由
 * 用途：php -S 127.0.0.1:5555 -t public router.php
 * 真实文件直接返回，否则转发到 index.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false; // 让 PHP 内置服务器直接返回
}

// 后台
if (str_starts_with($path, '/admin')) {
    // 转到 admin/index.php
    $adminFile = __DIR__ . '/public/admin/index.php';
    if (is_file($adminFile)) {
        require $adminFile;
        return true;
    }
}

require __DIR__ . '/public/index.php';
