@section('content')
<div class="settings-page-shell resource-page-shell">
    <div class="resource-page-head">
        <div>
            <strong>{{ (int)$total }}</strong>
            <span>条 X 推文</span>
        </div>
    </div>

    <form method="post" action="/admin/x/tweets/store" class="comment-form">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <input type="text" name="tweet_url" placeholder="粘贴 X(Twitter)链接，抓取后作为推文卡片发布到首页" required>
            <button type="submit" class="btn btn-primary">抓取并发布</button>
        </div>
    </form>

    @if(empty($list))
        <div class="admin-empty-state">还没有推文。粘贴一条 X 链接试试。</div>
    @else
        <table class="admin-table">
            <thead>
                <tr><th>作者</th><th>内容</th><th>时间</th><th>互动</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($list as $t)
                    <tr>
                        <td>{{ $t->tweet_author_name ?: ('@' . $t->tweetHandle()) }}</td>
                        <td>{{ mb_strimwidth((string)($t->content ?? ''), 0, 60, '…', 'UTF-8') }}</td>
                        <td>{{ \App\Core\Helper::formatDate($t->publishedAt(), 'Y-m-d H:i') }}</td>
                        <td>♥ {{ (int)($t->likes_count ?? 0) }} · 💬 {{ (int)($t->comments_count ?? 0) }}</td>
                        <td>
                            <form method="post" action="/admin/x/tweets/delete" onsubmit="return confirm('确定删除这条推文及其评论?');">
                                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                <input type="hidden" name="id" value="{{ $t->id }}">
                                <button type="submit" class="btn btn-danger">删除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {!! $paginator ?? '' !!}
</div>
@endsection
