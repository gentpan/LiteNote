<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Setting;

final class CommentSettingsService
{
    public const TYPE_POST = 'post';
    public const TYPE_PAGE = 'page';
    public const TYPE_TALK = 'talk';
    public const TYPE_MUSIC = 'music';

    private const DEFAULTS = [
        'enabled' => true,
        'post_enabled' => true,
        'page_enabled' => false,
        'talk_enabled' => true,
        'music_enabled' => true,
        'need_audit' => true,
        'captcha' => false,
        'email_required' => true,
        'replies_enabled' => true,
        'close_old_days' => 0,
    ];

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            ['k' => 'comment_need_audit', 'v' => '1', 'type' => 'bool', 'label' => '评论需要审核', 'group_name' => 'comment', 'sort' => 6],
            ['k' => 'comment_captcha', 'v' => '0', 'type' => 'bool', 'label' => '启用验证码', 'group_name' => 'comment', 'sort' => 7],
        ]);
    }

    public static function settings(): array
    {
        $settings = self::DEFAULTS;

        try {
            self::ensureDefaults();
            $settings['need_audit'] = self::bool('comment_need_audit', true);
            $settings['captcha'] = self::bool('comment_captcha', false);
        } catch (\Throwable) {
            return $settings;
        }

        return $settings;
    }

    public static function captchaEnabled(): bool
    {
        return (bool) self::settings()['captcha'];
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

    public static function typeFromIds(int $postId, int $pageId, int $talkId, int $musicId): string
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
