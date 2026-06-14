@extends('layouts.front')

@section('content')
    @php
        $postTotal = (int)($heatmap['articles'] ?? $total ?? 0);
        $postWords = (int)($heatmap['words'] ?? 0);
        $novelMilestones = [
            ['words' => 10000, 'author' => '芥川龙之介', 'title' => '罗生门'],
            ['words' => 20000, 'author' => '弗朗茨・卡夫卡', 'title' => '变形记'],
            ['words' => 40000, 'author' => '安托万・德・圣埃克苏佩里', 'title' => '小王子'],
            ['words' => 70000, 'author' => '欧内斯特・海明威', 'title' => '老人与海'],
            ['words' => 90000, 'author' => '乔治・奥威尔', 'title' => '动物农场'],
            ['words' => 120000, 'author' => '菲茨杰拉德', 'title' => '了不起的盖茨比'],
            ['words' => 235000, 'author' => '简・奥斯汀', 'title' => '傲慢与偏见'],
            ['words' => 300000, 'author' => '艾米莉・勃朗特', 'title' => '呼啸山庄'],
            ['words' => 450000, 'author' => '夏洛蒂・勃朗特', 'title' => '简・爱'],
            ['words' => 500000, 'author' => '加西亚・马尔克斯', 'title' => '百年孤独'],
            ['words' => 600000, 'author' => '列夫・托尔斯泰', 'title' => '安娜・卡列尼娜'],
            ['words' => 700000, 'author' => '维克多・雨果', 'title' => '悲惨世界'],
            ['words' => 800000, 'author' => '曹雪芹', 'title' => '红楼梦'],
            ['words' => 900000, 'author' => '托尔斯泰', 'title' => '战争与和平'],
            ['words' => 1000000, 'author' => '罗贯中', 'title' => '三国演义'],
            ['words' => 1500000, 'author' => '施耐庵', 'title' => '水浒传'],
            ['words' => 2000000, 'author' => '马塞尔・普鲁斯特', 'title' => '追忆似水年华'],
            ['words' => 3000000, 'author' => '金庸', 'title' => '鹿鼎记'],
        ];
        $novelMilestone = null;
        foreach ($novelMilestones as $milestone) {
            if ($postWords >= (int)$milestone['words']) {
                $novelMilestone = $milestone;
            }
        }
    @endphp
    <section class="post-list">
        <header class="posts-hero">
            <div class="posts-hero-head">
                <div class="posts-hero-kicker-row">
                    <span class="posts-hero-kicker">
                        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                        <span aria-hidden="true">·</span>
                        <span>POSTS</span>
                        <span aria-hidden="true">·</span>
                        <span>文章</span>
                    </span>
                    <p class="posts-hero-sub">
                        <span class="posts-hero-count">{{ number_format($postTotal) }} 篇文章 · 共 {{ number_format($postWords) }} 字</span>@if($novelMilestone)<span class="posts-hero-milestone">写完一本 {{ $novelMilestone['author'] }} 的《{{ $novelMilestone['title'] }}》了！</span>@endif
                    </p>
                </div>
            </div>
            @if(!empty($heatmap['days']))
                <div class="posts-heatmap site-heatmap">
                    <div class="posts-heatmap-scroll site-heatmap-scroll">
                        <div class="posts-heatmap-inner site-heatmap-inner" style="--weeks: {{ $heatmap['weeks'] ?? 53 }}">
                            <div class="posts-heatmap-months site-heatmap-months">
                                @foreach(($heatmap['months'] ?? []) as $month)
                                    <span style="grid-column: {{ $month['week'] }}">{{ $month['label'] }}</span>
                                @endforeach
                            </div>
                            <div class="posts-heatmap-cells site-heatmap-cells" aria-label="近一年文章写作热力图">
                                @foreach(($heatmap['days'] ?? []) as $day)
                                    @php
                                        $articleCount = (int)($day['articles'] ?? 0);
                                        $heatTitle = $articleCount > 0 ? $articleCount . ' 篇文章' : '没有文章';
                                    @endphp
                                    <span class="posts-heatmap-cell site-heatmap-cell level-{{ $day['level'] }} {{ !empty($day['muted']) ? 'is-muted' : '' }}"
                                          title="{{ $day['date'] }}：{{ $heatTitle }}"></span>
                                @endforeach
                            </div>
                            <div class="posts-heatmap-legend site-heatmap-legend">
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
        @if(empty($posts))
            <p class="empty">还没有文章，<a href="/admin">去后台发布</a></p>
        @endif
        @php
            $isFirstPage = (int)($page ?? 1) === 1;
            $featuredPosts = $isFirstPage ? array_slice($posts, 0, 3) : [];
            $compactPosts = $isFirstPage ? array_slice($posts, 3) : $posts;
        @endphp
        <div class="js-list-items">
        @if(!empty($featuredPosts))
            <div class="post-feature-grid">
                @foreach($featuredPosts as $index => $post)
                    @php
                        $postNumber = (int)($post->article_number ?? 0);
                        if ($postNumber <= 0) {
                            $postNumber = (int)($total ?? count($posts)) - (((int)($page ?? 1) - 1) * (int)($perPage ?? count($posts))) - (int)$index;
                        }
                        $postNumberText = str_pad((string) max(1, $postNumber), 2, '0', STR_PAD_LEFT);
                        $category = $post->getCategory();
                        $cover = $post->displayCover();
                    @endphp
                    <article class="post-feature-card">
                        <a class="post-feature-cover" href="{{ $post->getUrl() }}" aria-label="{{ $post->title }}">
                            <img src="{{ $cover }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                        </a>
                        <div class="post-feature-body">
                            <p class="post-feature-meta">
                                <span class="post-number">{{ $postNumberText }}</span>
                                @if($category)<a href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}">{{ $category->name }}</a>@endif
                                <span class="post-feature-time">{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                            </p>
                            <h3 class="post-feature-title">
                                @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                                <a href="{{ $post->getUrl() }}">{{ $post->title }}</a>
                            </h3>
                            <p class="post-feature-excerpt">{{ $post->summaryOrContent(96) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
        @foreach($compactPosts as $compactIndex => $post)
            @php
                $index = (int)$compactIndex + count($featuredPosts);
                $postNumber = (int)($post->article_number ?? 0);
                if ($postNumber <= 0) {
                    $postNumber = (int)($total ?? count($posts)) - (((int)($page ?? 1) - 1) * (int)($perPage ?? count($posts))) - (int)$index;
                }
                $postNumberText = str_pad((string) max(1, $postNumber), 2, '0', STR_PAD_LEFT);
                $category = $post->getCategory();
            @endphp
            <article class="post-compact-row">
                <a class="post-compact-link" href="{{ $post->getUrl() }}">
                    <span class="post-compact-number">{{ $postNumberText }}</span>
                    <span class="post-compact-title">
                        @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                        {{ $post->title }}
                    </span>
                    <span class="post-compact-side" aria-hidden="true">
                        <span class="post-compact-date">{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                    </span>
                </a>
            </article>
        @endforeach
        </div>
        {!! $paginator ?? '' !!}
    </section>
@endsection
