@section('content')
<div class="settings-page-shell resource-page-shell">
    <form method="post" action="/admin/x/tweets/store" class="x-tweet-capture-form x-tweet-capture-row">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <label class="x-tweet-url-field">
            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
            <input type="text" name="tweet_url" placeholder="粘贴 X 推文链接或 Tweet ID" required>
        </label>
        <button type="submit" class="btn btn-primary x-tweet-capture-submit">
            <i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>
            <span>抓取并发布</span>
        </button>
    </form>

    @if(empty($list))
        <div class="admin-empty-state">还没有推文。粘贴一条 X 链接试试。</div>
    @else
        <table class="admin-table x-admin-tweets-table"
               style="--x-col-author: 5%; --x-col-content: 70%; --x-col-time: 10%; --x-col-engage: 10%; --x-col-actions: 5%;">
            <colgroup>
                <col style="width: var(--x-col-author);">
                <col style="width: var(--x-col-content);">
                <col style="width: var(--x-col-time);">
                <col style="width: var(--x-col-engage);">
                <col style="width: var(--x-col-actions);">
            </colgroup>
            <thead>
                <tr><th>作者</th><th>内容</th><th>时间</th><th>互动</th><th>操作</th></tr>
            </thead>
            <tbody>
                @foreach($list as $t)
                    @php $tweetImages = $t->getImages(); @endphp
                    <tr>
                        <td>{{ $t->tweet_author_name ?: ('@' . $t->tweetHandle()) }}</td>
                        <td>
                            <div class="x-admin-tweet-content">
                                <span>{{ mb_strimwidth((string)($t->content ?? ''), 0, 150, '…', 'UTF-8') }}</span>
                                @if(!empty($tweetImages))
                                    <div class="x-admin-tweet-thumbs" aria-label="推文图片">
                                        @foreach(array_slice($tweetImages, 0, 4) as $img)
                                            <a href="{{ $img }}" target="_blank" rel="noopener noreferrer" class="x-admin-tweet-thumb" title="点击放大">
                                                <img src="{{ $img }}" alt="" loading="lazy" decoding="async">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td>{{ \App\Core\Helper::formatDate($t->publishedAt(), 'Y-m-d H:i') }}</td>
                        <td>
                            <span class="admin-inline-stat" title="点赞">
                                <i class="fa-regular fa-heart" aria-hidden="true"></i>
                                <span>{{ (int)($t->likes_count ?? 0) }}</span>
                            </span>
                            <span class="admin-inline-stat" title="评论">
                                <i class="fa-regular fa-comment" aria-hidden="true"></i>
                                <span>{{ (int)($t->comments_count ?? 0) }}</span>
                            </span>
                        </td>
                        <td>
                            <div class="admin-action-bar">
                                <form method="post" action="/admin/x/tweets/refresh"
                                      class="admin-inline-action-form"
                                      data-confirm="确定重新抓取这条推文并覆盖当前缓存的内容和图片？"
                                      data-confirm-title="刷新推文缓存"
                                      data-confirm-text="确认刷新">
                                    <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                    <input type="hidden" name="id" value="{{ $t->id }}">
                                    <button type="submit"
                                            class="admin-action-btn admin-action-refresh"
                                            title="刷新缓存"
                                            aria-label="刷新缓存">
                                        <i class="fa-solid fa-rotate-right"></i>
                                    </button>
                                </form>
                                <form method="post" action="/admin/x/tweets/delete"
                                      class="admin-inline-action-form"
                                      data-confirm="确定删除这条推文及其评论？"
                                      data-confirm-title="删除推文"
                                      data-confirm-text="确认删除">
                                    <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                    <input type="hidden" name="id" value="{{ $t->id }}">
                                    <button type="submit"
                                            class="admin-action-btn admin-action-delete"
                                            title="删除"
                                            aria-label="删除">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {!! $paginator ?? '' !!}
</div>
@endsection
