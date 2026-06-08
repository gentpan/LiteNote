<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Comment;
use App\Models\Talk;
use App\Services\ImageUploadService;
use App\Services\WeatherService;

class TalkController
{
    public function index(): string
    {
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $whereSql = "is_public = ? AND COALESCE(music_id, 0) = 0 AND (COALESCE(post_type, '') = '' OR post_type = 'talk')";
        ['items' => $list, 'total' => $total] = Talk::paginate(
            $page,
            $perPage,
            'published_at DESC, created_at DESC, id DESC',
            $whereSql,
            [Toggle::On->value]
        );
        $this->attachTalkComments($list);

        return View::render('front.talk.index', [
            'list' => $list,
            'total' => $total,
            'page'  => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/talk')),
            'pageTitle' => '滔客',
            'activeNav' => 'talk',
        ]);
    }

    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Talk::find((int)$id);
        if (!$item || (int)$item->is_public !== 1) {
            Response::json(['code' => 1, 'msg' => '滔客不存在'], 404);
        }

        $liked = Session::get('liked_talk', []);
        $liked = is_array($liked) ? $liked : [];
        if (!empty($liked[$id])) {
            Response::json([
                'code' => 2,
                'msg' => '已经点赞过了',
                'likes' => (int)($item->likes_count ?? 0),
                'liked' => true,
            ]);
        }

