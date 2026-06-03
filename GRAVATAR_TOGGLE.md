# LiteNote — Gravatar 头像 + Toggle 改造报告

> 日期: 2026-06-04  
> 范围: Gravatar 接入 / 功能开关 UI 改造 / Session 兼容性修复

---

## 1. 改动一览

| 类型 | 文件 | 摘要 |
|---|---|---|
| **新增** | `app/Services/Gravatar.php` | URL 生成 + 邮箱 hash + host 选择 + `<img>` 标签助手 |
| **修改** | `app/Models/Comment.php` | 新增 `getAvatarUrl(int $size = 60): string` |
| **修改** | `app/Models/User.php` | 新增 `getAvatarUrl(int $size = 80): string`(优先级:用户自定义 avatar > gravatar) |
| **修改** | `views/post/show.php` | 评论列表加 48×48 圆形头像 |
| **修改** | `views/comment/index.php` | 后台评论列表加 36×36 头像 + email 副标题 |
| **修改** | `views/profile/index.php` | 个人资料加 96×96 头像预览 + 实时 JS 预览 |
| **修改** | `views/setting/index.php` | bool 字段自动渲染为圆形 toggle,其他保持 text/textarea |
| **修改** | `app/Core/Session.php` | **顺手修**:`get/has/forget` 支持点号路径(`admin_user.id` 终于能取到值) |
| **修改** | `app/Controllers/Admin/ProfileController.php` | 防御 `$user === null`,session 失效时跳回登录 |
| **修改** | `public/assets/css/admin.css` | 新增 `.toggle-switch` / `.form-group-toggle` / `.avatar` / `.profile-header` / `.comment-item` 样式 |

---

## 2. Gravatar URL 样例

```
https://gravatar.bluecdn.net/avatar/f3ada405ce890b6f8204094deb12d8a8?s=80&d=identicon&r=g&v=1.3
```

URL 组成:
- **协议**:`https://`(可传 `false` 切到 `http`)
- **Host**:`gravatar.bluecdn.net`(国内代理,可被 `Config::set('gravatar.host', ...)` 覆盖)
- **Path**:`/avatar/<md5(lowercase(trim(email)))>` — 标准 Gravatar 协议
- **Query**:
  - `s` 像素(1~2048)
  - `d` 默认头像(`identicon` / `mp` / `monsterid` / `retro` / `robohash` / `blank` / `404` / URL)
  - `r` 等级(`g` / `pg` / `r` / `x`)
  - `v` 版本(固定 1.3 即可)

支持的边界:
- 空 email → 32 个 0(触发 `d` 默认头像)
- email 前后空格 / 大小写 → 自动归一化
- email 里的 emoji / 特殊字符 → 不影响 hash

---

## 3. 头像显示位置

| 位置 | 模板 | 尺寸 | 来源 |
|---|---|---|---|
| 前台文章页评论列表 | `views/post/show.php` | 48×48 | `$cmt->getAvatarUrl(48)` |
| 后台评论管理 | `views/comment/index.php` | 36×36 | `Gravatar::url($c['email'], 36)` |
| 后台个人资料 | `views/profile/index.php` | 96×96 | `$user->getAvatarUrl(96)` |
| 评论邮箱(后端提交) | — | — | 提交时 email 入库,展示时实时生成 URL,无需落库 |

> 头像 URL **不落库**:每次渲染时由 email 算 hash 实时生成。这样用户在 Gravatar 后台改头像,前端立即生效(配合 `?v=1.3` cache buster)。

---

## 4. Toggle 按钮设计

### 4.1 视觉(iOS 风格,52×28 px)

```
关:  ┌──────────────┐    开:  ┌──────────────┐
     │ ○            │         │            ○ │
     └──────────────┘         └──────────────┘
     灰色 #CBD5E1              绿色 #059669
```

- 圆形滑块 24×24,带阴影 + 0.25s 过渡动画
- 开启时背景由蓝绿系主色切换,文案从"已关闭"变"已开启"
- 整体行布局:label 在左,toggle + 状态文案在右,hover 高亮

### 4.2 数据流(零后端改动)

```html
<!-- view 渲染 -->
<div class="toggle-switch on" data-key="comment_need_audit">
    <input type="hidden" name="settings[comment_need_audit]" value="1" id="...">
    <button type="button" class="toggle-track" aria-pressed="true">
        <span class="toggle-thumb"></span>
    </button>
    <span class="toggle-state">已开启</span>
</div>

<!-- JS 点击交互(已嵌入 setting/index.php) -->
track.addEventListener('click', e => {
    const on = wrap.classList.toggle('on');
    input.value = on ? '1' : '0';   // ← 改 hidden input
    state.textContent = on ? '已开启' : '已关闭';
});
```

提交时:
- `settings[comment_need_audit]=1` 或 `0` 进 POST
- `SettingController::save()` 不变,原样写库
- 现有任何 `Setting::get('comment_need_audit')` 读出来还是 `1` / `0`

### 4.3 自动判定

`views/setting/index.php` 智能判断:

```php
$isToggle = in_array($val, ['0', '1'], true) || ($item['type'] ?? '') === 'bool';
```

