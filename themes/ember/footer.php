</main>

        <footer class="site-footer">
            <div class="footer-copy">
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
            </div>
            <div class="footer-socials">
                <button type="button" class="footer-social footer-rss-copy" data-copy-url="/rss.xml" title="复制本站 RSS 地址" aria-label="复制本站 RSS 地址">
                    <i class="fa-solid fa-square-rss" aria-hidden="true"></i>
                </button>
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
    <script src="{{ $mainJs }}?v={{ \App\Services\ThemeManager::assetVersion($mainJs) }}" defer></script>
</body>
</html>
