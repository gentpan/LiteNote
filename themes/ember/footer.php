</main>

        <footer class="site-footer">
            <div class="footer-copy">
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
                @if(!empty($site['beian']))<a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener">{{ $site['beian'] }}</a>@endif
            </div>
            <div class="footer-socials">
                <button type="button" class="footer-social footer-rss-copy" data-copy-url="/rss.xml" title="复制本站 RSS 地址" aria-label="复制本站 RSS 地址">
                    <i class="fa-solid fa-square-rss"></i>
                </button>
                @if(!empty($socials))
                    @foreach($socials as $s)
                        @if(($s['key'] ?? '') !== 'email' && strpos((string)($s['url'] ?? ''), 'mailto:') !== 0)
                            <a href="{{ $s['url'] }}" class="footer-social" title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}" target="_blank" rel="nofollow noopener">{!! $s['icon'] !!}</a>
                        @endif
                    @endforeach
                @endif
            </div>
        </footer>
    </div>

    <script src="{{ $mainJs }}?v={{ \App\Services\ThemeManager::assetVersion($mainJs) }}"></script>
</body>
</html>

