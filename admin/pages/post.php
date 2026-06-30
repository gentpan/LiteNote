@extends('layouts.admin')

@section('content')
    @php
        $postStatusLabels = \App\Enums\PostStatus::options();
    @endphp
    <div class="admin-toolbar post-admin-toolbar">
        <a class="btn btn-primary" href="/admin/posts/create"><i class="fa-solid fa-pen"></i> 写文章</a>
        <a class="btn" href="/admin/posts/import"><i class="fa-solid fa-file-import"></i> 导入</a>
        <button type="button" class="btn" data-open-category-dialog><i class="fa-solid fa-folder-tree"></i> 分类</button>
        <button type="button" class="btn" data-open-post-font-dialog><i class="fa-solid fa-font"></i> 字体设置</button>
        <form method="get" class="admin-search post-admin-search">
            <input type="text" name="q" value="{{ $keyword }}" placeholder="搜索标题...">
            <select name="status">
                <option value="">全部</option>
                <option value="published" {{ ($status ?? '') === 'published' ? 'selected' : '' }}>已发布</option>
                <option value="draft" {{ ($status ?? '') === 'draft' ? 'selected' : '' }}>草稿</option>
            </select>
            <button type="submit">筛选</button>
        </form>
    </div>

    @include('partials.admin-category-dialog')

    @php
        $fontOptions = $articleFontOptions ?? [];
        $fontCurrent = $articleFontCurrent ?? 'source-han-serif-cn';
        $titleFontCurrent = $titleFontCurrent ?? 'kuaikanshijieti';
    @endphp
    <div class="admin-modal-backdrop" id="post-font-dialog" hidden>
        <section class="admin-modal post-font-dialog" role="dialog" aria-modal="true" aria-labelledby="post-font-dialog-title">
            <div class="admin-modal-head">
                <div>
                    <h3 id="post-font-dialog-title"><i class="fa-solid fa-font"></i> 文章字体设置</h3>
                    <p>分别设置前台文章正文与详情页标题字体，保存后立即生效。</p>
                </div>
                <button type="button" class="admin-modal-close" data-post-font-dialog-close aria-label="关闭"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" action="/admin/posts/font-settings" class="admin-form post-font-dialog-body" data-no-dirty-form>
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <section class="post-font-section" data-font-group="article_font">
                    <h4 class="post-font-section-title">正文字体</h4>
                    <input type="search" class="post-font-search" placeholder="搜索字体名称..." data-font-search="article_font" autocomplete="off">
                    <div class="post-font-options" data-font-options="article_font">
                        @foreach($fontOptions as $fontKey => $fontOption)
                            <label class="post-font-option {{ $fontCurrent === $fontKey ? 'is-active' : '' }}" data-font-option data-font-label="{{ strtolower(($fontOption['label'] ?? $fontKey) . ' ' . $fontKey) }}" data-font-css="{{ $fontOption['css'] ?? '' }}">
                                <input type="radio" name="article_font" value="{{ $fontKey }}" {{ $fontCurrent === $fontKey ? 'checked' : '' }}>
                                <span class="post-font-option-main">
                                    <span class="post-font-option-title">{{ $fontOption['label'] ?? $fontKey }}</span>
                                    <span class="post-font-option-desc">{{ $fontOption['description'] ?? '' }}</span>
                                    @if(!empty($fontOption['preview']))
                                        <img class="post-font-option-preview-img" src="{{ $fontOption['preview'] }}" alt="{{ $fontOption['label'] ?? $fontKey }} 预览" loading="lazy" decoding="async">
                                    @endif
                                    <span class="post-font-option-preview" style="font-family: {{ $fontOption['family'] ?? 'inherit' }};">轻舟已过万重山，文章正文会使用这个字体。</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
                <section class="post-font-section" data-font-group="title_font">
                    <h4 class="post-font-section-title">标题字体</h4>
                    <input type="search" class="post-font-search" placeholder="搜索字体名称..." data-font-search="title_font" autocomplete="off">
                    <div class="post-font-options" data-font-options="title_font">
                        @foreach($fontOptions as $fontKey => $fontOption)
                            <label class="post-font-option {{ $titleFontCurrent === $fontKey ? 'is-active' : '' }}" data-font-option data-font-label="{{ strtolower(($fontOption['label'] ?? $fontKey) . ' ' . $fontKey) }}" data-font-css="{{ $fontOption['css'] ?? '' }}">
                                <input type="radio" name="title_font" value="{{ $fontKey }}" {{ $titleFontCurrent === $fontKey ? 'checked' : '' }}>
                                <span class="post-font-option-main">
                                    <span class="post-font-option-title">{{ $fontOption['label'] ?? $fontKey }}</span>
                                    <span class="post-font-option-desc">{{ $fontOption['description'] ?? '' }}</span>
                                    @if(!empty($fontOption['preview']))
                                        <img class="post-font-option-preview-img" src="{{ $fontOption['preview'] }}" alt="{{ $fontOption['label'] ?? $fontKey }} 预览" loading="lazy" decoding="async">
                                    @endif
                                    <span class="post-font-option-preview" style="font-family: {{ $fontOption['family'] ?? 'inherit' }};">潘吉诃德的骑士精神，文章标题会使用这个字体。</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
                <div class="admin-dialog-actions post-font-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> 保存设置</button>
                    <button type="button" class="btn" data-post-font-dialog-close>取消</button>
                </div>
            </form>
        </section>
    </div>

    <script>
    (function () {
        var dialog = document.getElementById('post-font-dialog');
        var openBtn = document.querySelector('[data-open-post-font-dialog]');
        if (!dialog || !openBtn) return;

        function openDialog() {
            dialog.hidden = false;
            document.body.classList.add('admin-dialog-open');
            dialog.querySelectorAll('input[type="radio"]:checked').forEach(function (radio) {
                ensureFontCss(radio.closest('[data-font-option]'));
            });
            setTimeout(function () {
                var checked = dialog.querySelector('input[name="article_font"]:checked')
                    || dialog.querySelector('input[name="title_font"]:checked');
                if (checked) checked.focus();
            }, 0);
        }

        function ensureFontCss(option) {
            if (!option) return;
            var css = option.getAttribute('data-font-css') || '';
            if (!css || document.querySelector('link[data-post-font-css="' + css + '"]')) {
                return;
            }
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = css;
            link.setAttribute('data-post-font-css', css);
            document.head.appendChild(link);
        }

        dialog.querySelectorAll('[data-font-search]').forEach(function (input) {
            var group = input.getAttribute('data-font-search') || '';
            var container = dialog.querySelector('[data-font-options="' + group + '"]');
            if (!container) return;
            input.addEventListener('input', function () {
                var keyword = String(input.value || '').trim().toLowerCase();
                container.querySelectorAll('[data-font-option]').forEach(function (option) {
                    var label = option.getAttribute('data-font-label') || '';
                    option.hidden = keyword !== '' && label.indexOf(keyword) === -1;
                });
            });
        });

        function closeDialog() {
            dialog.hidden = true;
            document.body.classList.remove('admin-dialog-open');
            openBtn.focus();
        }

        openBtn.addEventListener('click', openDialog);
        dialog.querySelectorAll('[data-post-font-dialog-close]').forEach(function (btn) {
            btn.addEventListener('click', closeDialog);
        });
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) closeDialog();
        });
        dialog.querySelectorAll('[data-font-group]').forEach(function (section) {
            var fieldName = section.getAttribute('data-font-group') || '';
            if (!fieldName) return;
            section.querySelectorAll('input[name="' + fieldName + '"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    ensureFontCss(radio.closest('[data-font-option]'));
                    section.querySelectorAll('[data-font-option]').forEach(function (option) {
                        var input = option.querySelector('input[name="' + fieldName + '"]');
                        option.classList.toggle('is-active', !!input && input.checked);
                    });
                });
            });
        });
        document.addEventListener('keydown', function (event) {
            if (!dialog.hidden && event.key === 'Escape') closeDialog();
        });
    })();
    </script>

    <form method="post" action="/admin/posts/bulk"
          data-no-dirty-form
          data-confirm="确定执行所选批量操作？"
          data-confirm-title="确认执行操作"
          data-confirm-tone="primary"
          data-confirm-text="确认执行">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <table class="admin-table admin-action-table admin-action-table-wide post-admin-table">
            <colgroup>
                <col class="post-col-check">
                <col class="post-col-id">
                <col class="post-col-title">
                <col class="post-col-category">
                <col class="post-col-views">
                <col class="post-col-comments">
                <col class="post-col-status">
                <col class="post-col-date">
                <col class="post-col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th><input type="checkbox" id="check-all"></th>
                    <th>ID</th>
                    <th>标题</th>
                    <th>分类</th>
                    <th>浏览</th>
                    <th>评论</th>
                    <th>状态</th>
                    <th>发布时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($posts as $p)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $p->id }}"></td>
                    <td>{{ $p->id }}</td>
                    <td>
                        @if($p->is_top)<span class="badge badge-top" data-post-badge="is_top">顶</span>@endif
                        @if($p->is_recommend)<span class="badge badge-recommend" data-post-badge="is_recommend">荐</span>@endif
                        <a href="{{ $p->getUrl() }}" target="_blank">{{ $p->title }}</a>
                    </td>
                    <td>{{ $p->getCategory()?->name }}</td>
                    <td>{{ $p->views }}</td>
                    <td>{{ $p->comments_count }}</td>
                    <td><span class="status status-{{ $p->status }}">{{ $postStatusLabels[$p->status] ?? $p->status }}</span></td>
                    <td>{!! !empty($p->published_at) ? \App\Core\Helper::dateTimeTag((string)$p->published_at) : '<span class="muted">—</span>' !!}</td>
                    <td>
                        <div class="post-action-bar">
                            <a href="/admin/posts/{{ $p->id }}/edit" class="post-action-btn post-action-edit" title="编辑" aria-label="编辑">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <button type="button"
                                    class="post-action-btn post-action-top {{ $p->is_top ? 'is-active' : '' }}"
                                    title="{{ $p->is_top ? '取消置顶' : '置顶' }}"
                                    aria-label="{{ $p->is_top ? '取消置顶' : '置顶' }}"
                                    data-post-toggle
                                    data-field="is_top"
                                    data-id="{{ $p->id }}"
                                    data-active="{{ $p->is_top ? '1' : '0' }}"
                                    data-action="/admin/posts/{{ $p->id }}/toggle"
                                    data-csrf="{{ $csrf }}">
                                <i class="fa-solid fa-thumbtack"></i>
                            </button>
                            <button type="button"
                                    class="post-action-btn post-action-recommend {{ $p->is_recommend ? 'is-active' : '' }}"
                                    title="{{ $p->is_recommend ? '取消推荐' : '推荐' }}"
                                    aria-label="{{ $p->is_recommend ? '取消推荐' : '推荐' }}"
                                    data-post-toggle
                                    data-field="is_recommend"
                                    data-id="{{ $p->id }}"
                                    data-active="{{ $p->is_recommend ? '1' : '0' }}"
                                    data-action="/admin/posts/{{ $p->id }}/toggle"
                                    data-csrf="{{ $csrf }}">
                                <i class="fa-solid fa-star"></i>
                            </button>
                            <button type="submit"
                                    form="post-delete-form-{{ $p->id }}"
                                    class="post-action-btn post-action-delete"
                                    title="删除"
                                    aria-label="删除">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="bulk-bar" data-bulk-bar hidden>
            <select name="bulk_action">
                <option value="">批量操作</option>
                <option value="publish">发布</option>
                <option value="draft">转草稿</option>
                <option value="top">置顶</option>
                <option value="untop">取消置顶</option>
                <option value="delete">删除</option>
            </select>
            <button type="submit"><i class="fa-solid fa-arrow-right"></i> 应用</button>
        </div>
    </form>

    @foreach($posts as $p)
        <form id="post-delete-form-{{ $p->id }}" method="post" action="/admin/posts/{{ $p->id }}/delete" class="hidden"
              data-no-dirty-form
              data-confirm="确定删除这篇文章？删除后关联评论也会一并移除，此操作不可撤销。"
              data-confirm-title="删除文章"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
        </form>
    @endforeach
    {!! $paginator ?? '' !!}
@endsection
