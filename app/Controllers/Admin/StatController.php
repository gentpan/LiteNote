<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Services\StatService;

class StatController
{
    public function index(): string
    {
        $today = StatService::today();
        $total = StatService::total();
        $last7 = StatService::last7Days();
        $topPosts = StatService::topPosts(10);
        return View::render('stat.index', [
            'today'    => $today,
            'total'    => $total,
            'last7'    => $last7,
            'topPosts' => $topPosts,
            'pageTitle' => '访问统计',
        ], 'layouts.admin');
    }
}
