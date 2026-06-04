@extends('layouts.front')

@section('content')
    <section class="shuoshuo-list">
        <h2 class="section-title">说说</h2>
        @if(\App\Core\Session::hasFlash('talk_publish_success'))
            <div class="alert alert-success">{{ \App\Core\Session::getFlash('talk_publish_success') }}</div>
        @endif
        @if(\App\Core\Session::hasFlash('talk_publish_error'))
            <div class="alert alert-error">{{ \App\Core\Session::getFlash('talk_publish_error') }}</div>
        @endif
        @if(!empty($currentAdmin))
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
        @if(empty($list))
            <p class="empty">还没有说说</p>
        @endif
        @foreach($list as $s)
            @php
                $comments = $s->getRelation('comments') ?: [];
                $keywords = $s->getKeywords();
                $displayContent = $s->contentWithoutKeywords();
            @endphp
            <article class="shuoshuo-card" id="shuoshuo-{{ $s->id }}">
                <div class="shuoshuo-content">{{ $displayContent }}</div>

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
                    <div class="shuoshuo-meta-main">
                        <span class="shuoshuo-keywords">
                            @foreach($keywords as $keyword)
                                <span>#{{ $keyword }}</span>
                            @endforeach
                        </span>
                        <span class="feed-shuoshuo-dot">·</span>
                        <span class="time">{!! \App\Core\Helper::timeTag($s->created_at) !!}</span>
                    </div>
                    <div class="shuoshuo-meta-actions">
                        <button type="button" class="feed-action shuoshuo-like-btn" data-id="{{ $s->id }}">
                            <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($s->likes_count ?? 0) }}</span>
                        </button>
                        <button type="button" class="feed-action shuoshuo-comment-toggle" data-target="shuoshuo-comments-{{ $s->id }}">
                            <i class="fa-regular fa-comment"></i><span>{{ (int)($s->comments_count ?? count($comments)) }}</span>
                        </button>
                    </div>
                </div>
                <div class="shuoshuo-comments" id="shuoshuo-comments-{{ $s->id }}">
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
                        <input type="hidden" name="shuoshuo_id" value="{{ $s->id }}">
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
        @endforeach
        {!! $paginator ?? '' !!}
    </section>
@endsection
