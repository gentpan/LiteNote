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
                        @endphp
                        @if($socialKey !== 'email' && !$isRssLink && strpos($socialUrl, 'mailto:') !== 0)
                            <a href="{{ $s['url'] }}" class="footer-social" title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}" target="_blank" rel="nofollow noopener">@php $footerSocialIcon = (string)($s['icon'] ?? ''); @endphp
                                @if(str_contains($footerSocialIcon, 'fa-x-twitter'))<i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                                @elseif(str_contains($footerSocialIcon, 'fa-github'))<i class="fa-brands fa-github" aria-hidden="true"></i>
                                @elseif(str_contains($footerSocialIcon, 'fa-rss') || str_contains($footerSocialIcon, 'fa-square-rss'))<i class="fa-solid fa-square-rss" aria-hidden="true"></i>
                                @else <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                @endif</a>
                        @endif
                    @endforeach
                @endif
            </div>
        </footer>
    </div>

    @if(!empty($site['site_analytics_code']))
        {!! $site['site_analytics_code'] !!}
    @endif
    @if(!empty($needsLiteZoom))
        <script src="/themes/ember/assets/litezoom.min.js?v={{ \App\Services\ThemeManager::assetVersion('/themes/ember/assets/litezoom.min.js') }}" defer></script>
    @endif
    <script src="{{ $mainJs }}?v={{ \App\Services\ThemeManager::assetVersion($mainJs) }}" defer></script>
</body>
</html>
