<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Services\ThemeManager;

class SettingController
{
    public function index(): string
    {
        $this->ensureSiteSettings();
        $grouped = Setting::grouped();
        unset($grouped['ai'], $grouped['analytics'], $grouped['feature']);
        return View::render('setting.index', [
            'grouped' => $grouped,
            'themes' => ThemeManager::all(),
            'activeTheme' => ThemeManager::activeKey(),
            'csrf'    => Session::csrfToken(),
            'pageTitle' => '站点设置',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $data = (array) $request->input('settings', []);
        foreach ($data as $k => $v) {
            if ($k === 'theme') {
                continue;
            }
            if ($k === 'site_theme' && !in_array((string)$v, ThemeManager::keys(), true)) {
                $v = 'ember';
            }
            Setting::set($k, $v);
        }
        // 刷新共享 view 和 config
        $all = Setting::allAsArray();
        foreach ($all as $k => $v) {
            \App\Core\View::share($k, $v);
            \App\Core\Config::set("site.{$k}", $v);
        }
        // 整个 site 数组要重新 share（因为 bootstrap 时已经 share 过旧的）
        \App\Core\View::share('site', \App\Core\Config::get('site'));
        Session::flash('success', '设置已保存');
        Response::redirect('/admin/settings');
    }

    private function ensureSiteSettings(): void
    {
        Setting::ensureDefaults([
            [
                'k' => 'site_avatar_url',
                'v' => '',
                'type' => 'string',
                'label' => '站点头像地址',
                'group_name' => 'basic',
                'sort' => 8,
            ],
            [
                'k' => 'site_theme',
                'v' => 'ember',
                'type' => 'string',
                'label' => '前台主题',
                'group_name' => 'basic',
                'sort' => 9,
            ],
        ]);
    }

}
