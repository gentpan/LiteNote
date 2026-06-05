<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Response;
use App\Services\UmamiService;

class StatController
{
    public function summary(): never
    {
        $report = UmamiService::report(7);
        unset($report['config']['token'], $report['config']['apiKey']);
        Response::json([
            'umami' => $report,
        ]);
    }
}
