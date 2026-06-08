<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;

final class Attachment extends Model
{
    protected static string $table = 'attachments';

    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'id DESC', ?string $whereSql = null, array $params = []): array
    {
        // 兼容旧调用：如果 whereSql 是单个 filetype 值（如 'image'），转为条件
        // 但更好的做法是让调用方直接传 whereSql
        return parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
    }

    /**
     * 按文件类型分页查询（兼容旧接口）。
     */
    public static function paginateByType(int $page, int $perPage, ?string $type = null): array
    {
        $whereSql = null;
        $params = [];
        if ($type) {
            [$whereSql, $params] = self::whereForCategory($type);
        }
        return parent::paginate($page, $perPage, 'id DESC', $whereSql, $params);
    }

    public static function categoryOptions(): array
    {
        return [
            'image' => '图片',
            'video' => '视频',
            'audio' => '音乐',
            'lyrics' => '歌词',
            'x' => 'X 资源',
            'file' => '文件',
        ];
    }

    public static function categoryCounts(): array
    {
        $counts = [];
        foreach (self::categoryOptions() as $key => $_label) {
            [$where, $params] = self::whereForCategory($key);
            $counts[$key] = (int)self::db()->fetchColumn('SELECT COUNT(*) FROM attachments WHERE ' . $where, $params);
        }
        return $counts;
    }

    public static function syncLocalUploadFiles(int $limit = 500): int
    {
        $uploadDir = rtrim((string)Config::get('upload.path'), '/');
        $uploadUrl = rtrim((string)Config::get('upload.url'), '/');
        if ($uploadDir === '' || $uploadUrl === '' || !is_dir($uploadDir)) {
            return 0;
        }

        $allowed = array_map('strtolower', (array)Config::get('upload.allowed_ext', []));
        $indexed = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === '' || ($allowed !== [] && !in_array($ext, $allowed, true))) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($path, strlen($uploadDir))), '/');
            if ($relative === '') {
                continue;
            }
            $url = $uploadUrl . '/' . $relative;
            if (self::findBy('fileurl', $url)) {
                continue;
            }

            $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';
            $att = new self([
                'filename' => $file->getFilename(),
                'original_name' => $file->getFilename(),
                'filepath' => $path,
                'fileurl' => $url,
                'filetype' => $ext,
                'filesize' => (int)$file->getSize(),
                'mime_type' => $mime,
                'user_id' => 1,
            ]);
            $att->save();
            $indexed++;

            if ($indexed >= $limit) {
                break;
            }
        }

        return $indexed;
    }

    public function isImage(): bool
    {
        return in_array(strtolower((string)$this->filetype), ['jpg','jpeg','png','gif','webp'], true);
    }

    public function isVideo(): bool
    {
        $ext = strtolower((string)$this->filetype);
        $mime = strtolower((string)($this->mime_type ?? ''));
        return str_starts_with($mime, 'video/') || in_array($ext, ['mp4','webm','mov','m4v','avi','mkv'], true);
    }

    public function isAudio(): bool
    {
        $ext = strtolower((string)$this->filetype);
        $mime = strtolower((string)($this->mime_type ?? ''));
        return str_starts_with($mime, 'audio/') || in_array($ext, ['mp3','m4a','wav','ogg','flac','aac'], true);
    }

    public function isLyrics(): bool
    {
        $ext = strtolower((string)$this->filetype);
        $name = strtolower((string)($this->original_name ?? $this->filename ?? ''));
        return in_array($ext, ['lrc'], true) || str_ends_with($name, '.lrc');
    }

    public function isXResource(): bool
    {
        $url = (string)($this->fileurl ?? '');
        $path = (string)($this->filepath ?? '');
        return str_contains($url, '/uploads/x/') || str_contains($path, '/uploads/x/');
    }

    public function categoryKey(): string
    {
        if ($this->isXResource()) return 'x';
        if ($this->isImage()) return 'image';
        if ($this->isVideo()) return 'video';
        if ($this->isLyrics()) return 'lyrics';
        if ($this->isAudio()) return 'audio';
        return 'file';
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->categoryKey()] ?? '文件';
    }

    private static function whereForCategory(string $type): array
    {
        return match ($type) {
            'image' => ["LOWER(filetype) IN ('jpg','jpeg','png','gif','webp') AND fileurl NOT LIKE ?", ['%/uploads/x/%']],
            'video' => ["(LOWER(filetype) IN ('mp4','webm','mov','m4v','avi','mkv') OR LOWER(COALESCE(mime_type, '')) LIKE 'video/%')", []],
            'audio', 'music' => ["(LOWER(filetype) IN ('mp3','m4a','wav','ogg','flac','aac') OR LOWER(COALESCE(mime_type, '')) LIKE 'audio/%')", []],
            'lyrics' => ["LOWER(filetype) = 'lrc' OR LOWER(original_name) LIKE '%.lrc'", []],
            'x' => ["fileurl LIKE ? OR filepath LIKE ?", ['%/uploads/x/%', '%/uploads/x/%']],
            'file' => ["LOWER(filetype) NOT IN ('jpg','jpeg','png','gif','webp','mp4','webm','mov','m4v','avi','mkv','mp3','m4a','wav','ogg','flac','aac','lrc') AND LOWER(COALESCE(mime_type, '')) NOT LIKE 'video/%' AND LOWER(COALESCE(mime_type, '')) NOT LIKE 'audio/%'", []],
            default => ['filetype = ?', [$type]],
        };
    }
}
