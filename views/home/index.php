@extends('layouts.front')

@section('content')
    @if(!empty($currentAdmin))
        @if(\App\Core\Session::hasFlash('talk_publish_success'))
            <div class="alert alert-success">{{ \App\Core\Session::getFlash('talk_publish_success') }}</div>
        @endif
        @if(\App\Core\Session::hasFlash('talk_publish_error'))
            <div class="alert alert-error">{{ \App\Core\Session::getFlash('talk_publish_error') }}</div>
        @endif
        <form class="front-publish-form" method="post" action="/talk/publish">
            <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
            <div class="front-publish-head">
                <span>发布说说</span>
                <label><input type="checkbox" name="is_public" value="1" checked> 公开</label>
            </div>
            <textarea name="content" rows="4" placeholder="今天想说点什么..." required></textarea>
            <div class="front-publish-grid">
                <input type="text" name="images" placeholder="图片 URL，多个用英文逗号分隔">
                <input type="text" name="music" placeholder="音乐链接">
                <input type="text" name="mood" placeholder="关键词/心情，例如 日常">
            </div>
            <div class="front-publish-actions">
                <span>{{ $currentAdmin->nickname ?: $currentAdmin->username }}</span>
                <button type="submit">发布</button>
            </div>
        </form>
    @endif
    <section class="feed-list">
        @if(empty($feedItems))
            <p class="empty">还没有内容</p>
        @endif

        @foreach($feedItems as $feed)
            @php $item = $feed['item']; @endphp

            @if($feed['type'] === 'post')
                @php $category = $item->getCategory(); @endphp
                <article class="feed-card feed-post-card">
                    <div class="feed-post-main">
                        <div class="feed-post-meta">
                            <div class="feed-post-type">
                                <span>文章</span>
                                @if($category)
                                    <span class="feed-post-dot">·</span>
                                    <a href="/category/{{ $category->slug }}">{{ $category->name }}</a>
                                @endif
                                <span class="feed-post-dot">·</span>
                                <span class="feed-post-time">{!! \App\Core\Helper::timeTag($item->published_at) !!}</span>
                            </div>
                        </div>
                        <h2 class="feed-post-title">
                            <a href="/post/{{ $item->slug }}.html">{{ $item->title }}</a>
                        </h2>
                        <p class="feed-post-excerpt">{{ $item->summaryOrContent(180) }}</p>
                    </div>
                    @if($item->cover)
                        <a class="feed-post-thumb" href="/post/{{ $item->slug }}.html" aria-label="{{ $item->title }}">
                            <img src="{{ $item->cover }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
                        </a>
                    @endif
                </article>
            @else
                @php
                    $images = $item->getImages();
                    $imageCount = count($images);
                    $music = $item->getMusicEmbed();
                    $comments = $item->getRelation('comments') ?: [];
                    $keywords = $item->getKeywords();
                    $displayContent = $item->contentWithoutKeywords();
                @endphp
                <article class="feed-card feed-shuoshuo-card" id="shuoshuo-{{ $item->id }}">
                    <div class="shuoshuo-content">{{ $displayContent }}</div>

                    @if(!empty($images))
                        <div class="shuoshuo-images shuoshuo-images-count-{{ min($imageCount, 10) }}">
                            @foreach($images as $img)
                                <img src="{{ trim($img) }}" alt="" loading="lazy">
                            @endforeach
                        </div>
                    @endif

                    @if($music)
                        <div class="shuoshuo-music">
                            <div class="music-player">
                                {!! $music['html'] !!}
                            </div>
                        </div>
                    @endif

                    <div class="feed-actions">
                        <div class="feed-shuoshuo-meta">
                            <span class="feed-shuoshuo-keywords">
                                @foreach($keywords as $keyword)
                                    <span>#{{ $keyword }}</span>
                                @endforeach
                            </span>
                            <span class="feed-shuoshuo-dot">·</span>
                            <span>{!! \App\Core\Helper::timeTag($item->created_at) !!}</span>
                        </div>
                        <div class="feed-shuoshuo-side">
                            <button type="button" class="feed-action shuoshuo-like-btn" data-id="{{ $item->id }}" aria-label="点赞">
                                <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($item->likes_count ?? 0) }}</span>
                            </button>
                            <button type="button" class="feed-action shuoshuo-comment-toggle" data-target="shuoshuo-comments-{{ $item->id }}">
                                <i class="fa-regular fa-comment"></i><span>{{ (int)($item->comments_count ?? count($comments)) }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="shuoshuo-comments" id="shuoshuo-comments-{{ $item->id }}">
                        @if(!empty($comments))
                            <ul class="shuoshuo-comment-list">
                                @foreach($comments as $cmt)
                                    <li>
                                        <strong>{{ $cmt->nickname }}</strong>
                                        <span class="comment-time">· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                        <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                                        <span class="shuoshuo-comment-content">{{ $cmt->content }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @php
                            $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
                            $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
                        @endphp
                        <form class="comment-form shuoshuo-comment-form" method="post" action="/comment/submit" data-comment-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
                            <input type="hidden" name="shuoshuo_id" value="{{ $item->id }}">
                            <input type="hidden" name="parent_id" value="0">
                            <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                            <div class="form-row">
                                <input type="text" name="nickname" value="{{ $adminCommentName }}" placeholder="昵称 *" required @if(!empty($currentAdmin)) readonly @endif>
                                <input type="email" name="email" value="{{ $adminCommentEmail }}" placeholder="邮箱 *" required @if(!empty($currentAdmin)) readonly @endif>
                                <input type="text" name="website" placeholder="网站(选填)">
                            </div>
                            <textarea name="content" rows="3" placeholder="写评论..." required></textarea>
                            <button type="submit">提交评论</button>
                        </form>
                    </div>
                </article>
            @endif
        @endforeach
    </section>
@endsection
