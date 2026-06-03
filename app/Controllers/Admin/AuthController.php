<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

class AuthController
{
    public function loginForm(): string
    {
        return View::render('auth.login', [
            'csrf' => Session::csrfToken(),
            'error' => Session::getFlash('login_error'),
        ], 'layouts.admin_auth');
    }

    public function login(Request $request): never
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        $user = User::byUsername($username);
        if (!$user || !$user->verifyPassword($password)) {
            Session::flash('login_error', '用户名或密码错误');
            Response::redirect('/admin/login');
        }

        User::db()->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip,
        ], 'id = :id', ['id' => $user->id]);

        Session::set('admin_user', [
            'id'       => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'role'     => $user->role,
        ]);
        Session::regenerate();
        Response::redirect('/admin');
    }

    public function logout(): never
    {
        Session::destroy();
        Response::redirect('/admin/login');
    }
}
