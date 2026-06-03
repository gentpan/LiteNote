<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

class ProfileController
{
    public function index(): string
    {
        $id = (int) Session::get('admin_user.id', 0);
        $user = $id > 0 ? User::find($id) : null;
        if (!$user) {
            // 会话里的 id 在 users 表中已不存在,踢回登录
            Session::flash('login_error', '账号不存在或已失效,请重新登录');
            Session::destroy();
            Response::redirect('/admin/login');
        }
        return View::render('profile.index', [
            'user' => $user,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '个人资料',
        ], 'layouts.admin');
    }

    public function update(Request $request): never
    {
        $id = (int) Session::get('admin_user.id', 0);
        $user = $id > 0 ? User::find($id) : null;
        if (!$user) {
            Session::flash('error', '用户不存在');
            Response::redirect('/admin/profile');
        }
        $user->fill([
            'nickname' => trim((string) $request->input('nickname', '')),
            'email'    => trim((string) $request->input('email', '')),
            'avatar'   => trim((string) $request->input('avatar', '')),
        ]);

        // 社交链接:来自表单 socials[i][key|url|icon|label]
        $rawSocials = (array) $request->input('socials', []);
        $socials = [];
        foreach ($rawSocials as $row) {
            if (!is_array($row)) continue;
            $socials[] = [
                'key'   => trim((string)($row['key']   ?? '')),
                'url'   => trim((string)($row['url']   ?? '')),
                'icon'  => trim((string)($row['icon']  ?? '')),
                'label' => trim((string)($row['label'] ?? '')),
            ];
        }
        $user->setSocialLinks($socials);

        $user->save();
        Session::set('admin_user.nickname', $user->nickname);
        Session::flash('success', '资料已更新');
        Response::redirect('/admin/profile');
    }

    public function password(Request $request): never
    {
        $id = (int) Session::get('admin_user.id', 0);
        $user = $id > 0 ? User::find($id) : null;
        if (!$user) {
            Session::flash('error', '用户不存在');
            Response::redirect('/admin/profile');
        }
        $old = (string) $request->input('old_password', '');
        $new = (string) $request->input('new_password', '');
        $cfm = (string) $request->input('confirm_password', '');

        if (!$user->verifyPassword($old)) {
            Session::flash('error', '原密码错误');
            Response::redirect('/admin/profile');
        }
        if (strlen($new) < 6) {
            Session::flash('error', '新密码至少 6 位');
            Response::redirect('/admin/profile');
        }
        if ($new !== $cfm) {
            Session::flash('error', '两次新密码不一致');
            Response::redirect('/admin/profile');
        }
        $user->password = password_hash($new, PASSWORD_DEFAULT);
        $user->save();
        Session::flash('success', '密码已修改');
        Response::redirect('/admin/profile');
    }
}
