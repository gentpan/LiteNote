<?php
declare(strict_types=1);

namespace App\Enums;

/**
 * 文章发布状态
 *
 * 设计原则:
 *   - backed string enum,->value 即数据库存储值,保持与现有 schema 兼容
 *   - 统一所有 'published' / 'draft' 字面量,IDE 可跳转
 *   - 不做 Model cast(保持 Model 基类的轻量 __get),调用方写
 *       $post->status === PostStatus::Published->value
 *     即可,行为完全等价于旧字符串比较
 */
enum PostStatus: string
{
    case Published = 'published';
    case Draft     = 'draft';

    /** 用于 Validator 的 in: 规则 */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** 用于后台下拉显示 */
    public function label(): string
    {
        return match ($this) {
            self::Published => '已发布',
            self::Draft     => '草稿',
        };
    }

    /** 用于后台下拉的 [value => label] */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }
        return $out;
    }
}
