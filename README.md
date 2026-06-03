# LiteNote

> 一个用 PHP 8.5 写的轻量级个人博客系统,**零外部依赖**,自实现 MVC + 模板引擎 + Markdown 解析 + RSS 生成。
>
> 在线演示:[litenote.io](https://litenote.io) · 中文界面

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-3.x-003B57?logo=sqlite&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-stable-blue)
![Zero Dependency](https://img.shields.io/badge/dependencies-zero-orange)

---

## ✨ 特性

### 内容管理
- 📝 **文章管理** — 分类、封面、置顶/推荐、伪静态 URL、Markdown/HTML 双编辑器
- 📑 **页面管理** — 自定义单页(关于/友链),可加入导航
- 📎 **附件管理** — 拖拽上传,按类型管理
- 🔗 **友情链接** — CRUD + RSS 抓取 + 友链更新自动聚合
- 💬 **说说** — 短内容时间线
- 💭 **评论** — 前台提交 + 后台审核 + 嵌套回复 + 反垃圾

### 前台功能
- 📡 **RSS 订阅** — `/feed`(本站)、`/friends/feed`(友链聚合)
- 🔍 **全文搜索** — 标题/摘要/正文 LIKE
- 📊 **访问统计** — PV/UV/IP/Referer/UA,后台图表

### 设计与体验
- 🎨 **两套主题** — Default(蓝灰工具型)/ Ember(暖米文艺型),CSS 变量驱动,后台一键切换
- 🌓 **圆形 iOS 风格开关** — 后台设置页所有 bool 字段,丝滑动画
- 👤 **Gravatar 头像** — 评论 / 个人资料 / 文章作者卡,自动 fallback
- 🌐 **社交链接字段** — 自定义平台 + FontAwesome 图标 + 任意 URL
- 📱 **响应式布局** — 桌面顶部 nav / 移动底部 nav,自动切换

### 工程亮点
- **零 Composer 依赖** — 不需要 `composer install`,clone 就能跑
- **自实现编译型模板引擎** — 布局继承 / View Composer / mtime 自动失效
- **PHP 8.5 完整特性** — readonly class / enum / nullsafe / never return type
- **安全** — CSRF + htmlspecialchars 默认转义 + SQL 注入白名单 + 模板 XSS 过滤
- **SQLite / MySQL 双驱动** — 切换零代码

---

## 🎨 主题

| 主题 | 风格 | 主色 | 适用 |
|---|---|---|---|
| **Default** | 工具型蓝灰渐变 | `#2f5c8c` | 工具 / 技术 / 严肃内容 |
| **Ember** | 暖米 + 陶土红 | `#E65A4C` | 文艺 / 生活 / 随笔 |

主题文件位于 `public/assets/css/themes/`,添加新主题:
```bash
cp public/assets/css/themes/default.css public/assets/css/themes/mytheme.css
# 编辑颜色变量 → 后台 → 设置 → 主题 → 填入 mytheme → 保存
```

---

## 🚀 快速开始

### 1. 启动开发服务器

```bash
cd LiteNote
php -S 127.0.0.1:5555 -t public router.php
```

### 2. 完成安装

浏览器访问 [http://127.0.0.1:5555/install](http://127.0.0.1:5555/install),一键建表 + 默认数据。

### 3. 登录后台

[http://127.0.0.1:5555/admin/login](http://127.0.0.1:5555/admin/login)

```
默认账号:admin / admin123   ← 登录后立即修改
```

### 4. 发布第一篇文章

后台 → 文章 → 写文章 → 填标题/内容 → 保存。前台首页即可看到。

> 数据库默认 SQLite,无需任何配置。生产环境切 MySQL:修改 `config/config.php` 的 `database.driver` 段。

---

## 🛠️ 技术栈

| 组件 | 实现 |
|---|---|
| **Web 框架** | 自实现 MVC + Front Controller + 中间件 |
| **路由** | 自实现,支持 `/post/{slug}` 占位符 + 路由组 |
| **ORM** | 自实现 ActiveRecord 基类 + 预加载 + 关系缓存 |
| **模板引擎** | 自实现编译型,`@extends` / `@section` / `@yield` / `@include` |
| **View Composer** | 自动注入共享数据(站点 author / settings) |
| **Markdown 解析** | 自实现(标题/列表/代码块/引用/链接/图片) |
| **RSS 生成** | 自实现 RSS 2.0 XML 拼装 |
| **CSRF** | 一次性 token + `hash_equals` 时序安全比较 |
| **前端** | 原生 CSS + Vanilla JS + FontAwesome 6(无构建工具) |

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
├── app/
│   ├── Core/        # 框架核心(11 个类)
│   ├── Models/      # 数据模型(11 个,1 基类 + 10 子类)
│   ├── Controllers/ # 控制器(26 个,Front 13 + Admin 13)
│   ├── Services/    # 业务服务(Installer / Gravatar / StatService / FriendRssService)
│   ├── Middleware/  # 中间件(AdminAuth / CsrfMiddleware)
│   ├── Enums/       # PHP enum(PostStatus / CommentStatus / Toggle)
│   ├── Traits/      # 横切(HasSlug / HasFlashRedirect)
│   └── bootstrap.php
├── views/           # 模板(按模块分目录)
├── routes/          # 路由定义(web.php + admin.php)
├── public/          # Web 根目录
│   ├── index.php
│   ├── admin/index.php
│   └── assets/
│       ├── css/     # admin.css + themes/{default,ember}.css
│       ├── js/      # admin.js + front.js
│       └── fontawesome/  # 6.x
├── config/config.php
├── router.php       # PHP 内置服务器入口(开发用)
└── storage/         # 运行时(SQLite / 模板缓存 / 日志)
```

---

## 📚 文档

| 文档 | 用途 |
|---|---|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | 详细架构设计(请求生命周期 / 数据流 / ER 图 / 关键决策) |
| [REFACTORING.md](./REFACTORING.md) | 早期代码审查 + 重构报告 |
| [ENUM_REFACTOR.md](./ENUM_REFACTOR.md) | PHP enum 替代魔法字符串的改造 |
| [GRAVATAR_TOGGLE.md](./GRAVATAR_TOGGLE.md) | Gravatar 头像接入 + iOS toggle 按钮 |
| [DEPLOYMENT_2026-06-04.md](./DEPLOYMENT_2026-06-04.md) | 生产部署到 [litenote.io](https://litenote.io) 的完整记录 |

---

## 🌐 部署

LiteNote 已经在 [litenote.io](https://litenote.io) 上线,生产环境:
- **OS**:Debian 13 + 宝塔面板
- **Web**:nginx + Let's Encrypt SSL
- **PHP**:8.5.2-FPM
- **DB**:SQLite 3

完整部署步骤见 [DEPLOYMENT_2026-06-04.md](./DEPLOYMENT_2026-06-04.md),核心要点:
```bash
# 同步代码
tar -czf - --exclude=./storage . | ssh root@host "cd /var/www/litenote && tar -xzf -"

# 触发 schema 自升级
sudo -u www php -r 'require "app/bootstrap.php"; App\Services\Installer::install();'

# 改 app.url 为生产域名
sed -i "s|'url'\s*=>\s*'http://127.0.0.1:5555'|'url' => 'https://yourdomain.com'|" config/config.php
```

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
- [FontAwesome 6](https://fontawesome.com/) — 图标库
- 所有用 LiteNote 写博客的人
