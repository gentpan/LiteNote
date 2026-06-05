<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

interface ActivityAdapter
{
    public function provider(): string;

    /**
     * Pull remote platform data and write normalized activities.
     *
     * @return array{created:int,updated:int,skipped:int,message:string}
     */
    public function sync(ActivityIntegration $integration): array;
}
