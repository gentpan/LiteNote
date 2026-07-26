</main>

        <footer class="kero-foot">
            <p>
                <span>{{ $site['title'] ?? 'LiteNote' }}</span>
                <span class="kero-foot-sep">·</span>
                <span>Kero</span>
            </p>
            <div class="kero-foot-links">
                <button type="button" data-copy-url="/rss.xml">RSS</button>
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

    @if(empty($currentAdmin))
    @php
        $loginPasskeyAvailable = false;
        try {
            $loginPasskeyAvailable = (new \App\Services\PasskeyService())->hasAnyCredential();
        } catch (\Throwable $e) {
            $loginPasskeyAvailable = false;
        }
    @endphp
    <div class="login-overlay" data-login-overlay hidden>
        <div class="login-modal" role="dialog" aria-modal="true" aria-label="登录后台">
            <button type="button" class="login-modal-close" data-login-close aria-label="关闭"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></button>
            <div class="login-modal-head">
                <span class="login-modal-icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></span>
                <div>
                    <p class="login-modal-title">登录后台</p>
                    <p class="login-modal-subtitle">{{ $site['title'] ?? 'LiteNote' }}</p>
                </div>
            </div>
            <form class="login-modal-form" data-login-form>
                <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                <label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="username" placeholder="用户名" autocomplete="username" required></label>
                <label class="login-modal-field"><i class="fa-solid fa-lock" aria-hidden="true"></i><input name="password" type="password" placeholder="密码" autocomplete="current-password" required></label>
                <p class="login-modal-error" data-login-error hidden></p>
                <button type="submit" class="login-modal-submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> 登录</button>
                @if($loginPasskeyAvailable)
                <button type="button" class="login-modal-passkey" data-login-passkey><i class="fa-solid fa-key" aria-hidden="true"></i> 使用 Passkey 登录</button>
                @endif
                <a class="login-modal-forgot" href="/admin/forgot">忘记密码？</a>
            </form>
        </div>
    </div>
    @endif

    @if(!empty($site['site_analytics_code']))
        {!! \App\Core\Helper::sanitizeAnalyticsCode((string)$site['site_analytics_code']) !!}
    @endif
    <script src="{{ $themeJs }}?v={{ \App\Services\ThemeManager::assetVersion($themeJs) }}"></script>
</body>
</html>
