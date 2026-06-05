<?php
/**
 * 全局配置
 */

$env = static function (string $key, string $default = ''): string {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }
    $value = getenv($key);
    return $value !== false && $value !== '' ? (string)$value : $default;
};

$envBool = static function (string $key, bool $default = false) use ($env): bool {
    $value = $env($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
};

return [
    'app' => [
        'name'      => 'LiteNote',
        'version'   => '1.0.0',
        'url'       => 'http://127.0.0.1:5555',
        'debug'     => true,
        'timezone'  => 'Asia/Shanghai',
        'locale'    => 'zh-CN',
        'key'       => 'change-me-32-bytes-random-secret!!',  // 用于加密 cookie 等
    ],

    'database' => [
        'sqlite' => __DIR__ . '/runtime/storage/database.sqlite',
        'activity' => __DIR__ . '/runtime/storage/activity.sqlite',
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
        'path'    => __DIR__ . '/uploads',
        'url'     => '/uploads',
        'max_size' => 50 * 1024 * 1024,  // 50MB
        'allowed_ext' => ['jpg','jpeg','png','gif','webp','mp3','m4a','wav','ogg','flac','aac','pdf','zip','txt','md'],
    ],

    'pagination' => [
        'front_per_page' => 10,
        'admin_per_page' => 20,
    ],

    'cache' => [
        'driver' => 'file',  // file | none
        'path'   => __DIR__ . '/runtime/storage/cache',
        'ttl'    => 3600,
    ],

    'mail' => [
        'sendflare' => [
            'enabled'   => $env('SENDFLARE_API_TOKEN') !== '',
            'endpoint'  => $env('SENDFLARE_ENDPOINT', 'https://api.sendflare.com/v1/send'),
            'token'     => $env('SENDFLARE_API_TOKEN'),
            'from'      => $env('SENDFLARE_FROM', 'noreply@example.com'),
            'from_name' => $env('SENDFLARE_FROM_NAME', 'LiteNote'),
            'notify_to' => $env('COMMENT_NOTIFY_TO'),
        ],
    ],

    'ai' => [
        'provider' => $env('AI_PROVIDER', 'deepseek'),
        'openai' => [
            'api_key'  => $env('OPENAI_API_KEY'),
            'model'    => $env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => $env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'deepseek' => [
            'api_key'  => $env('DEEPSEEK_API_KEY'),
            'model'    => $env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
            'base_url' => $env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        ],
    ],

    'analytics' => [
        'umami' => [
            'enabled'    => $envBool('UMAMI_ENABLED', false),
            'base_url'   => $env('UMAMI_BASE_URL'),
            'website_id' => $env('UMAMI_WEBSITE_ID'),
            'token'      => $env('UMAMI_TOKEN'),
            'api_key'    => $env('UMAMI_API_KEY'),
            'timezone'   => $env('UMAMI_TIMEZONE', 'Asia/Shanghai'),
            'script_url' => $env('UMAMI_SCRIPT_URL'),
        ],
    ],
];
