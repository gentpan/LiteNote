@extends('layouts.front')

@section('content')
    <article class="post-detail">
        @if($post->cover)
            <header class="post-hero-card">
                <div class="post-cover">
                    <img src="{{ $post->cover }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                </div>
                <div class="post-hero-title">
                    <h1 class="post-title">
                        @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                        {{ $post->title }}
                    </h1>
                </div>
            </header>
        @else
            <header class="post-header">
                <h1 class="post-title">
                    @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                    {{ $post->title }}
                </h1>
            </header>
        @endif
        <div class="post-content">
            {!! $post->html() !!}
        </div>
        <footer class="post-footer-meta">
            <p class="post-meta">
                <span><i class="fa-regular fa-calendar"></i> {!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                @if($category)
                    <span><i class="fa-solid fa-folder"></i> <a href="/category/{{ $category->slug }}">{{ $category->name }}</a></span>
                @endif
                <span><i class="fa-regular fa-eye"></i> {{ $post->views }} 浏览</span>
                <span><i class="fa-regular fa-comments"></i> {{ count($comments) }} 评论</span>
            </p>
        </footer>
        {{-- 标签功能已下线,UI 隐藏(数据 + 代码保留) --}}
        {{-- 文章底部 author block 已删除(2026-06) --}}

        <section class="comments">
            <h3>评论 ({{ count($comments) }})</h3>
            @if(\App\Core\Session::hasFlash('comment_success'))
                <div class="alert alert-success">{{ \App\Core\Session::getFlash('comment_success') }}</div>
            @endif
            @if(\App\Core\Session::hasFlash('comment_error'))
                <div class="alert alert-error">{{ \App\Core\Session::getFlash('comment_error') }}</div>
            @endif
            <ul class="comment-list">
                @foreach($comments as $cmt)
                    <li class="comment-item">
                        <img class="comment-avatar" src="{{ $cmt->getAvatarUrl(48) }}" alt="{{ $cmt->nickname }}" loading="lazy" width="48" height="48">
                        <div class="comment-body">
                            <div class="comment-meta">
                                <strong>{{ $cmt->nickname }}</strong>
                                <span>· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                            </div>
                            <div class="comment-content">{{ $cmt->content }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <form class="comment-form" method="post" action="/comment/submit">
                <input type="hidden" name="post_id" value="{{ $post->id }}">
                <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                <div class="form-row">
                    <input type="text" name="nickname" placeholder="昵称 *" required>
                    <input type="email" name="email" placeholder="邮箱(选填)">
                    <input type="text" name="website" placeholder="网站(选填)">
                </div>
                <textarea name="content" rows="5" placeholder="说点什么... *" required></textarea>
                <button type="submit">提交评论</button>
            </form>
        </section>
    </article>
@endsection
