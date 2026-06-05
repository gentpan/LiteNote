<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/**
 * 统计服务
 * - 记录每次访问的 PV
 * - 按 IP + day 去重算 UV
 * - 按天聚合查询
 */
final class StatService
{
    public static function record(Request $request): void
    {
        // 后台请求不计入统计
        if (str_starts_with($request->path, '/admin')) {
            return;
        }
        // 静态资源不统计
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|webp|ico|svg|woff2?|ttf|map)$/i', $request->path)) {
            return;
        }
        // feed 也不计
        if (str_ends_with($request->path, '/feed') || str_ends_with($request->path, '/feed/')) {
            return;
        }
        try {
            $db = Database::getInstance();
            $day = date('Y-m-d');
            $db->insert('stats', [
                'path'    => mb_substr($request->path, 0, 250),
                'ip'      => $request->ip,
                'ua'      => mb_substr($request->ua, 0, 250),
                'referer' => mb_substr((string)($request->server['HTTP_REFERER'] ?? ''), 0, 490),
                'day'     => $day,
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function today(): array
    {
        $db = Database::getInstance();
        $day = date('Y-m-d');
        $pv = (int) $db->fetchColumn('SELECT COUNT(*) FROM stats WHERE day = ?', [$day]);
        $uv = (int) $db->fetchColumn('SELECT COUNT(DISTINCT ip) FROM stats WHERE day = ?', [$day]);
        return ['pv' => $pv, 'uv' => $uv, 'day' => $day];
    }

    public static function total(): array
    {
        $db = Database::getInstance();
        $pv = (int) $db->fetchColumn('SELECT COUNT(*) FROM stats');
        $uv = (int) $db->fetchColumn('SELECT COUNT(DISTINCT ip) FROM stats');
        return ['pv' => $pv, 'uv' => $uv];
    }

    public static function last7Days(): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT day, COUNT(*) AS pv, COUNT(DISTINCT ip) AS uv
             FROM stats
             WHERE day >= date('now', '-6 days')
             GROUP BY day
             ORDER BY day ASC"
        );
        return $rows;
    }

    public static function topPosts(int $limit = 10): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT path, COUNT(*) AS hits
             FROM stats
             WHERE path LIKE '/post/%'
             GROUP BY path
             ORDER BY hits DESC
             LIMIT {$limit}"
        );
    }
}
