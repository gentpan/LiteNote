<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Services\CommentSettingsService;
use App\Services\FaviconService;
use App\Services\PermalinkService;
use App\Services\ReadingSettingsService;

class SettingController
{
    public function index(): string
    {
        return $this->renderSection('basic');
    }

    public function reading(): string
    {
        return $this->renderSection('reading');
    }

    public function comments(): string
    {
        return $this->renderSection('comment');
    }

    public function permalinks(): string
    {
        return $this->renderSection('permalink');
    }

    private function renderSection(string $section): string
    {
        $this->ensureSiteSettings();
        ReadingSettingsService::ensureDefaults();
        CommentSettingsService::ensureDefaults();
        PermalinkService::ensureDefaults();

        $grouped = Setting::grouped();
        unset($grouped['ai'], $grouped['analytics'], $grouped['feature'], $grouped['mail']);
        $allowed = match ($section) {
            'reading' => ['reading'],
            'comment' => ['comment'],
            'permalink' => ['permalink'],
            default => ['basic', 'link', 'media', 'security'],
        };
        $grouped = array_filter(
            $grouped,
            static fn(string $group): bool => in_array($group, $allowed, true),
            ARRAY_FILTER_USE_KEY
        );

        return View::render('setting.index', [
            'grouped' => $grouped,
            'favicon' => FaviconService::status(),
            'csrf'    => Session::csrfToken(),
            'section' => $section,
            'showFavicon' => $section === 'basic',
            'permalinkConflicts' => $section === 'permalink' ? PermalinkService::pageSlugConflicts() : [],
            'pageTitle' => '系统设置',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $data = (array) $request->input('settings', []);
        foreach ($data as $k => $v) {
            if (in_array($k, ['theme', 'site_theme'], true)) {
                continue;
            }
            Setting::set($k, $this->sanitizeSetting((string)$k, $v));
        }
        $section = (string) $request->input('section', 'basic');
        if ($section === 'permalink') {
            $conflicts = PermalinkService::pageSlugConflicts(5);
            if ($conflicts !== []) {
                $slugs = implode('、', array_map(static fn(array $row): string => (string)$row['slug'], $conflicts));
                Session::flash('warning', '简短模式下页面地址优先，以下文章 slug 会被页面覆盖：' . $slugs);
            }
        }

        // 刷新共享 view 和 config
        $all = Setting::allAsArray();
        foreach ($all as $k => $v) {
            \App\Core\View::share($k, $v);
            \App\Core\Config::set("site.{$k}", $v);
        }
        // 整个 site 数组要重新 share（因为 bootstrap 时已经 share 过旧的）
        \App\Core\View::share('site', \App\Core\Config::get('site'));
        Session::flash('success', '设置已保存');
        Response::redirect(match ($section) {
            'reading' => '/admin/settings/reading',
            'comment' => '/admin/settings/comments',
            'permalink' => '/admin/settings/permalinks',
            default => '/admin/settings',
        });
    }

    public function uploadFavicon(Request $request): never
    {
        if (empty($_FILES['favicon'])) {
            Response::json(['code' => 1, 'msg' => '没有选择图标文件']);
        }

        try {
            $status = FaviconService::upload($_FILES['favicon']);
            Response::json(['code' => 0, 'msg' => '图标已生成并部署', 'data' => $status]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    private function ensureSiteSettings(): void
    {
        Setting::ensureDefaults([
            [
                'k' => 'site_avatar_url',
                'v' => '',
                'type' => 'string',
                'label' => '站点头像地址',
                'group_name' => 'basic',
                'sort' => 8,
            ],
            [
                'k' => 'site_theme',
                'v' => 'ember',
                'type' => 'string',
                'label' => '前台主题',
                'group_name' => 'basic',
                'sort' => 9,
            ],
        ]);
    }

    private function sanitizeSetting(string $key, mixed $value): mixed
    {
        $value = is_array($value) ? '' : trim((string)$value);

        return match ($key) {
            'home_feed_mode' => in_array($value, ['mixed', 'posts', 'talks'], true) ? $value : 'mixed',
            'home_total_limit' => (string)max(1, min(60, (int)$value)),
            'home_post_limit', 'home_talk_limit' => (string)max(0, min(60, (int)$value)),
            'post_list_per_page' => (string)max(1, min(50, (int)$value)),
            'comment_close_old_days' => (string)max(0, min(3650, (int)$value)),
            'permalink_mode' => in_array($value, ['default', 'simple', 'category', 'numeric'], true) ? $value : 'default',
            'permalink_numeric_source' => in_array($value, ['id', 'six'], true) ? $value : 'six',
            'permalink_numeric_suffix' => in_array($value, ['', '.html'], true) ? $value : '.html',
            'permalink_numeric_prefix' => $this->sanitizePermalinkPrefix($value),
            default => $value,
        };
    }

    private function sanitizePermalinkPrefix(string $value): string
    {
        $value = trim($value, '/');
        if ($value === '') {
            return '';
        }
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) ? $value : 'post';
    }

}
