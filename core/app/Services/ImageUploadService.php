<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Helper;
use App\Core\Session;
use App\Models\Attachment;
use App\Models\Setting;

final class ImageUploadService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const WEBP_QUALITY = 86;

    public static function isImageUpload(array $file): bool
    {
        $name = strtolower((string)($file['name'] ?? ''));
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            return true;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_file($tmp)) {
            $mime = self::detectMime($tmp);
            return in_array($mime, self::IMAGE_MIMES, true);
        }

        return false;
    }

    public static function missingUploadMessage(string $label = '文件'): string
    {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return '请选择' . $label;
        }

        $uploadLimit = ini_get('upload_max_filesize') ?: '';
        $postLimit = ini_get('post_max_size') ?: '';
        $limit = trim("upload_max_filesize={$uploadLimit}, post_max_size={$postLimit}", ', ');
        return $label . '超过服务器上传限制' . ($limit !== '' ? '（' . $limit . '）' : '');
    }

    /**
     * @return array{id:int|string|null,url:string,name:string,size:int,type:string,filename:string,mime:string}
     */
    public static function upload(array $file, string $purpose = 'image'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $maxSize = (int)Config::get('upload.max_size', 5 * 1024 * 1024);
        if ((int)($file['size'] ?? 0) > $maxSize) {
            throw new \RuntimeException('图片太大，最大允许 ' . Helper::bytesToHuman($maxSize));
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new \RuntimeException('上传临时文件不存在');
        }

        $mime = self::detectMime($tmp);
        if (!in_array($mime, self::IMAGE_MIMES, true)) {
            throw new \RuntimeException('只允许上传 JPG、PNG、GIF、WebP 图片');
        }

        $uploadDir = (string)Config::get('upload.path');
        $sub = date('Y/m');
        $subDir = $uploadDir . '/' . $sub;
        if (!is_dir($subDir) && !mkdir($subDir, 0775, true) && !is_dir($subDir)) {
            throw new \RuntimeException('上传目录创建失败');
        }

        $base = pathinfo(Helper::safeFilename((string)($file['name'] ?? 'image')), PATHINFO_FILENAME);
        $base = trim((string)$base, '.-_');
        if ($base === '') {
            $base = $purpose;
        }

        $convertToWebp = (string)Setting::get('attachment_image_webp_enabled', '1') === '1';
        if (!$convertToWebp) {
            return self::saveOriginalImage($file, $tmp, $mime, $subDir, $sub, $base);
        }

        $filename = $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.webp';
        $absPath = $subDir . '/' . $filename;

        self::convertToWebp($tmp, $mime, $absPath);

        $relUrl = rtrim((string)Config::get('upload.url'), '/') . '/' . $sub . '/' . $filename;
        $filesize = is_file($absPath) ? (int)filesize($absPath) : 0;

        $att = new Attachment([
            'filename'      => $filename,
            'original_name' => (string)($file['name'] ?? $filename),
            'filepath'      => $absPath,
            'fileurl'       => $relUrl,
            'filetype'      => 'webp',
            'filesize'      => $filesize,
            'mime_type'     => 'image/webp',
            'user_id'       => Session::get('admin_user.id', 1),
        ]);
        $att->save();

        return [
            'id'       => $att->id ?? null,
            'url'      => Helper::url($relUrl),
            'relative_url' => $relUrl,
            'fileurl'  => $relUrl,
            'name'     => (string)($file['name'] ?? $filename),
            'size'     => $filesize,
            'type'     => 'image',
            'filename' => $filename,
            'mime'     => 'image/webp',
        ];
    }

    private static function saveOriginalImage(array $file, string $tmp, string $mime, string $subDir, string $sub, string $base): array
    {
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) ?: 'jpg',
        };
        $filename = $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absPath = $subDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $absPath)) {
            if (!copy($tmp, $absPath)) {
                throw new \RuntimeException('图片保存失败');
            }
        }

        $relUrl = rtrim((string)Config::get('upload.url'), '/') . '/' . $sub . '/' . $filename;
        $filesize = is_file($absPath) ? (int)filesize($absPath) : 0;
        $att = new Attachment([
            'filename' => $filename,
            'original_name' => (string)($file['name'] ?? $filename),
            'filepath' => $absPath,
            'fileurl' => $relUrl,
            'filetype' => $ext,
            'filesize' => $filesize,
            'mime_type' => $mime,
            'user_id' => Session::get('admin_user.id', 1),
        ]);
        $att->save();

        return [
            'id' => $att->id ?? null,
            'url' => Helper::url($relUrl),
            'relative_url' => $relUrl,
            'fileurl' => $relUrl,
            'name' => (string)($file['name'] ?? $filename),
            'size' => $filesize,
            'type' => 'image',
            'filename' => $filename,
            'mime' => $mime,
        ];
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return mime_content_type($path) ?: '';
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => self::missingUploadMessage('图片'),
            UPLOAD_ERR_PARTIAL => '图片只上传了一部分，请重试',
            UPLOAD_ERR_NO_FILE => '请选择图片',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少上传临时目录',
            UPLOAD_ERR_CANT_WRITE => '服务器无法写入上传文件',
            UPLOAD_ERR_EXTENSION => '上传被 PHP 扩展拦截',
            default => '上传错误: ' . $error,
        };
    }

    private static function convertToWebp(string $source, string $mime, string $target): void
    {
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick($source);
                if ($image->getNumberImages() > 1) {
                    $image = $image->coalesceImages();
                    $image->setIteratorIndex(0);
                }
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(self::WEBP_QUALITY);
                $image->stripImage();
                if (!$image->writeImage($target)) {
                    throw new \RuntimeException('WebP 转换失败');
                }
                $image->clear();
                return;
            } catch (\Throwable) {
                // 继续尝试 GD/cwebp，避免单一图片库兼容性导致上传失败。
            }
        }

        if (function_exists('imagewebp')) {
            $resource = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($source),
                'image/png'  => imagecreatefrompng($source),
                'image/gif'  => imagecreatefromgif($source),
                'image/webp' => imagecreatefromwebp($source),
                default      => false,
            };
            if (!$resource) {
                throw new \RuntimeException('图片读取失败');
            }
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($resource);
            }
            imagealphablending($resource, true);
            imagesavealpha($resource, true);
            $ok = imagewebp($resource, $target, self::WEBP_QUALITY);
            if (!$ok) {
                throw new \RuntimeException('WebP 转换失败');
            }
            return;
        }

        $cwebp = trim((string)shell_exec('command -v cwebp 2>/dev/null'));
        if ($cwebp !== '') {
            $cmd = escapeshellcmd($cwebp) . ' -quiet -q ' . self::WEBP_QUALITY . ' '
                . escapeshellarg($source) . ' -o ' . escapeshellarg($target);
            exec($cmd, $output, $code);
            if ($code === 0 && is_file($target)) {
                return;
            }
        }

        if ($mime === 'image/webp' && copy($source, $target)) {
            return;
        }

        throw new \RuntimeException('服务器缺少 WebP 转换组件');
    }
}
