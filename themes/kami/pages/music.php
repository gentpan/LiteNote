@extends('layouts.front')

@section('content')
    @php
        $featured = $list[0] ?? null;
        $songCount = count($list);
        $totalLikes = 0;
        $totalPlays = 0;
        foreach ($list as $song) {
            $totalLikes += (int)($song->likes_count ?? 0);
            $totalPlays += (int)($song->play_count ?? 0);
        }
    @endphp
    <section class="music-page">
        @if($featured)
            @php $featuredLines = $featured->lyricsLines(4); @endphp
            <div class="music-page-panel">
                <div class="music-player-suite">
                    <article class="music-disc-player" data-music-disc-player data-current-index="0" data-current-id="{{ $featured->id }}">
                        <audio preload="metadata" src="{{ $featured->audio_url }}"></audio>
                        <div class="music-vinyl-side">
                            <img class="music-tonearm-img" src="/themes/kami/assets/images/music/tonearm.png" alt="" loading="lazy" width="305" height="555">
                            <div class="music-record-shell">
                                <img class="music-record-border" src="/themes/kami/assets/images/music/record-border.png" alt="" loading="lazy" width="828" height="828">
                                <div class="music-cover-disc">
                                    <img class="music-cover-img {{ $featured->cover_url ? '' : 'is-hidden' }}" data-music-cover src="{{ $featured->cover_url ?: '' }}" alt="{{ $featured->title }}" loading="lazy">
                                    <span class="music-cover-fallback {{ $featured->cover_url ? 'is-hidden' : '' }}" data-music-cover-fallback>{{ $featured->fallbackInitial() }}</span>
                                </div>
                                <span class="music-record-hole" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="music-player-main">
                            <div class="music-player-top">
                                <div class="music-title-stack">
                                    <div class="music-title-line">
                                        <h1 data-music-title>{{ $featured->title }}</h1>
                                    </div>
                                    <p class="music-artist-line">
                                        <span data-music-artist>{{ $featured->artist ?: '未知歌手' }}</span>
                                        <span data-music-album>{{ $featured->album ? ' · '.$featured->album : '' }}</span>
                                    </p>
                                </div>
                                <button type="button" class="music-like-button" data-music-like aria-label="喜欢这首音乐">
                                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                                    <span data-music-likes>{{ (int)($featured->likes_count ?? 0) }}</span>
                                </button>
                            </div>
                            <div class="music-lyric-lines" data-music-lyrics>
                                @if(!empty($featuredLines))
                                    @foreach($featuredLines as $line)
                                        <span>{{ $line }}</span>
                                    @endforeach
                                @else
                                    <span>暂无歌词，按下播放让这首歌先响起来。</span>
                                @endif
                            </div>
                            <div class="music-progress-block">
                                <div class="music-progress-time">
                                    <span data-music-current>0:00</span>
                                    <span data-music-duration>{{ $featured->duration ?: '0:00' }}</span>
                                </div>
                                <button type="button" class="music-progress-track" data-music-progress aria-label="调整播放进度">
                                    <span data-music-progress-played></span>
                                </button>
                            </div>
                            <div class="music-control-row">
                                <button type="button" class="music-control-btn" data-music-prev aria-label="上一首">
                                    <i class="fa-solid fa-backward-step" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="music-control-btn music-control-play" data-music-play aria-label="播放/暂停">
                                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="music-control-btn" data-music-next aria-label="下一首">
                                    <i class="fa-solid fa-forward-step" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </article>

                    @php
                        $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
                        $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
                        $featuredComments = $featured->getRelation('comments') ?: [];
                    @endphp
                    <section class="comments music-comments" id="music-comments" data-music-comments>
                    <h3>
                        <span>歌曲评论</span>
                        <small data-music-comment-title>{{ $featured->title }}</small>
                        <em><span data-music-comment-count>{{ count($featuredComments) }}</span> 条</em>
                        <button type="button" class="music-comments-close" data-music-comments-toggle aria-label="收起音乐评论">
                            <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                        </button>
                    </h3>

                    @foreach($list as $index => $song)
                        @php
                            $songComments = $song->getRelation('comments') ?: [];
                            $songCommentIndex = 0;
                        @endphp
                        <div class="music-comment-thread {{ (int)$index === 0 ? 'is-active' : '' }}"
                             data-music-comment-thread="{{ $song->id }}"
                             data-comment-count="{{ count($songComments) }}"
                             @if((int)$index !== 0) hidden @endif>
                            @if(!empty($songComments))
                                <ul class="music-song-comment-list">
                                    @foreach($songComments as $cmt)
                                        @php
                                            $songCommentIndex++;
                                            $isFeaturedComment = $songCommentIndex === 1;
                                        @endphp
                                        <li class="music-song-comment {{ $isFeaturedComment ? 'is-featured' : '' }}" data-id="{{ $cmt->id }}">
                                            <span class="music-song-comment-avatar">
                                                @if($isFeaturedComment)
                                                    <img src="{{ $cmt->getAvatarUrl(80) }}" alt="" loading="lazy" width="48" height="48">
                                                @else
                                                    <i class="fa-solid fa-music" aria-hidden="true"></i>
                                                @endif
                                            </span>
                                            <div class="music-song-comment-body">
                                                <div class="music-song-comment-meta">
                                                    <strong>{{ $cmt->nickname }}</strong>
                                                    @if($isFeaturedComment)<em>博主</em>@endif
                                                    <span>{!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                                </div>
                                                <div class="music-song-comment-content">{{ preg_replace('/^@\S+\s*/u', '', (string) $cmt->content) }}</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="empty music-comment-empty">还没有评论，听完这一首再写一句吧。</p>
                            @endif
                        </div>
                    @endforeach

                    <form class="comment-form music-comment-form" method="post" action="/comment/submit" data-comment-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
                        <input type="hidden" name="music_id" value="{{ $featured->id }}">
                        <input type="hidden" name="parent_id" value="0">
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        @if(!empty($currentAdmin))
                            <input type="hidden" name="nickname" value="{{ $adminCommentName }}">
                            <input type="hidden" name="email" value="{{ $adminCommentEmail }}">
                        @else
                            <div class="form-row comment-profile-fields">
                                <input type="text" name="nickname" placeholder="昵称 *" required>
                                <input type="email" name="email" placeholder="邮箱{{ \App\Services\CommentSettingsService::emailRequired() ? ' *' : '（选填）' }}" {{ \App\Services\CommentSettingsService::emailRequired() ? 'required' : '' }}>
                                <input type="text" name="website" placeholder="网站(选填)">
                            </div>
                        @endif
                        <div class="music-comment-composer">
                            <textarea name="content" rows="4" placeholder="写下这首歌带来的片刻..." required></textarea>
                            <div class="comment-actions">
                                @if(!empty($currentAdmin))
                                    <button type="button" class="comment-profile-toggle comment-profile-toggle-admin" aria-label="当前登录头像">
                                        <img class="comment-admin-avatar" src="{{ $currentAdmin->getAvatarUrl(80) }}" alt="">
                                    </button>
                                @else
                                    <button type="button" class="comment-profile-toggle" data-comment-profile-toggle aria-label="切换评论资料" hidden>
                                        <img class="comment-admin-avatar" src="{{ \App\Services\Gravatar::url('', 80) }}" alt="" data-comment-profile-avatar data-comment-avatar-default="{{ \App\Services\Gravatar::url('', 80) }}">
                                    </button>
                                @endif
                                <button type="submit">提交评论</button>
                            </div>
                        </div>
                    </form>
                    </section>
                </div>

                <div class="music-track-list">
                    <div class="music-track-list-head">
                        <h2>播放列表</h2>
                        <span>{{ $songCount }} 首</span>
                    </div>
                    @foreach($list as $index => $song)
                        <button type="button"
                                id="music-{{ $song->id }}"
                                class="music-track-row {{ (int)$index === 0 ? 'is-active' : '' }}"
                                data-music-track
                                data-index="{{ $index }}"
                                data-id="{{ $song->id }}"
                                data-title="{{ $song->title }}"
                                data-artist="{{ $song->artist ?: '未知歌手' }}"
                                data-album="{{ $song->album ?? '' }}"
                                data-audio="{{ $song->audio_url }}"
                                data-cover="{{ $song->cover_url ?? '' }}"
                                data-duration="{{ $song->duration ?? '' }}"
                                data-likes="{{ (int)($song->likes_count ?? 0) }}"
                                data-comments="{{ count($song->getRelation('comments') ?: []) }}"
                                data-lyrics-url="{{ $song->lyrics_url ?? '' }}"
                                data-lyrics="{{ base64_encode((string)($song->lyrics ?? '')) }}">
                            <span class="music-track-cover">
                                @if($song->cover_url)
                                    <img src="{{ $song->cover_url }}" alt="" loading="lazy">
                                @else
                                    <span>{{ $song->fallbackInitial() }}</span>
                                @endif
                            </span>
                            <span class="music-track-info">
                                <strong>{{ $song->title }}</strong>
                                <small>{{ $song->artist ?: '未知歌手' }}</small>
                            </span>
                            <span class="music-track-side">
                                <span><i class="fa-regular fa-comment" aria-hidden="true"></i> <b data-music-track-comments>{{ count($song->getRelation('comments') ?: []) }}</b></span>
                                <span>{{ $song->duration ?: '试听' }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                <div class="music-rain-quota-card music-page-footer">
                    <div>
                        <span class="music-rain-kicker"><i class="fa-solid fa-cloud-sun" aria-hidden="true"></i> 阴雨额度</span>
                        <h2>{{ $featured ? $featured->title : '今日歌单' }}</h2>
                        <p>{{ $featured ? '给今天留一点可以循环播放的背景音乐。' : '还没有添加音乐，去后台录入第一首吧。' }}</p>
                    </div>
                    <div class="music-rain-stats">
                        <span><strong>{{ $total }}</strong> 首音乐</span>
                        <span><strong>{{ $totalPlays }}</strong> 次播放</span>
                        <span><strong>{{ $totalLikes }}</strong> 个喜欢</span>
                    </div>
                </div>
            </div>
        @else
            <p class="empty">还没有音乐，<a href="/admin/music">去后台添加</a></p>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
