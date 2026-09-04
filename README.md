# LiteNote

**兼容 PHP 8.5 / 8.6 与 SQLite 的轻量自托管个人发布系统。**

一套代码，覆盖博客、短动态、音乐分享、X 卡片、评论与 RSS。生产环境无需 Composer，上传即可运行；后台功能完整，适合个人站点长期维护与二次开发。

[在线演示](https://litenote.io) · [问题反馈](https://github.com/gentpan/LiteNote/issues)

![PHP](https://img.shields.io/badge/PHP-8.5%20%7C%208.6-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-3-003B57?logo=sqlite&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Version](https://img.shields.io/badge/version-1.1.0-0052D9)

---

## 为什么选择 LiteNote

| 特点 | 说明 |
|------|------|
| **部署简单** | 单文件 SQLite，无 MySQL/Redis 依赖；Caddy / Nginx + PHP-FPM 即可 |
| **生产零 Composer** | 核心代码自包含，服务器不必安装 `vendor/` |
| **后台完整** | 文章、页面、分类、评论、音乐、友链、附件、主题、插件、设置一应俱全 |
| **数据与源码分离** | `runtime/`、`uploads/` 本地保留，Git 只跟踪代码 |
| **可扩展** | 主题系统 + 插件钩子，自带 X 卡片插件示例 |
| **安全基线** | Passkey（WebAuthn）、CSRF、限流、凭据加密、生产强制 `APP_KEY` |

---

## 功能概览

### 内容与发布

- **文章** — Markdown 正文、分类、封面、Slug、草稿/发布、置顶/推荐、AI 摘要
- **短动态（滔客）** — 图文、位置、天气、音乐关联、点赞、评论
- **自定义页面** — 短链接导航、归档、全文搜索（FTS5）、`llms.txt`
- **X 卡片** — 服务端抓取并缓存推文，前台直接渲染

### 媒体与互动

- **音乐** — 本地/线上导入、封面、歌词、播放页、歌曲评论
- **评论** — AJAX 提交、审核、验证码、嵌套回复、Gravatar、IP 归属地
- **读者墙** — 评论者展示与排序
- **RSS** — `/rss.xml` 标准订阅

### 后台与集成

- **动态同步** — GitHub、Spotify、网易云、NeoDB、Bilibili 等活动流
- **邮件** — SendFlare / SMTP，找回密码与评论通知
- **Telegram** — 说说发布 Webhook
- **附件** — 本地存储 / S3、CDN、定时备份 CLI
- **登录** — 密码 + Passkey；邮箱找回密码

### 主题

内置 **ember**、**kami** 两套前台主题，支持深色模式、响应式布局。

---

## 技术栈

| 层级 | 选型 |
|------|------|
| 运行时 | PHP 8.5 / 8.6 |
| 数据库 | SQLite 3（WAL + FTS5 全文搜索） |
| 架构 | 自研 MVC · 路由 · 中间件 · 服务层 |
| 模板 | 编译型模板引擎 |
| 前端 | 原生 CSS + Vanilla JS（esbuild 压缩 `.min` 资源） |
| 测试 | PHPUnit · GitHub Actions CI |

---

## 快速开始

### 环境要求

- PHP **8.5 或 8.6**（8.6 正式版发布前仅建议用于兼容性测试）
- 扩展：**pdo**、**pdo_sqlite**、**sqlite3**（必需）
- **FTS5**（推荐，全文搜索；缺失时降级为 LIKE）
- **GD**（推荐，验证码与图片处理）
- Web 服务器将请求转发至 `index.php`

### 本地运行

```bash
git clone https://github.com/gentpan/LiteNote.git
cd LiteNote
cp .env.example .env
# 编辑 .env：至少设置 APP_KEY（32 字符以上随机字符串）

php -S 127.0.0.1:5555 index.php
```

浏览器访问 `http://127.0.0.1:5555`，后台入口 `/admin`。

首次部署会从种子库复制默认数据；初始账号见 `runtime/storage/.initial-admin-password`（若存在），默认可能是 `admin / admin123` — **请立即修改密码并绑定 Passkey**。

### 生产部署（简要）

```bash
# 1. 构建并上传代码（排除 .env、database.sqlite、uploads 等运行时数据）
npm install && npm run deploy:assets

# 2. 服务器上设置 .env（APP_URL、APP_KEY、APP_DEBUG=false）

# 3. 目录权限
chown -R www-data:www-data runtime uploads
```

推荐使用 **Caddy** 或 **Nginx + PHP-FPM**。若前面还有 CDN/反代，可在 `.env` 配置 `TRUSTED_PROXIES`。

---

## 目录结构

```text
LiteNote/
├── index.php          # 前台入口
├── admin/             # 后台页面与静态资源
├── core/              # 框架、控制器、模型、服务、路由
├── themes/            # 前台主题（ember / kami）
├── plugins/           # 插件（含 X 卡片）
├── scripts/           # 资源构建、备份 CLI
├── tests/             # PHPUnit 测试
├── uploads/           # 用户上传（git 忽略内容，保留 .htaccess）
├── runtime/           # SQLite、缓存、日志、文章 Markdown
├── config.php         # 应用配置
└── .env.example       # 环境变量模板
```

---

## 配置说明

复制 `.env.example` 为 `.env`，常用变量：

| 变量 | 用途 |
|------|------|
| `APP_URL` | 站点 URL |
| `APP_KEY` | 加密密钥（生产必填，≥32 字符） |
| `APP_DEBUG` | 调试模式；生产务必 `false` |
| `TRUSTED_PROXIES` | 可信反向代理 IP，逗号分隔 |
| `ACTIVITY_API_TOKEN` | Activity API Bearer 鉴权（可选） |
| `DEEPSEEK_*` / `OPENAI_*` | AI 文章摘要 |
| `X_BEARER_TOKEN` | X 卡片官方 API（可选） |
| `SENDFLARE_*` / `SMTP_*` | 邮件发送 |
| `TELEGRAM_*` | Telegram 说说发布 |

完整列表见 [`.env.example`](.env.example)。

---

## 开发与 CI

生产不依赖 PHPUnit；本地开发与 CI 需要：

```bash
composer install          # PHPUnit
npm install
npm run build:assets      # 压缩 themes/、admin/assets/、plugins/ 资源
composer test             # 运行测试，并把弃用提示视为失败
```

- `npm run deploy:assets` — 构建后删除已有 `.min` 对应的源文件（生产裁剪）
- `php scripts/backup.php` — 按设置执行数据库/文件备份（可挂 cron）

推送至 `main` 分支会触发 GitHub Actions：PHP 8.5 / 8.6 语法检查与 PHPUnit，以及资源构建校验。

---

## 插件与主题

**插件** — 在 `plugins/` 下放置 `plugin.json`，后台启用后自动注册路由、菜单与 Activity 适配器。参考 `plugins/x/`（X 卡片）。

**主题** — 在 `themes/` 下新增目录与 `theme.json`，后台切换即可。模板语法见现有 `ember` / `kami` 主题。

---

## 安全提示

- 生产环境必须设置强随机 `APP_KEY`
- 首次登录后修改默认密码，并绑定 Passkey
- OAuth / S3 等敏感凭据使用 `APP_KEY` 加密存储
- 公开 Activity API 默认可读但剥离 `metadata`；需要保护时设置 `ACTIVITY_API_TOKEN`
- 上传目录已包含 `.htaccess` 禁止脚本执行

---

## 开源协议

[MIT](LICENSE)

---

<p align="center">
  如果 LiteNote 对你有帮助，欢迎 Star ⭐
</p>
