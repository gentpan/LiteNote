<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Music;
use App\Models\Talk;
use App\Services\TweetFetchService;

class TalkController
{
    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $result = Talk::db()->fetchAll(
            "SELECT * FROM talk ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page-1)*$perPage)
        );
        $total = (int) Talk::db()->fetchColumn("SELECT COUNT(*) FROM talk");
        return View::render('talk.index', [
            'list' => array_map(fn($r) => new Talk($r), $result),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, '/admin/talk'),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '滔客管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        $type = $this->normalizePostType((string)($_GET['type'] ?? 'talk'));
        return View::render('talk.form', [
            'item' => null,
            'formType' => $type,
            'musicOptions' => Music::publicOptions(120),
            'csrf' => Session::csrfToken(),
            'pageTitle' => $this->formTitle($type),
        ], 'layouts.admin');
    }

    public function edit($request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $item = Talk::find($id);
        if (!$item) {
            Session::flash('error', '滔客不存在');
            Response::redirect('/admin/talk');
        }
        $type = $this->normalizePostType((string)($item->post_type ?? 'talk'));
        if ($item->isTweet()) {
            $type = 'tweet';
        } elseif ((int)($item->music_id ?? 0) > 0) {
            $type = 'music';
        }
        return View::render('talk.form', [
            'item' => $item,
            'formType' => $type,
            'musicOptions' => Music::publicOptions(120),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '编辑' . $this->typeLabel($type),
        ], 'layouts.admin');
    }

    public function store(Request $request): never
    {
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->save($request, (int)($params['id'] ?? 0));
    }

    private function save(Request $request, ?int $id): never
    {
        Talk::ensureTweetDataColumn();

        $postType = $this->normalizePostType((string)$request->input('post_type', 'talk'));
        $content = $postType === 'tweet' ? '' : trim((string) $request->input('content', ''));
        $images  = $postType === 'tweet' ? '' : trim((string) $request->input('images', ''));
        $mood    = $postType === 'tweet' ? 'X' : ($postType === 'music' ? '' : trim((string) $request->input('mood', '')));
        $musicId = $postType === 'music' ? $this->normalizeMusicId((int)$request->input('music_id', 0)) : 0;
        $public  = $postType === 'talk' ? Toggle::fromInput($request->input('is_public', 0))->value : Toggle::On->value;
        $tweetUrl = trim((string)$request->input('tweet_url', ''));
        $tweetData = [];
        $tweetId = Talk::extractTweetId($tweetUrl);
        $publishedAt = $postType === 'talk' ? trim((string)$request->input('published_at', '')) : '';
        $existingItem = $id ? Talk::find($id) : null;

        if ($postType === 'tweet' && $tweetUrl === '' && $tweetId !== '') {
            $tweetUrl = 'https://x.com/i/web/status/' . $tweetId;
        }

        if ($postType === 'tweet' && $tweetUrl === '' && $tweetId === '') {
            Session::flash('error', '请填写 X 链接');
            Response::redirect($id ? "/admin/talk/{$id}/edit" : '/admin/talk/create?type=tweet');
        }
        if ($postType === 'talk' && $content === '') {
            Session::flash('error', '内容不能为空');
            Response::redirect($id ? "/admin/talk/{$id}/edit" : '/admin/talk/create?type=talk');
        }
        if ($postType === 'music' && $musicId <= 0) {
            Session::flash('error', '请选择要分享的音乐');
            Response::redirect($id ? "/admin/talk/{$id}/edit" : '/admin/talk/create?type=music');
        }

        if ($postType === 'tweet') {
            try {
                $tweetData = (new TweetFetchService())->fetch($tweetUrl);
                $tweetId = (string)($tweetData['id'] ?? $tweetId);
                $tweetUrl = (string)($tweetData['url'] ?? $tweetUrl);
                $content = trim((string)($tweetData['text'] ?? ''));
            } catch (\Throwable $e) {
                Session::flash('error', 'X 链接获取失败：' . $e->getMessage());
                Response::redirect($id ? "/admin/talk/{$id}/edit" : '/admin/talk/create?type=tweet');
            }
            if ($content === '') {
                $content = 'X 原帖 ' . ($tweetUrl ?: $tweetId);
            }
            $images = implode(',', array_slice($tweetData['images'] ?? [], 0, 4));
            unset(
                $tweetData['likes_count'],
                $tweetData['reposts_count'],
                $tweetData['replies_count'],
                $tweetData['quotes_count'],
                $tweetData['views_count']
            );
        } elseif ($postType === 'music' && $content === '') {
            $content = '分享一首音乐';
        }
        if ($publishedAt === '' && $postType !== 'talk' && $existingItem) {
            $publishedAt = (string)($existingItem->published_at ?? $existingItem->created_at ?? '');
        }

        $fields = [
            'content' => $content,
            'images'  => $images,
            'mood'    => $mood,
            'music_id' => $musicId,
            'is_public' => $public,
            'post_type' => $postType,
            'tweet_id' => $postType === 'tweet' ? $tweetId : '',
            'tweet_url' => $postType === 'tweet' ? $tweetUrl : '',
            'tweet_author_name' => $postType === 'tweet' ? (string)($tweetData['author_name'] ?? '') : '',
            'tweet_author_handle' => $postType === 'tweet' ? (string)($tweetData['author_handle'] ?? '') : '',
            'tweet_author_avatar' => $postType === 'tweet' ? (string)($tweetData['author_avatar'] ?? '') : '',
            'tweet_author_verified' => $postType === 'tweet' && !empty($tweetData['author_verified']) ? 1 : 0,
            'tweet_posted_at' => $postType === 'tweet' ? ($tweetData['posted_at'] ?? null) : null,
            'tweet_likes_count' => 0,
            'tweet_reposts_count' => 0,
            'tweet_data' => $postType === 'tweet' ? json_encode($tweetData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'published_at' => $publishedAt !== '' ? $publishedAt : date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $item = $existingItem ?: Talk::find($id);
            if ($item) {
                $item->fill($fields);
                $item->save();
            }
        } else {
            $item = new Talk($fields);
            $item->save();
        }
        Session::flash('success', $id ? $this->typeLabel($postType) . '已更新' : $this->typeLabel($postType) . '已发布');
        Response::redirect('/admin/talk');
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            Talk::db()->delete('talk', 'id = ?', [$id]);
        }
        Session::flash('success', '滔客已删除');
        Response::redirect('/admin/talk');
    }

    private function normalizeMusicId(int $musicId): int
    {
        if ($musicId <= 0) {
            return 0;
        }
        $music = Music::find($musicId);
        if (!$music || (int)$music->is_public !== Toggle::On->value) {
            return 0;
        }
        return (int)$music->id;
    }

    private function normalizePostType(string $type): string
    {
        return in_array($type, ['talk', 'tweet', 'music'], true) ? $type : 'talk';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'tweet' => 'X 分享',
            'music' => '音乐分享',
            default => '滔客',
        };
    }

    private function formTitle(string $type): string
    {
        return match ($type) {
            'tweet' => '分享 X',
            'music' => '分享音乐',
            default => '写滔客',
        };
    }
}
