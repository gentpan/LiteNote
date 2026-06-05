# LiteNote

> 一个用 PHP 8.5 写的轻量级个人博客系统,**零外部依赖**,自实现 MVC + 模板引擎 + Markdown 解析 + RSS 生成。
>
> 在线演示:[litenote.io](https://litenote.io) · 中文界面

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Version](https://img.shields.io/badge/version-1.0.0-0052D9)
![SQLite](https://img.shields.io/badge/SQLite-3.x-003B57?logo=sqlite&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-stable-blue)
![Zero Dependency](https://img.shields.io/badge/dependencies-zero-orange)

---

## ✨ 特性

### 内容管理
- 📝 **文章管理** — 分类、封面、`/post/{slug}.html`、Markdown 编辑器/本地 MD 导入、AI 摘要,置顶/推荐在列表快捷操作
- 🗂️ **分类** — 自定义 FontAwesome 图标 + 配色 + 描述,可选是否进导航
- 📑 **页面管理** — 自定义单页直接使用 `/{slug}`,订阅/音乐/滔客等系统页可用 toggle 控制是否进导航
- 📎 **附件管理** — 拖拽上传,按类型管理
- 🔗 **友情链接** — CRUD + favicon 列表 + 本站信息复制,RSS 聚合统一显示在订阅页
- 💬 **滔客** — 短内容时间线(图片九宫格 / 关联音乐 / 点赞 / 评论),管理员登录后可前台发布
- 🎵 **音乐** — 独立后台音乐库 + 发布时间歌单 + 黑胶播放器 + LRC 歌词 + 与滔客共享点赞/评论
- 💭 **评论** — AJAX 提交 + 图片验证码 + 嵌套回复 + 后台审核 + 反垃圾

### 前台功能
- 📡 **RSS / AI 收录** — `/rss.xml`(本站)、`/feeds`(订阅聚合页)、`/llms.txt`(AI 索引)
- 🔍 **全文搜索** — 标题/摘要/正文 LIKE
- 📊 **访问统计** — 接入自建/云端 Umami API,后台展示实时、趋势、来源、设备等指标
- ♾️ **加载更多** — 首屏自动 + 后续手动,带 loading 与"没有更多"提示

### 账号与安全
- 🔑 **Passkey 登录** — WebAuthn 免密登录(后台)
- 📧 **邮箱找回密码** — 配置邮件服务后一键发送重置链接(token 1 小时有效)
- 🛡️ **多层防护** — CSRF token + 评论图片验证码 + htmlspecialchars 默认转义 + SQL 注入白名单 + 模板 XSS 过滤

### 设计与体验
- 🎨 **前台 Ember 默认主题** — 暖米 + 陶土红为唯一浅色主题,保留深色模式切换;后台固定科技蓝管理面板
- 🧊 **深色磨砂导航** — 玻璃拟态胶囊 nav,滚动后展开为通栏磨砂条,整体式下拉抽屉
- 🟢 **彩色分类下拉** — 文章菜单双列卡片,每个分类独立图标 + 颜色 + 描述;分类页同色 Hero
- 🌓 **胶囊开关** — 后台 bool 字段 AJAX 保存,蓝色系统态,尺寸统一
- 🖼️ **图片淡入 + 灯箱** — 模糊到清晰渐显 + 合并版 ViewImage,预览图约占屏幕 70%
- 👤 **Gravatar 头像** — 评论 / 个人资料 / 文章作者卡,自动 fallback
- 🌐 **社交链接字段** — 自定义平台 + FontAwesome 图标 + 任意 URL
- 📱 **响应式布局** — 桌面顶部 nav / 移动底部 nav,自动切换

### 工程亮点
- **零 Composer 依赖** — 不需要 `composer install`,clone 就能跑
- **自实现编译型模板引擎** — 布局继承 / View Composer / 运行时递归 `@include`(支持循环作用域)/ mtime 自动失效
- **PHP 8.5 完整特性** — readonly class / enum / nullsafe / never return type
- **SQLite 单库** — 无需数据库服务器,文件级备份与迁移简单

---

## 🎨 主题

LiteNote 前台采用独立主题目录。`core/` 只负责框架能力,主题拥有自己的模板、函数、CSS、JS、图片和局部组件。

| 区域 | 风格 | 主色 | 文件 |
|---|---|---|---|
| **Ember** | 暖色黑胶播放器 + 内容流 | `#e9554f` | `themes/ember/assets/main.css` |
| **Kami** | 暖米纸底 + 油墨蓝 | `#1B365D` | `themes/kami/assets/main.css` |
| **后台** | 腾讯云式黑白侧栏 + 科技蓝 | `#0052D9` | `admin/assets/css/admin.css` |

主题约定:

```text
themes/{theme}/
├── index.php        # 首页 / 内容流
├── single.php       # 文章详情
├── page.php         # 独立页面
├── header.php
├── footer.php
├── layout.php
├── functions.php
├── theme.json
├── inc/             # 评论、卡片、播放器评论等功能片段
├── pages/           # 音乐、X、动态、归档、搜索、友链等页面模板
└── assets/
    ├── main.css
    ├── main.js
    └── images/
```

后台不参与前台主题系统。当前系统默认主题是 `ember`。

---

## 🚀 快速开始

### 1. 启动开发服务器

```bash
cd LiteNote
brew services start php
caddy run --config Caddyfile
```

### 2. 初始化数据库

首次运行需建表 + 写入默认数据(含默认管理员):

```bash
php -r 'define("BASE_PATH", getcwd()); require "core/app/bootstrap.php"; App\Services\Installer::install();'
```

> 数据库 schema 采用幂等自升级:每次 `Installer::install()` 会自动补齐新增字段,升级版本无需手动迁移。

### 3. 登录后台

[http://127.0.0.1:5555/admin/login](http://127.0.0.1:5555/admin/login)

```
默认账号:admin / admin123   ← 登录后立即修改
```

### 4. 配置环境变量

AI 摘要、Umami 统计、邮件等凭据通过 `.env` 或服务器环境变量配置,不在后台设置页保存。

```bash
cp .env.example .env
```

常用键:

```dotenv
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=
DEEPSEEK_MODEL=deepseek-v4-flash
DEEPSEEK_BASE_URL=https://api.deepseek.com

UMAMI_ENABLED=false
UMAMI_BASE_URL=
UMAMI_WEBSITE_ID=
UMAMI_TOKEN=
UMAMI_API_KEY=
UMAMI_TIMEZONE=Asia/Shanghai
UMAMI_SCRIPT_URL=
```

### 5. 发布第一篇文章

后台 → 文章 → 写文章 → 填标题/内容 → 保存。前台首页即可看到。

> 数据库固定使用 SQLite,无需额外数据库服务;默认文件位于 `runtime/storage/database.sqlite`。

---

## 🛠️ 技术栈

| 组件 | 实现 |
|---|---|
| **Web 框架** | 自实现 MVC + Front Controller + 中间件 |
| **路由** | 自实现,支持 `/post/{slug}.html`、`/{slug}` 占位符 + 路由组 |
| **ORM** | 自实现 ActiveRecord 基类 + 预加载 + 关系缓存 |
| **模板引擎** | 自实现编译型,`@extends` / `@section` / `@yield` / 运行时递归 `@include`(循环作用域) |
| **View Composer** | 自动注入共享数据(站点 author / settings) |
| **数据库** | SQLite + PDO,单文件存储 |
| **Markdown 解析** | 自实现(标题/列表/代码块/引用/链接/图片) |
| **RSS 生成** | 自实现 RSS 2.0 XML 拼装 |
| **CSRF** | 一次性 token + `hash_equals` 时序安全比较 |
| **前端** | 原生 CSS + Vanilla JS + FontAwesome 7(CDN,无构建工具) |
| **邮件** | SendFlare API(评论通知 / 找回密码),未配置自动降级 |

### PHP 8.5 特性使用

```php
enum PostStatus: string {
    case Published = 'published';
    case Draft     = 'draft';
}

readonly class Database { /* ... */ }

function findUser(int $id): ?User {
    return $this->db?->fetchOne(...);  // nullsafe
}
```

---

## 📁 项目结构

```
LiteNote/
├── admin/           # 后台入口、后台页面和后台资源
│   ├── assets/      # 后台 css/js/images
│   ├── pages/       # 后台页面模板
│   └── parts/       # 后台布局与组件
├── core/            # 框架核心、路由、数据库结构、系统页
│   ├── app/
│   │   ├── Core/        # Request / Response / Router / View / Config / Database
│   │   ├── Controllers/ # Front / Admin / Api 控制器
│   │   ├── Models/      # ActiveRecord 风格模型
│   │   ├── Services/    # Installer / ThemeManager / RSS / Upload / Sync 等服务
│   │   ├── Middleware/
│   │   ├── Enums/
│   │   └── bootstrap.php
│   ├── database/    # 建表 / migration SQL,不是运行时数据库
│   ├── routes/      # web.php / admin.php
│   └── system/      # 系统兜底页面,目前只保留 404
├── themes/          # 前台主题
│   └── ember/
│       ├── index.php
│       ├── single.php
│       ├── page.php
│       ├── header.php
│       ├── footer.php
│       ├── functions.php
│       ├── inc/
│       ├── pages/
│       └── assets/
├── plugins/         # 插件目录(预留)
├── uploads/         # 上传目录
├── runtime/storage/ # SQLite、缓存、导入文件、文章正文
├── config.php       # 根目录配置
├── .env             # 本地密钥和第三方 API 配置
├── .env.example
├── index.php        # 根目录前台入口
├── Caddyfile        # 本地 Caddy 开发配置
└── .htaccess        # Web 服务器重写入口
```

---

## 🌐 部署

LiteNote 已经在 [litenote.io](https://litenote.io) 上线,生产环境:
- **OS**:Debian 13 + 宝塔面板
- **Web**:nginx + Let's Encrypt SSL
- **PHP**:8.5.2-FPM
- **DB**:SQLite 3

核心部署要点:
```bash
# 同步代码与当前 SQLite 数据
tar -czf - --exclude=./.git --exclude=./runtime/storage/cache --exclude=./runtime/storage/logs . \
  | ssh root@host "mkdir -p /var/www/litenote && tar -xzf - -C /var/www/litenote"

# 触发 schema 自升级
sudo -u www php -r 'define("BASE_PATH", getcwd()); require "core/app/bootstrap.php"; App\Services\Installer::install();'

# 改 app.url 为生产域名
sed -i "s|'url'\s*=>\s*'http://127.0.0.1:5555'|'url' => 'https://yourdomain.com'|" config.php
```

本地推荐使用 Caddy + PHP-FPM;线上站点目录指向项目根目录,nginx 使用 `try_files $uri $uri/ /index.php?$query_string;`,后台入口为 `/admin/`。

---

## 🤝 贡献

LiteNote 是个人项目,但欢迎:
- 🐛 提 Issue 报告 bug
- 💡 提 PR 改进功能
- ⭐ 给个 star 表示支持

---

## 📄 License

[MIT](./LICENSE) © gentpan

---

## 🙏 致谢

- PHP 8.5 / PDO / SQLite 社区
- [FontAwesome 7](https://fontawesome.com/) — 图标库
- 所有用 LiteNote 写博客的人