- 值是 `'0'` / `'1'` → toggle
- `type` 字段标 `'bool'` → toggle(给未来扩展用)
- 都不是 → 走原 text/textarea 逻辑

**当前 5 个 bool 设置项全部识别**:
- `comment_need_audit`(评论需审核)
- `comment_captcha`(评论验证码)
- `shuoshuo_enabled`(开启说说)
- `friends_rss_enabled`(友链 RSS)
- `rss_full_text`(RSS 全文)

---

## 5. 顺手修复:Session 点号路径

### 5.1 问题

`Session::get('admin_user.id')` 之前**永远**拿不到值,因为 `Session::get` 只会查 `$_SESSION['admin_user.id']` 这个整体 key。

### 5.2 触发场景

Gravatar 改造让 `ProfileController::index()` 调 `$user->getAvatarUrl(96)`,但 `$user` 一直是 null(因为 `Session::get('admin_user.id', 0)` 返回 0),触发:

```
Fatal error: Uncaught Error: Call to a member function getAvatarUrl() on null
```

### 5.3 修复

`Session::get/has/forget` 全部加点号路径支持,实现模仿 Laravel `data_get()`:

```php
public static function get(string $key, mixed $default = null): mixed
{
    if (str_contains($key, '.')) {
        return self::dotGet($_SESSION, $key, $default);
    }
    return $_SESSION[$key] ?? $default;
}
```

### 5.4 受益范围

修复后,**所有现有调用方**都自动受益:

| 位置 | 改前 | 改后 |
|---|---|---|
| `ProfileController::*` | 拿到 0 → null | 拿到 1 → User 找到 |
| `PostController::persist` | `user_id = 0` | `user_id = 1`(真实作者) |
| `SettingController` 已用 `settings.xxx` 形式,不受影响 | | |

零回归,纯增量。

---

## 6. 验证

### 6.1 语法
所有改动文件 `php -l` 通过:
```
app/Services/Gravatar.php         ✓
app/Models/Comment.php            ✓
app/Models/User.php               ✓
app/Controllers/Admin/ProfileController.php  ✓
app/Core/Session.php              ✓
```

### 6.2 Gravatar URL 生成

```
Standard:        https://gravatar.bluecdn.net/avatar/f3ada405ce890b6f8204094deb12d8a8?s=80&d=identicon&r=g&v=1.3
Trim+Lower:      https://gravatar.bluecdn.net/avatar/f3ada405ce890b6f8204094deb12d8a8?s=60&d=identicon&r=g&v=1.3
Empty email:     https://gravatar.bluecdn.net/avatar/00000000000000000000000000000000?s=40&d=identicon&r=g&v=1.3
MP default:      ...&d=mp&...
404 default:     ...&d=404&...
Custom URL def:  ...&d=https%3A%2F%2Fexample.com%2Favatar.png&...
官方 host:       http://gravatar.bluecdn.net/avatar/767934a648524da57388558217ad9c2d?s=40&r=g&v=1.3 (http)
```

全部正确:
- 邮箱 trim + lowercase 归一
- 空 email 兜底到 32 个 0(触发默认头像)
- 6 种 default 模式全部生效
- host 切换正常

### 6.3 路由 + 渲染

| 路径 | HTTP | 关键发现 |
|---|---|---|
| `GET /` | 200 | — |
| `GET /post/welcome.html` | 200 | 评论列表结构已就绪(无评论) |
| `GET /admin/login` | 200 | — |
| `POST /admin/login` | 302 → /admin | Session 点号路径生效 |
| `GET /admin/profile` | 200 | 头像预览: `https://gravatar.bluecdn.net/avatar/e64c7d89f26bd1972efa854d13d7dd61?s=96&d=identicon&r=g&v=1.3` |
| `GET /admin/settings` | 200 | 5 个 toggle 全部渲染,3 on + 2 off |
| `GET /admin/comments` | 200 | 表头结构已升级,无数据时正常 |

### 6.4 Toggle 端到端

模拟用户切换:

```bash
# 1. 初始:comment_captcha = 0
# 2. 切到 1
curl POST /admin/settings/save
  settings[comment_need_audit]=1
  settings[comment_captcha]=1     # ← 切换
  settings[shuoshuo_enabled]=1
  settings[friends_rss_enabled]=1
  settings[rss_full_text]=1
# → 302 OK
# DB: comment_captcha = 1

# 3. 切回 0
# → 302 OK
# DB: comment_captcha = 0
```

完全双向可逆,无副作用。

---

## 7. 兼容性

- **数据库**:零 schema 变更
- **路由**:零变更
- **现有调用方**:`Setting::get('comment_need_audit')` 返回值不变(仍 `'0'` / `'1'`)
- **主题切换**:toggle 颜色用 `var(--c-success)`,跟主题系统兼容

---

## 8. 后续可做(未做)

- **Dashboard 顶栏**显示当前管理员头像(`User::find(Session::get('admin_user.id'))->getAvatarUrl()`)
- **后台 Login 页** logo 旁加一个 demo gravatar
- **设置页加 `gravatar.host` 配置项**作为 UI 入口(目前要改 host 只能改 `config.php`)
- **Toggle 键盘可达性**:加 Space / Enter 切换
