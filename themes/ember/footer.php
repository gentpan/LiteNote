</main>

        <footer class="site-footer">
            <div class="footer-copy">
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
            </div>
            <div class="footer-socials">
                <button type="button" class="footer-social footer-rss-copy" data-copy-url="/rss.xml" title="复制本站 RSS 地址" aria-label="复制本站 RSS 地址">
                    <i class="fa-solid fa-square-rss"></i>
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
                            <a href="{{ $s['url'] }}" class="footer-social" title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}" target="_blank" rel="nofollow noopener">{!! $s['icon'] !!}</a>
                        @endif
                    @endforeach
                @endif
            </div>
        </footer>
    </div>

    @if(!empty($site['site_analytics_code']))
        {!! $site['site_analytics_code'] !!}
    @endif
    <script src="/themes/ember/assets/litezoom.js?v={{ \App\Services\ThemeManager::assetVersion('/themes/ember/assets/litezoom.js') }}"></script>
    <script src="{{ $mainJs }}?v={{ \App\Services\ThemeManager::assetVersion($mainJs) }}"></script>
</body>
</html>
