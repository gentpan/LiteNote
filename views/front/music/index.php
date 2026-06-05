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
        <div class="music-rain-quota-card">
            <div>
                <span class="music-rain-kicker"><i class="fa-solid fa-cloud-rain"></i> 阴雨额度</span>
                <h2>{{ $featured ? ($featured->mood ?: $featured->title) : '今日歌单' }}</h2>
                <p>{{ $featured ? ($featured->description ?: '给今天留一点可以循环播放的背景音乐。') : '还没有添加音乐，去后台录入第一首吧。' }}</p>
            </div>
            <div class="music-rain-stats">
                <span><strong>{{ $total }}</strong> 首音乐</span>
                <span><strong>{{ $totalPlays }}</strong> 次播放</span>
                <span><strong>{{ $totalLikes }}</strong> 个喜欢</span>
            </div>
        </div>

        @if($featured)
            @php $featuredLines = $featured->lyricsLines(4); @endphp
            <div class="music-player-suite">
                <article class="music-disc-player" data-music-disc-player data-current-index="0" data-current-id="{{ $featured->id }}">
                    <audio preload="metadata" src="{{ $featured->audio_url }}"></audio>
                    <div class="music-vinyl-side">
                        <img class="music-tonearm-img" src="/assets/images/music/tonearm.png" alt="" loading="lazy" width="305" height="555">
                        <div class="music-record-shell">
                            <img class="music-record-border" src="/assets/images/music/record-border.png" alt="" loading="lazy" width="828" height="828">
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
                                    <span class="music-vip-badge">VIP</span>
                                </div>
                                <p class="music-artist-line">
                                    <span data-music-artist>{{ $featured->artist ?: '未知歌手' }}</span>
                                    <span data-music-album>{{ $featured->album ? ' · '.$featured->album : '' }}</span>
                                </p>
                            </div>
                            <button type="button" class="music-like-button" data-music-like aria-label="喜欢这首音乐">
                                <i class="fa-regular fa-heart"></i>
                                <span data-music-likes>{{ (int)($featured->likes_count ?? 0) }}</span>
                            </button>
                        </div>
                        <div class="music-lyric-lines" data-music-lyrics>
                            @if(!empty($featuredLines))
                                @foreach($featuredLines as $line)
                                    <span>{{ $line }}</span>
                                @endforeach
                            @else
                                <span>{{ $featured->description ?: '暂无歌词，按下播放让这首歌先响起来。' }}</span>
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
                                <i class="fa-solid fa-backward-step"></i>
                            </button>
                            <button type="button" class="music-control-btn music-control-play" data-music-play aria-label="播放/暂停">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <button type="button" class="music-control-btn" data-music-next aria-label="下一首">
                                <i class="fa-solid fa-forward-step"></i>
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
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </h3>

                @foreach($list as $index => $song)
                    @php
                        $songComments = $song->getRelation('comments') ?: [];
                        $songThreads = \App\Core\Helper::nestComments($songComments);
                        $songThreadIndex = 0;
                    @endphp
                    <div class="music-comment-thread {{ (int)$index === 0 ? 'is-active' : '' }}"
                         data-music-comment-thread="{{ $song->id }}"
                         data-comment-count="{{ count($songComments) }}"
                         @if((int)$index !== 0) hidden @endif>
                        @if(!empty($songComments))
                            <ul class="comment-list">
                                @foreach($songThreads as $thread)
                                    @php
                                        $cmt = $thread['comment'];
                                        $songThreadIndex++;
                                    @endphp
                                    <li class="comment-item {{ $songThreadIndex === 1 ? 'music-comment-featured' : 'music-comment-card' }}" data-id="{{ $cmt->id }}">
                                        <div class="comment-body">
                                            <div class="comment-meta">
                                                <strong>{{ $cmt->nickname }}</strong>
                                                <span class="ct">· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                                <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                                            </div>
                                            <div class="comment-content">{{ $cmt->content }}</div>
                                        </div>
                                        @if(!empty($thread['replies']))
                                            <ul class="comment-reply-list">
                                                @foreach($thread['replies'] as $reply)
                                                    <li class="comment-item comment-reply" data-id="{{ $reply->id }}">
                                                        <div class="comment-body">
                                                            <div class="comment-meta">
                                                                <strong>{{ $reply->nickname }}</strong>
                                                                @if(!empty($reply->reply_to_name))<span class="reply-arrow">›</span><span class="reply-target">{{ $reply->reply_to_name }}</span>@endif
                                                                <span class="ct">· {!! \App\Core\Helper::timeTag($reply->created_at) !!}</span>
                                                                <button type="button" class="comment-reply-btn" data-parent-id="{{ $reply->id }}" data-nickname="{{ $reply->nickname }}">回复</button>
                                                            </div>
                                                            <div class="comment-content">{{ preg_replace('/^@\S+\s*/u', '', (string) $reply->content) }}</div>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
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
                        <div class="comment-admin-bar">
                            <img class="comment-admin-avatar" src="{{ $currentAdmin->getAvatarUrl(80) }}" alt="{{ $adminCommentName }}">
                            <div class="comment-admin-info">
                                <span class="comment-admin-name">{{ $adminCommentName }}</span>
                                @if($adminCommentEmail !== '')<span class="comment-admin-email">{{ $adminCommentEmail }}</span>@endif
                            </div>
                            <a class="comment-admin-logout" href="/admin/logout">注销</a>
                        </div>
                        <input type="hidden" name="nickname" value="{{ $adminCommentName }}">
                        <input type="hidden" name="email" value="{{ $adminCommentEmail }}">
                    @else
                        <div class="form-row">
                            <input type="text" name="nickname" placeholder="昵称 *" required>
                            <input type="email" name="email" placeholder="邮箱 *" required>
                            <input type="text" name="website" placeholder="网站(选填)">
                        </div>
                    @endif
                    <textarea name="content" rows="4" placeholder="写下这首歌带来的片刻..." required></textarea>
                    <div class="comment-actions">
                        @include('partials.comment-captcha')
                        <button type="submit">提交评论</button>
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
                    @php
                        $songPublishedAt = $song->publishedAt();
                        $songPublishedLabel = \App\Core\Helper::formatDate($songPublishedAt, 'Y-m-d');
                    @endphp
                    <button type="button"
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
                            data-description="{{ $song->description ?? '' }}"
                            data-mood="{{ $song->mood ?? '' }}"
                            data-likes="{{ (int)($song->likes_count ?? 0) }}"
                            data-comments="{{ count($song->getRelation('comments') ?: []) }}"
                            data-published="{{ $songPublishedAt }}"
                            data-lyrics="{{ base64_encode((string)($song->lyrics ?? '')) }}">
                        <span class="music-track-number">{{ str_pad((string)((int)$index + 1), 2, '0', STR_PAD_LEFT) }}</span>
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
                            @if($song->mood)<em>#{{ $song->mood }}</em>@endif
                            <span><i class="fa-regular fa-calendar"></i> {{ $songPublishedLabel }}</span>
                            <span><i class="fa-regular fa-comment"></i> <b data-music-track-comments>{{ count($song->getRelation('comments') ?: []) }}</b></span>
                            <span>{{ $song->duration ?: '试听' }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        @else
            <p class="empty">还没有音乐，<a href="/admin/music">去后台添加</a></p>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
