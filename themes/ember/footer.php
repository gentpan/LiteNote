</main>

        <footer class="site-footer">
            <div class="footer-copy">
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
            </div>
            <div class="footer-socials">
                <button type="button" class="footer-social footer-rss-copy" data-copy-url="/rss.xml" title="复制本站 RSS 地址" aria-label="复制本站 RSS 地址">
                    <i class="fa-solid fa-square-rss" aria-hidden="true"></i>
                </button>
                <button type="button" class="footer-social footer-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式">
                    <span class="side-theme-icon" aria-hidden="true">
                        <span class="theme-icon-moon">@include('partials.ln-icon', ['name' => 'moon'])</span>
                        <span class="theme-icon-sun">@include('partials.ln-icon', ['name' => 'sun'])</span>
                    </span>
                    <span data-theme-label hidden>深色模式</span>
                </button>
                @if(!empty($currentAdmin))
                    <a class="footer-social footer-account-button" href="/admin" aria-label="进入后台" title="进入后台">
                        @include('partials.ln-icon', ['name' => 'gauge'])
                    </a>
                @else
                    <div class="footer-identity side-identity" data-side-identity>
                        <button type="button" class="footer-social footer-account-button nav-account-btn" data-account-open data-login-open aria-label="设置评论身份" title="设置评论身份">
                            <img class="side-identity-avatar" data-side-identity-avatar alt="" hidden>
                            <span class="side-identity-fallback" aria-hidden="true">@include('partials.ln-icon', ['name' => 'user'])</span>
                        </button>
                        <span data-side-identity-name hidden></span>
                        <span data-side-identity-stat hidden>设置评论身份 / 注册</span>
                    </div>
                @endif
                @if(!empty($socials))
                    @foreach($socials as $s)
                        @php
                            $socialKey = strtolower((string)($s['key'] ?? ''));
                            $socialUrl = (string)($s['url'] ?? '');
                            $socialPath = parse_url($socialUrl, PHP_URL_PATH) ?: $socialUrl;
                            $isRssLink = in_array($socialKey, ['rss', 'feed'], true)
                                || in_array(rtrim($socialPath, '/'), ['/rss.xml', '/feed'], true);
                            $socialQr = trim((string)($s['qr'] ?? ''));
                            $hasSocialQr = $socialQr !== '' && in_array($socialKey, ['wechat', 'weixin', 'telegram'], true);
                            $isQrOnlyLink = $socialUrl === '' || $socialUrl === '#';
                            $socialHref = $isQrOnlyLink ? '#' : $socialUrl;
                            $isMailLink = str_starts_with($socialUrl, 'mailto:');
                        @endphp
                        @if(!$isRssLink)
                            <a href="{{ $socialHref }}" class="footer-social{{ $hasSocialQr ? ' footer-social-has-qr' : '' }}" title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}" @if($isQrOnlyLink) onclick="return false" @elseif(!$isMailLink) target="_blank" rel="nofollow noopener" @endif>
                                @php $footerSocialIcon = (string)($s['icon'] ?? ''); @endphp
                                @if($footerSocialIcon)
                                    {!! $footerSocialIcon !!}
                                @else
                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                @endif
                                @if($hasSocialQr)
                                    <span class="footer-social-qr" role="tooltip">
                                        <img src="{{ $socialQr }}" alt="{{ $s['label'] }}二维码" loading="lazy">
                                    </span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                @endif
            </div>
        </footer>
    </div>

    @if(!empty($site['site_analytics_code']))
        {!! \App\Core\Helper::sanitizeAnalyticsCode((string)$site['site_analytics_code']) !!}
    @endif
    @if(!empty($needsLiteZoom))
        <script src="/themes/ember/assets/litezoom.min.js?v={{ \App\Services\ThemeManager::assetVersion('/themes/ember/assets/litezoom.min.js') }}" defer></script>
    @endif
    @php $authUtilsJs = \App\Services\ThemeManager::scriptAsset('/themes/shared/assets/front-auth-utils.js'); @endphp
    <script src="{{ $authUtilsJs }}?v={{ \App\Services\ThemeManager::assetVersion($authUtilsJs) }}" defer></script>
    <script src="{{ $lnIconsJs ?? \App\Services\ThemeManager::scriptAsset('/themes/ember/assets/icons/ln-icons.js') }}?v={{ \App\Services\ThemeManager::assetVersion($lnIconsJs ?? '/themes/ember/assets/icons/ln-icons.js') }}" defer></script>
    <script src="{{ $mainJs }}?v={{ \App\Services\ThemeManager::assetVersion($mainJs) }}" defer></script>
</body>
</html>
