@extends('layouts.front')

@section('content')
    <article class="page-detail">
        <h1>{{ $page->title }}</h1>
        <div class="page-content">
            {!! $page->content !!}
        </div>

        @if(!empty($comments))
            <section class="comments">
                <h3>评论 ({{ count($comments) }})</h3>
                <ul class="comment-list">
                    @foreach($comments as $cmt)
                        <li class="comment-item">
                            <div class="comment-meta">
                                <strong>{{ $cmt->nickname }}</strong>
                                <span>· {{ \App\Core\Helper::humanDate($cmt->created_at) }}</span>
                            </div>
                            <div class="comment-content">{{ $cmt->content }}</div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(\App\Core\Session::hasFlash('comment_success'))
            <div class="alert alert-success">{{ \App\Core\Session::getFlash('comment_success') }}</div>
        @endif
        @if(\App\Core\Session::hasFlash('comment_error'))
            <div class="alert alert-error">{{ \App\Core\Session::getFlash('comment_error') }}</div>
        @endif
        <form class="comment-form" method="post" action="/comment/submit">
            <input type="hidden" name="page_id" value="{{ $page->id }}">
            <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
            <div class="form-row">
                <input type="text" name="nickname" placeholder="昵称 *" required>
                <input type="email" name="email" placeholder="邮箱（选填）">
            </div>
            <textarea name="content" rows="5" placeholder="说点什么... *" required></textarea>
            <button type="submit">提交评论</button>
        </form>
    </article>
@endsection
