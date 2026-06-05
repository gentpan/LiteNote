@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <button class="btn btn-primary" id="add-cat-btn" type="button">+ 新建分类</button>
    </div>

    <form id="cat-form" method="post" action="/admin/categories/save" class="admin-form hidden">
        <h3 id="cat-form-title" class="admin-form-title">新建分类</h3>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <input type="hidden" name="id" id="cat-id" value="">
        <div class="form-row">
            <div class="form-group">
                <label>名称 *</label>
                <input type="text" name="name" id="cat-name" required>
            </div>
            <div class="form-group">
                <label>slug</label>
                <input type="text" name="slug" id="cat-slug" placeholder="留空自动生成">
            </div>
        </div>

        <div class="form-group">
            <label>图标（FontAwesome 类名）</label>
            <div class="icon-field">
                <span class="icon-preview"><i id="cat-icon-preview" class="fa-regular fa-folder"></i></span>
                <input type="text" name="icon" id="cat-icon" placeholder="例如 fa-solid fa-rocket（留空用默认文件夹）">
            </div>
            <div class="icon-presets" id="cat-icon-presets">
                @php
                    $presetIcons = [
                        'fa-regular fa-folder', 'fa-solid fa-code', 'fa-solid fa-pen-nib', 'fa-regular fa-lightbulb',
                        'fa-solid fa-book', 'fa-solid fa-camera', 'fa-solid fa-music', 'fa-solid fa-gamepad',
                        'fa-regular fa-heart', 'fa-solid fa-mug-hot', 'fa-solid fa-plane', 'fa-solid fa-rocket',
                        'fa-solid fa-flask', 'fa-solid fa-terminal', 'fa-solid fa-palette', 'fa-solid fa-leaf',
                    ];
                @endphp
                @foreach($presetIcons as $pi)
                    <button type="button" class="icon-preset" data-icon="{{ $pi }}" title="{{ $pi }}"><i class="{{ $pi }}"></i></button>
                @endforeach
            </div>
        </div>

        <div class="form-group">
            <label>颜色（下拉菜单 / 分类页配色）</label>
            <input type="hidden" name="color" id="cat-color" value="">
            <div class="color-presets" id="cat-color-presets">
                @foreach(['0'=>'绿','1'=>'蓝','2'=>'橙','3'=>'粉','4'=>'紫','5'=>'青'] as $ci => $label)
                    <button type="button" class="color-preset cat-color-{{ $ci }}" data-color="{{ $ci }}" title="{{ $label }}"></button>
                @endforeach
                <button type="button" class="color-preset color-auto" data-color="" title="自动(按分类自动取色)">自动</button>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group flex-2">
                <label>描述</label>
                <input type="text" name="description" id="cat-desc" placeholder="分类简介（可选）">
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" id="cat-sort" value="0">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="cat-submit-btn">保存</button>
            <button type="button" class="btn" id="cat-cancel-btn">取消</button>
        </div>
    </form>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>图标</th>
                <th>名称</th>
                <th>slug</th>
                <th>描述</th>
                <th>文章数</th>
                <th>排序</th>
                <th>菜单显示</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $c)
            <tr>
                <td>{{ $c->id }}</td>
                <td class="cat-ico-cell"><i class="{{ $c->iconClass() }}"></i></td>
                <td>{{ $c->name }}</td>
                <td><code>{{ $c->slug }}</code></td>
                <td class="muted">{{ $c->description }}</td>
                <td>{{ $counts[$c->id] ?? 0 }}</td>
                <td>{{ $c->sort }}</td>
                <td>
                    <form method="post" action="/admin/categories/toggle" class="nav-toggle-form" data-ajax-toggle>
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $c->id }}">
                        <input type="hidden" name="show_in_nav" value="0">
                        <label class="cat-switch" title="{{ $c->show_in_nav ? '点击从菜单栏隐藏' : '点击在菜单栏显示' }}">
                            <input type="checkbox"
                                   name="show_in_nav"
                                   value="1"
                                   data-no-dirty
                                   aria-label="菜单显示"
                                   {{ $c->show_in_nav ? 'checked' : '' }}>
                            <span class="cat-switch-slider"></span>
                        </label>
                    </form>
                </td>
                <td>
                    <div class="admin-action-bar">
                        <button type="button"
                            class="admin-action-btn admin-action-edit edit-cat-btn"
                            title="编辑"
                            aria-label="编辑"
                            data-id="{{ $c->id }}"
                            data-name="{{ $c->name }}"
                            data-slug="{{ $c->slug }}"
                            data-description="{{ $c->description }}"
                            data-sort="{{ $c->sort }}"
                            data-icon="{{ $c->icon }}"
                            data-color="{{ $c->color }}">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                        <form method="post" action="/admin/categories/delete"
                              data-confirm="确定删除这个分类？删除后该分类下文章将变为未分类。"
                              data-confirm-title="删除分类"
                              data-confirm-text="确认删除">
                            <input type="hidden" name="_csrf" value="{{ $csrf }}">
                            <input type="hidden" name="id" value="{{ $c->id }}">
                            <button type="submit"
                                    class="admin-action-btn admin-action-delete"
                                    title="删除"
                                    aria-label="删除">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <script>
    (function () {
        var form = document.getElementById('cat-form');
        var title = document.getElementById('cat-form-title');
        var submitBtn = document.getElementById('cat-submit-btn');
        var preview = document.getElementById('cat-icon-preview');
        var f = {
            id:      document.getElementById('cat-id'),
            name:    document.getElementById('cat-name'),
            slug:    document.getElementById('cat-slug'),
            sort:    document.getElementById('cat-sort'),
            icon:    document.getElementById('cat-icon'),
            desc:    document.getElementById('cat-desc'),
            color:   document.getElementById('cat-color')
        };

        function setPreview(cls) {
            cls = (cls || '').trim();
            preview.className = (/^[a-zA-Z0-9 _-]+$/.test(cls) && cls !== '') ? cls : 'fa-regular fa-folder';
        }

        function setColor(val) {
            val = (val === undefined || val === null) ? '' : String(val);
            f.color.value = val;
            document.querySelectorAll('.color-preset').forEach(function (b) {
                b.classList.toggle('is-active', (b.dataset.color || '') === val);
            });
        }

        f.icon.addEventListener('input', function () { setPreview(this.value); });

        document.querySelectorAll('.icon-preset').forEach(function (b) {
            b.addEventListener('click', function () {
                f.icon.value = b.dataset.icon;
                setPreview(b.dataset.icon);
            });
        });

        document.querySelectorAll('.color-preset').forEach(function (b) {
            b.addEventListener('click', function () { setColor(b.dataset.color || ''); });
        });

        function resetForm() {
            f.id.value = ''; f.name.value = ''; f.slug.value = ''; f.sort.value = '0';
            f.icon.value = ''; f.desc.value = '';
            setPreview('');
            setColor('');
            title.textContent = '新建分类';
            submitBtn.textContent = '保存';
        }

        document.getElementById('add-cat-btn').addEventListener('click', function () {
            if (form.classList.contains('hidden') || f.id.value !== '') {
                resetForm();
                form.classList.remove('hidden');
                f.name.focus();
            } else {
                form.classList.add('hidden');
            }
        });

        document.getElementById('cat-cancel-btn').addEventListener('click', function () {
            form.classList.add('hidden');
        });

        document.querySelectorAll('.edit-cat-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var d = btn.dataset;
                f.id.value = d.id;
                f.name.value = d.name || '';
                f.slug.value = d.slug || '';
                f.sort.value = d.sort || '0';
                f.icon.value = d.icon || '';
                f.desc.value = d.description || '';
                setPreview(d.icon || '');
                setColor(d.color || '');
                title.textContent = '编辑分类';
                submitBtn.textContent = '更新';
                form.classList.remove('hidden');
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                f.name.focus();
            });
        });
    })();
    </script>
@endsection
