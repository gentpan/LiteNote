<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

class SettingController
{
    public function index(): string
    {
        $this->ensureAiSettings();
        $grouped = Setting::grouped();
        return View::render('setting.index', [
            'grouped' => $grouped,
            'csrf'    => Session::csrfToken(),
            'pageTitle' => '站点设置',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $data = (array) $request->input('settings', []);
        foreach ($data as $k => $v) {
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

    private function ensureAiSettings(): void
    {
        Setting::ensureDefaults([
            [
                'k' => 'ai_provider',
                'v' => 'deepseek',
                'type' => 'string',
                'label' => 'AI 服务商',
                'group_name' => 'ai',
                'sort' => 1,
            ],
            [
                'k' => 'deepseek_api_key',
                'v' => '',
                'type' => 'password',
                'label' => 'DeepSeek API Key',
                'group_name' => 'ai',
                'sort' => 2,
            ],
            [
                'k' => 'deepseek_model',
                'v' => 'deepseek-v4-flash',
                'type' => 'string',
                'label' => 'DeepSeek 模型',
                'group_name' => 'ai',
                'sort' => 3,
            ],
            [
                'k' => 'deepseek_base_url',
                'v' => 'https://api.deepseek.com',
                'type' => 'string',
                'label' => 'DeepSeek Base URL',
                'group_name' => 'ai',
                'sort' => 4,
            ],
        ]);
    }
}
