<?php
declare(strict_types=1);

namespace App\Enums;

/**
 * 评论状态
 *
 * 替代原 Comment::STATUS_PENDING / STATUS_APPROVED / STATUS_SPAM 三个 const
 */
enum CommentStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Spam     = 'spam';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending  => '待审核',
            self::Approved => '已通过',
            self::Spam     => '垃圾',
        };
    }

    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }
        return $out;
    }
}
