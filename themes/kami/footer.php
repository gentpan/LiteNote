</main>

        <footer class="kami-footer">
            <div class="kami-mark" aria-hidden="true">紙</div>
            <div>
                <p class="kami-footer-title">{{ $site['title'] ?? 'LiteNote' }} · Kami</p>
                <p class="kami-footer-sub">Serif 承担层级，sans 承担功能，暖灰承担节奏，油墨蓝承担焦点。</p>
            </div>
            <div class="kami-footer-links">
                <button type="button" data-copy-url="/rss.xml" class="kami-copy-rss">RSS</button>
                @if(!empty($socials))
                    @foreach($socials as $s)
                        @php
                            $socialKey = strtolower((string)($s['key'] ?? ''));
                            $socialUrl = (string)($s['url'] ?? '');
                            if ($socialKey === 'x' || $socialKey === 'twitter') {
                                $socialUrl = '/xmarks';
                            } elseif ($socialKey === 'github') {
                                $socialUrl = 'https://xifeng.dev';
                            }
                        @endphp
                        @if(!in_array($socialKey, ['email', 'rss'], true) && strpos($socialUrl, 'mailto:') !== 0)
                            <a href="{{ $socialUrl }}" target="_blank" rel="nofollow noopener">{{ $s['label'] }}</a>
                        @endif
                    @endforeach
                @endif
            </div>
        </footer>
    </div>

    <div class="kami-side-actions" aria-label="快捷操作">
        @if(!empty($currentAdmin))
        <a class="side-admin-entry" href="/admin" aria-label="进入后台" title="进入后台"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></a>
        @else
        <button type="button" class="side-admin-entry" data-login-open aria-label="登录" title="登录"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></button>
        @endif
        @if(empty($currentAdmin))
        <div class="side-identity" data-side-identity>
            <button type="button" class="side-identity-trigger" data-identity-open aria-label="评论身份" title="评论身份">
                <img class="side-identity-avatar" data-side-identity-avatar alt="" hidden>
                <span class="side-identity-fallback" aria-hidden="true"><i class="fa-regular fa-circle-user" aria-hidden="true"></i></span>
            </button>
            <div class="side-identity-card" aria-hidden="true">
                <span class="side-identity-name" data-side-identity-name></span>
                <span class="side-identity-stat" data-side-identity-stat>设置评论身份，留下你的足迹</span>
            </div>
        </div>
        @endif
        <button type="button" class="side-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式">
            <span class="side-theme-icon" aria-hidden="true">
                <i class="fa-solid fa-moon theme-icon-moon" aria-hidden="true"></i>
                <i class="fa-solid fa-sun theme-icon-sun" aria-hidden="true"></i>
            </span>
            <span class="side-theme-label" data-theme-label>深色模式</span>
        </button>
    </div>

    @if(empty($currentAdmin))
    <div class="login-overlay" data-login-overlay hidden>
        <div class="login-modal" role="dialog" aria-modal="true" aria-label="登录后台">
            <button type="button" class="login-modal-close" data-login-close aria-label="关闭"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></button>
            <div class="login-modal-head">
                <span class="login-modal-icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></span>
                <div>
                    <p class="login-modal-title">登录后台</p>
                    <p class="login-modal-subtitle">{{ $site['title'] ?? 'LiteNote' }} 管理入口</p>
                </div>
            </div>
            <form class="login-modal-form" data-login-form>
                <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                <label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="username" placeholder="用户名" autocomplete="username" required></label>
                <label class="login-modal-field"><i class="fa-solid fa-lock" aria-hidden="true"></i><input name="password" type="password" placeholder="密码" autocomplete="current-password" required></label>
                <p class="login-modal-error" data-login-error hidden></p>
                <button type="submit" class="login-modal-submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> 登录</button>
                <button type="button" class="login-modal-passkey" data-login-passkey><i class="fa-solid fa-key" aria-hidden="true"></i> 使用 Passkey 登录</button>
                <a class="login-modal-forgot" href="/admin/forgot">忘记密码？</a>
            </form>
        </div>
    </div>
    @endif

    @if(!empty($site['site_analytics_code']))
        {!! $site['site_analytics_code'] !!}
    @endif
    <script src="{{ $themeJs }}?v={{ \App\Services\ThemeManager::assetVersion($themeJs) }}"></script>
</body>
</html>
