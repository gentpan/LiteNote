<?php
/**
 * 全局配置
 */

return [
    'app' => [
        'name'      => 'LiteNote',
        'url'       => 'http://127.0.0.1:5555',
        'debug'     => true,
        'timezone'  => 'Asia/Shanghai',
        'locale'    => 'zh-CN',
        'key'       => 'change-me-32-bytes-random-secret!!',  // 用于加密 cookie 等
    ],

    'database' => [
        'driver'    => 'sqlite',  // sqlite | mysql
        'sqlite'    => __DIR__ . '/../storage/database.sqlite',
        'mysql'     => [
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => 'blog',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],
    ],

    'site' => [
        'title'       => '我的个人博客',
        'subtitle'    => '记录、分享、思考',
        'description' => '一个用 PHP 8.5 写的小博客',
        'keywords'    => 'PHP,博客,个人',
        'beian'       => '',
        'comment_need_audit' => true,
        'comment_captcha'    => false,
    ],

    'upload' => [
        'path'    => __DIR__ . '/../public/assets/uploads',
        'url'     => '/assets/uploads',
        'max_size' => 5 * 1024 * 1024,  // 5MB
        'allowed_ext' => ['jpg','jpeg','png','gif','webp','pdf','zip','txt','md'],
    ],

    'pagination' => [
        'front_per_page' => 10,
        'admin_per_page' => 20,
    ],

    'cache' => [
        'driver' => 'file',  // file | none
        'path'   => __DIR__ . '/../storage/cache',
        'ttl'    => 3600,
    ],

    'mail' => [
        'sendflare' => [
            'enabled'   => (bool) getenv('SENDFLARE_API_TOKEN'),
            'endpoint'  => getenv('SENDFLARE_ENDPOINT') ?: 'https://api.sendflare.com/v1/send',
            'token'     => getenv('SENDFLARE_API_TOKEN') ?: '',
            'from'      => getenv('SENDFLARE_FROM') ?: 'noreply@example.com',
            'from_name' => getenv('SENDFLARE_FROM_NAME') ?: 'LiteNote',
            'notify_to' => getenv('COMMENT_NOTIFY_TO') ?: '',
        ],
    ],
];
