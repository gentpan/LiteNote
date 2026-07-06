<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\ApiResponse;
use App\Core\Config;
use App\Core\Request;

/**
 * Activity API 可选鉴权：配置 ACTIVITY_API_TOKEN 后要求 Bearer 匹配。
 */
final class ActivityApiAuthMiddleware
{
    public function handle(Request $request): bool
    {
        $token = trim((string) Config::get('activity_api_token', ''));
        if ($token === '') {
            return true;
        }

        $bearer = (string) ($request->bearerToken() ?? '');
        if ($bearer === '' || !hash_equals($token, $bearer)) {
            ApiResponse::error('Unauthorized', 401, 'activity_api_unauthorized');
        }

        return true;
    }

    public static function isPublic(): bool
    {
        return trim((string) Config::get('activity_api_token', '')) === '';
    }
}
