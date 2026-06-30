<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 前台 state-changing 请求的 CSRF 校验。
 */
final class FrontCsrf
{
    public static function token(): string
    {
        return Session::csrfToken();
    }

    public static function verify(Request $request): void
    {
        $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
        if (!Session::verifyCsrf(is_string($token) ? $token : null)) {
            Response::json(['code' => 419, 'msg' => '会话已过期，请刷新页面后重试'], 419);
        }
    }
}
