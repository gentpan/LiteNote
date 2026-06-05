<?php
declare(strict_types=1);

namespace App\Traits;

use App\Core\Helper;

/**
 * 统一 slug 生成与唯一性校验逻辑。
 * 要求使用方：
 *   - 存在 static::$table
 *   - 存在 static::findBySlug(string): ?static
 *   - 存在 static::$pk（默认 'id'）
 */
trait HasSlug
{
    /**
     * 根据标题生成唯一 slug。
     *
     * @param string $title 原始标题
     * @param int|null $excludeId 排除的 ID（编辑场景）
     * @return string
     */
    public static function makeUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Helper::slugify($title);
        $base = $slug;
        $i = 1;

        while (true) {
            $existing = static::findBySlug($slug);
            if (!$existing || ($excludeId !== null && (int) $existing->{static::$pk} === $excludeId)) {
                break;
            }
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * 校验并返回安全的 slug；若留空则自动生成。
     *
     * @param string $raw 用户输入的 slug（可能为空）
     * @param string $fallbackTitle 用于生成 slug 的标题
     * @param int|null $excludeId
     * @return string
     */
    public static function resolveSlug(string $raw, string $fallbackTitle, ?int $excludeId = null): string
    {
        $slug = trim($raw);
        if ($slug === '') {
            $slug = Helper::slugify($fallbackTitle);
        }
        return static::makeUniqueSlug($slug, $excludeId);
    }
}
