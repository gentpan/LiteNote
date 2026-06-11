@extends('layouts.front')

@section('content')
    @php
        $typeCounts = [];
        $todayMeta = $todayStats['metadata'] ?? [];
        if (is_string($todayMeta)) {
            $todayMeta = json_decode($todayMeta, true) ?: [];
        }
        $typeCounts = is_array($todayMeta['type_counts'] ?? null) ? $todayMeta['type_counts'] : [];
    @endphp
    <section class="activity-page">
        <header class="activity-hero">
            <div>
                <p class="activity-kicker">Activity</p>
                <h2>西风的动态</h2>
                <p>记录代码、音乐、电影、AI 和生活片段。</p>
            </div>
            <a href="/activity" class="activity-hero-link">查看全部</a>
        </header>

        <div class="activity-stats-grid">
            <article class="activity-stat-card">
                <strong>{{ (int)($todayStats['total_events'] ?? 0) }}</strong>
                <span>今日记录</span>
                <small>{{ (int)($todayStats['active_types'] ?? 0) }} 类活动</small>
            </article>
            <article class="activity-stat-card">
                <strong>{{ number_format((int)($todayStats['ai_total_tokens'] ?? 0)) }}</strong>
                <span>AI Tokens</span>
                <small>输入 {{ number_format((int)($todayStats['ai_input_tokens'] ?? 0)) }} · 输出 {{ number_format((int)($todayStats['ai_output_tokens'] ?? 0)) }}</small>
            </article>
            <article class="activity-stat-card">
                <strong>{{ (int)($heatmap['activeDays'] ?? 0) }}</strong>
                <span>活跃天数</span>
                <small>最近一年</small>
            </article>
        </div>

        <div class="activity-panels">
            <section class="activity-panel">
                <div class="activity-panel-head">
                    <h3>类型分布</h3>
                    <span>Today</span>
                </div>
                @if(empty($typeCounts))
                    <p class="activity-muted">今天还没有公开动态。</p>
                @else
                    <div class="activity-type-bars">
                        @foreach($typeCounts as $type => $count)
                            @php $percent = max(6, min(100, (int)$count * 18)); @endphp
                            <div class="activity-type-bar activity-type-{{ $type }}">
                                <span>{{ $types[$type]['label'] ?? $type }}</span>
                                <i><b style="width: {{ $percent }}%"></b></i>
                                <em>{{ (int)$count }}</em>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="activity-panel activity-heat-panel">
                <div class="activity-panel-head">
                    <h3>年度点阵</h3>
                    <span class="activity-heat-range">
                        <b class="activity-heat-range-full">365D</b>
                        <b class="activity-heat-range-mobile">120D</b>
                    </span>
                </div>
                <div class="site-heatmap activity-heatmap" aria-label="近一年动态热力图">
                    <div class="site-heatmap-scroll">
                        <div class="site-heatmap-inner" style="--weeks: {{ $heatmap['weeks'] ?? 53 }}">
                            <div class="site-heatmap-months">
                                @foreach(($heatmap['months'] ?? []) as $month)
                                    <span style="grid-column: {{ $month['week'] }}">{{ $month['label'] }}</span>
                                @endforeach
                            </div>
                            <div class="site-heatmap-cells">
                                @foreach(($heatmap['days'] ?? []) as $cell)
                                    @php
                                        $eventCount = (int)($cell['total'] ?? 0);
                                        $heatTitle = $eventCount > 0 ? $eventCount . ' 条动态' : '没有动态';
                                    @endphp
                                    <span class="site-heatmap-cell level-{{ $cell['level'] }} {{ !empty($cell['muted']) ? 'is-muted' : '' }}"
                                          title="{{ $cell['date'] }}：{{ $heatTitle }}"></span>
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
            </section>
        </div>

        <nav class="activity-tabs" aria-label="动态类型筛选">
            <a href="/activity" class="{{ $activeType === '' ? 'active' : '' }}">全部</a>
            @foreach($types as $key => $def)
                <a href="/activity?type={{ $key }}" class="{{ $activeType === $key ? 'active' : '' }}">{{ $def['label'] }}</a>
            @endforeach
        </nav>

        @if(empty($list))
            <p class="empty">还没有动态</p>
        @else
            <div class="activity-timeline">
                @foreach($list as $activity)
                    @include('partials.activity-item')
                @endforeach
            </div>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
