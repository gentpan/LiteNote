@extends('layouts.admin')

@section('content')
    @php
        $s = $settings ?? [];
        $driver = (string)($s['driver'] ?? 'sendflare');
        $statusText = $configured ? '已配置' : '未就绪';
        $statusClass = $configured ? 'status-published' : 'status-draft';
        $driverLabels = [
            'sendflare' => 'SendFlare API',
            'smtp' => 'SMTP',
        ];
    @endphp

    <div class="settings-page-shell">
        @include('partials.admin-settings-tabs')

        <div class="mail-status-grid">
            <div class="mail-status-card">
                <span class="mail-status-icon"><i class="fa-regular fa-envelope"></i></span>
                <div>
                    <strong>{{ $driverLabels[$driver] ?? $driver }}</strong>
                    <span class="status {{ $statusClass }}">{{ $statusText }}</span>
                </div>
            </div>
            <div class="mail-status-card">
                <span class="mail-status-icon"><i class="fa-solid fa-paper-plane"></i></span>
                <div>
                    <strong>{{ (int)($stats['sent'] ?? 0) }}</strong>
                    <small>已发送</small>
                </div>
            </div>
            <div class="mail-status-card">
                <span class="mail-status-icon mail-status-icon-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div>
                    <strong>{{ (int)($stats['failed'] ?? 0) }}</strong>
                    <small>失败</small>
                </div>
            </div>
            <div class="mail-status-card">
                <span class="mail-status-icon"><i class="fa-solid fa-ban"></i></span>
                <div>
                    <strong>{{ (int)($stats['skipped'] ?? 0) }}</strong>
                    <small>已跳过</small>
                </div>
            </div>
        </div>

        <form method="post" action="/admin/settings/mail/save" class="admin-form mail-settings-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <h3 class="settings-group-title"><i class="fa-solid fa-sliders"></i> 基础配置</h3>
        <div class="settings-section mail-settings-section">
            <div class="form-row">
                <div class="form-group form-group-toggle">
                    <label>启用邮件</label>
                    <label class="cat-switch" title="启用邮件发送">
                        <input type="checkbox" name="mail[mail_enabled]" value="1" {{ !empty($s['enabled']) ? 'checked' : '' }}>
                        <span class="cat-switch-slider"></span>
                    </label>
                </div>
                <div class="form-group">
                    <label>邮件驱动</label>
                    <select name="mail[mail_driver]">
                        @foreach($driverLabels as $key => $label)
                            <option value="{{ $key }}" {{ $driver === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>发件邮箱</label>
                    <input type="email" name="mail[mail_from]" value="{{ $s['from'] ?? '' }}" placeholder="noreply@example.com">
                </div>
                <div class="form-group">
                    <label>发件名称</label>
                    <input type="text" name="mail[mail_from_name]" value="{{ $s['from_name'] ?? '' }}" placeholder="LiteNote">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>管理员通知邮箱</label>
                    <input type="text" name="mail[mail_notify_to]" value="{{ $s['notify_to'] ?? '' }}" placeholder="多个邮箱用英文逗号分隔">
                </div>
                <div class="form-group">
                    <label>新文章通知收件人</label>
                    <input type="text" name="mail[mail_post_recipients]" value="{{ $s['post_recipients'] ?? '' }}" placeholder="多个邮箱用英文逗号分隔">
                </div>
            </div>
            <div class="form-group">
                <label>邮件页脚</label>
                <textarea name="mail[mail_template_footer]" rows="2" placeholder="可填写站点说明或联系信息">{{ $s['footer'] ?? '' }}</textarea>
            </div>
        </div>

        <h3 class="settings-group-title"><i class="fa-solid fa-bell"></i> 通知开关</h3>
        <div class="settings-section mail-event-grid">
            @foreach([
                'mail_event_comment_admin' => '新评论通知管理员',
                'mail_event_comment_reply' => '评论回复通知访客',
                'mail_event_post' => '发布新文章通知',
                'mail_event_link' => '友链审核通过通知',
            ] as $key => $label)
                <div class="form-group form-group-toggle">
                    <label>{{ $label }}</label>
                    <label class="cat-switch">
                        <input type="checkbox" name="mail[{{ $key }}]" value="1" {{ \App\Models\Setting::get($key, '1') ? 'checked' : '' }}>
                        <span class="cat-switch-slider"></span>
                    </label>
                </div>
            @endforeach
        </div>

        <h3 class="settings-group-title"><i class="fa-solid fa-key"></i> 服务商密钥</h3>
        <div class="settings-section mail-provider-grid">
            <section class="mail-provider-card">
                <h4>SendFlare</h4>
                <div class="form-group">
                    <label>Endpoint</label>
                    <input type="text" name="mail[mail_sendflare_endpoint]" value="{{ $s['sendflare_endpoint'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label>API Token</label>
                    <input type="password" name="mail[mail_sendflare_token]" value="" placeholder="{{ !empty($s['sendflare_token']) ? '已保存，留空不修改' : 'Bearer token' }}" autocomplete="off" data-no-dirty>
                </div>
            </section>

            <section class="mail-provider-card">
                <h4>SMTP</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Host</label>
                        <input type="text" name="mail[mail_smtp_host]" value="{{ $s['smtp_host'] ?? '' }}" placeholder="smtp.example.com">
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="mail[mail_smtp_port]" value="{{ $s['smtp_port'] ?? 587 }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>加密</label>
                        <select name="mail[mail_smtp_secure]">
                            @foreach(['tls' => 'TLS / STARTTLS', 'ssl' => 'SSL', 'none' => '不加密'] as $key => $label)
                                <option value="{{ $key }}" {{ ($s['smtp_secure'] ?? 'tls') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" name="mail[mail_smtp_username]" value="{{ $s['smtp_username'] ?? '' }}">
                    </div>
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="mail[mail_smtp_password]" value="" placeholder="{{ !empty($s['smtp_password']) ? '已保存，留空不修改' : 'SMTP password' }}" autocomplete="off" data-no-dirty>
                </div>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> 保存邮件设置</button>
        </div>
        </form>

        <form method="post" action="/admin/settings/mail/test" class="admin-form mail-test-form" data-no-dirty-form>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <h3 class="settings-group-title"><i class="fa-solid fa-vial"></i> 测试发送</h3>
        <div class="form-row">
            <div class="form-group">
                <label>测试收件邮箱</label>
                <input type="email" name="test_to" placeholder="留空则发送到管理员通知邮箱">
            </div>
            <div class="form-group mail-test-action">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> 发送测试邮件</button>
            </div>
        </div>
        </form>

        <section class="admin-form mail-log-panel">
        <h3 class="settings-group-title"><i class="fa-regular fa-rectangle-list"></i> 最近邮件日志</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>时间</th>
                    <th>驱动</th>
                    <th>类型</th>
                    <th>收件人</th>
                    <th>主题</th>
                    <th>状态</th>
                    <th>错误</th>
                </tr>
            </thead>
            <tbody>
                @if(empty($logs))
                    <tr><td colspan="7" class="empty">暂无邮件日志</td></tr>
                @else
                    @foreach($logs as $log)
                        <tr>
                            <td>{!! \App\Core\Helper::dateTimeTag((string)$log->created_at) !!}</td>
                            <td>{{ $log->provider ?: '-' }}</td>
                            <td>{{ $log->mail_type ?: '-' }}</td>
                            <td>{{ $log->recipient ?: '-' }}</td>
                            <td>{{ $log->subject ?: '-' }}</td>
                            <td>
                                <span class="status {{ $log->status === 'sent' ? 'status-published' : ($log->status === 'skipped' ? 'status-pending' : 'status-draft') }}">
                                    {{ $log->status === 'sent' ? '已发送' : ($log->status === 'skipped' ? '已跳过' : '失败') }}
                                </span>
                            </td>
                            <td><div class="comment-cell">{{ $log->error ?: '-' }}</div></td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
        </section>
    </div>
@endsection
