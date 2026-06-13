<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Services\LoginRateLimiter;
use App\Services\Mailer;

class AuthController
{
    public function loginForm(): string
    {
        return View::render('auth.login', [
            'csrf' => Session::csrfToken(),
            'error' => Session::getFlash('login_error'),
            'success' => (($_GET['password_changed'] ?? '') === '1') ? '密码已修改，请重新登录' : '',
        ], 'layouts.admin_auth');
    }

    public function login(Request $request): never
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $isAjax = $request->isAjax();
        $ip = $request->ip;

        if (LoginRateLimiter::tooManyAttempts($ip, $username)) {
            $message = '登录尝试次数过多，请 ' . LoginRateLimiter::decayMinutes() . ' 分钟后再试';
            if ($isAjax) {
                Response::json(['ok' => false, 'message' => $message], 429);
            }
            Session::flash('login_error', $message);
            Response::redirect('/admin/login');
        }

        $user = User::byUsername($username);
        if (!$user || !$user->verifyPassword($password)) {
            LoginRateLimiter::recordFailure($ip, $username);
            if ($isAjax) {
                Response::json(['ok' => false, 'message' => '用户名或密码错误'], 401);
            }
            Session::flash('login_error', '用户名或密码错误');
            Response::redirect('/admin/login');
        }

        LoginRateLimiter::clear($ip, $username);

        User::db()->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ], 'id = :id', ['id' => $user->id]);

        Session::set('admin_user', [
            'id'       => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'role'     => $user->role,
            'status'   => (int) $user->status,
        ]);
        Session::regenerate();

        if ($isAjax) {
            Response::json(['ok' => true, 'redirect' => '/admin']);
        }
        Response::redirect('/admin');
    }

    public function logout(): never
    {
        Session::destroy();
        Response::redirect('/admin/login');
    }

    /**
     * 忘记密码 — 申请表单。
     */
    public function forgotForm(): string
    {
        return View::render('auth.forgot', [
            'csrf'         => Session::csrfToken(),
            'error'        => Session::getFlash('forgot_error'),
            'success'      => Session::getFlash('forgot_success'),
            'mailEnabled'  => Mailer::isConfigured(),
        ], 'layouts.admin_auth');
    }

    /**
     * 忘记密码 — 处理申请,生成 token 并发送重置邮件。
     */
    public function forgot(Request $request): never
    {
        // 未配置邮件:功能不可用,如实告知
        if (!Mailer::isConfigured()) {
            Session::flash('forgot_error', '邮件服务未配置,无法通过邮箱找回密码。请使用 Passkey 登录,或在服务器上手动重置。');
            Response::redirect('/admin/forgot');
        }

        $account = trim((string) $request->input('account', ''));
        $user = User::byUsername($account);
        if (!$user && filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $user = User::findBy('email', $account);
        }

        // 命中账号且绑定了合法邮箱才真正发送;无论结果如何都返回相同提示,避免账号枚举
        if ($user) {
            $email = trim((string) ($user->email ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $token = bin2hex(random_bytes(32));
                User::db()->update('users', [
                    'reset_token'      => hash('sha256', $token),
                    'reset_expires_at' => date('Y-m-d H:i:s', time() + 3600),
                ], 'id = :id', ['id' => $user->id]);

                $url = Helper::url('/admin/reset?token=' . $token);
                Mailer::send(
                    $email,
                    '重置 LiteNote 后台密码',
                    Mailer::renderPasswordReset($url, (string) $user->username)
                );
            }
        }

        Session::flash('forgot_success', '如果该账号存在且已绑定邮箱,重置链接已发送至对应邮箱,请注意查收(含垃圾箱)。链接 1 小时内有效。');
        Response::redirect('/admin/forgot');
    }

    /**
     * 重置密码 — 设置新密码的表单(凭 token)。
     */
    public function resetForm(Request $request): string
    {
        $token = trim((string) $request->input('token', ''));
        $user = $this->userByResetToken($token);

        return View::render('auth.reset', [
            'csrf'  => Session::csrfToken(),
            'token' => $token,
            'valid' => $user !== null,
            'error' => Session::getFlash('reset_error'),
        ], 'layouts.admin_auth');
    }

    /**
     * 重置密码 — 校验 token 并写入新密码。
     */
    public function reset(Request $request): never
    {
        $token = trim((string) $request->input('token', ''));
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirm', '');

        $user = $this->userByResetToken($token);
        if (!$user) {
            Session::flash('reset_error', '重置链接无效或已过期,请重新申请。');
            Response::redirect('/admin/forgot');
        }
        if (strlen($password) < 6) {
            Session::flash('reset_error', '密码至少需要 6 位。');
            Response::redirect('/admin/reset?token=' . urlencode($token));
        }
        if ($password !== $confirm) {
            Session::flash('reset_error', '两次输入的密码不一致。');
            Response::redirect('/admin/reset?token=' . urlencode($token));
        }

        User::db()->update('users', [
            'password'         => password_hash($password, PASSWORD_DEFAULT),
            'reset_token'      => null,
            'reset_expires_at' => null,
        ], 'id = :id', ['id' => $user->id]);

        Session::flash('reset_success', '密码已重置,请用新密码登录。');
        Response::redirect('/admin/login');
    }

    /**
     * 凭 token 查回用户(校验存在 + 未过期)。
     */
    private function userByResetToken(string $token): ?User
    {
        if ($token === '') {
            return null;
        }
        $user = User::findBy('reset_token', hash('sha256', $token));
        if (!$user) {
            return null;
        }
        $expires = (string) ($user->reset_expires_at ?? '');
        if ($expires === '' || strtotime($expires) < time()) {
            return null;
        }
        return $user;
    }
}
