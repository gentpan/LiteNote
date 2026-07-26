@php
    // 首页摘要区：用最新说说替代空的今日 events 计数
    $homeTalks = \App\Models\Talk::paginate(1, 3)['items'] ?? [];
@endphp
<section class="kero-section kero-talk-summary" aria-label="最新说说">
    <div class="kero-section-head">
        <h2 class="kero-section-label">说说</h2>
        <a class="kero-section-more" href="/talk">全部</a>
    </div>

    <div class="kero-rows">
        @if(empty($homeTalks))
            <div class="kero-row">
                <div class="kero-row-label">
                    <span class="kero-row-kind">talk</span>
                </div>
                <div class="kero-row-body">
                    <p class="kero-row-title">还没有说说</p>
                    <p class="kero-row-desc">短想法、近况与随手记会先出现在这里。</p>
                </div>
            </div>
        @else
            @foreach($homeTalks as $talk)
                @php
                    $isMusicTalk = (int)($talk->music_id ?? 0) > 0;
                    $preview = trim($talk->contentWithoutKeywords());
                    if ($preview === '') {
                        $preview = $isMusicTalk ? '分享了一首音乐' : '一条说说';
                    }
                    $preview = \App\Core\Helper::truncate($preview, 96);
                    $images = $talk->getImages();
                @endphp
                <a class="kero-row kero-row-link" href="/talk#talk-{{ $talk->id }}">
                    <div class="kero-row-label">
                        <span class="kero-row-kind">{{ $isMusicTalk ? 'music' : 'talk' }}</span>
                        <span class="kero-row-time">{!! \App\Core\Helper::timeTag($talk->publishedAt()) !!}</span>
                    </div>
                    <div class="kero-row-body">
                        <p class="kero-row-title">{{ $preview }}</p>
                        <p class="kero-row-meta">
                            @if(!empty($images))
                                <span><i class="fa-regular fa-image" aria-hidden="true"></i> {{ count($images) }}</span>
                            @endif
                            <span><i class="fa-regular fa-thumbs-up" aria-hidden="true"></i> {{ (int)($talk->likes_count ?? 0) }}</span>
                            <span><i class="fa-regular fa-comment" aria-hidden="true"></i> {{ (int)($talk->comments_count ?? 0) }}</span>
                        </p>
                    </div>
                </a>
            @endforeach
        @endif
    </div>
</section>
