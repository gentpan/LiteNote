<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Toggle;

/**
 * Talk 模型（改进版）
 * 变更点：
 * 1. 增加 music 字段支持
 * 2. 增加 getMusicEmbed() 解析网易云/QQ音乐/通用链接
 * 3. 默认查询条件改用 Toggle enum
 */
final class Talk extends Model
{
    protected static string $table = 'talk';
    protected static array $sortable = ['id', 'created_at', 'likes_count', 'comments_count'];

    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'id DESC', ?string $whereSql = null, array $params = []): array
    {
        // 如果未指定 where，默认只查公开的
        if ($whereSql === null) {
            $whereSql = 'is_public = ' . Toggle::On->value;
        }
        return parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
    }

    public function getImages(): array
    {
        if (empty($this->images)) return [];
        return array_filter(explode(',', $this->images));
    }

    public function getKeywords(): array
    {
        $content = (string)($this->content ?? '');
        preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches);

        $keywords = array_values(array_unique(array_filter(array_map('trim', $matches[1] ?? []))));
        if (!empty($keywords)) {
            return $keywords;
        }

        $mood = trim((string)($this->mood ?? ''));
        return $mood !== '' ? [$mood] : ['日常'];
    }

    public function contentWithoutKeywords(): string
    {
        $content = (string)($this->content ?? '');
        $content = preg_replace('/[ \t]*#[\p{L}\p{N}_-]+/u', '', $content) ?? $content;
        return trim($content);
    }

    public static function recentPublic(int $limit = 10): array
    {
        $rows = self::db()->fetchAll(
            "SELECT * FROM talk WHERE is_public = ? ORDER BY created_at DESC, id DESC LIMIT {$limit}",
            [Toggle::On->value]
        );
        return array_map(fn($row) => new self($row), $rows);
    }

    public static function like(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        self::db()->query('UPDATE talk SET likes_count = COALESCE(likes_count, 0) + 1 WHERE id = ?', [$id]);
        return (int) self::db()->fetchColumn('SELECT COALESCE(likes_count, 0) FROM talk WHERE id = ?', [$id]);
    }

    /**
     * 解析音乐链接，返回可嵌入的 HTML 或元数据数组。
     * 支持：网易云音乐、QQ音乐、通用音频链接。
     */
    public function getMusicEmbed(): ?array
    {
        if (empty($this->music)) {
            return null;
        }
        $url = trim((string)$this->music);

        // 网易云音乐
        // 支持格式：
        // https://music.163.com/song?id=123456
        // https://music.163.com/#/song?id=123456
        // https://music.163.com/outchain/player?type=2&id=123456&auto=0
        if (str_contains($url, 'music.163.com')) {
            if (preg_match('/[?&]id=(\d+)/', $url, $m)) {
                $id = $m[1];
                return [
                    'type' => 'netease',
                    'id'   => $id,
                    'html' => '<iframe frameborder="no" border="0" marginwidth="0" marginheight="0" width="100%" height="86" src="https://music.163.com/outchain/player?type=2&id=' . $id . '&auto=0&height=66"></iframe>',
                ];
            }
        }

        // QQ音乐
        // https://y.qq.com/n/ryqq/songDetail/001xxxx
        if (str_contains($url, 'y.qq.com')) {
            if (preg_match('/songDetail\/(\w+)/', $url, $m)) {
                $mid = $m[1];
                return [
                    'type' => 'qq',
                    'id'   => $mid,
                    'html' => '<iframe frameborder="0" border="0" marginwidth="0" marginheight="0" width="100%" height="86" src="https://y.qq.com/n/ryqq/player?mid=' . $mid . '"></iframe>',
                ];
            }
        }

        // 通用音频直链（mp3/m4a/wav/ogg/flac/aac）→ 自定义卡片播放器(封面+标题+进度)
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? $url);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3', 'm4a', 'wav', 'ogg', 'flac', 'aac'], true)) {
            $cover  = trim((string)($this->music_cover ?? ''));
            $title  = trim((string)($this->music_title ?? '')) ?: (pathinfo($path, PATHINFO_FILENAME) ?: '未命名音频');
            $artist = trim((string)($this->music_artist ?? ''));

            $urlE    = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $titleE  = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $artistE = htmlspecialchars($artist, ENT_QUOTES, 'UTF-8');
            $coverClass = $cover !== '' ? 'music-card-cover has-cover' : 'music-card-cover';
            $coverStyle = $cover !== '' ? ' style="background-image:url(\'' . htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') . '\')"' : '';

            $html = '<div class="music-card" data-audio="' . $urlE . '">'
                . '<div class="' . $coverClass . '"' . $coverStyle . '>'
                . '<button type="button" class="music-card-btn" aria-label="播放/暂停"><i class="fa-solid fa-play"></i></button>'
                . '</div>'
                . '<div class="music-card-info">'
                . '<div class="music-card-title">' . $titleE . '</div>'
                . ($artist !== '' ? '<div class="music-card-artist">' . $artistE . '</div>' : '')
                . '<div class="music-card-progress">'
                . '<span class="music-card-cur">0:00</span>'
                . '<div class="music-card-track"><div class="music-card-played"></div></div>'
                . '<span class="music-card-dur">0:00</span>'
                . '</div>'
                . '</div>'
                . '<audio preload="none" src="' . $urlE . '"></audio>'
                . '</div>';

            return ['type' => 'card', 'url' => $url, 'html' => $html];
        }

        // 未知格式，作为外链展示
        return [
            'type' => 'link',
            'url'  => $url,
            'html' => '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener" class="music-link"><i class="fa-solid fa-music"></i> 收听音乐</a>',
        ];
    }
}
