<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;

final class CommentSettingsService
{
    public const TYPE_POST = 'post';
    public const TYPE_PAGE = 'page';
    public const TYPE_TALK = 'talk';
    public const TYPE_MUSIC = 'music';
    public const TYPE_X_TWEET = 'x_tweet';

    private const DEFAULTS = [
        'enabled' => true,
        'post_enabled' => true,
        'page_enabled' => false,
        'talk_enabled' => true,
        'music_enabled' => true,
        'need_audit' => true,
        'email_required' => true,
        'replies_enabled' => true,
        'close_old_days' => 0,
    ];

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            ['k' => 'comment_need_audit', 'v' => '1', 'type' => 'bool', 'label' => '评论需要审核', 'group_name' => 'comment', 'sort' => 6],
        ]);
    }

    public static function settings(): array
    {
        $settings = self::DEFAULTS;

        try {
            self::ensureDefaults();
            $settings['need_audit'] = self::bool('comment_need_audit', true);
        } catch (\Throwable) {
            return $settings;
        }

        return $settings;
    }

    /** 验证码恒启用(无后台开关);白名单邮箱与管理员在调用点豁免。 */
    public static function captchaEnabled(): bool
    {
        return true;
    }

    public static function emailRequired(): bool
    {
        return (bool) self::settings()['email_required'];
    }

    public static function repliesEnabled(): bool
    {
        return (bool) self::settings()['replies_enabled'];
    }

    public static function needAudit(): bool
    {
        return (bool) self::settings()['need_audit'];
    }

    public static function typeFromIds(int $postId, int $pageId, int $talkId, int $musicId, int $xTweetId = 0): string
    {
        if ($postId > 0) {
            return self::TYPE_POST;
        }
        if ($pageId > 0) {
            return self::TYPE_PAGE;
        }
        if ($musicId > 0) {
            return self::TYPE_MUSIC;
        }
        if ($xTweetId > 0) {
            return self::TYPE_X_TWEET;
        }
        return self::TYPE_TALK;
    }

    public static function enabledFor(string $type, ?object $target = null): bool
    {
        $settings = self::settings();
        if (!$settings['enabled']) {
            return false;
        }

        if ($type === self::TYPE_PAGE && $target instanceof Page && (string)($target->slug ?? '') === 'friends') {
            return true;
        }

        if ($type === self::TYPE_POST && $target instanceof Post) {
            Post::ensurePublishingOptionsSchema();
            if ((int)($target->is_private ?? 0) === 1) {
                return false;
            }
            if ((int)($target->allow_comments ?? 1) !== 1) {
                return false;
            }
        }

        $key = $type . '_enabled';
        if (array_key_exists($key, $settings) && !$settings[$key]) {
            return false;
        }

        return true;
    }

    private static function bool(string $key, bool $default): bool
    {
        $value = Setting::get($key, $default ? '1' : '0');
        return $value === true || $value === 1 || $value === '1';
    }
}
