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
                                $socialUrl = '/x';
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

    <button type="button" class="side-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式">
        <span class="side-theme-icon" aria-hidden="true">
            <i class="fa-solid fa-moon theme-icon-moon"></i>
            <i class="fa-solid fa-sun theme-icon-sun"></i>
        </span>
        <span class="side-theme-label" data-theme-label>深色模式</span>
    </button>

    <script src="{{ $themeJs }}?v={{ \App\Services\ThemeManager::assetVersion($themeJs) }}"></script>
</body>
</html>

