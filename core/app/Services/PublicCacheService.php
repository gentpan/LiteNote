<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;

/**
 * 前台公开页缓存键集中管理，内容变更时统一失效。
 */
final class PublicCacheService
{
  private const KEYS = [
    'rss.xml',
    'llms.txt',
    'archives.page',
    'home.posts-heatmap',
    'talk.hero',
    'activity.page-heatmap',
  ];

  public static function forgetAll(): void
  {
    $cache = new FileCache();
    foreach (self::KEYS as $key) {
      $cache->forget($key);
    }
    (new ActivityCacheService())->forget();
  }

  public static function forgetContent(): void
  {
    self::forgetAll();
  }

  public static function forget(string $key): void
  {
    (new FileCache())->forget($key);
  }
}
