<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Services\ThemeManager;

final class ThemeController
{
    public function index(): string
    {
        $this->ensureThemeSetting();

        return View::render('theme.index', [
            'themes' => ThemeManager::all(),
            'activeTheme' => ThemeManager::activeKey(),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '主题管理',
        ], 'layouts.admin');
    }

    public function activate(Request $request): never
    {
        $key = trim((string)$request->input('theme', ''));
        try {
            ThemeManager::activate($key);
            Session::flash('success', '主题已切换');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/admin/themes');
    }

    public function delete(Request $request): never
    {
        $key = trim((string)$request->input('theme', ''));
        try {
            ThemeManager::delete($key);
            Session::flash('success', '主题已删除');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/admin/themes');
    }

    private function ensureThemeSetting(): void
    {
        Setting::ensureDefaults([
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
