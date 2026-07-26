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
        $musicCoverStyle = $musicCover !== '' ? "background-image:url('" . htmlspecialchars($musicCover, ENT_QUOTES, 'UTF-8') . "')" : '';
    @endphp
    <div class="talk-music-share" data-music-id="{{ $music->id }}">
        <div class="talk-music-share-head">
            <span class="talk-music-share-type"><i class="fa-solid fa-music" aria-hidden="true"></i> 音乐说说</span>
        </div>
        <div class="music-card talk-music-player" data-audio="{{ $musicAudio }}" role="button" tabindex="0" aria-label="播放音乐：{{ $musicTitle }}">
            <span class="music-card-cover {{ $musicCover !== '' ? 'has-cover' : '' }}" @if($musicCoverStyle !== '') style="{!! $musicCoverStyle !!}" @endif>
                @if($musicCover === '')<span>{{ $music->fallbackInitial() }}</span>@endif
            </span>
            <span class="music-card-info">
                <span class="music-card-line">
                    <span class="music-card-title">{{ $musicTitle }}</span>
                    <span class="music-card-track" aria-hidden="true"><span class="music-card-played"></span></span>
                </span>
                <span class="music-card-artist">{{ $musicSubtitle }}</span>
            </span>
            <span class="music-card-controls">
                <button type="button" class="music-card-control music-card-skip" data-music-skip="-15" aria-label="后退 15 秒"><i class="fa-solid fa-backward-step" aria-hidden="true"></i></button>
                <button type="button" class="music-card-control music-card-btn" aria-label="播放/暂停"><i class="fa-solid fa-play" aria-hidden="true"></i></button>
                <button type="button" class="music-card-control music-card-skip" data-music-skip="15" aria-label="前进 15 秒"><i class="fa-solid fa-forward-step" aria-hidden="true"></i></button>
            </span>
            <span class="music-card-time"><span class="music-card-cur">0:00</span><span>/</span><span class="music-card-dur">{{ $music->duration ?: '0:00' }}</span></span>
            <audio preload="none" src="{{ $musicAudio }}"></audio>
        </div>
    </div>
@endif
