@extends('layouts.front')

@section('content')
    @php
        $__monthNames = [
            '01' => '1月', '02' => '2月', '03' => '3月', '04' => '4月',
            '05' => '5月', '06' => '6月', '07' => '7月', '08' => '8月',
            '09' => '9月', '10' => '10月', '11' => '11月', '12' => '12月',
            '00' => '未归档',
        ];
    @endphp

    <section class="archive-page">
        <div class="archive-shell">
            <header class="archive-hero">
                <div class="archive-title">
                    <i class="fa-solid fa-box-archive"></i>
                    <h1>归档</h1>
                </div>
                <div class="archive-stats" aria-label="站点归档统计">
                    <span><strong>{{ number_format($stats['articles'] ?? 0) }}</strong> 篇文章</span>
                    <span><strong>{{ number_format($stats['days'] ?? 0) }}</strong> 天</span>
                    <span><strong>{{ number_format($stats['words'] ?? 0) }}</strong> 字</span>
                    <span><strong>{{ number_format($stats['comments'] ?? 0) }}</strong> 条评论</span>
                </div>
            </header>

            <section class="archive-heat-panel">
                <div class="archive-section-head">
                    <h2>近一年更新热力图</h2>
                    <p>颜色越深表示当天发文越多</p>
                </div>
                <div class="archive-heat-scroll">
                    <div class="archive-heat-inner" style="--weeks: {{ $heatmap['weeks'] ?? 53 }}">
                        <div class="archive-heat-months">
                            @foreach(($heatmap['months'] ?? []) as $month)
                                <span style="grid-column: {{ $month['week'] }}">{{ $month['label'] }}</span>
                            @endforeach
                        </div>
                        <div class="archive-heat-cells" aria-label="近一年每日发文热力图">
                            @foreach(($heatmap['days'] ?? []) as $day)
                                <span class="archive-heat-cell level-{{ $day['level'] }} {{ !empty($day['muted']) ? 'is-muted' : '' }}"
                                      title="{{ $day['date'] }}：{{ $day['count'] }} 篇"></span>
                            @endforeach
                        </div>
                        <div class="archive-heat-legend">
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
            </section>

            <section class="archive-categories">
                <div class="archive-section-head archive-section-head-row">
                    <h2><i class="fa-solid fa-folder-tree"></i> 分类</h2>
                    <p>{{ count($categoryCards ?? []) }} 个</p>
                </div>
                <div class="archive-category-panel">
                    <div class="archive-category-grid">
                        @foreach(($categoryCards ?? []) as $cat)
                            <a class="archive-category-card cat-color-{{ $cat['color'] }}" href="/category/{{ $cat['slug'] }}">
                                <div class="archive-category-main">
                                    <span class="archive-category-icon"><i class="{{ $cat['icon'] }}"></i></span>
                                    <div>
                                        <h3>{{ $cat['name'] }}</h3>
                                        <span>{{ $cat['count'] }} 篇</span>
                                    </div>
                                </div>
                                <p>{{ $cat['description'] }}</p>
                                <div class="archive-category-foot">
                                    <span><i class="fa-regular fa-pen-to-square"></i> {{ $cat['latestTitle'] }}</span>
                                    @if(!empty($cat['latestAt']))
                                        <time>{{ $cat['latestAt'] }}</time>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="archive-years">
                @foreach(($years ?? []) as $year => $yearGroup)
                    <article class="archive-year">
                        <header class="archive-year-head">
                            <h2>{{ $year }}</h2>
                            <span>{{ $yearGroup['total'] ?? 0 }} 篇</span>
                        </header>

                        @foreach(($yearGroup['months'] ?? []) as $month => $monthGroup)
                            <div class="archive-month-head">
                                <strong>{{ $__monthNames[$month] ?? ($month . '月') }}</strong>
                                <span>{{ $monthGroup['total'] ?? 0 }} 篇</span>
                            </div>
                            <div class="archive-post-list">
                                @foreach(($monthGroup['items'] ?? []) as $p)
                                    @php
                                        $__date = (string)($p['published_at'] ?? '');
                                        $__categoryIcon = (string)($p['category_icon'] ?? '');
                                        $__categoryIcon = preg_match('/^[a-zA-Z0-9 _-]+$/', $__categoryIcon) ? $__categoryIcon : 'fa-regular fa-file-lines';
                                    @endphp
                                    <a class="archive-post-row" href="/post/{{ $p['slug'] }}.html">
                                        <time>{{ substr($__date, 5, 5) }}</time>
                                        <i class="{{ $__categoryIcon }}"></i>
                                        <span class="archive-post-title">{{ $p['title'] }}</span>
                                        <span class="archive-post-meta">
                                            <span><i class="fa-regular fa-comment"></i> {{ (int)($p['comments_count'] ?? 0) }}</span>
                                            <span><i class="fa-regular fa-eye"></i> {{ (int)($p['views'] ?? 0) }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </article>
                @endforeach
            </section>
        </div>
    </section>
@endsection
