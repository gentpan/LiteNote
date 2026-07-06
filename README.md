# LiteNote

LiteNote 是一个基于 PHP 8.5 和 SQLite 的轻量级个人发布系统，适合用来搭建个人博客、记录说说、分享音乐、展示 X 卡片、管理评论与 RSS。

项目目标很直接：部署简单、运行成本低、后台完整、代码结构清晰，方便个人长期维护和二次开发。

[在线演示](https://litenote.io)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-3.x-003B57?logo=sqlite&logoColor=white)
![Version](https://img.shields.io/badge/version-1.0.0-0052D9)
![License](https://img.shields.io/badge/license-MIT-green)

## 主要功能

- 文章发布：Markdown、分类、封面、Slug、草稿、置顶、推荐、AI 摘要。
- 短内容：支持图片、音乐关联、点赞、评论，适合作为个人动态时间线。
- 音乐分享：音频、封面、歌词、播放页、歌曲评论、发布时间排序。
- X 卡片：服务端抓取 X 内容，缓存文本、作者、媒体和互动数据，前端直接渲染。
- 评论系统：AJAX 提交、审核、验证码、嵌套回复、头像、IP 归属地。
- 页面与导航：自定义页面、归档、搜索、友链、订阅、RSS、`llms.txt`。
- 后台管理：文章、页面、分类、评论、音乐、友链、附件、设置、动态同步。
- 登录安全：Passkey 登录、邮箱找回密码、CSRF 防护、评论反垃圾。

## 技术栈

| 模块 | 实现 |
|---|---|
| 语言 | PHP 8.5 |
| 数据库 | SQLite 3 + PDO |
| 架构 | 自实现 MVC、Front Controller、中间件、服务层 |
| 路由 | 自实现 Router，支持路由组和参数匹配 |
| 模板 | 自实现编译型模板引擎 |
| Markdown | 内置 Markdown 解析 |
| 前端 | 原生 CSS + Vanilla JavaScript |
| 图标 | FontAwesome CDN |
| 邮件 | SendFlare API |
| 存储 | 本地文件系统，保存上传文件、缓存媒体、Markdown 正文和 SQLite 数据 |

## 项目优势

- 不依赖 Composer，核心系统开箱即用。
- SQLite 单文件数据库，备份、迁移、部署都更简单。
- 后台功能完整，不需要再接入外部 CMS。
- X 和音乐信息由服务端抓取并缓存，前端加载更稳定。
- 运行时数据和源码分离，适合 Git 管理与服务器部署。
- 目录结构清晰，框架、后台、主题、上传、运行时数据各自独立。
- 代码规模可控，便于个人定制和长期维护。

## 目录结构

```text
LiteNote/
├── admin/           # 后台入口、后台页面和后台资源
├── core/            # 框架核心、控制器、模型、服务、路由
├── themes/          # 前台主题
├── plugins/         # 插件目录
├── scripts/         # 前端资源构建与备份 CLI（见下方「开发与 CI」）
├── tests/           # PHPUnit 单元测试
├── .github/         # GitHub Actions CI 工作流
├── uploads/         # 上传文件
├── runtime/         # SQLite、缓存、导入文件、生成内容
├── config.php       # 应用配置
├── .env.example     # 环境变量示例
└── index.php        # 前台入口
```

## 开发与 CI

`scripts/` 与 `tests/` 为工程辅助目录，生产部署不依赖 PHPUnit，但构建压缩资源需要 `scripts/`。

```bash
composer install          # 安装 PHPUnit（仅开发/CI）
npm install
npm run build:assets      # 压缩 themes/admin/plugins 下的 CSS/JS
vendor/bin/phpunit        # 运行单元测试
```

生产环境部署资源时可执行 `npm run deploy:assets`（构建后删除已有 `.min` 对应的源文件）。

## 运行要求

- PHP 8.5+
- SQLite 扩展
- PDO 扩展
- GD 扩展，推荐用于验证码和图片处理
- Web Server，将请求转发到 `index.php`

## 本地运行

```bash
cp .env.example .env
php -S 127.0.0.1:5555 index.php
```

默认后台账号：

```text
admin / admin123
```

首次登录后请立即修改默认密码。

## 配置

密钥和第三方 API 信息通过 `.env` 或服务器环境变量配置。

常用集成：

- DeepSeek 或兼容 AI 服务，用于文章摘要。
- SendFlare，用于找回密码和评论通知邮件。
- X API Bearer Token，用于服务端抓取 X 内容。

## License

MIT