        $count = Talk::like((int)$id);
        $liked[$id] = 1;
        Session::set('liked_talk', $liked);
        Response::json(['code' => 0, 'likes' => $count, 'liked' => true]);
    }

    public function publish(Request $request): never
    {
        $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (!Session::has('admin_user.id')) {
            if ($isAjax) {
                Response::json(['code' => 401, 'msg' => '请先登录后台'], 401);
            }
            Response::redirect('/admin/login');
        }

        if (!Session::verifyCsrf((string)$request->input('_csrf', ''))) {
            if ($isAjax) {
                Response::json(['code' => 419, 'msg' => '会话已过期，请刷新页面后重试'], 419);
            }
            Session::flash('talk_publish_error', '会话已过期，请刷新页面后重试');
            $this->back();
        }

        $content = trim((string)$request->input('content', ''));
        $images = trim((string)$request->input('images', ''));
        $mood = trim((string)$request->input('mood', ''));
        $public = Toggle::fromInput($request->input('is_public', 0))->value;
        $location = $this->locationPayload($request);
        $weather = $this->weatherPayload($request);

        if ($content === '') {
            if ($isAjax) {
                Response::json(['code' => 1, 'msg' => '滔客内容不能为空']);
            }
            Session::flash('talk_publish_error', '滔客内容不能为空');
            $this->back();
        }

        Talk::ensureLocationSchema();
        $item = new Talk([
            'content' => $content,
            'images' => $images,
            'mood' => $mood,
            'music_id' => 0,
            'is_public' => $public,
            'post_type' => 'talk',
            'location_name' => $location['name'],
            'location_city' => $location['city'],
            'location_lat' => $location['lat'],
            'location_lng' => $location['lng'],
            'location_provider' => $location['provider'],
            'location_data' => $location['data'],
            'weather_label' => $weather['label'],
            'weather_icon' => $weather['icon'],
            'weather_temp' => $weather['temperature'],
            'weather_code' => $weather['code'],
            'weather_data' => $weather['data'],
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $item->save();

        if ($isAjax) {
            $item->setRelation('comments', []);
            $s = $item;
            $html = View::render('partials.talk-card', ['s' => $s]);
            Response::json([
                'code' => 0,
                'msg' => '滔客已发布',
                'html' => $html,
                'id' => (int)$item->id,
            ]);
        }

        Session::flash('talk_publish_success', '滔客已发布');
        Response::redirect('/talk#talk-' . $item->id);
    }

    public function uploadImage(Request $request): never
    {
        if (!Session::has('admin_user.id')) {
            Response::json(['code' => 401, 'msg' => '请先登录后台'], 401);
        }
        if (!Session::verifyCsrf((string)$request->input('_csrf', ''))) {
            Response::json(['code' => 419, 'msg' => '会话已过期，请刷新页面后重试'], 419);
        }

        $file = $request->files['image'] ?? null;
        if (!is_array($file)) {
            Response::json(['code' => 1, 'msg' => ImageUploadService::missingUploadMessage('图片')]);
        }

        try {
            $data = ImageUploadService::upload($file, 'talk');
            Response::json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function weather(Request $request): never
    {
        if (!Session::has('admin_user.id')) {
            Response::json(['code' => 401, 'msg' => '请先登录后台'], 401);
        }

        $lat = trim((string)$request->input('lat', ''));
        $lng = trim((string)$request->input('lng', ''));
        $place = mb_substr(trim((string)$request->input('place', '')), 0, 80);
        $service = new WeatherService();
        $data = [];

        if (is_numeric($lat) && is_numeric($lng)) {
            $data = $service->currentByCoordinates((float)$lat, (float)$lng, $place);
        } else {
            $data = $service->currentByIp($request->ip);
        }

        if ($data === []) {
            Response::json(['code' => 1, 'msg' => '暂时无法获取天气，请先选择位置或稍后重试']);
        }

        Response::json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
    }

    private function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/talk';
        Response::redirect($ref);
    }

    /**
     * @return array{name:string, city:string, lat:string, lng:string, provider:string, data:string}
     */
    private function locationPayload(Request $request): array
    {
        $name = mb_substr(trim((string)$request->input('location_name', '')), 0, 160);
        $city = mb_substr(trim((string)$request->input('location_city', '')), 0, 80);
        $lat = trim((string)$request->input('location_lat', ''));
        $lng = trim((string)$request->input('location_lng', ''));
        $provider = trim((string)$request->input('location_provider', ''));
        $data = trim((string)$request->input('location_data', ''));

        if ($name === '') {
            return ['name' => '', 'city' => '', 'lat' => '', 'lng' => '', 'provider' => '', 'data' => ''];
        }

        if ($city === '') {
            $city = $name;
        }
        if (!preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $lat)) {
            $lat = '';
        }
        if (!preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $lng)) {
            $lng = '';
        }
        if ($lat === '' || $lng === '') {
            return ['name' => '', 'city' => '', 'lat' => '', 'lng' => '', 'provider' => '', 'data' => ''];
        }
        if ($provider !== 'mapbox') {
            $provider = 'mapbox';
        }

        if ($data !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = json_encode([
                    'id' => mb_substr((string)($decoded['id'] ?? ''), 0, 120),
                    'name' => $name,
                    'city' => $city,
                    'full_name' => mb_substr(trim((string)($decoded['full_name'] ?? $decoded['place_name'] ?? '')), 0, 220),
                    'lat' => $lat,
                    'lng' => $lng,
                    'provider' => $provider,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            } else {
                $data = '';
            }
        }

        return [
            'name' => $name,
            'city' => $city,
            'lat' => $lat,
            'lng' => $lng,
            'provider' => $provider,
            'data' => $data,
        ];
    }

    /**
     * @return array{label:string, icon:string, temperature:string, code:int, data:string}
     */
    private function weatherPayload(Request $request): array
    {
        $label = mb_substr(trim((string)$request->input('weather_label', '')), 0, 40);
        $icon = trim((string)$request->input('weather_icon', ''));
        $temp = trim((string)$request->input('weather_temp', ''));
        $code = (int)$request->input('weather_code', 0);
        $data = trim((string)$request->input('weather_data', ''));

        if ($label === '' || !preg_match('/^[a-zA-Z0-9 _-]+$/', $icon)) {
            return ['label' => '', 'icon' => '', 'temperature' => '', 'code' => 0, 'data' => ''];
        }
        if ($temp !== '' && !preg_match('/^-?\d{1,2}(?:\.\d)?$/', $temp)) {
            $temp = '';
        }
        if ($data !== '') {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? json_encode([
                'label' => $label,
                'icon' => $icon,
                'temperature' => $temp,
                'code' => $code,
                'place' => mb_substr(trim((string)($decoded['place'] ?? '')), 0, 80),
                'source' => mb_substr(trim((string)($decoded['source'] ?? '')), 0, 40),
                'fetched_at' => mb_substr(trim((string)($decoded['fetched_at'] ?? '')), 0, 40),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '' : '';
        }

        return [
            'label' => $label,
            'icon' => $icon,
            'temperature' => $temp,
            'code' => $code,
            'data' => $data,
        ];
    }

    /**
     * @param Talk[] $list
     */
    private function attachTalkComments(array $list): void
    {
        foreach ($list as $item) {
            $item->setRelation('comments', Comment::forTalk((int)$item->id));
        }
    }

}
