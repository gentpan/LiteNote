@extends('layouts.front')

@section('content')
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
                    $music = $item->getMusicEmbed();
                    $comments = $item->getRelation('comments') ?: [];
                @endphp
                <article class="feed-card feed-shuoshuo-card" id="shuoshuo-{{ $item->id }}">
                    <div class="feed-card-kicker">
                        <span>说说</span>
                        <time>{!! \App\Core\Helper::timeTag($item->created_at) !!}</time>
                    </div>
                    <div class="shuoshuo-content">{{ $item->content }}</div>

                    @if(!empty($images))
                        <div class="shuoshuo-images">
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
                        <button type="button" class="feed-action shuoshuo-like-btn" data-id="{{ $item->id }}">
                            <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($item->likes_count ?? 0) }}</span>
                        </button>
                        <button type="button" class="feed-action shuoshuo-comment-toggle" data-target="shuoshuo-comments-{{ $item->id }}">
                            <i class="fa-regular fa-comment"></i><span>{{ (int)($item->comments_count ?? count($comments)) }}</span>
                        </button>
                        @if($item->mood)
                            <span class="feed-action mood">{!! $item->mood !!}</span>
                        @endif
                    </div>

                    <div class="shuoshuo-comments" id="shuoshuo-comments-{{ $item->id }}">
                        @if(!empty($comments))
                            <ul class="shuoshuo-comment-list">
                                @foreach($comments as $cmt)
                                    <li>
                                        <strong>{{ $cmt->nickname }}</strong>
                                        <span>{{ $cmt->content }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form class="comment-form shuoshuo-comment-form" method="post" action="/comment/submit">
                            <input type="hidden" name="shuoshuo_id" value="{{ $item->id }}">
                            <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                            <div class="form-row">
                                <input type="text" name="nickname" placeholder="昵称 *" required>
                                <input type="email" name="email" placeholder="邮箱(选填)">
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
