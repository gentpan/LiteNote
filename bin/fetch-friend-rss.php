<?php
declare(strict_types=1);

use App\Services\FriendRssService;

require __DIR__ . '/../app/bootstrap.php';

$items = FriendRssService::refreshAggregate(5, 50);

echo sprintf(
    "[%s] refreshed friend subscriptions: %d items\n",
    date('Y-m-d H:i:s'),
    count($items)
);
