@extends('layouts.front')

@section('content')
    <section class="talk-list">
        <header class="talk-hero">
            <div class="talk-hero-head">
                <div class="talk-hero-kicker-row">
                    <span class="talk-hero-kicker">TALK</span>
                    <p class="talk-hero-sub">共 {{ (int)($heroTotal ?? 0) }} 条 · {{ (int)($heroActiveDays ?? 0) }} 天有更新</p>
                </div>
                <h2 class="talk-hero-title">滔客</h2>
            </div>
            @if(!empty($heroHeatDays))
                <div class="talk-heatmap site-heatmap">
                    <div class="site-heatmap-scroll">
                        <div class="site-heatmap-inner" style="--weeks: {{ $heroHeatWeeks ?? 53 }}">
                            <div class="site-heatmap-months">
                                @foreach(($heroHeatMonths ?? []) as $month)
                                    <span style="grid-column: {{ $month['week'] }}">{{ $month['label'] }}</span>
                                @endforeach
                            </div>
                            <div class="site-heatmap-cells" role="img" aria-label="滔客发布活跃热力图">
                                @foreach($heroHeatDays as $day)
                                    @php
                                        $talkCount = (int)($day['count'] ?? 0);
                                        $heatTitle = $talkCount > 0 ? $talkCount . ' 条说说' : '没有说说';
                                    @endphp
                                    <span class="site-heatmap-cell level-{{ $day['level'] }} {{ !empty($day['muted']) ? 'is-muted' : '' }}"
                                          title="{{ $day['date'] }}：{{ $heatTitle }}"></span>
                                @endforeach
                            </div>
                            <div class="site-heatmap-legend">
                                <span>少</span>
                                <i class="level-0"></i>
                                <i class="level-1"></i>
                                <i class="level-2"></i>
                                <i class="level-3"></i>
                                <i class="level-4"></i>
                                <span>多</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </header>
        @if(!empty($heroMoods))
            @php $activeKeyword = $activeKeyword ?? ''; @endphp
            <nav class="talk-keyword-rail" aria-label="按关键词筛选滔客" data-talk-keyword-rail>
                <a class="talk-keyword-chip{{ $activeKeyword === '' ? ' is-active' : '' }}" href="{{ \App\Core\Helper::url('/talk') }}" data-no-pjax="1" data-keyword="">
                    <span class="talk-keyword-name">全部</span><b>{{ (int)($heroTotal ?? 0) }}</b>
                </a>
                @foreach($heroMoods as $m)
                    <a class="talk-keyword-chip{{ $activeKeyword === $m['name'] ? ' is-active' : '' }}" href="{{ \App\Core\Helper::url('/talk?keyword=' . rawurlencode($m['name'])) }}" data-no-pjax="1" data-keyword="{{ $m['name'] }}">
                        <span class="talk-keyword-name">#{{ $m['name'] }}</span><b>{{ $m['count'] }}</b>
                    </a>
                @endforeach
            </nav>
        @endif
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
