<?php
declare(strict_types=1);

namespace App\Enums;

/**
 * 通用布尔开关(int 0/1)
 *
 * 用于所有 is_* 字段:
 *   - is_top, is_recommend (Post)
 *   - is_nav              (Page)
 *   - is_enabled          (Link)
 *   - is_public           (Shuoshuo)
 *
 * 设计:backed int,->value 即 0/1,保持与现有 schema 兼容。
 * 注意 MySQL 也有 boolean 别名 = tinyint(1),但当前表用 INTEGER,所以 int 最稳。
 */
enum Toggle: int
{
    case Off = 0;
    case On  = 1;

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Off => '关',
            self::On  => '开',
        };
    }

    /** 从任意输入归一化为 enum(用于表单输入) */
    public static function fromInput(mixed $value): self
    {
        if ($value === true || $value === 'on' || $value === '1' || $value === 1) {
            return self::On;
        }
        return self::Off;
    }
}
