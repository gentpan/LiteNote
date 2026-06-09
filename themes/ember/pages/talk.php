@extends('layouts.front')

@section('content')
    <section class="talk-list">
        <header class="talk-hero">
            <div class="talk-hero-head">
                <div class="talk-hero-headtext">
                    <span class="talk-hero-kicker">TALK</span>
                    <h2 class="talk-hero-title">滔客</h2>
                </div>
                <p class="talk-hero-sub">共 {{ (int)($heroTotal ?? 0) }} 条 · {{ (int)($heroActiveDays ?? 0) }} 天有更新</p>
            </div>
            @if(!empty($heroWeeks))
                <div class="talk-heatmap">
                    <div class="talk-heatmap-grid" role="img" aria-label="滔客发布活跃热力图">
                        @foreach($heroWeeks as $week)
                            <div class="talk-heatmap-col">
                                @foreach($week as $day)
                                    <span class="talk-heatmap-cell lv-{{ $day['level'] }}" title="{{ $day['date'] }} · {{ $day['count'] }} 条"></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="talk-heatmap-legend">
                        <span>少</span>
                        <i class="lv-0"></i><i class="lv-1"></i><i class="lv-2"></i><i class="lv-3"></i><i class="lv-4"></i>
                        <span>多</span>
                    </div>
                </div>
            @endif
            @if(!empty($heroMoods))
                <div class="talk-keywords">
                    <span class="talk-keywords-label">关键词</span>
                    @foreach($heroMoods as $m)
                        <span class="talk-keyword">{{ $m['name'] }}<b>{{ $m['count'] }}</b></span>
                    @endforeach
                </div>
            @endif
        </header>
        @include('partials.talk-publish-form')
        @if(empty($list))
            <p class="empty">还没有滔客</p>
        @endif
        <div class="js-list-items">
        @foreach($list as $s)
            @include('partials.talk-card')
        @endforeach
        </div>
        {!! $paginator ?? '' !!}
    </section>
@endsection
