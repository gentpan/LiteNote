<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 把耗时任务放到响应发送后执行（通过 register_shutdown_function）。
 *
 * 适用于评论通知邮件、外部 RSS/Activity 同步、AI 摘要等不能阻塞用户等待的场景。
 * 注意：这并不会把任务移出当前 PHP 进程，只是让浏览器/客户端先收到响应；
 * 高负载站点应使用真正的队列/Worker。
 */
final class Background
{
    public static function run(callable $job): void
    {
        register_shutdown_function(static function () use ($job): void {
            try {
                $job();
            } catch (\Throwable $e) {
                error_log('LiteNote background job failed: ' . $e->getMessage());
            }
        });
    }
}
