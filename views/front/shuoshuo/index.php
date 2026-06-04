@extends('layouts.front')

@section('content')
    <section class="shuoshuo-list">
        <h2 class="section-title">说说</h2>
        @if(empty($list))
            <p class="empty">还没有说说</p>
        @endif
        @foreach($list as $s)
            @php
                $comments = $s->getRelation('comments') ?: [];
            @endphp
            <article class="shuoshuo-card" id="shuoshuo-{{ $s->id }}">
                <div class="shuoshuo-content">{{ $s->content }}</div>

                @php $images = $s->getImages(); @endphp
                @if(!empty($images))
                    <div class="shuoshuo-images">
                        @foreach($images as $img)
                            <img src="{{ trim($img) }}" alt="" loading="lazy">
                        @endforeach
                    </div>
                @endif

                @php $music = $s->getMusicEmbed(); @endphp
                @if($music)
                    <div class="shuoshuo-music">
                        <div class="music-player">
                            {!! $music['html'] !!}
                        </div>
                    </div>
                @endif

                <div class="shuoshuo-meta">
                    @if($s->mood)
                        <span class="mood">{!! $s->mood !!}</span>
                    @endif
                    <span class="time">{!! \App\Core\Helper::timeTag($s->created_at) !!}</span>
                    <button type="button" class="feed-action shuoshuo-like-btn" data-id="{{ $s->id }}">
                        <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($s->likes_count ?? 0) }}</span>
                    </button>
                    <button type="button" class="feed-action shuoshuo-comment-toggle" data-target="shuoshuo-comments-{{ $s->id }}">
                        <i class="fa-regular fa-comment"></i><span>{{ (int)($s->comments_count ?? count($comments)) }}</span>
                    </button>
                </div>
                <div class="shuoshuo-comments" id="shuoshuo-comments-{{ $s->id }}">
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
                        <input type="hidden" name="shuoshuo_id" value="{{ $s->id }}">
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
        @endforeach
        {!! $paginator ?? '' !!}
    </section>
@endsection
