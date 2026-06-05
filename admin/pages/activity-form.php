@extends('layouts.admin')

@section('content')
    @php
        $meta = $item ? $item->metadata() : [];
        $metadataText = $meta ? json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $happenedValue = $item ? date('Y-m-d\TH:i', strtotime((string)$item->happened_at)) : date('Y-m-d\TH:i');
    @endphp
    <form method="post" action="/admin/activities/{{ $item->id }}/edit" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <div class="form-row">
            <div class="form-group">
                <label>类型</label>
                <select name="type">
                    @foreach($types as $key => $def)
                        <option value="{{ $key }}" {{ ($item->type ?? 'manual') === $key ? 'selected' : '' }}>{{ $def['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>动作</label>
                <select name="action">
                    @foreach($actions as $key => $label)
                        <option value="{{ $key }}" {{ ($item->action ?? 'manual') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>可见性</label>
                <select name="visibility">
                    @foreach(['public' => '公开', 'private' => '私密', 'hidden' => '隐藏但参与统计'] as $key => $label)
                        <option value="{{ $key }}" {{ ($item->visibility ?? 'public') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>标题 *</label>
            <input type="text" name="title" value="{{ $item->title ?? '' }}" required>
        </div>

        <div class="form-group">
            <label>内容</label>
            <textarea name="content" rows="5" placeholder="短评、备注或这条动态的上下文">{{ $item->content ?? '' }}</textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>来源</label>
                <input type="text" name="source" value="{{ $item->source ?? '' }}" readonly>
            </div>
            <div class="form-group flex-2">
                <label>链接</label>
                <input type="url" name="url" value="{{ $item->url ?? '' }}" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>发生时间</label>
                <input type="datetime-local" name="happened_at" value="{{ $happenedValue }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>外部 ID</label>
                <input type="text" name="external_id" value="{{ $item->external_id ?? '' }}" placeholder="用于同步去重">
            </div>
            <div class="form-group">
                <label>评分</label>
                <input type="number" min="0" max="5" step="0.5" name="rating" value="{{ $meta['rating'] ?? '' }}" placeholder="0-5">
            </div>
        </div>

        <div class="form-group">
            <label>Metadata JSON</label>
            <textarea name="metadata" rows="6" placeholder='{"artist":"杨丞琳","track":"过敏"}'>{{ $metadataText }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/activities" class="btn">取消</a>
        </div>
    </form>
@endsection
