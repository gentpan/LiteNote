<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Services\UmamiService;

class StatController
{
    public function index(): string
    {
        $days = max(1, min(180, (int)($_GET['days'] ?? 7)));
        $report = UmamiService::report($days);
        return View::render('stat.index', [
            'report' => $report,
            'pageTitle' => 'Umami 统计',
        ], 'layouts.admin');
    }
}
