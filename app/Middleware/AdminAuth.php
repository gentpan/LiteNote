<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * 后台鉴权中间件
 */
final class AdminAuth
{
    public function handle(Request $request): bool
    {
        $user = Session::get('admin_user');
        if (!$user) {
            if ($request->isAjax()) {
                Response::json(['error' => 'Unauthorized'], 401);
            }
            Response::redirect('/admin/login');
        }
        return true;
    }
}
