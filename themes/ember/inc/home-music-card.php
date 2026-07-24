@php
    $music = $music ?? ($item ? $item->getRelation('music') : null);
    $comments = $comments ?? ($item ? ($item->getRelation('comments') ?: []) : []);
    $commentCount = $commentCount ?? count($comments);
@endphp
@if(!empty($music))
    @php
        $musicTitle = trim((string)($music->title ?? '未命名音乐'));
        $musicArtist = trim((string)($music->artist ?? '未知歌手'));
        $musicAlbum = trim((string)($music->album ?? ''));
        $musicCover = trim((string)($music->cover_url ?? ''));
        $musicAudio = trim((string)($music->audio_url ?? ''));
        $musicLines = $music->lyricsLines(6);
        $shareText = trim((string)($item->contentWithoutKeywords() ?? ''));
    @endphp
<article class="home-card home-card--music music-talk-card home-music-card" id="talk-{{ $item->id }}">
    <div class="music-card talk-music-player music-card-lyric-skin home-music-player"
         data-audio="{{ $musicAudio }}"
         data-lyrics-url="{{ $music->lyrics_url ?? '' }}"
         data-lyrics="{{ base64_encode((string)($music->lyrics ?? '')) }}"
         @if($musicCover !== '') style="--home-music-cover-bg: url('{{ htmlspecialchars($musicCover, ENT_QUOTES, 'UTF-8') }}');" @endif>
        <div class="home-music-bg" aria-hidden="true"></div>
        <div class="home-music-actions" aria-label="音乐互动">
            <button type="button" class="home-action music-share-like-btn" data-music-id="{{ $music->id }}" aria-label="喜欢这首音乐">
                @include('partials.ln-icon', ['name' => 'heart', 'trigger' => 'both'])<span data-music-like-count>{{ (int)($music->likes_count ?? 0) }}</span>
            </button>
        </div>
        <div class="home-music-layout">
            <div class="home-music-visual">
                <img class="home-music-tonearm-img" src="/themes/ember/assets/images/music/tonearm.png" alt="" loading="lazy" width="305" height="555">
                <div class="home-music-turntable">
                    <div class="home-music-record">
                        <div class="home-music-cover">
                            @if($musicCover !== '')
                                <img src="{{ $musicCover }}" alt="{{ $musicTitle }}" loading="lazy" decoding="async">
                            @else
                                <span>{{ $music->fallbackInitial() }}</span>
                            @endif
                        </div>
                        <div class="home-music-spindle"></div>
                    </div>
                    <button type="button" class="music-card-control music-card-btn home-music-play" aria-label="播放音乐：{{ $musicTitle }}">
                        @include('partials.ln-icon', ['name' => 'play', 'trigger' => 'both'])
                    </button>
                </div>
            </div>

            <div class="home-music-copy">
                <div class="home-music-head">
                    <div class="home-music-title-block">
                        <h2 class="music-card-title home-music-title">{{ $musicTitle }}</h2>
                        <p class="music-card-artist home-music-artist">{{ $musicArtist }}</p>
                    </div>
                </div>

                <div class="music-card-lyrics home-music-lyrics" data-music-card-lyrics>
                    @if(!empty($musicLines))
                        @foreach($musicLines as $line)
                            <span>{{ $line }}</span>
                        @endforeach
                    @else
                        <span>{{ $musicTitle }} - {{ $musicArtist }}</span>
                    @endif
                </div>

                <div class="music-card-progress home-music-progress">
                    <span class="music-card-cur">0:00</span>
                    <span class="music-card-track" aria-hidden="true"><span class="music-card-played"></span></span>
                    <span class="music-card-dur">{{ $music->duration ?: '0:00' }}</span>
                </div>
            </div>
        </div>
        <audio preload="none" src="{{ $musicAudio }}"></audio>
    </div>

    @include('partials.music-share-comments')
</article>
@endif
