@extends('layouts.front')

@section('content')
    @php
        $sourceFilters = $sourceFilters ?? [];
        $activityTotal = (int)($activityTotal ?? $total ?? 0);
        $allUrl = \App\Core\Helper::url('/activity');
    @endphp
    <section class="activity-page">
        <header class="activity-hero">
            <div class="activity-hero-main">
                <div class="activity-title-mark">
                    <span aria-hidden="true"></span>
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    <b>Activity</b>
                    <em>动态</em>
                </div>
                <p>聚合 Spotify、NeoDB、网易云音乐、GitHub，以及站内发布的文章和说说。</p>
            </div>
        </header>

        <section class="activity-panel activity-heat-panel">
            <div class="activity-panel-head">
                <h3>年度动态</h3>
                <span>{{ (int)($heatmap['totalEvents'] ?? 0) }} 条 · {{ (int)($heatmap['activeDays'] ?? 0) }} 天</span>
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
                                    $cellType = preg_replace('/[^a-z0-9_-]/i', '', (string)($cell['type'] ?? ''));
                                    $typeLabel = (string)($cell['type_label'] ?? '');
                                    $heatTitle = $eventCount > 0
                                        ? trim($typeLabel . ' · ' . $eventCount . ' 条动态', ' ·')
                                        : '没有动态';
                                    $cellClasses = [
                                        'site-heatmap-cell',
                                        'level-' . (int)($cell['level'] ?? 0),
                                    ];
                                    if ($eventCount > 0 && $cellType !== '') {
                                        $cellClasses[] = 'has-activity';
                                        $cellClasses[] = 'activity-type-' . $cellType;
                                    }
                                    if (!empty($cell['muted'])) {
                                        $cellClasses[] = 'is-muted';
                                    }
                                @endphp
                                <span class="{{ implode(' ', $cellClasses) }}"
                                      title="{{ $cell['date'] }}：{{ $heatTitle }}"></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <nav class="activity-source-tabs" aria-label="动态来源筛选">
            <a href="{{ $allUrl }}" class="{{ $activeSource === '' && $activeType === '' ? 'active' : '' }}">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <span>全部</span>
                <b>{{ $activityTotal }}</b>
            </a>
            @foreach($sourceFilters as $source)
                <a href="{{ \App\Core\Helper::url('/activity?source=' . rawurlencode($source['source'])) }}"
                   class="{{ $activeSource === $source['source'] ? 'active' : '' }}">
                    <i class="{{ $source['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $source['label'] }}</span>
                    <b>{{ (int)$source['count'] }}</b>
                </a>
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
