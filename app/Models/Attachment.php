<?php
declare(strict_types=1);

namespace App\Models;

final class Attachment extends Model
{
    protected static string $table = 'attachments';

    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'id DESC', ?string $whereSql = null, array $params = []): array
    {
        // 兼容旧调用：如果 whereSql 是单个 filetype 值（如 'image'），转为条件
        // 但更好的做法是让调用方直接传 whereSql
        return parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
    }

    /**
     * 按文件类型分页查询（兼容旧接口）。
     */
    public static function paginateByType(int $page, int $perPage, ?string $type = null): array
    {
        $whereSql = null;
        $params = [];
        if ($type) {
            $whereSql = 'filetype = ?';
            $params[] = $type;
        }
        return parent::paginate($page, $perPage, 'id DESC', $whereSql, $params);
    }

    public function isImage(): bool
    {
        return in_array(strtolower((string)$this->filetype), ['jpg','jpeg','png','gif','webp'], true);
    }
}
