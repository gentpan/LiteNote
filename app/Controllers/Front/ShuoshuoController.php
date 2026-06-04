<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Comment;
use App\Models\Shuoshuo;

class ShuoshuoController
{
    public function index(): string
    {
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $list, 'total' => $total] = Shuoshuo::paginate($page, $perPage);
        foreach ($list as $item) {
            $item->setRelation('comments', Comment::forShuoshuo((int)$item->id));
        }

        return View::render('front.shuoshuo.index', [
            'list' => $list,
            'total' => $total,
            'page'  => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, Helper::url('/shuoshuo')),
            'pageTitle' => '说说',
            'activeNav' => 'shuoshuo',
        ]);
    }

    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Shuoshuo::find((int)$id);
        if (!$item || (int)$item->is_public !== 1) {
            Response::json(['code' => 1, 'msg' => '说说不存在'], 404);
        }

        $count = Shuoshuo::like((int)$id);
        Response::json(['code' => 0, 'likes' => $count]);
    }
}
