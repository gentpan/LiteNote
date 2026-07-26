<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\ActionRateLimiter;
use App\Services\Mailer;

/**
 * 前台读者注册 / 邮箱验证。
 * 角色固定为 reader，绝不会写成 admin。
 */
final class AuthController
{
    /** 保留用户名（小写比较）。 */
    private const RESERVED_USERNAMES = [
        'admin', 'administrator', 'root', 'system', 'support',
        'xifeng', 'xi_feng', 'xifengg', 'owner', 'blog', 'webmaster',
    ];

    /** 保留昵称（小写 / 原样比较）。 */
    private const RESERVED_NICKNAMES = [
        'admin', 'administrator', '管理员', '西风', 'xifeng', 'xi_feng',
    ];

    public function register(Request $request): never
    {
        $isAjax = $request->isAjax();
        $ip = $request->ip;

        if (!Mailer::isConfigured()) {
            $this->fail('站点尚未配置邮件服务，暂时无法注册。请稍后再试或联系站长。', 503, $isAjax);
        }

        if (ActionRateLimiter::tooMany('front_register', $ip, 8, 3600)) {
            $this->fail('注册过于频繁，请稍后再试', 429, $isAjax);
        }

        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $nickname = trim((string) $request->input('nickname', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $website = trim((string) $request->input('website', ''));
        $captchaInput = strtolower(trim((string) $request->input('captcha', '')));
        $captchaCode = (string) Session::get('_captcha', '');
        Session::forget('_captcha');

        if ($captchaCode === '' || $captchaInput !== $captchaCode) {
            $this->fail('验证码错误，请重新输入', 422, $isAjax);
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $this->fail('用户名需为 3–30 位字母、数字或下划线', 422, $isAjax);
        }
        if ($this->isReservedUsername($username)) {
            $this->fail('该用户名不可用，请换一个', 422, $isAjax);
        }
        if (strlen($password) < 6) {
            $this->fail('密码至少需要 6 位', 422, $isAjax);
        }
        if ($nickname === '' || mb_strlen($nickname) > 50) {
            $this->fail('请填写昵称（不超过 50 字）', 422, $isAjax);
        }
        if ($this->isReservedNickname($nickname)) {
            $this->fail('该昵称不可用，请换一个', 422, $isAjax);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('请填写有效邮箱', 422, $isAjax);
        }
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $this->fail('网站地址格式不正确', 422, $isAjax);
        }

        if (User::byUsername($username)) {
            $this->fail('该用户名已被占用', 422, $isAjax);
        }
        if (User::findBy('email', $email)) {
            $this->fail('该邮箱已被注册', 422, $isAjax);
        }

        ActionRateLimiter::hit('front_register', $ip, 8, 3600);

        $token = bin2hex(random_bytes(32));
        $user = new User([
            'username'          => $username,
            'password'          => password_hash($password, PASSWORD_DEFAULT),
            'email'             => $email,
            'nickname'          => $nickname,
            'role'              => 'reader',
            'status'            => 1,
            'email_verified_at' => null,
            'verify_token'      => hash('sha256', $token),
            'verify_expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);
        if ($website !== '') {
            $user->setSocialLinks([[
                'key'   => 'website',
                'url'   => $website,
                'icon'  => 'fa-solid fa-globe',
                'label' => '网站',
            ]]);
        }
        $user->save();

        $verifyUrl = Helper::url('/auth/verify?token=' . urlencode($token));
        $sent = Mailer::send(
            $email,
            '验证你的 ' . (string) (\App\Core\Config::get('site.title', 'LiteNote')) . ' 读者账号',
            Mailer::renderEmailVerify($verifyUrl, $nickname !== '' ? $nickname : $username),
            ['type' => 'account_verify', 'respect_unsubscribe' => false]
        );

        if (!$sent) {
            // 邮件失败时删掉半成品账号，避免占位
            try {
                $user->delete();
            } catch (\Throwable) {
                // ignore
            }
            $this->fail('验证邮件发送失败，请稍后重试或联系站长检查邮件配置', 502, $isAjax);
        }

        Response::json([
            'ok'          => true,
            'need_verify' => true,
            'email'       => $email,
            'message'     => '注册成功！验证邮件已发送至 ' . $email . '，请在 24 小时内点击链接激活（含垃圾箱）。',
        ]);
    }

    /** GET /auth/verify?token= */
    public function verify(Request $request): never
    {
        $token = trim((string) $request->input('token', ''));
        $user = $this->userByVerifyToken($token);
        if (!$user) {
            Session::flash('login_error', '验证链接无效或已过期，请重新注册或申请重发验证邮件。');
            Response::redirect('/?login=1&mode=login');
        }

        if (($user->role ?? '') === 'admin') {
            Response::redirect('/');
        }

        User::db()->update('users', [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'verify_token'      => null,
            'verify_expires_at' => null,
            'role'              => 'reader',
            'last_login_at'     => date('Y-m-d H:i:s'),
            'last_login_ip'     => $request->ip,
        ], 'id = :id', ['id' => $user->id]);

        $fresh = User::find((int) $user->id) ?: $user;
        Session::set('admin_user', [
            'id'       => $fresh->id,
            'username' => $fresh->username,
            'nickname' => $fresh->nickname,
            'role'     => 'reader',
            'status'   => 1,
        ]);
        Session::regenerate();

        Response::redirect('/?verified=1');
    }

    /** POST /auth/resend-verify */
    public function resendVerify(Request $request): never
    {
        $isAjax = $request->isAjax();
        $ip = $request->ip;

        if (!Mailer::isConfigured()) {
            $this->fail('邮件服务未配置，无法重发验证邮件', 503, $isAjax);
        }
        if (ActionRateLimiter::tooMany('front_resend_verify', $ip, 5, 3600)) {
            $this->fail('发送过于频繁，请稍后再试', 429, $isAjax);
        }

        $account = trim((string) $request->input('account', $request->input('username', '')));
        $email = strtolower(trim((string) $request->input('email', '')));

        $user = null;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = User::findBy('email', $email);
        }
        if (!$user && $account !== '') {
            $user = User::byUsername($account);
            if (!$user && filter_var($account, FILTER_VALIDATE_EMAIL)) {
                $user = User::findBy('email', strtolower($account));
            }
        }

        // 防枚举：统一成功文案
        $okMessage = '如果该账号存在且尚未验证，验证邮件已重新发送，请查收（含垃圾箱）。';

        if ($user && $user->isReader() && !$user->isEmailVerified()) {
            ActionRateLimiter::hit('front_resend_verify', $ip, 5, 3600);
            $token = bin2hex(random_bytes(32));
            User::db()->update('users', [
                'verify_token'      => hash('sha256', $token),
                'verify_expires_at' => date('Y-m-d H:i:s', time() + 86400),
            ], 'id = :id', ['id' => $user->id]);

            $to = trim((string) ($user->email ?? ''));
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $verifyUrl = Helper::url('/auth/verify?token=' . urlencode($token));
                Mailer::send(
                    $to,
                    '验证你的读者账号',
                    Mailer::renderEmailVerify($verifyUrl, (string) ($user->nickname ?: $user->username)),
                    ['type' => 'account_verify', 'respect_unsubscribe' => false]
                );
            }
        }

        Response::json(['ok' => true, 'message' => $okMessage]);
    }

    /**
     * @return array{nickname:string,email:string,website:string}
     */
    public static function identityPayload(User $user, string $website = ''): array
    {
        if ($website === '') {
            foreach ($user->getSocialLinks() as $link) {
                if (($link['key'] ?? '') === 'website' && ($link['url'] ?? '') !== '') {
                    $website = (string) $link['url'];
                    break;
                }
            }
        }

        return [
            'nickname' => (string) ($user->nickname ?: $user->username),
            'email'    => (string) ($user->email ?? ''),
            'website'  => $website,
        ];
    }

    private function userByVerifyToken(string $token): ?User
    {
        if ($token === '') {
            return null;
        }
        $user = User::findBy('verify_token', hash('sha256', $token));
        if (!$user) {
            return null;
        }
        $expires = (string) ($user->verify_expires_at ?? '');
        if ($expires === '' || strtotime($expires) < time()) {
            return null;
        }
        return $user;
    }

    private function isReservedUsername(string $username): bool
    {
        $key = strtolower($username);
        if (in_array($key, self::RESERVED_USERNAMES, true)) {
            return true;
        }
        $author = User::find(1);
        if ($author) {
            if (strtolower((string) $author->username) === $key) {
                return true;
            }
        }
        return false;
    }

    private function isReservedNickname(string $nickname): bool
    {
        $raw = trim($nickname);
        $lower = strtolower($raw);
        foreach (self::RESERVED_NICKNAMES as $blocked) {
            if ($raw === $blocked || $lower === strtolower($blocked)) {
                return true;
            }
        }
        $author = User::find(1);
        if ($author) {
            $authorNick = trim((string) ($author->nickname ?? ''));
            if ($authorNick !== '' && (strcasecmp($authorNick, $raw) === 0 || $authorNick === $raw)) {
                return true;
            }
        }
        return false;
    }

    private function fail(string $message, int $status, bool $isAjax): never
    {
        if ($isAjax) {
            Response::json(['ok' => false, 'message' => $message], $status);
        }
        Session::flash('register_error', $message);
        Response::redirect('/?login=1&tab=register');
    }
}
