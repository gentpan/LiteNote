<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;
use App\Core\Response;

/**
 * CSRF 校验中间件（仅 POST 校验）
 */
final class CsrfMiddleware
{
    public function handle(Request $request): bool
    {
        if (!$request->isPost()) return true;
        $token = $request->input('_csrf') ?? ($request->header('X-CSRF-Token'));
        if (!Session::verifyCsrf($token)) {
            if ($request->isAjax()) {
                Response::json(['error' => 'CSRF token mismatch'], 419);
            }
            Response::text('CSRF token mismatch', 419);
        }
        return true;
    }
}
