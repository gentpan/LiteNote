<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Toggle;

/**
 * Shuoshuo 模型（改进版）
 * 变更点：
 * 1. 增加 music 字段支持
 * 2. 增加 getMusicEmbed() 解析网易云/QQ音乐/通用链接
 * 3. 默认查询条件改用 Toggle enum
 */
final class Shuoshuo extends Model
{
    protected static string $table = 'shuoshuo';

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

        // 通用音频链接（mp3/m4a/wav/ogg）
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3', 'm4a', 'wav', 'ogg', 'flac'], true)) {
            return [
                'type' => 'audio',
                'url'  => $url,
                'html' => '<audio controls preload="none" style="width:100%;height:40px;"><source src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></audio>',
            ];
        }

        // 未知格式，作为外链展示
        return [
            'type' => 'link',
            'url'  => $url,
            'html' => '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener" class="music-link"><i class="fa-solid fa-music"></i> 收听音乐</a>',
        ];
    }
}
