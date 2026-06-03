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

class AttachmentController
{
    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 24;
        $type = $_GET['type'] ?? null;
        $result = Attachment::paginateByType($page, $perPage, $type);

        return View::render('attachment.index', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page'  => $page,
            'perPage' => $perPage,
            'type'  => $type,
            'paginator' => Helper::paginate($page, $result['total'], $perPage, '/admin/attachments'),
            'csrf'  => Session::csrfToken(),
            'pageTitle' => '附件管理',
        ], 'layouts.admin');
    }

    public function upload(Request $request): never
    {
        if (empty($_FILES['file'])) {
            Response::json(['code' => 1, 'msg' => '没有文件']);
        }
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['code' => 1, 'msg' => '上传错误: ' . $file['error']]);
        }
        if ($file['size'] > Config::get('upload.max_size')) {
            Response::json(['code' => 1, 'msg' => '文件太大']);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = Config::get('upload.allowed_ext', []);
        if (!in_array($ext, $allowed, true)) {
            Response::json(['code' => 1, 'msg' => '不允许的文件类型: ' . $ext]);
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
                'url'  => $relUrl,
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file',
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
}
