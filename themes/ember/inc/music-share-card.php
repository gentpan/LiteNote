@php
    /** @var \App\Models\Music|null $music */
    $music = $music ?? null;
@endphp
@if(!empty($music))
    @php
        $musicTitle = trim((string)($music->title ?? '未命名音乐'));
        $musicArtist = trim((string)($music->artist ?? '未知歌手'));
        $musicAlbum = trim((string)($music->album ?? ''));
        $musicCover = trim((string)($music->cover_url ?? ''));
        $musicAudio = trim((string)($music->audio_url ?? ''));
        $musicSubtitle = $musicArtist . ($musicAlbum !== '' ? ' · ' . $musicAlbum : '');
        $musicLines = $music->lyricsLines(4);
        $musicCoverStyle = $musicCover !== '' ? "background-image:url('" . htmlspecialchars($musicCover, ENT_QUOTES, 'UTF-8') . "')" : '';
    @endphp
    <div class="talk-music-share" data-music-id="{{ $music->id }}">
        <div class="music-card talk-music-player music-card-lyric-skin"
             data-audio="{{ $musicAudio }}"
             data-lyrics-url="{{ $music->lyrics_url ?? '' }}"
             data-lyrics="{{ base64_encode((string)($music->lyrics ?? '')) }}"
             role="button"
             tabindex="0"
             aria-label="播放音乐：{{ $musicTitle }}">
            <span class="music-card-lyric-copy">
                <span class="music-card-info">
                    <span class="music-card-title-row">
                        <span class="music-card-title">{{ $musicTitle }}</span>
                    </span>
                    <span class="music-card-artist">{{ $musicSubtitle }}</span>
                </span>
                <span class="music-card-lyrics" data-music-card-lyrics>
                    @if(!empty($musicLines))
                        @foreach($musicLines as $line)
                            <span>{{ $line }}</span>
                        @endforeach
                    @else
                        <span>{{ $musicTitle }} - {{ $musicArtist }}</span>
                    @endif
                </span>
            </span>
            <span class="music-card-art" aria-hidden="true">
                <span class="music-card-vinyl">
                    <span class="music-card-cover {{ $musicCover !== '' ? 'has-cover' : '' }}" @if($musicCoverStyle !== '') style="{!! $musicCoverStyle !!}" @endif>
                        @if($musicCover === '')<span>{{ $music->fallbackInitial() }}</span>@endif
                    </span>
                </span>
            </span>
            <span class="music-card-controls">
                <button type="button" class="music-card-control music-card-btn" aria-label="播放/暂停"><i class="fa-solid fa-play"></i></button>
            </span>
            <span class="music-card-progress">
                <span class="music-card-cur">0:00</span>
                <span class="music-card-track" aria-hidden="true"><span class="music-card-played"></span></span>
                <span class="music-card-dur">{{ $music->duration ?: '0:00' }}</span>
            </span>
            <audio preload="none" src="{{ $musicAudio }}"></audio>
        </div>
    </div>
@endif
