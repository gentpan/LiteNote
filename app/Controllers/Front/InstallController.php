<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\View;
use App\Services\Installer;

class InstallController
{
    public function index(): string
    {
        $installed = Installer::isInstalled();
        $log = [];
        if (!$installed) {
            $log = Installer::install();
        }
        return View::render('install.done', [
            'installed' => true,
            'log'       => $log,
            'pageTitle' => '安装完成',
        ], 'layouts.front');
    }

    public function install(): string
    {
        $log = Installer::install();
        return View::render('install.done', [
            'installed' => true,
            'log'       => $log,
            'pageTitle' => '安装完成',
        ], 'layouts.front');
    }
}
