<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PluginManager;

final class PluginController
{
    public function index(): string
    {
        return View::render('plugin.index', [
            'plugins' => PluginManager::all(),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '插件管理',
        ], 'layouts.admin');
    }

    public function toggle(Request $request, array $params): never
    {
        $key = (string)($params['key'] ?? '');
        try {
            if (PluginManager::isEnabled($key)) {
                PluginManager::disable($key);
                Session::flash('success', '插件已禁用');
            } else {
                PluginManager::enable($key);
                Session::flash('success', '插件已启用');
            }
        } catch (\Throwable $e) {
            Session::flash('error', '操作失败：' . Helper::publicErrorMessage($e));
        }
        Response::redirect('/admin/plugins');
    }
}
