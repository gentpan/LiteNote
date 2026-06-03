# LiteNote - PHP 8.5 个人博客

一个用 PHP 8.5 写的轻量级个人博客系统，原生代码不依赖任何框架。

## ✨ 主题

LiteNote 内置两套主题，可通过后台「设置」切换：

| 主题 | 风格 | 主色 | 适用场景 |
|---|---|---|---|
| **Default（默认）** | 参考 networksdb.io，工具型蓝灰配色 | `#2f5c8c` 深蓝渐变 | 工具/技术/严肃内容 |
| **Ember（余烬）** | 暖米色背景 + 陶土红强调 | `#E65A4C` 朱砂红 | 文艺/生活/随笔 |

主题文件位于 `public/assets/css/themes/`，添加新主题只需：
1. 在 `themes/` 下创建 `<name>.css`
2. 后台 → 设置 → 主题 → 填入 `<name>` → 保存

## ✨ 功能

- 📝 **文章管理** — 分类、标签（关键词）、slug 固定链接、伪静态、Markdown/HTML 双编辑
- 📑 **页面管理** — 自定义单页（如关于页、友链页），可加入导航
- 📎 **附件管理** — 拖拽上传，文件分类管理，前台可调用
- 🔗 **友情链接** — CRUD + RSS 抓取 + 自动聚合（友链 RSS 显示在友链页）
- 📡 **RSS 订阅** — `/feed` 输出本站 RSS，`/friends/feed` 输出友链聚合 RSS
- 💬 **说说** — 短内容时间线
- 📊 **访问统计** — PV/UV/IP/Referer/UA 记录 + 后台图表
- ⚙️ **站点设置** — KV 存储，后台可视化配置
- 💭 **评论** — 前台提交 + 后台审核 + 嵌套回复 + 简单反垃圾

## 🚀 快速开始

```bash
# 启动开发服务器
cd blog
php -S 127.0.0.1:5555 -t public router.php

# 访问
open http://127.0.0.1:5555/install   # 首次访问会建表 + 写入默认数据
open http://127.0.0.1:5555/          # 博客首页
open http://127.0.0.1:5555/admin/login  # 后台
```

默认管理员账号：`admin` / `admin123`（登录后请立即修改）

## 📁 目录结构

```
blog/
├── public/                # Web 根目录
│   ├── index.php          # 前端控制器
│   ├── .htaccess          # Apache 伪静态
│   ├── admin/             # 后台入口
│   └── assets/            # 静态资源
├── app/
│   ├── Core/              # 框架核心（路由/视图/DB/Session/...）
│   ├── Models/            # 数据模型（10 张表对应 10 个 Model）
│   ├── Controllers/       # 控制器（前台 + 后台）
│   ├── Middleware/        # 中间件
│   └── Services/          # 业务服务（统计/友链 RSS/安装）
├── views/                 # 模板
│   ├── front/             # 前台视图
│   ├── admin/             # 后台视图
│   └── layouts/           # 布局
├── storage/               # 运行时（cache/logs/sqlite）
├── config/                # 配置
├── routes/                # 路由表
└── router.php             # PHP 内置服务器路由
```

## 🗄️ 数据库

默认使用 SQLite（无需配置）。要切换 MySQL，修改 `config/config.php`：

```php
'database' => [
    'driver' => 'mysql',
    'mysql'  => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'blog',
        'username' => 'root',
        'password' => '',
    ],
],
```

## 🌐 伪静态规则

- `/` — 首页
- `/post/{slug}.html` — 文章详情
- `/category/{slug}` — 分类页
- `/tag/{slug}` — 标签页
- `/page/{slug}.html` — 单页
- `/shuoshuo` — 说说
- `/archives` — 归档
- `/search` — 搜索
- `/friends` — 友链页
- `/feed` — RSS
- `/friends/feed` — 友链聚合 RSS
- `/admin/*` — 后台

## 🛠️ 技术栈

- PHP 8.5（readonly class、enum、nullsafe `?->`、first-class callable）
- PDO + SQLite
- 原生 CSS + Vanilla JS
- 自实现 Markdown 解析器（不引第三方）
- 自实现 RSS 生成器
- 自实现模板引擎（带 layout 继承）

## 📋 系统要求

- PHP 8.5+
- PDO 扩展（SQLite 或 MySQL）
- Apache + mod_rewrite（生产环境）/ `php -S`（开发环境）

## 🔐 默认账号

| 角色 | 用户名 | 密码 |
|------|--------|------|
| 管理员 | admin | admin123 |

**⚠️ 部署到生产前务必修改密码！**
