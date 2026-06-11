@extends('layouts.admin')

@section('content')
    @php
        $s = $settings ?? [];
        $hasToken = trim((string)($s['TELEGRAM_BOT_TOKEN'] ?? '')) !== '';
        $secret = (string)($s['TELEGRAM_WEBHOOK_SECRET'] ?? '');
        $chatIds = (string)($s['TELEGRAM_ALLOWED_CHAT_IDS'] ?? '');
    @endphp

    <div class="settings-page-shell telegram-settings-page">
        @include('partials.admin-settings-tabs')

        <div class="mail-status-grid">
            <div class="mail-status-card">
                <span class="mail-status-icon {{ !empty($configured) ? 'mail-status-icon-success' : '' }}"><i class="fa-brands fa-telegram"></i></span>
                <div>
                    <strong>Telegram 发布说说</strong>
                    <small>{{ !empty($configured) ? '已配置' : '未就绪' }}</small>
                </div>
            </div>
            <div class="mail-status-card">
                <span class="mail-status-icon"><i class="fa-solid fa-file-code"></i></span>
                <div>
                    <strong>.env</strong>
                    <small>{{ !empty($envStatus['writable']) ? '可写入' : '不可写' }}</small>
                </div>
            </div>
            <div class="mail-status-card">
                <span class="mail-status-icon"><i class="fa-solid fa-link"></i></span>
                <div>
                    <strong>Webhook</strong>
                    <small>{{ $webhookUrl ?? '/telegram/webhook' }}</small>
                </div>
            </div>
        </div>

        <form method="post" action="/admin/settings/telegram/save" class="admin-form mail-settings-form" data-dirty-watch>
            <input type="hidden" name="_csrf" value="{{ $csrf }}">

            <h3 class="settings-group-title"><i class="fa-solid fa-key"></i> 环境变量配置</h3>
            <div class="settings-section mail-settings-section">
                <div class="form-group">
                    <label>Bot Token <code class="setting-key">TELEGRAM_BOT_TOKEN</code></label>
                    <input type="password" name="telegram[telegram_bot_token]" value="" placeholder="{{ $hasToken ? '已保存，留空不修改' : '从 BotFather 获取，例如 123456:ABC...' }}" autocomplete="off" data-no-dirty>
                    <p class="field-hint">用于调用 Telegram Bot API，消息里的图片需要用它下载到站内上传目录。请到 Telegram 的 @BotFather 创建 bot 后复制 token。</p>
                </div>
                <div class="form-group">
                    <label>Webhook Secret <code class="setting-key">TELEGRAM_WEBHOOK_SECRET</code></label>
                    <input type="text" name="telegram[telegram_webhook_secret]" value="{{ $secret }}" placeholder="留空会自动生成">
                    <p class="field-hint">用于校验 Telegram 请求。站点会同时支持 Telegram 官方 secret header 和 /telegram/webhook/{secret} 兼容地址。</p>
                </div>
                <div class="form-group">
                    <label>允许发布的 Chat ID <code class="setting-key">TELEGRAM_ALLOWED_CHAT_IDS</code></label>
                    <input type="text" name="telegram[telegram_allowed_chat_ids]" value="{{ $chatIds }}" placeholder="例如 123456789，多个用英文逗号分隔">
                    <p class="field-hint">只允许这些 Telegram 用户、群或频道发来的消息创建说说。留空表示所有给 bot 发消息的人都可以发布，不建议公开站点这样设置。</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> 保存 Telegram 设置</button>
            </div>
        </form>

        <section class="admin-form mail-log-panel telegram-help-panel">
            <h3 class="settings-group-title"><i class="fa-regular fa-circle-question"></i> 使用说明</h3>
            <div class="settings-section">
                <ol class="telegram-help-list">
                    <li>在 Telegram 找到 <strong>@BotFather</strong>，使用 <code>/newbot</code> 创建 bot，复制 Bot Token 填到上面的 <code>TELEGRAM_BOT_TOKEN</code>。</li>
                    <li>把 <code>TELEGRAM_WEBHOOK_SECRET</code> 保存好；留空保存时系统会自动生成。</li>
                    <li>把你的个人、群组或频道 chat id 填到 <code>TELEGRAM_ALLOWED_CHAT_IDS</code>，多个用英文逗号分隔。</li>
                    <li>执行下面的 setWebhook 命令，把 Telegram 消息推送到本站。</li>
                    <li>之后给 bot 发送文本会发布为说说；发送图片时，图片会保存到站内上传目录，caption 会作为正文。</li>
                </ol>

                <div class="form-group">
                    <label>Webhook 地址</label>
                    <input type="text" value="{{ $webhookUrl ?? '' }}" readonly data-no-dirty>
                    <p class="field-hint">正式部署时这里必须是公网 HTTPS 地址；本地 127.0.0.1 只能用于页面测试，Telegram 无法从公网访问本地地址。</p>
                </div>
                <div class="form-group">
                    <label>兼容地址</label>
                    <input type="text" value="{{ $webhookUrlWithSecret ?? '' }}" readonly data-no-dirty>
                    <p class="field-hint">如果你的代理无法传递 <code>X-Telegram-Bot-Api-Secret-Token</code> 请求头，可以使用这个带 secret 的 webhook 地址。</p>
                </div>
                <div class="form-group">
                    <label>setWebhook 命令</label>
                    <textarea rows="3" readonly data-no-dirty>{{ $setWebhookCommand ?? '' }}</textarea>
                    <p class="field-hint">把命令里的 <code>${TELEGRAM_BOT_TOKEN}</code> 替换成真实 token 后执行；如果已保存 secret，命令会自动带上当前 secret。</p>
                </div>
            </div>
        </section>

        <section class="admin-form mail-log-panel telegram-help-panel">
            <h3 class="settings-group-title"><i class="fa-solid fa-list-check"></i> 字段用途</h3>
            <div class="settings-section">
                <table class="admin-table telegram-env-table">
                    <thead>
                        <tr>
                            <th>环境变量</th>
                            <th>是否必填</th>
                            <th>使用位置</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>TELEGRAM_BOT_TOKEN</code></td>
                            <td>必填</td>
                            <td>下载 Telegram 图片、调用 Bot API</td>
                            <td>{{ $hasToken ? '已填写' : '未填写，请到 BotFather 获取后填写' }}</td>
                        </tr>
                        <tr>
                            <td><code>TELEGRAM_WEBHOOK_SECRET</code></td>
                            <td>必填</td>
                            <td>校验 webhook 请求</td>
                            <td>{{ $secret !== '' ? '已填写' : '未填写，保存时会自动生成' }}</td>
                        </tr>
                        <tr>
                            <td><code>TELEGRAM_ALLOWED_CHAT_IDS</code></td>
                            <td>建议填写</td>
                            <td>限制哪些 Telegram 会话可以发布说说</td>
                            <td>{{ $chatIds !== '' ? $chatIds : '未填写，建议填写自己的 chat id' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
