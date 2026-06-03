<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Response;
use App\Services\StatService;

class StatController
{
    public function summary(): never
    {
        Response::json([
            'today' => StatService::today(),
            'total' => StatService::total(),
            'last7' => StatService::last7Days(),
        ]);
    }
}
