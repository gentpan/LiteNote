<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

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
}
