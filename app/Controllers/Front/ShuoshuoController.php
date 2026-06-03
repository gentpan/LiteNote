<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Shuoshuo;

class ShuoshuoController
{
    public function index(): string
    {
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $list, 'total' => $total] = Shuoshuo::paginate($page, $perPage);

        return View::render('front.shuoshuo.index', [
            'list' => $list,
            'total' => $total,
            'page'  => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, Helper::url('/shuoshuo')),
            'pageTitle' => '说说',
        ]);
    }
}
