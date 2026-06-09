<?php
declare(strict_types=1);

namespace App\Core;

final class TemplateMap
{
    private const ADMIN_PREFIXES = [
        'activity.', 'attachment.', 'auth.', 'comment.', 'dashboard.', 'link.', 'mail.',
        'music.', 'page.form', 'page.index', 'plugin.', 'post.form', 'post.import',
        'post.index', 'profile.', 'setting.', 'stat.', 'talk.', 'telegram.', 'theme.',
    ];

    private const SITE_PREFIXES = [
        'archive.', 'category.', 'friend.', 'front.', 'home.', 'page.show',
        'post.show', 'search.',
    ];

    public static function map(string $template): string
    {
        if (
            str_starts_with($template, 'layouts.') ||
            str_starts_with($template, 'partials.') ||
            str_starts_with($template, 'admin.') ||
            str_starts_with($template, 'system.')
        ) {
            return $template;
        }

        if (self::matchesAny($template, self::ADMIN_PREFIXES)) {
            return 'admin.' . $template;
        }

        if (self::matchesAny($template, self::SITE_PREFIXES)) {
            return $template;
        }

        if (str_starts_with($template, 'errors.')) {
            return 'system.' . $template;
        }

        return $template;
    }

    public static function isFrontendTemplate(string $template): bool
    {
        return str_starts_with($template, 'home.')
            || str_starts_with($template, 'post.')
            || str_starts_with($template, 'page.')
            || str_starts_with($template, 'category.')
            || str_starts_with($template, 'archive.')
            || str_starts_with($template, 'search.')
            || str_starts_with($template, 'friend.')
            || str_starts_with($template, 'front.')
            || str_starts_with($template, 'partials.')
            || str_starts_with($template, 'errors.');
    }

    private static function matchesAny(string $template, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($template, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
