<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Helper;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Services\CommentSettingsService;
use App\Services\FaviconService;
use App\Services\ImageUploadService;
use App\Services\PermalinkService;
use App\Services\ReadingSettingsService;

class SettingController
{
    public function index(): string
    {
        return $this->renderSection('basic');
    }

    public function comments(): never
    {
        Response::redirect('/admin/comments');
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

    public function uploadSiteLogo(Request $request): never
    {
        if (empty($_FILES['logo'])) {
            Response::json(['code' => 1, 'msg' => '没有选择 Logo 文件']);
        }

        try {
            $data = ImageUploadService::upload($_FILES['logo'], 'site-logo');
            Response::json(['code' => 0, 'msg' => 'Logo 已上传', 'data' => $data]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => Helper::publicErrorMessage($e)]);
        }
    }

    public function save(Request $request): never
    {
        $data = (array) $request->input('settings', []);
        foreach ($data as $k => $v) {
            if (Setting::isHiddenKey((string)$k)) {
                continue;
            }
            Setting::set($k, $this->sanitizeSetting((string)$k, $v));
        }
        $section = (string) $request->input('section', 'basic');
        if ($section === 'permalink') {
            $conflicts = PermalinkService::pageSlugConflicts(5);
            if ($conflicts !== []) {
                $slugs = implode('、', array_map(static fn(array $row): string => (string)$row['slug'], $conflicts));
                Session::flash('warning', '当前固定链接规则会与页面地址冲突，页面地址优先：' . $slugs);
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
            'comment' => '/admin/comments',
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
            Response::json(['code' => 1, 'msg' => Helper::publicErrorMessage($e)]);
        }
    }

    private function ensureSiteSettings(): void
    {
        Setting::ensureDefaults([
            [
                'k' => 'site_avatar_url',
                'v' => '',
                'type' => 'string',
                'label' => '站点 Logo',
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
            [
                'k' => 'site_analytics_code',
                'v' => '',
                'type' => 'textarea',
                'label' => '统计代码',
                'group_name' => 'basic',
                'sort' => 10,
            ],
            [
                'k' => 'site_mapbox_token',
                'v' => '',
                'type' => 'string',
                'label' => 'Mapbox 公开 Token',
                'group_name' => 'basic',
                'sort' => 11,
            ],
        ]);
        $siteAvatar = Setting::findBy('k', 'site_avatar_url');
        if ($siteAvatar && (string)$siteAvatar->label !== '站点 Logo') {
            $siteAvatar->label = '站点 Logo';
            $siteAvatar->save();
        }
    }

    private function sanitizeSetting(string $key, mixed $value): mixed
    {
        $value = is_array($value) ? '' : trim((string)$value);

        return match ($key) {
            'permalink_base' => PermalinkService::sanitizeBase($value),
            'permalink_base_custom' => PermalinkService::sanitizeSegment($value, 'blog'),
            'permalink_pattern' => PermalinkService::sanitizePattern($value),
            'permalink_suffix_mode' => in_array($value, ['', '.html', '.htm', 'custom'], true) ? $value : '.html',
            'permalink_suffix_custom' => PermalinkService::sanitizeSuffix($value),
            'site_avatar_url' => $this->absoluteUploadUrl($value),
            default => $value,
        };
    }

    private function absoluteUploadUrl(string $value): string
    {
        if ($value === '' || preg_match('#^https?://#i', $value)) {
            return $value;
        }
        return Helper::url('/' . ltrim($value, '/'));
    }

}
