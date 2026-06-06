<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\Setting;

final class CommentSettingsService
{
    public const TYPE_POST = 'post';
    public const TYPE_PAGE = 'page';
    public const TYPE_TALK = 'talk';
    public const TYPE_MUSIC = 'music';

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            ['k' => 'comment_enabled', 'v' => '1', 'type' => 'bool', 'label' => '全站评论开关', 'group_name' => 'comment', 'sort' => 1],
            ['k' => 'comment_post_enabled', 'v' => '1', 'type' => 'bool', 'label' => '文章评论开关', 'group_name' => 'comment', 'sort' => 2],
            ['k' => 'comment_page_enabled', 'v' => '1', 'type' => 'bool', 'label' => '页面评论开关', 'group_name' => 'comment', 'sort' => 3],
            ['k' => 'comment_talk_enabled', 'v' => '1', 'type' => 'bool', 'label' => '说说评论开关', 'group_name' => 'comment', 'sort' => 4],
            ['k' => 'comment_music_enabled', 'v' => '1', 'type' => 'bool', 'label' => '音乐评论开关', 'group_name' => 'comment', 'sort' => 5],
            ['k' => 'comment_need_audit', 'v' => '1', 'type' => 'bool', 'label' => '评论需要审核', 'group_name' => 'comment', 'sort' => 6],
            ['k' => 'comment_captcha', 'v' => '0', 'type' => 'bool', 'label' => '启用验证码', 'group_name' => 'comment', 'sort' => 7],
            ['k' => 'comment_email_required', 'v' => '1', 'type' => 'bool', 'label' => '评论者邮箱必填', 'group_name' => 'comment', 'sort' => 8],
            ['k' => 'comment_replies_enabled', 'v' => '1', 'type' => 'bool', 'label' => '允许回复评论', 'group_name' => 'comment', 'sort' => 9],
            ['k' => 'comment_close_old_days', 'v' => '0', 'type' => 'number', 'label' => '关闭旧文章评论天数', 'group_name' => 'comment', 'sort' => 10],
        ]);
    }

    public static function settings(): array
    {
        try {
            self::ensureDefaults();
            return [
                'enabled' => self::bool('comment_enabled', true),
                'post_enabled' => self::bool('comment_post_enabled', true),
                'page_enabled' => self::bool('comment_page_enabled', true),
                'talk_enabled' => self::bool('comment_talk_enabled', true),
                'music_enabled' => self::bool('comment_music_enabled', true),
                'need_audit' => self::bool('comment_need_audit', true),
                'captcha' => self::bool('comment_captcha', false),
                'email_required' => self::bool('comment_email_required', true),
                'replies_enabled' => self::bool('comment_replies_enabled', true),
                'close_old_days' => max(0, min(3650, (int) Setting::get('comment_close_old_days', 0))),
            ];
        } catch (\Throwable) {
            return [
                'enabled' => true,
                'post_enabled' => true,
                'page_enabled' => true,
                'talk_enabled' => true,
                'music_enabled' => true,
                'need_audit' => true,
                'captcha' => false,
                'email_required' => true,
                'replies_enabled' => true,
                'close_old_days' => 0,
            ];
        }
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

        $key = $type . '_enabled';
        if (array_key_exists($key, $settings) && !$settings[$key]) {
            return false;
        }

        if ($type === self::TYPE_POST && $target instanceof Post) {
            $days = (int)$settings['close_old_days'];
            if ($days > 0) {
                $publishedAt = strtotime((string)($target->published_at ?? ''));
                if ($publishedAt > 0 && $publishedAt < strtotime('-' . $days . ' days')) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function bool(string $key, bool $default): bool
    {
        $value = Setting::get($key, $default ? '1' : '0');
        return $value === true || $value === 1 || $value === '1';
    }
}
