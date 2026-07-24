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
        'version'   => '1.1.0',
        'url'       => $env('APP_URL', 'http://127.0.0.1:5555'),
        'debug'     => $envBool('APP_DEBUG', false),
        'timezone'  => 'Asia/Shanghai',
        'locale'    => 'zh-CN',
        // 强烈建议通过 .env 的 APP_KEY 设置 32 字节以上随机字符串，不要依赖回退值
        'key'       => $env('APP_KEY', 'change-me-32-bytes-random-secret!!'),
        // 反向代理 IP 列表（逗号分隔），仅当 REMOTE_ADDR 命中时才读取 X-Forwarded-For 等头
        'trusted_proxies' => array_values(array_filter(array_map(
            'trim',
            explode(',', $env('TRUSTED_PROXIES', ''))
        ))),
    ],

    'activity_api_token' => $env('ACTIVITY_API_TOKEN', ''),

    'database' => [
        'sqlite' => __DIR__ . '/runtime/storage/database.sqlite',
        'activity' => __DIR__ . '/runtime/storage/activity.sqlite',
    ],

    'site' => [
        'title'       => '我的个人博客',
        'subtitle'    => '记录、分享、思考',
        'description' => '一个用 PHP 8.5 写的小博客',
        'keywords'    => 'PHP,博客,个人',
        'site_analytics_code' => '',
        'comment_need_audit' => true,
        'comment_captcha'    => false,
    ],

    'upload' => [
        'path'    => __DIR__ . '/uploads',
        'url'     => '/uploads',
        'max_size' => 50 * 1024 * 1024,  // 50MB
        'allowed_ext' => ['jpg','jpeg','png','gif','webp','mp3','m4a','wav','ogg','flac','aac','lrc','mp4','webm','mov','m4v','avi','mkv','pdf','zip','txt','md'],
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
        'enabled' => $envBool('MAIL_ENABLED', $env('SENDFLARE_API_TOKEN') !== ''),
        'driver' => $env('MAIL_DRIVER', 'sendflare'), // sendflare | smtp
        'from' => $env('MAIL_FROM', $env('SENDFLARE_FROM', 'noreply@example.com')),
        'from_name' => $env('MAIL_FROM_NAME', $env('SENDFLARE_FROM_NAME', 'LiteNote')),
        'notify_to' => $env('MAIL_NOTIFY_TO', $env('COMMENT_NOTIFY_TO')),
        'post_recipients' => $env('MAIL_POST_RECIPIENTS'),
        'sendflare' => [
            'enabled'   => $env('SENDFLARE_API_TOKEN') !== '',
            'endpoint'  => $env('SENDFLARE_ENDPOINT', 'https://api.sendflare.com/v1/send'),
            'token'     => $env('SENDFLARE_API_TOKEN'),
            'from'      => $env('SENDFLARE_FROM', $env('MAIL_FROM', 'noreply@example.com')),
            'from_name' => $env('SENDFLARE_FROM_NAME', $env('MAIL_FROM_NAME', 'LiteNote')),
            'notify_to' => $env('COMMENT_NOTIFY_TO', $env('MAIL_NOTIFY_TO')),
        ],
        'smtp' => [
            'host' => $env('SMTP_HOST'),
            'port' => (int)$env('SMTP_PORT', '587'),
            'secure' => $env('SMTP_SECURE', 'tls'),
            'username' => $env('SMTP_USERNAME'),
            'password' => $env('SMTP_PASSWORD'),
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

    'meting' => [
        'base_url' => rtrim($env('METING_BASE_URL', 'https://api.meting.io'), '/'),
        'token' => $env('METING_TOKEN'),
        'quality' => $env('METING_QUALITY', 'exhigh'),
        'timeout' => (int)$env('METING_TIMEOUT', '12'),
    ],

    'netease' => [
        'api_base_url' => rtrim($env('NETEASE_API_BASE_URL', 'http://127.0.0.1:3000'), '/'),
    ],
];
