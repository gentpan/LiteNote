<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Attachment;
use App\Models\Setting;
use App\Services\BackupService;
use App\Services\ImageUploadService;
use App\Services\S3StorageService;

class AttachmentController
{
    private const SETTING_KEYS = [
        'attachment_cdn_enabled',
        'attachment_cdn_url',
        'attachment_image_webp_enabled',
        'attachment_s3_enabled',
        'attachment_s3_endpoint',
        'attachment_s3_bucket',
        'attachment_s3_region',
        'attachment_s3_access_key',
        'attachment_s3_secret_key',
        'attachment_s3_prefix',
        'attachment_s3_delete_remote',
        'attachment_backup_enabled',
        'attachment_backup_s3_enabled',
        'attachment_backup_time',
        'attachment_backup_retention_days',
        'attachment_backup_keep_versions',
        'attachment_backup_last_run_date',
        'attachment_backup_last_status',
    ];

    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;
        $type = trim((string)($_GET['type'] ?? ''));
        $type = $type !== '' ? $type : null;
        Attachment::syncLocalUploadFiles();
        $result = Attachment::paginateByType($page, $perPage, $type);
        $baseUrl = '/admin/attachments' . ($type ? '?type=' . rawurlencode($type) : '');

        return View::render('attachment.index', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page'  => $page,
            'perPage' => $perPage,
            'type'  => $type,
            'categoryOptions' => Attachment::categoryOptions(),
            'categoryCounts' => Attachment::categoryCounts(),
            'attachmentSettings' => $this->attachmentSettings(),
            'paginator' => Helper::paginate($page, $result['total'], $perPage, $baseUrl),
            'csrf'  => Session::csrfToken(),
            'pageTitle' => '附件管理',
        ], 'layouts.admin');
    }

    public function settings(): string
    {
        return View::render('attachment.settings', [
            'attachmentSettings' => $this->attachmentSettings(),
            'csrf'  => Session::csrfToken(),
            'pageTitle' => '附件设置',
        ], 'layouts.admin');
    }

    public function saveSettings(Request $request): never
    {
        $values = [
            'attachment_cdn_enabled' => $request->input('attachment_cdn_enabled', '0') === '1' ? '1' : '0',
            'attachment_cdn_url' => $this->cleanUrl((string)$request->input('attachment_cdn_url', '')),
            'attachment_image_webp_enabled' => $request->input('attachment_image_webp_enabled', '0') === '1' ? '1' : '0',
            'attachment_s3_enabled' => $request->input('attachment_s3_enabled', '0') === '1' ? '1' : '0',
            'attachment_s3_endpoint' => $this->cleanUrl((string)$request->input('attachment_s3_endpoint', '')),
            'attachment_s3_bucket' => trim((string)$request->input('attachment_s3_bucket', '')),
            'attachment_s3_region' => trim((string)$request->input('attachment_s3_region', 'auto')),
            'attachment_s3_access_key' => trim((string)$request->input('attachment_s3_access_key', '')),
            'attachment_s3_secret_key' => trim((string)$request->input('attachment_s3_secret_key', '')),
            'attachment_s3_prefix' => trim((string)$request->input('attachment_s3_prefix', ''), "/ \t\n\r\0\x0B"),
            'attachment_s3_delete_remote' => '1',
            'attachment_backup_enabled' => $request->input('attachment_backup_enabled', '0') === '1' ? '1' : '0',
            'attachment_backup_s3_enabled' => $request->input('attachment_backup_s3_enabled', '0') === '1' ? '1' : '0',
            'attachment_backup_time' => $this->cleanBackupTime((string)$request->input('attachment_backup_time', '00:00')),
            'attachment_backup_retention_days' => (string)max(1, min(365, (int)$request->input('attachment_backup_retention_days', '15'))),
            'attachment_backup_keep_versions' => (string)max(1, min(200, (int)$request->input('attachment_backup_keep_versions', '10'))),
        ];

        Setting::setMany($values);
        Session::flash('success', '附件设置已保存');
        $redirect = (string)$request->input('redirect', '/admin/settings/attachments');
        Response::redirect($redirect === '/admin/attachments' ? $redirect : '/admin/settings/attachments');
    }

    private function attachmentSettings(): array
    {
        $defaults = [
            'attachment_cdn_enabled' => '0',
            'attachment_cdn_url' => '',
            'attachment_image_webp_enabled' => '1',
            'attachment_s3_enabled' => '0',
            'attachment_s3_endpoint' => '',
            'attachment_s3_bucket' => '',
            'attachment_s3_region' => 'auto',
            'attachment_s3_access_key' => '',
            'attachment_s3_secret_key' => '',
            'attachment_s3_prefix' => '',
            'attachment_s3_delete_remote' => '1',
        ];
        $defaults = array_merge($defaults, BackupService::defaults());

        foreach (self::SETTING_KEYS as $key) {
            $defaults[$key] = (string)Setting::get($key, $defaults[$key] ?? '');
        }

        return $defaults;
    }

    public function testS3(Request $request): never
    {
        try {
            $result = (new S3StorageService($this->s3ConfigFromRequest($request)))->testConnection();
            Response::json(['code' => 0, 'msg' => $result['message'], 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function clearS3(Request $request): never
    {
        try {
            $result = (new S3StorageService($this->s3ConfigFromRequest($request)))->clearPrefix();
            $msg = '已删除 ' . (int)$result['deleted'] . ' 个对象';
            if (!empty($result['truncated'])) {
                $msg .= '，达到本次上限，请再次执行';
            }
            Response::json(['code' => 0, 'msg' => $msg, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function s3ClearCommand(Request $request): never
    {
        try {
            $command = (new S3StorageService($this->s3ConfigFromRequest($request)))->clearCommand();
            Response::json(['code' => 0, 'msg' => '清空命令已生成', 'data' => ['command' => $command]]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function backupNow(Request $request): never
    {
        try {
            $settings = array_merge($this->attachmentSettings(), $this->s3ConfigFromRequest($request), $this->backupConfigFromRequest($request));
            $result = (new BackupService())->run($settings, true);
            Response::json(['code' => 0, 'msg' => $result['message'], 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    private function cleanUrl(string $value): string
    {
        return rtrim(trim($value), '/');
    }

    private function s3ConfigFromRequest(Request $request): array
    {
        return [
            'attachment_s3_endpoint' => $this->cleanUrl((string)$request->input('attachment_s3_endpoint', '')),
            'attachment_s3_bucket' => trim((string)$request->input('attachment_s3_bucket', '')),
            'attachment_s3_region' => trim((string)$request->input('attachment_s3_region', 'auto')),
            'attachment_s3_access_key' => trim((string)$request->input('attachment_s3_access_key', '')),
            'attachment_s3_secret_key' => trim((string)$request->input('attachment_s3_secret_key', '')),
            'attachment_s3_prefix' => trim((string)$request->input('attachment_s3_prefix', ''), "/ \t\n\r\0\x0B"),
        ];
    }

    private function backupConfigFromRequest(Request $request): array
    {
        return [
            'attachment_backup_enabled' => $request->input('attachment_backup_enabled', '0') === '1' ? '1' : '0',
            'attachment_backup_s3_enabled' => $request->input('attachment_backup_s3_enabled', '0') === '1' ? '1' : '0',
            'attachment_backup_time' => $this->cleanBackupTime((string)$request->input('attachment_backup_time', '00:00')),
            'attachment_backup_retention_days' => (string)max(1, min(365, (int)$request->input('attachment_backup_retention_days', '15'))),
            'attachment_backup_keep_versions' => (string)max(1, min(200, (int)$request->input('attachment_backup_keep_versions', '10'))),
        ];
    }

    private function cleanBackupTime(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '00:00';
    }

    public function upload(Request $request): never
    {
        if (empty($_FILES['file'])) {
            Response::json(['code' => 1, 'msg' => ImageUploadService::missingUploadMessage('文件')]);
        }
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['code' => 1, 'msg' => '上传错误: ' . $file['error']]);
        }
        if ($file['size'] > Config::get('upload.max_size')) {
            Response::json(['code' => 1, 'msg' => '文件太大']);
        }

        if (ImageUploadService::isImageUpload($file)) {
            try {
                $data = ImageUploadService::upload($file, 'attachment');
                Response::json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
            } catch (\Throwable $e) {
                Response::json(['code' => 1, 'msg' => $e->getMessage()]);
            }
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = Config::get('upload.allowed_ext', []);
        if (!in_array($ext, $allowed, true)) {
            Response::json(['code' => 1, 'msg' => '不允许的文件类型: ' . $ext]);
        }

        $detectedMime = $this->detectMime($file['tmp_name']);
        $expectedMimes = $this->expectedMimesForExt($ext);
        if ($expectedMimes !== [] && !in_array($detectedMime, $expectedMimes, true)) {
            Response::json(['code' => 1, 'msg' => '文件 MIME 类型与扩展名不符: ' . $detectedMime]);
        }

        $uploadDir = Config::get('upload.path');
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $sub = date('Y/m');
        $subDir = $uploadDir . '/' . $sub;
        if (!is_dir($subDir)) mkdir($subDir, 0775, true);

        $safeName = Helper::safeFilename($file['name']);
        $absPath = $subDir . '/' . $safeName;
        // 防重名
        $i = 1;
        while (file_exists($absPath)) {
            $base = pathinfo($safeName, PATHINFO_FILENAME);
            $absPath = $subDir . '/' . $base . '_' . $i++ . '.' . $ext;
        }
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            Response::json(['code' => 1, 'msg' => '保存失败']);
        }

        $relUrl = Config::get('upload.url') . '/' . $sub . '/' . basename($absPath);
        $mime = mime_content_type($absPath) ?: $file['type'];

        $att = new Attachment([
            'filename'      => basename($absPath),
            'original_name' => $file['name'],
            'filepath'      => $absPath,
            'fileurl'       => $relUrl,
            'filetype'      => $ext,
            'filesize'      => (int) $file['size'],
            'mime_type'     => $mime,
            'user_id'       => Session::get('admin_user.id', 1),
        ]);
        $att->save();

        Response::json([
            'code' => 0,
            'msg'  => 'ok',
            'data' => [
                'id'   => $att->id,
                'url'  => Helper::url($relUrl),
                'relative_url' => $relUrl,
                'fileurl' => $relUrl,
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $att->categoryKey(),
            ],
        ]);
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        $att = Attachment::find($id);
        if ($att) {
            if (is_file($att->filepath)) {
                @unlink($att->filepath);
            }
            $att->delete();
        }
        Response::json(['code' => 0, 'msg' => '已删除']);
    }

    private function detectMime(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? strtolower(trim($mime)) : '';
    }

    /**
     * @return array<int,string>
     */
    private function expectedMimesForExt(string $ext): array
    {
        $map = [
            'mp3'  => ['audio/mpeg', 'audio/mp3'],
            'm4a'  => ['audio/mp4'],
            'wav'  => ['audio/wav', 'audio/x-wav'],
            'ogg'  => ['audio/ogg'],
            'flac' => ['audio/flac'],
            'aac'  => ['audio/aac'],
            'lrc'  => ['text/plain'],
            'mp4'  => ['video/mp4'],
            'webm' => ['video/webm'],
            'mov'  => ['video/quicktime'],
            'm4v'  => ['video/mp4'],
            'avi'  => ['video/x-msvideo'],
            'mkv'  => ['video/x-matroska'],
            'pdf'  => ['application/pdf'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            'txt'  => ['text/plain'],
            'md'   => ['text/plain'],
        ];
        return $map[$ext] ?? [];
    }
}
