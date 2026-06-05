@extends('layouts.front')

@section('content')
    <section class="reader-wall-page">
        <header class="reader-wall-head">
            <p class="reader-wall-kicker">Readers</p>
            <h2 class="section-title">读者墙</h2>
            <p class="reader-wall-desc">按已通过评论数量排序，头像来自评论邮箱；鼠标划过会展开百叶窗光影。</p>
            <div class="reader-wall-switch">
                <a href="/readers" class="{{ ($sort ?? 'count') === 'count' ? 'active' : '' }}">按评论数</a>
                <a href="/readers?sort=random" class="{{ ($sort ?? 'count') === 'random' ? 'active' : '' }}">随机看看</a>
            </div>
        </header>

        @if(empty($readers))
            <p class="empty">还没有读者评论。</p>
        @else
            <div class="reader-wall-grid">
                @foreach($readers as $reader)
                    <article class="reader-tile reader-weight-{{ $reader['weight'] }}" style="--tilt: {{ $reader['tilt'] }}deg; --delay: {{ $reader['delay'] }}ms;">
                        @if(!empty($reader['website']))
                            <a class="reader-tile-link" href="{{ $reader['website'] }}" target="_blank" rel="nofollow noopener">
                        @else
                            <div class="reader-tile-link">
                        @endif
                                <span class="reader-rank">#{{ str_pad((string)$reader['rank'], 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="reader-avatar-wrap">
                                    <img src="{{ $reader['avatar'] }}" alt="{{ $reader['nickname'] }}" loading="lazy" width="160" height="160">
                                </span>
                                <span class="reader-info">
                                    <strong>{{ $reader['nickname'] }}</strong>
                                    <span>{{ $reader['comments_count'] }} 条评论</span>
                                </span>
                        @if(!empty($reader['website']))
                            </a>
                        @else
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
