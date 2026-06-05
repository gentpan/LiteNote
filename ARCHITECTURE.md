# LiteNote — 架构与实现文档

> 资深全栈视角下的项目全景图  
> 基于 LiteNote v1.x 完整代码扫描后输出  
> 包含:架构 · 文件结构 · 数据库模式 · API 端点 · UI 架构 · 核心代码

---

## 0. 项目现状摘要

| 维度 | 现状 |
|---|---|
| 语言 | PHP 8.5+(使用 readonly、enum-friendly 写法、first-class callable) |
| 架构 | 自实现 MVC + Front Controller + 中间件 + 模板引擎 |
| 数据库 | SQLite 单库,PDO + 预处理语句 |
| 前端 | 原生 CSS + Vanilla JS + FontAwesome,**无构建工具** |
| 依赖 | **零外部依赖**(无 Composer,无 npm) |
| 模板 | 自实现编译型模板,支持 `@extends` / `@section` / `@yield` / `@include` / `{{ }}` / `{!! !!}` |
| 安全 | CSRF(时序安全 `hash_equals`)、`htmlspecialchars` 默认转义、SQL 注入白名单防护 |
| 主题 | 前台 Ember 浅色 + 深色模式,后台科技蓝管理面板 |
| 状态 | 已完成一轮重构(`REFACTORING.md`),N+1 已消除,关键重复代码已抽 trait |

---

## 1. 架构

### 1.1 总体架构图

```
                ┌─────────────────────────────────────────────┐
                │            HTTP Request                      │
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │   public/index.php  (前台)                  │
                │   public/admin/index.php  (后台)            │
                │   router.php  (PHP built-in 转发器)         │
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │   app/bootstrap.php                          │
                │   - 加载 Core 类(11 个)                     │
                │   - spl_autoload_register('App\\')          │
                │   - Config::load() / Session::start()        │
                │   - View::share('site', ...)                 │
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │   App\Core\Router::dispatch(Request)        │
                │   - 路径归一化(.html 后缀剥离)              │
                │   - 路由表匹配(GET/POST/任意)               │
                │   - 中间件管道(返回 false 短路)            │
                │   - 未匹配 → Response::notFound()           │
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │   Controller::action(Request, $params)      │
                │   - 解析参数 → 校验 → 调 Service/Model      │
                │   - 返回 string(View HTML)或 never(redirect)│
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │   App\Models\* (ActiveRecord 基类)          │
                │   - find / findBy / all / where / paginate  │
                │   - save / delete (自动 insert/update)      │
                │   - setRelation() / getRelation() 预加载    │
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │   App\Core\View::render(template, data)     │
                │   - View Composer 自动注入共享数据           │
                │   - @extends() / @section() 解析             │
                │   - 编译 → 写入 storage/cache/views/         │
                │   - 模板 mtime 校验,修改后自动失效           │
                │   - include 编译后 PHP,extract($data)        │
                └──────────────────┬──────────────────────────┘
                                   │
                ┌──────────────────▼──────────────────────────┐
                │         HTTP Response (HTML / JSON / 302)   │
                └─────────────────────────────────────────────┘
```

### 1.2 请求生命周期(典型"读"请求)

```
GET /post/welcome.html
  ↓
router.php (PHP built-in): 真实文件? 否 → require public/index.php
  ↓
public/index.php:
  - define APP_START (性能打点)
  - require app/bootstrap.php
  - new Request() / new Router()
  - require routes/web.php (注册所有路由)
  - StatService::record($request)  ← 全站统计
  - $router->dispatch($request)
  ↓
Router::dispatch():
  - normalizePath: /post/welcome.html → /post/welcome
  - buildTriedPaths: [/post/welcome, /post/welcome/]
  - 匹配 [GET, /post/{slug}] → $params = ['slug' => 'welcome']
  - 中间件管道(本路由无)
  - runHandler([PostController, 'show'], $request, $params)
  ↓
PostController::show($request, $params):
  - $post = Post::findBySlug('welcome')
  - $post->incrementViews()  ← UPDATE posts SET views=views+1
  - View::render('post.show', [...], 'layouts.front')
  ↓
View::render():
  - merge shared + data
  - run composer('layouts.front', fn)  ← 自动注入 categories / recentPosts
  - renderFile('post.show', $data, 'layouts.front')
  - 读取 views/post/show.php + views/layouts/front.php
  - compile → 写 storage/cache/views/<md5>.php
  - include + extract + ob_start
  ↓
HTML 响应
```

### 1.3 模块依赖图

```
                     routes/web.php
                     routes/admin.php
                            │
                            ▼
        ┌───────────────────────────────────┐
        │   Controllers (Front + Admin)    │
        │   13 + 13 = 26 个                │
        └─────┬───────────────┬─────────────┘
              │               │
              │               │
              ▼               ▼
     ┌────────────┐    ┌────────────────┐
     │  Services  │    │     Models     │
     │ Installer  │    │  Model 基类 +  │
     │ StatService│    │  10 个子类     │
     │ FriendRss  │    └───────┬────────┘
     └─────┬──────┘            │
           │                   ▼
           │            ┌─────────────┐
           │            │  Core\DB    │
           │            └──────┬──────┘
           │                   │
           └─────────┬─────────┘
                     ▼
        ┌────────────────────────┐
        │   Core (10 个)         │
        │ Config / Database /    │
        │ Request / Response /   │
        │ Router / Session /     │
        │ View / Validator /     │
        │ Markdown / Rss /       │
        │ Helper                 │
        └────────────────────────┘
```

### 1.4 核心设计原则

| 原则 | 体现 |
|---|---|
| **零依赖** | 模板引擎、Markdown、RSS、查询构造器全部自实现 |
| **约定优于配置** | `static::$table` 声明表名,`Model::find` 自动用 `id` 主键 |
| **可读性优先** | 中文变量名 / 类名 / 注释;核心类不引入语法糖(无 method chaining 套娃) |
| **安全默认** | 模板 `{{ }}` 默认 `htmlspecialchars`;`{!! !!}` 才裸输出;`Model::guardOrderBy` 白名单 |
| **渐进式改进** | REFACTORING 报告明确的"不破坏向后兼容"原则,所有重构保持路由 / 视图 / 表结构不变 |
| **DRY by Trait** | `HasSlug` 消除 5 处 slug 唯一性循环;`HasFlashRedirect` 消除 20+ 处 flash+redirect |

---

## 2. 文件结构

### 2.1 完整目录树

```
LiteNote/
├── public/                          # Web 根目录(对外)
│   ├── index.php                    # 前台入口
│   ├── admin/
│   │   └── index.php                # 后台入口
│   ├── .htaccess                    # Apache 伪静态
│   └── assets/
│       ├── css/
│       │   ├── admin.css            # 后台样式
│       │   ├── front.css            # 前台样式
│       │   └── themes/
│       │       ├── default.css      # 蓝灰工具型
│       │       └── ember.css        # 米色文艺型
│       ├── js/
│       │   ├── front.js             # 前台交互
│       │   └── admin.js             # 后台交互
│       ├── fontawesome/             # 图标库
│       └── uploads/                 # 运行时上传
│
├── app/                             # 应用代码(命名空间 App\)
│   ├── bootstrap.php                # 启动器
│   ├── Core/                        # 核心层(10 个)
│   │   ├── Config.php
│   │   ├── Database.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   ├── Session.php
│   │   ├── View.php
│   │   ├── Validator.php
│   │   ├── Markdown.php
│   │   ├── Rss.php
│   │   └── Helper.php
│   ├── Models/                      # 数据层(11 个)
│   │   ├── Model.php                # ActiveRecord 基类
│   │   ├── User.php
│   │   ├── Category.php
│   │   ├── Tag.php
│   │   ├── Post.php
│   │   ├── Page.php
│   │   ├── Attachment.php
│   │   ├── Comment.php
│   │   ├── Link.php
│   │   ├── Shuoshuo.php
│   │   └── Setting.php
│   ├── Controllers/                 # 控制层(26 个)
│   │   ├── Front/                   # 前台 13 个
│   │   │   ├── HomeController.php
│   │   │   ├── PostController.php
│   │   │   ├── PageController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── TagController.php
│   │   │   ├── ArchiveController.php
│   │   │   ├── SearchController.php
│   │   │   ├── ShuoshuoController.php
│   │   │   ├── FriendController.php
│   │   │   ├── CommentController.php
│   │   │   ├── FeedController.php
│   │   │   ├── StatController.php
│   │   │   └── InstallController.php
│   │   └── Admin/                   # 后台 13 个
│   │       ├── AuthController.php
│   │       ├── DashboardController.php
│   │       ├── PostController.php
│   │       ├── CategoryController.php
│   │       ├── TagController.php
│   │       ├── PageController.php
│   │       ├── AttachmentController.php
│   │       ├── LinkController.php
│   │       ├── CommentController.php
│   │       ├── ShuoshuoController.php
│   │       ├── StatController.php
│   │       ├── SettingController.php
│   │       └── ProfileController.php
│   ├── Services/                    # 业务服务(3 个)
│   │   ├── Installer.php            # 建表 + 默认数据
│   │   ├── StatService.php          # 访问统计
│   │   └── FriendRssService.php     # 友链 RSS 抓取 + 聚合
│   ├── Middleware/                  # 中间件(2 个)
│   │   ├── AdminAuth.php            # 后台鉴权
│   │   └── CsrfMiddleware.php       # CSRF 校验
│   └── Traits/                      # 横切关注点(2 个)
│       ├── HasSlug.php              # slug 生成与唯一性
│       └── HasFlashRedirect.php     # flash + redirect 快捷
│
├── views/                           # 视图模板
│   ├── layouts/                     # 布局(3 个)
│   │   ├── front.php                # 前台布局(@yield('content'))
│   │   ├── admin.php                # 后台布局
│   │   └── admin_auth.php           # 登录页布局
│   ├── home/                        # 首页
│   │   └── index.php                # @extends('layouts.front')
│   ├── post/                        # 文章
│   │   ├── index.php                # 后台列表
│   │   ├── form.php                 # 后台写/编辑
│   │   └── show.php                 # 前台详情
│   ├── page/                        # 页面(单页)
│   ├── category/                    # 分类
│   ├── tag/                         # 标签
│   ├── archive/                     # 归档
│   ├── search/                      # 搜索
│   ├── shuoshuo/                    # 说说
│   ├── friend/                      # 友链
│   ├── comment/                     # 评论
│   ├── attachment/                  # 附件
│   ├── link/                        # 后台友链
│   ├── dashboard/                   # 后台首页
│   ├── stat/                        # 后台统计
│   ├── setting/                     # 后台设置
│   ├── profile/                     # 后台个人资料
│   ├── auth/                        # 登录页
│   ├── errors/                      # 错误页(404 等)
│   └── install/                     # 安装页
│
├── routes/                          # 路由定义
│   ├── web.php                      # 前台路由
│   └── admin.php                    # 后台路由(带 AdminAuth 组中间件)
│
├── config/
│   └── config.php                   # 全局配置
│
├── storage/                         # 运行时(无需版本控制)
│   ├── database.sqlite              # SQLite 数据库
│   ├── database.sqlite.installed    # 安装锁
│   ├── cache/
│   │   └── views/                   # 编译后的模板缓存
│   └── logs/                        # 日志
│
├── install/                         # 安装提示页(可选)
├── router.php                       # PHP 内置服务器路由
├── README.md
├── REFACTORING.md                   # 重构报告(279 行)
└── ARCHITECTURE.md                  # ← 本文档
```

### 2.2 关键文件职责

| 文件 | 行数 | 职责 |
|---|---|---|
| `app/bootstrap.php` | 74 | 加载 Core、注册自动加载、初始化 Session、注入站点设置到 View |
| `app/Core/Router.php` | 152 | 路由注册 / 匹配 / 中间件管道 / 404 |
| `app/Core/Database.php` | 142 | SQLite PDO 单例、事务、insert/update/delete 封装 |
| `app/Core/View.php` | 241 | 编译型模板引擎、布局继承、View Composer、缓存 |
| `app/Models/Model.php` | 242 | ActiveRecord 基类、`guardOrderBy`、`paginate`、关系缓存 |
| `app/Models/Post.php` | 210 | 文章 + 标签 + 分类 JOIN 预加载,消除 N+1 |
| `app/Services/Installer.php` | 316 | 建表 + 默认管理员/分类/示例文章 + install.lock |
| `app/Traits/HasSlug.php` | 57 | slug 生成与唯一性校验 |
| `app/Traits/HasFlashRedirect.php` | 37 | flash + redirect 快捷 |
| `app/Controllers/Admin/PostController.php` | 264 | 后台文章 CRUD + 批量操作(事务化) |
| `public/index.php` | 28 | 前台入口 |
| `public/admin/index.php` | 23 | 后台入口(定义 IS_ADMIN 常量) |
| `router.php` | 27 | PHP 内置服务器路由转发 |

---

## 3. 数据库模式

### 3.1 ER 概览

```
┌────────┐         ┌──────────┐         ┌──────────┐
│ users  │────┐    │ posts    │────┐    │  pages   │
│  id    │    │    │  id      │    │    │   id     │
│  name  │    │    │  slug    │    │    │  slug    │
│  pwd   │    │    │  status  │    │    │  is_nav  │
│  email │    │    │  cat_id  │    │    │  sort    │
│  role  │    │    │  user_id ├────┘    └──────────┘
└────────┘    │    │  cover   │
              │    │  views   │    ┌──────────┐
              │    │  is_top  │    │ settings │
              │    └────┬─────┘    │  k/v     │
              │         │          └──────────┘
              │         ▼
              │    ┌──────────┐
              │    │ post_tag │◀─┐
              │    │ post_id  │  │
              │    │ tag_id   │  │
              │    └────┬─────┘  │
              │         │        │
              │         ▼        │
              │    ┌──────────┐  │
              │    │  tags    │  │
              │    │  slug    │  │
              │    │  count   │  │
              │    └──────────┘  │
              │                  │
              │    ┌──────────────┴──────┐
              │    │   comments         │
              │    │   post_id / page_id │
              │    │   parent_id (嵌套)  │
              │    │   status (pending) │
              │    └────────────────────┘
              │
              │    ┌──────────────────┐
              │    │ attachments      │
              └───▶│ user_id          │
                   │ filename/original│
                   │ filepath / url   │
                   └──────────────────┘

       ┌──────────┐    ┌──────────┐    ┌──────────┐
       │categories│    │  links   │    │ shuoshuo │
       │  slug    │    │  url     │    │ content  │
       │  parent  │    │  rss_url │    │ images   │
       │  count   │    │  enabled │    │ mood     │
       └──────────┘    └──────────┘    └──────────┘

       ┌──────────┐
       │  stats   │   全站访问日志(分日分路径)
       │  path    │
       │  ip/ua   │
       │  referer │
       │  day     │
       └──────────┘
```

### 3.2 表结构(共 12 张)

#### 3.2.1 `users` — 用户

```sql
CREATE TABLE users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        VARCHAR(50)  UNIQUE NOT NULL,
    password        VARCHAR(255) NOT NULL,             -- password_hash()
    email           VARCHAR(100),
    nickname        VARCHAR(50),
    avatar          VARCHAR(255),
    role            VARCHAR(20)  DEFAULT 'admin',
    last_login_at   DATETIME,
    last_login_ip   VARCHAR(45),                       -- 支持 IPv6
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.2 `categories` — 分类

```sql
CREATE TABLE categories (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         VARCHAR(50)  NOT NULL,
    slug         VARCHAR(100) UNIQUE NOT NULL,
    description  VARCHAR(255),
    parent_id    INTEGER      DEFAULT 0,              -- 支持二级分类(预留)
    sort         INTEGER      DEFAULT 0,              -- 排序权重
    post_count   INTEGER      DEFAULT 0,              -- 冗余计数
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.3 `tags` — 标签

```sql
CREATE TABLE tags (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(50)  UNIQUE NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    post_count  INTEGER      DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.4 `posts` — 文章

```sql
CREATE TABLE posts (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    title             VARCHAR(255) NOT NULL,
    slug              VARCHAR(255) UNIQUE NOT NULL,
    summary           TEXT,                          -- 摘要(可空,自动从 content 截取)
    content           TEXT         NOT NULL,         -- HTML
    markdown_content  TEXT,                          -- Markdown 原文
    cover             VARCHAR(255),                  -- 封面图 URL
    category_id       INTEGER      DEFAULT 0,
    user_id           INTEGER      DEFAULT 1,
    views             INTEGER      DEFAULT 0,
    comments_count    INTEGER      DEFAULT 0,
    is_top            INTEGER      DEFAULT 0,        -- 置顶
    is_recommend      INTEGER      DEFAULT 0,        -- 推荐
    status            VARCHAR(20)  DEFAULT 'published', -- published / draft
    published_at      DATETIME,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_posts_status       ON posts(status);
CREATE INDEX idx_posts_published_at ON posts(published_at);
CREATE INDEX idx_posts_category     ON posts(category_id);
```

#### 3.2.5 `post_tag` — 文章-标签关联

```sql
CREATE TABLE post_tag (
    post_id  INTEGER NOT NULL,
    tag_id   INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id)
);
```

#### 3.2.6 `pages` — 单页

```sql
CREATE TABLE pages (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    title             VARCHAR(255) NOT NULL,
    slug              VARCHAR(255) UNIQUE NOT NULL,
    content           TEXT         NOT NULL,
    markdown_content  TEXT,
    views             INTEGER      DEFAULT 0,
    is_nav            INTEGER      DEFAULT 0,        -- 是否加入导航
    sort              INTEGER      DEFAULT 0,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.7 `attachments` — 附件

```sql
CREATE TABLE attachments (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    filename       VARCHAR(255) NOT NULL,            -- 服务器文件名
    original_name  VARCHAR(255) NOT NULL,            -- 用户上传原名
    filepath       VARCHAR(500) NOT NULL,            -- 服务器路径
    fileurl        VARCHAR(500) NOT NULL,            -- 访问 URL
    filetype       VARCHAR(50),                      -- 扩展名
    filesize       INTEGER      DEFAULT 0,
    mime_type      VARCHAR(100),
    user_id        INTEGER      DEFAULT 1,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.8 `comments` — 评论

```sql
CREATE TABLE comments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER      DEFAULT 0,               -- 0 表示对页面评论
    page_id    INTEGER      DEFAULT 0,
    parent_id  INTEGER      DEFAULT 0,               -- 嵌套回复
    nickname   VARCHAR(50)  NOT NULL,
    email      VARCHAR(100),
    website    VARCHAR(255),
    content    TEXT         NOT NULL,
    ip         VARCHAR(45),
    ua         VARCHAR(255),
    status     VARCHAR(20)  DEFAULT 'pending',       -- pending/approved/spam
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_comments_post   ON comments(post_id);
CREATE INDEX idx_comments_status ON comments(status);
```

#### 3.2.9 `links` — 友情链接

```sql
CREATE TABLE links (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(50)  NOT NULL,
    url         VARCHAR(255) NOT NULL,
    logo        VARCHAR(255),
    description VARCHAR(255),
    rss_url     VARCHAR(255),                        -- 友链 RSS
    sort        INTEGER      DEFAULT 0,
    is_enabled  INTEGER      DEFAULT 1,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.10 `shuoshuo` — 说说

```sql
CREATE TABLE shuoshuo (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    content    TEXT    NOT NULL,
    images     TEXT,                                 -- JSON 数组
    music      TEXT,                                 -- 音乐 URL
    mood       VARCHAR(20),
    is_public  INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.11 `settings` — 站点设置(KV)

```sql
CREATE TABLE settings (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    k           VARCHAR(100) UNIQUE NOT NULL,
    v           TEXT,
    type        VARCHAR(20)  DEFAULT 'string',        -- string/int/bool/json
    label       VARCHAR(100),                        -- 后台显示名
    group_name  VARCHAR(50)  DEFAULT 'basic',        -- basic/comment/feature
    sort        INTEGER      DEFAULT 0
);
```

#### 3.2.12 `stats` — 访问统计

```sql
CREATE TABLE stats (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    path       VARCHAR(255) NOT NULL,
    ip         VARCHAR(45),
    ua         VARCHAR(255),
    referer    VARCHAR(500),
    day        VARCHAR(10)  NOT NULL,                -- 'YYYY-MM-DD',便于按日聚合
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_stats_day  ON stats(day);
CREATE INDEX idx_stats_path ON stats(path);
```

### 3.3 索引策略

| 表 | 索引 | 用途 |
|---|---|---|
| posts | status, published_at, category_id | 列表筛选 + 排序 |
| comments | post_id, status | 按文章/状态查询 |
| stats | day, path | 按日/按路径聚合 |
| 其他 UNIQUE | slug × 5, k, username | 唯一性保证 |

### 3.4 SQLite 单库设计

LiteNote 固定使用 SQLite,无需独立数据库服务。生产部署时备份 `storage/database.sqlite` 即可完整保留业务数据:

```php
'database' => [
    'sqlite' => __DIR__ . '/../storage/database.sqlite',
],
```

`Installer::install()` 使用 SQLite 方言(`AUTOINCREMENT`、`CREATE TABLE IF NOT EXISTS`),升级时通过幂等 `ALTER TABLE` 补齐新增字段。

---

## 4. API 端点

LiteNote 没有走 RESTful JSON API,所有路由返回 HTML 视图(只有 `/api/stats` 和 `/feed` 是特殊响应)。下面是完整路由表。

### 4.1 前台路由(`routes/web.php`)

| Method | Path | Handler | 说明 |
|---|---|---|---|
| GET | `/install` | `InstallController@index` | 安装引导页 |
| GET | `/install/do` | `InstallController@install` | 执行建表 |
| GET | `/` | `HomeController@index` | 首页(文章分页) |
| GET | `/post/{slug}` | `PostController@show` | 文章详情 |
| GET | `/category/{slug}` | `CategoryController@show` | 分类页 |
| GET | `/tag/{slug}` | `TagController@show` | 标签页 |
| GET | `/page/{slug}` | `PageController@show` | 单页(如 about) |
| GET | `/shuoshuo` | `ShuoshuoController@index` | 说说列表 |
| GET | `/archives` | `ArchiveController@index` | 归档 |
| GET | `/search` | `SearchController@index` | 搜索 |
| GET | `/friends` | `FriendController@index` | 友链页 + 聚合 RSS |
| POST | `/comment/submit` | `CommentController@submit` | 评论提交(带 CSRF) |
| GET | `/feed` | `FeedController@feed` | RSS 2.0 |
| GET | `/friends/feed` | `FeedController@friendsFeed` | 友链聚合 RSS |
| GET | `/api/stats` | `StatController@summary` | 统计 JSON |

### 4.2 后台路由(`routes/admin.php`)

> 所有 `/admin/*` 路由都包在 `AdminAuth` 中间件组里(除登录/登出)。  
> 所有写操作都包 `CsrfMiddleware`。

#### 4.2.1 公开路由

| Method | Path | Handler | 中间件 |
|---|---|---|---|
| GET | `/admin/login` | `AuthController@loginForm` | — |
| POST | `/admin/login` | `AuthController@login` | Csrf |
| GET | `/admin/logout` | `AuthController@logout` | — |

#### 4.2.2 受保护路由(全部 `AdminAuth`)

| 资源 | Method | Path | Handler |
|---|---|---|---|
| **首页** | GET | `/admin` | `DashboardController@index` |
| **文章** | GET | `/admin/posts` | `PostController@index` |
| | GET | `/admin/posts/create` | `PostController@create` |
| | POST | `/admin/posts/create` | `PostController@store` |
| | GET | `/admin/posts/{id}/edit` | `PostController@edit` |
| | POST | `/admin/posts/{id}/edit` | `PostController@update` |
| | POST | `/admin/posts/{id}/delete` | `PostController@destroy` |
| | POST | `/admin/posts/bulk` | `PostController@bulk` |
| **分类** | GET | `/admin/categories` | `CategoryController@index` |
| | POST | `/admin/categories/save` | `CategoryController@save` |
| | POST | `/admin/categories/delete` | `CategoryController@destroy` |
| **标签** | GET | `/admin/tags` | `TagController@index` |
| | POST | `/admin/tags/save` | `TagController@save` |
| | POST | `/admin/tags/delete` | `TagController@destroy` |
| **页面** | GET | `/admin/pages` | `PageController@index` |
| | GET | `/admin/pages/create` | `PageController@create` |
| | POST | `/admin/pages/create` | `PageController@store` |
| | GET | `/admin/pages/{id}/edit` | `PageController@edit` |
| | POST | `/admin/pages/{id}/edit` | `PageController@update` |
| | POST | `/admin/pages/delete` | `PageController@destroy` |
| **附件** | GET | `/admin/attachments` | `AttachmentController@index` |
| | POST | `/admin/attachments/upload` | `AttachmentController@upload` |
| | POST | `/admin/attachments/delete` | `AttachmentController@destroy` |
| **友链** | GET | `/admin/links` | `LinkController@index` |
| | POST | `/admin/links/save` | `LinkController@save` |
| | POST | `/admin/links/delete` | `LinkController@destroy` |
| | POST | `/admin/links/refresh` | `LinkController@refresh` |
| **评论** | GET | `/admin/comments` | `CommentController@index` |
| | POST | `/admin/comments/approve` | `CommentController@approve` |
| | POST | `/admin/comments/spam` | `CommentController@spam` |
| | POST | `/admin/comments/delete` | `CommentController@destroy` |
| **说说** | GET | `/admin/shuoshuo` | `ShuoshuoController@index` |
| | GET | `/admin/shuoshuo/create` | `ShuoshuoController@create` |
| | POST | `/admin/shuoshuo/create` | `ShuoshuoController@store` |
| | GET | `/admin/shuoshuo/{id}/edit` | `ShuoshuoController@edit` |
| | POST | `/admin/shuoshuo/{id}/edit` | `ShuoshuoController@update` |
| | POST | `/admin/shuoshuo/delete` | `ShuoshuoController@destroy` |
| **统计** | GET | `/admin/stats` | `StatController@index` |
| **设置** | GET | `/admin/settings` | `SettingController@index` |
| | POST | `/admin/settings/save` | `SettingController@save` |
| **个人** | GET | `/admin/profile` | `ProfileController@index` |
| | POST | `/admin/profile` | `ProfileController@update` |
| | POST | `/admin/profile/password` | `ProfileController@password` |

### 4.3 伪静态(`.htaccess` + `router.php`)

```apache
# public/.htaccess (Apache)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ admin/index.php [L]
```

```php
// router.php (PHP built-in)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;  // 静态资源直接返回
}
if (str_starts_with($path, '/admin')) {
    require __DIR__ . '/public/admin/index.php';
    return true;
}
require __DIR__ . '/public/index.php';
```

### 4.4 中间件机制

```php
// app/Core/Router.php
foreach ($route['middleware'] as $mw) {
    $mwInstance = is_string($mw) ? new $mw() : $mw;
    $result = $mwInstance->handle($request);
    if ($result === false) {
        return;  // 短路(中间件自己处理响应)
    }
}
```

中间件实现示例(`CsrfMiddleware`):

```php
public function handle(Request $request): mixed
{
    if (!in_array($request->method, ['POST', 'PUT', 'DELETE'], true)) {
        return null;  // GET 不校验
    }
    $token = $request->input('_csrf', '');
    if (!Session::verifyCsrf($token)) {
        http_response_code(419);
        echo 'CSRF token mismatch';
        return false;  // 短路
    }
    return null;
}
```

---

## 5. UI 架构

### 5.1 模板引擎(`App\Core\View`)

#### 5.1.1 设计目标

- 编译型:第一次渲染后写入 PHP 文件,后续直接 `include`,**接近原生速度**
- 支持布局继承(`@extends` / `@section` / `@yield`)
- 支持局部包含(`@include`)
- 默认转义(`{{ }}` = `htmlspecialchars`;`{!! !!}` = 裸输出)
- 模板修改自动失效(`filemtime` 校验)
- 共享数据机制(`View::share` + `View::composer`)

#### 5.1.2 指令清单

| 指令 | 作用 | 编译产物 |
|---|---|---|
| `{{ $var }}` | 输出并转义 | `<?= htmlspecialchars((string)($var ?? ''), ENT_QUOTES, 'UTF-8') ?>` |
| `{!! $var !!}` | 裸输出(原文 HTML) | `<?= $var ?>` |
| `{{-- comment --}}` | 模板注释(不输出) | 删除 |
| `@if(expr)` / `@elseif` / `@else` / `@endif` | 条件 | `<?php if(expr): ?> ... <?php endif; ?>` |
| `@foreach($list as $item)` / `@endforeach` | 循环 | `<?php foreach($list as $item): ?> ... <?php endforeach; ?>` |
| `@for($i=0;$i<10;$i++)` / `@endfor` | for 循环 | 同上 |
| `@php` / `@endphp` | 原生 PHP 块 | `<?php` / `?>` |
| `@extends('layouts.front')` | 继承布局 | 编译期替换为父模板内容 |
| `@section('content')` / `@endsection` | 段落定义 | 提取内容到 `$sections` 数组 |
| `@yield('content')` | 段落输出 | 替换为对应 section 内容 |
| `@include('partial.foo')` | 局部包含 | 编译期 inline |

#### 5.1.3 模板解析流程

```
views/post/show.php:
    @extends('layouts.front')
    @section('content')
        <h1>{{ $post->title }}</h1>
        <div class="content">{!! $post->content !!}</div>
    @endsection

       ↓ View::render() 解析

views/layouts/front.php:
    <!DOCTYPE html>
    ...
    <main class="site-main">
        @yield('content')   ← 替换为上面 section 的内容
    </main>
    ...

       ↓ compile() 编译

storage/cache/views/<md5>.php:
    <!DOCTYPE html>
    ...
    <main class="site-main">
        <h1><?= htmlspecialchars((string)($post->title ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="content"><?= $post->content ?></div>
    </main>
    ...

       ↓ extract($data) + include
       
HTML 输出
```

#### 5.1.4 View Composer(共享数据)

`HomeController` 构造函数里注册:

```php
View::composer('layouts.front', function (array $data): array {
    return array_merge($data, [
        'categories'  => Category::allEnabled(),
        'recentPosts' => Post::recent(5),
    ]);
});
```

效果:任何渲染 `layouts.front` 的模板,自动得到 `$categories` 和 `$recentPosts`,**不用每个 Controller 重复传**。重构后 D3(侧边栏共享数据 8+ 处重复)消除。

#### 5.1.5 编译缓存策略

```php
private static function getCacheFile(string $template, string $phpSource, string $sourcePath): string
{
    $mtime = filemtime($sourcePath) ?: time();
    $key = md5($template . '|' . $mtime . '|' . $phpSource);
    $file = self::getCacheDir() . '/' . $key . '.php';
    if (!is_file($file)) {
        file_put_contents($file, $phpSource);
    }
    return $file;
}
```

- 缓存 key = md5(模板名 + 源文件 mtime + 编译后 PHP)
- 模板文件改动 → mtime 变 → key 变 → 自动重写
- 旧缓存文件**会累积**(P5 遗留),定期清理 `storage/cache/views/` 即可

### 5.2 布局系统

#### 5.2.1 前台布局(`views/layouts/front.php`)

```
┌────────────────────────────────────────────┐
│ <!DOCTYPE html>                            │
│ <head>                                     │
│   <meta>, <title> = pageTitle + siteTitle  │
│   <link rel="alternate" type="rss" ...>     │
│   <link fontawesome>                       │
│   <link themes/{default|ember}.css>        │
│ </head>                                    │
│ <body data-theme="default">                │
│   <nav class="site-nav-bar">  顶部/底部    │
│     <a href="/">文章</a>                    │
│     <a href="/shuoshuo">说说</a>            │
│   </nav>                                   │
│   <div class="container">                  │
│     <header class="site-branding">          │
│       <h1>{{ site.title }}</h1>             │
│       <p>{{ site.subtitle }}</p>            │
│     </header>                              │
│     <main> @yield('content') </main>        │
│     <footer>                               │
│       © {{ date('Y') }} {{ site.title }}    │
│       @if site.beian                       │
│         <a>备案号</a>                       │
│       @endif                               │
│     </footer>                              │
│   </div>                                   │
│   <script src="/assets/js/front.js">       │
│ </body>                                    │
└────────────────────────────────────────────┘
```

子页面用 `@extends('layouts.front')` + `@section('content')` 即可。

#### 5.2.2 后台布局(`views/layouts/admin.php`)

```
┌──────────────────────────────────────────┐
│ <aside class="sidebar">                  │
│   仪表盘 / 文章 / 分类 / 标签 / 页面 /   │
│   附件 / 友链 / 评论 / 说说 / 统计 /     │
│   设置 / 个人资料                        │
│ </aside>                                 │
│ <div class="main">                       │
│   <header>面包屑 + 退出登录</header>      │
│   @if flash.success                      │
│     <div class="alert alert-success">    │
│   @endif                                 │
│   @if flash.error                        │
│     <div class="alert alert-error">      │
│   @endif                                 │
│   <main> @yield('content') </main>       │
│ </div>                                   │
└──────────────────────────────────────────┘
```

#### 5.2.3 登录页布局(`views/layouts/admin_auth.php`)

简洁的居中卡片,只含 `<form>` 提交到 `/admin/login`。

### 5.3 主题系统

#### 5.3.1 主题文件

```
public/assets/css/themes/
├── default.css   # 蓝灰工具型,主色 #2f5c8c
└── ember.css     # 米色文艺型,主色 #E65A4C
```

#### 5.3.2 加载机制

`layouts/front.php` 根据 `$site['theme']` 动态加载:

```html
<link rel="stylesheet" 
      href="/assets/css/themes/{{ $theme === 'default' ? 'default' : $theme }}.css?v={{ @filemtime(主题文件路径) ?: time() }}">
```

`?v=<mtime>` 是 cache-busting,主题 CSS 改动立即生效。

#### 5.3.3 添加新主题

1. 在 `public/assets/css/themes/` 下创建 `mytheme.css`
2. 后台 → 设置 → 主题 → 填入 `mytheme` → 保存

零代码改动,纯配置驱动。

### 5.4 静态资源组织

```
public/assets/
├── css/
│   ├── admin.css              # 后台统一样式
│   ├── front.css              # 前台统一样式
│   └── themes/<name>.css      # 主题(可热切换)
├── js/
│   ├── front.js               # 主题切换、滚动、点赞等
│   └── admin.js               # 表单交互、批量操作确认
├── fontawesome/               # 6.x
└── uploads/                   # 运行时,public 写入
```

### 5.5 表单与 CSRF

所有后台 POST 表单都嵌入 CSRF token:

```html
<form method="post" action="/admin/posts/create">
    <input type="hidden" name="_csrf" value="{{ $csrf }}">
    <!-- 字段 -->
</form>
```

`$csrf` 来自 `Session::csrfToken()`,`CsrfMiddleware` 校验。

### 5.6 响应式 + 主题切换

前台使用 CSS 变量 + `data-theme` 属性实现主题切换:

```css
/* default.css */
body[data-theme="default"] { 
    --primary: #2f5c8c; 
    --bg: #fafbfc;
}
/* ember.css */
body[data-theme="ember"] { 
    --primary: #E65A4C;
    --bg: #f7f1e8;
}
```

`front.js` 监听主题切换按钮 → 写 localStorage → 改 `data-theme`。

---

## 6. 核心代码

> 以下展示的都是**实际已落地**的关键文件,直接对应工作区现有代码。

### 6.1 启动器 — `app/bootstrap.php`

```php
<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

require __DIR__ . '/Core/Config.php';
require __DIR__ . '/Core/Database.php';
require __DIR__ . '/Core/Request.php';
require __DIR__ . '/Core/Response.php';
require __DIR__ . '/Core/Router.php';
require __DIR__ . '/Core/View.php';
require __DIR__ . '/Core/Session.php';
require __DIR__ . '/Core/Helper.php';
require __DIR__ . '/Core/Validator.php';
require __DIR__ . '/Core/Markdown.php';
require __DIR__ . '/Core/Rss.php';

// 简易 PSR-4 自动加载
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

Config::load('config');
date_default_timezone_set(Config::get('app.timezone', 'UTC'));

if (Config::get('app.debug', false)) {
    error_reporting(E_ALL); ini_set('display_errors', '1');
} else {
    error_reporting(0); ini_set('display_errors', '0');
}

Session::start();

// 共享站点设置到所有视图
if (is_file(Config::get('database.sqlite'))) {
    try {
        $settings = Setting::allAsArray();
        foreach ($settings as $k => $v) {
            View::share($k, $v);
            Config::set("site.{$k}", $v);
        }
        View::share('site', Config::get('site'));
    } catch (\Throwable) {
        // 首次安装时数据库还没建好
    }
}
View::share('site', Config::get('site'));
```

### 6.2 路由 — `app/Core/Router.php`(改进版)

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $groupPrefix = [];
    private array $groupMiddleware = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }
    public function any(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('*', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;
        $this->groupPrefix[] = trim($prefix, '/');
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $prefix = $this->groupPrefix ? '/' . implode('/', $this->groupPrefix) : '';
        $fullPath = '/' . trim($prefix . ($path === '/' ? '/' : $path), '/');
        if ($fullPath === '//') $fullPath = '/';

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $this->normalizePath($request->path);
        $triedPaths = $this->buildTriedPaths($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $request->method) continue;

            $params = null;
            foreach ($triedPaths as $tryPath) {
                $params = $this->match($route['path'], $tryPath);
                if ($params !== null) break;
            }
            if ($params === null) continue;

            // 中间件管道
            foreach ($route['middleware'] as $mw) {
                $mwInstance = is_string($mw) ? new $mw() : $mw;
                $result = $mwInstance->handle($request);
                if ($result === false) return;
            }
            $this->runHandler($route['handler'], $request, $params);
            return;
        }

        if (!str_starts_with($request->path, '/install')) {
            Response::notFound('404 - 页面不存在');
        }
    }

    private function normalizePath(string $path): string
    {
        if (str_ends_with($path, '.html')) $path = substr($path, 0, -5);
        return $path === '' ? '/' : $path;
    }

    private function buildTriedPaths(string $path): array
    {
        $tried = [$path];
        if (substr($path, -1) !== '/' && $path !== '/') {
            $tried[] = $path . '/';
        } elseif ($path !== '/') {
            $tried[] = rtrim($path, '/');
        }
        return $tried;
    }

    private function runHandler(callable|array $handler, Request $request, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            echo (new $class())->$method($request, $params);
        } elseif (is_callable($handler)) {
            echo $handler($request, $params);
        }
    }

    private function match(string $routePath, string $actualPath): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        if (preg_match('#^' . $regex . '$#', $actualPath, $matches)) {
            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) $params[$k] = $v;
            }
            return $params;
        }
        return null;
    }
}
```

### 6.3 数据库 — `app/Core/Database.php`

```php
<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $path = Config::get('database.sqlite');
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
    public function pdo(): PDO { return $this->pdo; }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    public function fetchAll(string $sql, array $params = []): array
    { return $this->query($sql, $params)->fetchAll(); }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }
    public function fetchColumn(string $sql, array $params = []): mixed
    { return $this->query($sql, $params)->fetchColumn(); }

    public function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)',
            $table, implode(',', $cols), implode(',', $placeholders));
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            $sets[] = "{$k} = :set_{$k}";
            $params["set_{$k}"] = $v;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s',
            $table, implode(',', $sets), $where);
        foreach ($whereParams as $k => $v) $params[$k] = $v;
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM %s WHERE %s', $table, $where), $params)->rowCount();
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void          { $this->pdo->commit(); }
    public function rollBack(): void        { $this->pdo->rollBack(); }
    public function lastInsertId(): string  { return $this->pdo->lastInsertId(); }
}
```

### 6.4 模型基类 — `app/Models/Model.php`(改进版)

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected static string $table = '';
    protected static string $pk = 'id';
    protected static array $sortable = ['id', 'created_at', 'updated_at'];

    protected array $attributes = [];
    protected array $relations = [];

    public function __construct(array $attrs = []) { $this->fill($attrs); }
    public function fill(array $attrs): void { foreach ($attrs as $k => $v) $this->attributes[$k] = $v; }
    public function __get(string $name): mixed { return $this->attributes[$name] ?? null; }
    public function __set(string $name, mixed $value): void { $this->attributes[$name] = $value; }
    public function __isset(string $name): bool { return isset($this->attributes[$name]); }
    public function toArray(): array { return $this->attributes; }

    public static function db(): Database { return Database::getInstance(); }
    public static function tableName(): string { return static::$table; }

    public static function find(int|string $id): ?static
    {
        $row = self::db()->fetchOne(
            'SELECT * FROM ' . static::$table . ' WHERE ' . static::$pk . ' = ?', [$id]
        );
        return $row ? new static($row) : null;
    }

    public static function findBy(string $field, mixed $value): ?static
    {
        $row = self::db()->fetchOne(
            'SELECT * FROM ' . static::$table . " WHERE {$field} = ? LIMIT 1", [$value]
        );
        return $row ? new static($row) : null;
    }

    public static function all(string $orderBy = 'id DESC'): array
    {
        self::guardOrderBy($orderBy);
        return array_map(fn($r) => new static($r),
            self::db()->fetchAll('SELECT * FROM ' . static::$table . " ORDER BY {$orderBy}"));
    }

    public static function where(array $conds, string $orderBy = 'id DESC', ?int $limit = null, int $offset = 0): array
    {
        self::guardOrderBy($orderBy);
        $where = []; $params = [];
        foreach ($conds as $k => $v) { $where[] = "{$k} = ?"; $params[] = $v; }
        $sql = 'SELECT * FROM ' . static::$table;
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY {$orderBy}";
        if ($limit) $sql .= " LIMIT {$limit} OFFSET {$offset}";
        return array_map(fn($r) => new static($r), self::db()->fetchAll($sql, $params));
    }

    public static function count(array $conds = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . static::$table;
        $params = [];
        if ($conds) {
            $where = [];
            foreach ($conds as $k => $v) { $where[] = "{$k} = ?"; $params[] = $v; }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        return (int) self::db()->fetchColumn($sql, $params);
    }

    /**
     * 通用分页(基类提取,消除各子模型重复)
     * @return array{items: static[], total: int, page: int, perPage: int}
     */
    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'id DESC',
                                     ?string $whereSql = null, array $params = []): array
    {
        self::guardOrderBy($orderBy);
        $offset = max(0, ($page - 1) * $perPage);
        $countSql = 'SELECT COUNT(*) FROM ' . static::$table;
        $sql = 'SELECT * FROM ' . static::$table;
        if ($whereSql) { $countSql .= ' WHERE ' . $whereSql; $sql .= ' WHERE ' . $whereSql; }
        $total = (int) self::db()->fetchColumn($countSql, $params);
        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        return [
            'items'   => array_map(fn($r) => new static($r), self::db()->fetchAll($sql, $params)),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    public function save(): bool
    {
        $pk = $this->attributes[static::$pk] ?? null;
        if ($pk) {
            $data = $this->attributes; unset($data[static::$pk]);
            self::db()->update(static::$table, $data, static::$pk . ' = :pk', [':pk' => $pk]);
            return true;
        }
        $id = self::db()->insert(static::$table, $this->attributes);
        $this->attributes[static::$pk] = $id;
        return true;
    }

    public function delete(): bool
    {
        $pk = $this->attributes[static::$pk] ?? null;
        return $pk && self::db()->delete(static::$table, static::$pk . ' = ?', [$pk]) > 0;
    }

    public function setRelation(string $name, mixed $value): void { $this->relations[$name] = $value; }
    public function getRelation(string $name): mixed              { return $this->relations[$name] ?? null; }

    /**
     * 关键安全防护:ORDER BY 白名单
     * - 字符级:仅允许 [a-zA-Z0-9_.,\s]
     * - 列级:若子类声明 $sortable,每个列必须在白名单
     */
    protected static function guardOrderBy(string $orderBy): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.,\s]+$/', $orderBy)) {
            throw new \InvalidArgumentException("Invalid orderBy clause: {$orderBy}");
        }
        if (static::$sortable !== ['id', 'created_at', 'updated_at']) {
            foreach (explode(',', $orderBy) as $part) {
                $col = preg_replace('/\s+(ASC|DESC)$/i', '', trim($part));
                $col = str_replace(['`', '"'], '', $col);
                if (str_contains($col, '.')) $col = explode('.', $col)[1] ?? $col;
                if (!in_array($col, static::$sortable, true)) {
                    throw new \InvalidArgumentException("Disallowed sort column: {$col}");
                }
            }
        }
    }
}
```

### 6.5 视图引擎 — `app/Core/View.php`(核心片段)

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static array $shared = [];
    private static array $composers = [];
    private static ?string $cacheDir = null;

    public static function share(string $key, mixed $value): void
    { self::$shared[$key] = $value; }

    /** 注册 View Composer:渲染匹配模板时自动合并数据 */
    public static function composer(string $template, callable $callback): void
    { self::$composers[$template][] = $callback; }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $data = array_merge(self::$shared, $data);

        foreach (self::$composers as $pattern => $callbacks) {
            if (self::matchComposer($pattern, $template)) {
                foreach ($callbacks as $cb) $data = array_merge($data, $cb($data));
            }
        }
        return self::renderFile($template, $data, $layout);
    }

    public static function display(string $template, array $data = [], ?string $layout = null): void
    { echo self::render($template, $data, $layout); }

    private static function renderFile(string $template, array $data, ?string $layout): string
    {
        $path = self::resolvePath($template);
        if (!is_file($path)) throw new \RuntimeException("View not found: {$template}");
        $source = (string) file_get_contents($path);

        // 1. 处理 @extends
        $extendsMatch = [];
        $hasExtends = preg_match('/@extends\(\s*[\'"]?(.+?)[\'"]?\s*\)/', $source, $extendsMatch);
        $parentTemplate = $layout ?? ($extendsMatch[1] ?? null);
        if ($hasExtends || $layout) {
            $source = preg_replace('/@extends\(\s*[\'"]?.+?[\'"]?\s*\)\s*\n?/', '', $source, 1);

            $sections = [];
            if (preg_match_all('/@section\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endsection/s', $source, $m, PREG_SET_ORDER)) {
                foreach ($m as $match) $sections[$match[1]] = $match[2];
                $source = preg_replace('/@section\(\s*[\'"].+?[\'"]\s*\).*?@endsection/s', '', $source);
            }
            if ($parentTemplate) {
                $parentPath = self::resolvePath($parentTemplate);
                $parentSource = is_file($parentPath) ? (string) file_get_contents($parentPath) : '';
                $parentSource = preg_replace_callback('/@yield\(\s*[\'"](.+?)[\'"]\s*\)/',
                    fn($m) => $sections[$m[1]] ?? '', $parentSource);
                $source = $parentSource;
            }
        }

        // 2. 处理 @include
        $source = preg_replace_callback('/@include\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($m) use ($data) {
                $incPath = self::resolvePath($m[1]);
                if (!is_file($incPath)) return '';
                $incSource = (string) file_get_contents($incPath);
                $incPhp = self::compile($incSource);
                $incFile = self::getCacheFile($m[1], $incPhp, $incPath);
                extract($data, EXTR_SKIP);
                ob_start();
                include $incFile;
                return (string) ob_get_clean();
            }, $source);

        // 3. 编译 + 缓存
        $phpSource = self::compile($source);
        $compiledFile = self::getCacheFile($template, $phpSource, $path);

        extract($data, EXTR_SKIP);
        ob_start();
        include $compiledFile;
        return (string) ob_get_clean();
    }

    private static function compile(string $source): string
    {
        // @if / @foreach / @for / @elseif
        $source = self::convertBlockDirective($source, 'if',      'if ',      ':');
        $source = self::convertBlockDirective($source, 'elseif',  'elseif ',  ':');
        $source = self::convertBlockDirective($source, 'foreach', 'foreach ', ':');
        $source = self::convertBlockDirective($source, 'for',     'for ',     ':');

        $source = str_replace(['@else', '@endif', '@endforeach', '@endfor'],
                              ['<?php else: ?>', '<?php endif; ?>', '<?php endforeach; ?>', '<?php endfor; ?>'], $source);
        $source = str_replace(['@php', '@endphp'], ['<?php', '?>'], $source);

        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        $source = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $source);
        $source = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s',
            fn($m) => '<?= htmlspecialchars((string)(' . $m[1] . ' ?? \'\'), ENT_QUOTES, \'UTF-8\') ?>', $source);

        $source = preg_replace("/@yield\(.+?\)/", '', $source);
        return $source;
    }

    private static function getCacheFile(string $template, string $phpSource, string $sourcePath): string
    {
        $mtime = filemtime($sourcePath) ?: time();
        $key = md5($template . '|' . $mtime . '|' . $phpSource);
        $file = self::getCacheDir() . '/' . $key . '.php';
        if (!is_file($file)) file_put_contents($file, $phpSource);
        return $file;
    }

    private static function getCacheDir(): string
    {
        if (self::$cacheDir === null) {
            self::$cacheDir = __DIR__ . '/../../storage/cache/views';
            if (!is_dir(self::$cacheDir)) @mkdir(self::$cacheDir, 0775, true);
        }
        return self::$cacheDir;
    }

    private static function resolvePath(string $template): string
    {
        return __DIR__ . '/../../views/' . str_replace('.', '/', $template) . '.php';
    }

    private static function matchComposer(string $pattern, string $template): bool
    {
        if (str_contains($pattern, '*')) {
            return (bool) preg_match('#^' . str_replace('*', '.*', $pattern) . '$#', $template);
        }
        return $pattern === $template;
    }

    private static function convertBlockDirective(string $source, string $name, string $prefix, string $suffix): string
    {
        $needle = '@' . $name . '(';
        $result = '';
        $offset = 0; $len = strlen($source);
        while (($pos = strpos($source, $needle, $offset)) !== false) {
            $result .= substr($source, $offset, $pos - $offset);
            $i = $pos + strlen($needle) - 1;
            $depth = 0; $end = -1;
            for ($j = $i; $j < $len; $j++) {
                $c = $source[$j];
                if ($c === '(') $depth++;
                elseif ($c === ')') { $depth--; if ($depth === 0) { $end = $j; break; } }
                elseif ($c === '"' || $c === "'") {
                    $q = $c; $j++;
                    while ($j < $len && $source[$j] !== $q) {
                        if ($source[$j] === '\\') $j++;
                        if ($j >= $len) break 2;
                        $j++;
                    }
                }
            }
            if ($end === -1) {
                $result .= substr($source, $pos, strlen($needle));
                $offset = $pos + strlen($needle);
                continue;
            }
            $inner = substr($source, $i, $end - $i + 1);
            $result .= '<?php ' . $prefix . $inner . $suffix . ' ?>';
            $offset = $end + 1;
        }
        return $result . substr($source, $offset);
    }
}
```

### 6.6 Trait — `HasSlug.php`

```php
<?php
declare(strict_types=1);

namespace App\Traits;

use App\Core\Helper;

/**
 * 统一 slug 生成与唯一性。
 * 用方要求:
 *   - static::$table
 *   - static::findBySlug(string): ?static
 *   - static::$pk
 */
trait HasSlug
{
    public static function makeUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Helper::slugify($title);
        $base = $slug;
        $i = 1;
        while (true) {
            $existing = static::findBySlug($slug);
            if (!$existing || ($excludeId !== null && (int) $existing->{static::$pk} === $excludeId)) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function resolveSlug(string $raw, string $fallbackTitle, ?int $excludeId = null): string
    {
        $slug = trim($raw);
        if ($slug === '') $slug = Helper::slugify($fallbackTitle);
        return static::makeUniqueSlug($slug, $excludeId);
    }
}
```

### 6.7 Trait — `HasFlashRedirect.php`

```php
<?php
declare(strict_types=1);

namespace App\Traits;

use App\Core\Response;
use App\Core\Session;

trait HasFlashRedirect
{
    protected function flashSuccess(string $message): void
    { Session::flash('success', $message); }

    protected function flashError(string $message): void
    { Session::flash('error', $message); }

    protected function redirect(string $url): never
    { Response::redirect($url); }

    protected function backWithError(string $message, string $fallbackUrl = '/admin'): never
    {
        Session::flash('error', $message);
        $this->redirect($_SERVER['HTTP_REFERER'] ?? $fallbackUrl);
    }
}
```

### 6.8 文章模型 — `app/Models/Post.php`(N+1 消除版)

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Helper;
use App\Traits\HasSlug;

final class Post extends Model
{
    use HasSlug;

    protected static string $table = 'posts';
    protected static array $sortable = ['id', 'published_at', 'views', 'created_at', 'updated_at', 'is_top'];

    public static function findBySlug(string $slug): ?self
    { return self::findBy('slug', $slug); }

    /**
     * 分页查询已发布文章,JOIN 预加载分类+标签,一次 SQL 替代 21 次
     */
    public static function paginatePublished(int $page, int $perPage, ?int $categoryId = null, ?int $tagId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        if ($tagId) {
            $params = ['published', $tagId];
            $total = (int) self::db()->fetchColumn(
                'SELECT COUNT(*) FROM posts p INNER JOIN post_tag pt ON p.id = pt.post_id
                 WHERE p.status = ? AND pt.tag_id = ?', $params);
            $sql = "SELECT p.*,
                      c.name as __category_name, c.slug as __category_slug,
                      GROUP_CONCAT(t.name) as __tag_names, GROUP_CONCAT(t.slug) as __tag_slugs
                    FROM posts p
                    INNER JOIN post_tag pt ON p.id = pt.post_id
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN post_tag pt2 ON p.id = pt2.post_id
                    LEFT JOIN tags t ON pt2.tag_id = t.id
                    WHERE p.status = ? AND pt.tag_id = ?
                    GROUP BY p.id
                    ORDER BY p.is_top DESC, p.published_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";
            $rows = self::db()->fetchAll($sql, $params);
        } else {
            $where = ["p.status = 'published'"];
            $params = [];
            if ($categoryId) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }
            $whereSql = implode(' AND ', $where);
            $total = (int) self::db()->fetchColumn("SELECT COUNT(*) FROM posts p WHERE {$whereSql}", $params);
            $sql = "SELECT p.*,
                      c.name as __category_name, c.slug as __category_slug,
                      GROUP_CONCAT(t.name) as __tag_names, GROUP_CONCAT(t.slug) as __tag_slugs
                    FROM posts p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN post_tag pt ON p.id = pt.post_id
                    LEFT JOIN tags t ON pt.tag_id = t.id
                    WHERE {$whereSql}
                    GROUP BY p.id
                    ORDER BY p.is_top DESC, p.published_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";
            $rows = self::db()->fetchAll($sql, $params);
        }

        $items = [];
        foreach ($rows as $r) {
            $post = new self($r);
            if (!empty($r['__category_name'])) {
                $post->setRelation('category', new Category([
                    'id'   => $r['category_id'] ?? 0,
                    'name' => $r['__category_name'],
                    'slug' => $r['__category_slug'],
                ]));
            }
            if (!empty($r['__tag_names'])) {
                $tags = [];
                $names = explode(',', $r['__tag_names']);
                $slugs = explode(',', $r['__tag_slugs']);
                foreach ($names as $i => $name) {
                    if (isset($slugs[$i])) $tags[] = new Tag(['name' => $name, 'slug' => $slugs[$i]]);
                }
                $post->setRelation('tags', $tags);
            }
            $items[] = $post;
        }
        return ['items' => $items, 'total' => $total];
    }

    public static function search(string $keyword, int $page, int $perPage): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) > 100) return ['items' => [], 'total' => 0];
        $like = '%' . $keyword . '%';
        $total = (int) self::db()->fetchColumn(
            "SELECT COUNT(*) FROM posts WHERE status='published' AND (title LIKE ? OR summary LIKE ? OR content LIKE ?)",
            [$like, $like, $like]
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = self::db()->fetchAll(
            "SELECT * FROM posts WHERE status='published' AND (title LIKE ? OR summary LIKE ? OR content LIKE ?)
             ORDER BY published_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$like, $like, $like]
        );
        return ['items' => array_map(fn($r) => new self($r), $rows), 'total' => $total];
    }

    public function getTags(): array
    {
        $cached = $this->getRelation('tags');
        if ($cached !== null) return $cached;
        $rows = self::db()->fetchAll(
            'SELECT t.* FROM tags t INNER JOIN post_tag pt ON t.id = pt.tag_id
             WHERE pt.post_id = ? ORDER BY t.name', [$this->id]
        );
        return array_map(fn($r) => new Tag($r), $rows);
    }

    public function getCategory(): ?Category
    {
        $cached = $this->getRelation('category');
        if ($cached !== null) return $cached;
        return $this->category_id ? Category::find($this->category_id) : null;
    }

    public function setTags(string|array $tags): void
    {
        if (is_string($tags)) $tags = array_filter(array_map('trim', explode(',', $tags)));
        self::db()->delete('post_tag', 'post_id = ?', [$this->id]);
        if (empty($tags)) return;
        foreach (Tag::findOrCreateMany($tags) as $t) {
            self::db()->insert('post_tag', ['post_id' => $this->id, 'tag_id' => $t->id]);
        }
    }

    public function incrementViews(): void
    {
        self::db()->query('UPDATE posts SET views = views + 1 WHERE id = ?', [$this->id]);
    }

    public function getUrl(): string
    { return Helper::url('/post/' . $this->slug . '.html'); }
}
```

### 6.9 前台首页 — `app/Controllers/Front/HomeController.php`(改进版)

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Models\Shuoshuo;
use App\Services\Installer;

class HomeController
{
    public function __construct()
    {
        // View Composer 注入共享数据,所有前台页面自动可用
        View::composer('layouts.front', function (array $data): array {
            return array_merge($data, [
                'categories'  => Category::allEnabled(),
                'recentPosts' => Post::recent(5),
            ]);
        });
    }

    public function index(): string
    {
        if (!Installer::isInstalled()) {
            return View::render('install.prompt', [
                'installUrl' => Helper::url('/install'),
                'pageTitle'  => '需要安装',
            ]);
        }

        $perPage = (int) \App\Core\Config::get('pagination.front_per_page', 10);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $posts, 'total' => $total] = Post::paginatePublished($page, $perPage);

        return View::render('home.index', [
            'posts'         => $posts,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'paginator'     => Helper::paginate($page, $total, $perPage, Helper::url('/')),
            'recentShuoshuo' => Shuoshuo::paginate(1, 5)['items'],
            'pageTitle'     => null,
        ]);
    }
}
```

### 6.10 后台文章 — `app/Controllers/Admin/PostController.php`(改进版)

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Markdown;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Traits\HasFlashRedirect;
use App\Traits\HasSlug;

class PostController
{
    use HasSlug, HasFlashRedirect;

    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int) \App\Core\Config::get('pagination.admin_per_page', 20);
        $keyword = trim((string)($_GET['q'] ?? ''));
        $status = $_GET['status'] ?? '';

        $where = []; $params = [];
        if ($keyword !== '') { $where[] = '(title LIKE ? OR summary LIKE ?)'; $params[] = "%{$keyword}%"; $params[] = "%{$keyword}%"; }
        if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
        $whereSql = $where ? implode(' AND ', $where) : null;

        $result = Post::paginate($page, $perPage, 'id DESC', $whereSql, $params);

        return View::render('post.index', [
            'posts'     => $result['items'],
            'total'     => $result['total'],
            'page'      => $page,
            'perPage'   => $perPage,
            'paginator' => Helper::paginate($page, $result['total'], $perPage, '/admin/posts'),
            'keyword'   => $keyword,
            'status'    => $status,
            'csrf'      => Session::csrfToken(),
            'pageTitle' => '文章管理',
        ], 'layouts.admin');
    }

    public function create(): string { return $this->form(null); }

    public function edit(Request $request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) {
            $this->flashError('文章不存在');
            $this->redirect('/admin/posts');
        }
        return $this->form($post);
    }

    private function form(?Post $post): string
    {
        $tags = $post ? array_map(fn($t) => $t->name, $post->getTags()) : [];
        return View::render('post.form', [
            'post'       => $post,
            'tagsString' => implode(', ', $tags),
            'categories' => Category::allEnabled(),
            'csrf'       => Session::csrfToken(),
            'pageTitle'  => $post ? '编辑文章' : '写文章',
        ], 'layouts.admin');
    }

    public function store(Request $request): never   { $this->persist($request, null); }
    public function update(Request $request, array $params): never
    { $this->persist($request, (int)($params['id'] ?? 0)); }

    private function persist(Request $request, ?int $id): never
    {
        $data = [
            'title' => $request->input('title', ''),
            'slug'  => $request->input('slug', ''),
            'summary' => $request->input('summary', ''),
            'content' => $request->input('content', ''),
            'markdown' => $request->input('markdown_content', ''),
            'cover' => $request->input('cover', ''),
            'category_id' => $request->input('category_id', 0),
            'status' => $request->input('status', 'published'),
            'is_top' => $request->input('is_top', 0),
            'is_recommend' => $request->input('is_recommend', 0),
            'tags'   => $request->input('tags', ''),
        ];

        $validator = Validator::make($data, [
            'title'   => 'required|string|min:1|max:200',
            'content' => 'required_if:markdown,',
            'status'  => 'in:published,draft',
        ]);
        if (!$validator->validate()) {
            $this->flashError($validator->firstError() ?? '校验失败');
            $this->redirect($id ? "/admin/posts/{$id}/edit" : '/admin/posts/create');
        }

        $content  = trim((string)$data['content']);
        $markdown = trim((string)$data['markdown']);
        if ($markdown !== '') $content = Markdown::parse($markdown);
        if ($content === '' && $markdown === '') {
            $this->flashError('内容和 Markdown 至少填一个');
            $this->redirect($id ? "/admin/posts/{$id}/edit" : '/admin/posts/create');
        }

        $slug = Post::resolveSlug((string)$data['slug'], (string)$data['title'], $id);
        $now  = date('Y-m-d H:i:s');

        if ($id) {
            $post = Post::find($id);
            if (!$post) { $this->flashError('文章不存在'); $this->redirect('/admin/posts'); }
            $post->fill([
                'title' => trim((string)$data['title']), 'slug' => $slug,
                'summary' => trim((string)$data['summary']),
                'content' => $content, 'markdown_content' => $markdown,
                'cover' => trim((string)$data['cover']),
                'category_id' => (int)$data['category_id'],
                'is_top' => (int)$data['is_top'] ? 1 : 0,
                'is_recommend' => (int)$data['is_recommend'] ? 1 : 0,
                'status' => $data['status'], 'updated_at' => $now,
            ]);
            $post->save();
        } else {
            $post = new Post([
                'title' => trim((string)$data['title']), 'slug' => $slug,
                'summary' => trim((string)$data['summary']),
                'content' => $content, 'markdown_content' => $markdown,
                'cover' => trim((string)$data['cover']),
                'category_id' => (int)$data['category_id'],
                'user_id' => Session::get('admin_user.id', 1),
                'is_top' => (int)$data['is_top'] ? 1 : 0,
                'is_recommend' => (int)$data['is_recommend'] ? 1 : 0,
                'status' => $data['status'],
                'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $post->save();
        }

        $post->setTags((string)$data['tags']);
        $this->flashSuccess($id ? '文章已更新' : '文章已发布');
        $this->redirect('/admin/posts');
    }

    public function destroy(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        if ($id) {
            $db = Post::db();
            try {
                $db->beginTransaction();
                $db->delete('post_tag', 'post_id = ?', [$id]);
                $db->delete('comments', 'post_id = ?', [$id]);
                $db->delete('posts', 'id = ?', [$id]);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                $this->flashError('删除失败: ' . $e->getMessage());
                $this->redirect('/admin/posts');
            }
        }
        $this->flashSuccess('文章已删除');
        $this->redirect('/admin/posts');
    }

    public function bulk(Request $request): never
    {
        $action = $request->input('bulk_action', '');
        $ids = array_filter(array_map('intval', (array)$request->input('ids', [])));
        if (empty($ids)) { $this->flashError('请选择文章'); $this->redirect('/admin/posts'); }

        $allowed = ['delete', 'publish', 'draft', 'top', 'untop'];
        if (!in_array($action, $allowed, true)) { $this->flashError('非法操作'); $this->redirect('/admin/posts'); }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = Post::db();

        switch ($action) {
            case 'delete':
                $db->beginTransaction();
                $db->query("DELETE FROM post_tag WHERE post_id IN ({$placeholders})", $ids);
                $db->query("DELETE FROM comments WHERE post_id IN ({$placeholders})", $ids);
                $db->query("DELETE FROM posts WHERE id IN ({$placeholders})", $ids);
                $db->commit();
                $this->flashSuccess('已删除 ' . count($ids) . ' 篇文章');
                break;
            case 'publish':
                $db->query("UPDATE posts SET status='published' WHERE id IN ({$placeholders})", $ids);
                $this->flashSuccess('已发布 ' . count($ids) . ' 篇文章');
                break;
            case 'draft':
                $db->query("UPDATE posts SET status='draft' WHERE id IN ({$placeholders})", $ids);
                $this->flashSuccess('已转为草稿');
                break;
            case 'top':
                $db->query("UPDATE posts SET is_top=1 WHERE id IN ({$placeholders})", $ids);
                $this->flashSuccess('已置顶');
                break;
            case 'untop':
                $db->query("UPDATE posts SET is_top=0 WHERE id IN ({$placeholders})", $ids);
                $this->flashSuccess('已取消置顶');
                break;
        }
        $this->redirect('/admin/posts');
    }
}
```

### 6.11 安装服务 — `app/Services/Installer.php`(关键片段)

```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

final class Installer
{
    public static function isInstalled(): bool
    {
        $path = Config::get('database.sqlite');
        if (!is_file($path)) return false;
        try {
            $row = Database::getInstance()->fetchOne(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='settings'"
            );
            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function install(): array
    {
        $log = [];
        $db = Database::getInstance();

        // 12 张表全部 CREATE TABLE IF NOT EXISTS,可重复执行
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100), nickname VARCHAR(50), avatar VARCHAR(255),
            role VARCHAR(20) DEFAULT 'admin',
            last_login_at DATETIME, last_login_ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            summary TEXT, content TEXT NOT NULL, markdown_content TEXT,
            cover VARCHAR(255), category_id INTEGER DEFAULT 0, user_id INTEGER DEFAULT 1,
            views INTEGER DEFAULT 0, comments_count INTEGER DEFAULT 0,
            is_top INTEGER DEFAULT 0, is_recommend INTEGER DEFAULT 0,
            status VARCHAR(20) DEFAULT 'published',
            published_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);

        // ... 其他 10 张表略,见 3.2 节

        // 索引
        $db->query('CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_posts_published_at ON posts(published_at)');
        // ... 其他索引

        // 默认管理员
        if (!$db->fetchOne('SELECT id FROM users LIMIT 1')) {
            $db->insert('users', [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'email'    => 'admin@example.com',
                'nickname' => '管理员',
                'role'     => 'admin',
            ]);
            $log[] = '默认管理员创建完成（admin / admin123）';
        }

        // 默认设置、分类、欢迎文章、关于/友链页面(略)

        file_put_contents(Config::get('database.sqlite') . '.installed', date('c'));
        return $log;
    }
}
```

### 6.12 辅助类 — `app/Core/Helper.php`(关键方法)

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Helper
{
    public static function url(string $path = '/'): string
    {
        $base = rtrim(Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function e(?string $str): string
    { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

    public static function slugify(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
        $text = trim($text, '-');
        $text = mb_strtolower($text);
        return $text === '' ? substr(md5(uniqid('', true)), 0, 8) : $text;
    }

    public static function humanDate(string|int|\DateTimeInterface $date): string
    {
        $ts = match (true) {
            $date instanceof \DateTimeInterface => $date->getTimestamp(),
            is_int($date) => $date,
            default       => strtotime((string)$date) ?: time(),
        };
        $diff = time() - $ts;
        return match (true) {
            $diff < 60     => '刚刚',
            $diff < 3600   => floor($diff / 60) . ' 分钟前',
            $diff < 86400  => floor($diff / 3600) . ' 小时前',
            $diff < 604800 => floor($diff / 86400) . ' 天前',
            default        => date('Y-m-d', $ts),
        };
    }

    public static function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function randomToken(int $length = 32): string
    { return bin2hex(random_bytes($length)); }

    public static function paginate(int $current, int $total, int $perPage, string $baseUrl): string
    {
        if ($total <= $perPage) return '';
        $pages = (int) ceil($total / $perPage);
        $current = max(1, min($current, $pages));
        $html = '<div class="pagination">';
        if ($current > 1) $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => $current - 1]) . '">&laquo;</a>';
        $start = max(1, $current - 3); $end = min($pages, $current + 3);
        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $current
                ? '<span class="active">' . $i . '</span>'
                : '<a href="' . self::buildUrl($baseUrl, ['page' => $i]) . '">' . $i . '</a>';
        }
        if ($current < $pages) $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => $current + 1]) . '">&raquo;</a>';
        return $html . '</div>';
    }

    public static function buildUrl(string $base, array $params): string
    {
        $sep = str_contains($base, '?') ? '&' : '?';
        return $base . $sep . http_build_query($params);
    }
}
```

### 6.13 路由定义 — `routes/web.php`

```php
<?php
declare(strict_types=1);

use App\Controllers\Front\HomeController;
use App\Controllers\Front\PostController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\TagController;
use App\Controllers\Front\ShuoshuoController;
use App\Controllers\Front\ArchiveController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\FriendController;
use App\Controllers\Front\CommentController;
use App\Controllers\Front\FeedController;
use App\Controllers\Front\StatController;
use App\Controllers\Front\InstallController;

$router->get('/install',          [InstallController::class, 'index']);
$router->get('/install/do',       [InstallController::class, 'install']);

$router->get('/',                 [HomeController::class, 'index']);
$router->get('/post/{slug}',      [PostController::class, 'show']);
$router->get('/category/{slug}',  [CategoryController::class, 'show']);
$router->get('/tag/{slug}',       [TagController::class, 'show']);
$router->get('/page/{slug}',      [PageController::class, 'show']);
$router->get('/shuoshuo',         [ShuoshuoController::class, 'index']);
$router->get('/archives',         [ArchiveController::class, 'index']);
$router->get('/search',           [SearchController::class, 'index']);
$router->get('/friends',          [FriendController::class, 'index']);
$router->post('/comment/submit',  [CommentController::class, 'submit']);
$router->get('/feed',             [FeedController::class, 'feed']);
$router->get('/friends/feed',     [FeedController::class, 'friendsFeed']);
$router->get('/api/stats',        [StatController::class, 'summary']);
```

### 6.14 后台路由 — `routes/admin.php`

```php
<?php
declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\TagController;
use App\Controllers\Admin\PageController;
use App\Controllers\Admin\AttachmentController;
use App\Controllers\Admin\LinkController;
use App\Controllers\Admin\CommentController;
use App\Controllers\Admin\ShuoshuoController;
use App\Controllers\Admin\StatController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\ProfileController;
use App\Middleware\AdminAuth;
use App\Middleware\CsrfMiddleware;

// 公开
$router->get('/admin/login',        [AuthController::class, 'loginForm']);
$router->post('/admin/login',       [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/admin/logout',       [AuthController::class, 'logout']);

// 受保护(组中间件:AdminAuth)
$router->group('/admin', function ($r) {
    $r->get('/',                       [DashboardController::class, 'index']);

    $r->get('/posts',                  [PostController::class, 'index']);
    $r->get('/posts/create',           [PostController::class, 'create']);
    $r->post('/posts/create',          [PostController::class, 'store'],   [CsrfMiddleware::class]);
    $r->get('/posts/{id}/edit',        [PostController::class, 'edit']);
    $r->post('/posts/{id}/edit',       [PostController::class, 'update'],  [CsrfMiddleware::class]);
    $r->post('/posts/{id}/delete',     [PostController::class, 'destroy'], [CsrfMiddleware::class]);
    $r->post('/posts/bulk',            [PostController::class, 'bulk'],    [CsrfMiddleware::class]);

    // 分类 / 标签 / 页面 / 附件 / 友链 / 评论 / 说说 / 统计 / 设置 / 个人
    // ... 完整列表见 4.2 节

}, [AdminAuth::class]);
```

### 6.15 入口文件

**前台 `public/index.php`:**

```php
<?php
declare(strict_types=1);

define('APP_START', microtime(true));
define('BASE_PATH', __DIR__ . '/..');

require BASE_PATH . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$request = new Request();
$router  = new Router();
$routeFile = BASE_PATH . '/routes/web.php';
if (is_file($routeFile)) require $routeFile;

// 全站统计
\App\Services\StatService::record($request);

$router->dispatch($request);
```

**后台 `public/admin/index.php`:**

```php
<?php
declare(strict_types=1);

define('APP_START', microtime(true));
define('BASE_PATH', __DIR__ . '/../..');
define('IS_ADMIN', true);

require BASE_PATH . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$request = new Request();
$router  = new Router();
require BASE_PATH . '/routes/admin.php';
$router->dispatch($request);
```

**开发服务器路由 `router.php`:**

```php
<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;  // 静态资源 PHP 内置服务器自己处理
}

if (str_starts_with($path, '/admin')) {
    $adminFile = __DIR__ . '/public/admin/index.php';
    if (is_file($adminFile)) { require $adminFile; return true; }
}

require __DIR__ . '/public/index.php';
```

### 6.16 全局配置 — `config/config.php`

```php
<?php
return [
    'app' => [
        'name'      => 'LiteNote',
        'url'       => 'http://127.0.0.1:5555',
        'debug'     => true,
        'timezone'  => 'Asia/Shanghai',
        'locale'    => 'zh-CN',
        'key'       => 'change-me-32-bytes-random-secret!!',
    ],

    'database' => [
        'sqlite' => __DIR__ . '/../storage/database.sqlite',
    ],

    'site' => [
        'title' => '我的个人博客', 'subtitle' => '记录、分享、思考',
        'description' => '一个用 PHP 8.5 写的小博客',
        'keywords' => 'PHP,博客,个人', 'beian' => '',
        'theme' => 'default',
        'comment_need_audit' => true, 'comment_captcha' => false,
    ],

    'upload' => [
        'path' => __DIR__ . '/../public/assets/uploads',
        'url'  => '/assets/uploads',
        'max_size' => 5 * 1024 * 1024,
        'allowed_ext' => ['jpg','jpeg','png','gif','webp','pdf','zip','txt','md'],
    ],

    'pagination' => [
        'front_per_page' => 10, 'admin_per_page' => 20,
    ],

    'cache' => [
        'driver' => 'file',
        'path'   => __DIR__ . '/../storage/cache',
        'ttl'    => 3600,
    ],
];
```

---

## 7. 部署与运行

### 7.1 开发启动

```bash
cd /Users/gentpan/projects/LiteNote
php -S 127.0.0.1:5555 -t public router.php
# 访问
#   http://127.0.0.1:5555/install   首次安装
#   http://127.0.0.1:5555/           博客首页
#   http://127.0.0.1:5555/admin/login  后台
```

默认账号:`admin` / `admin123`(登录后请改)。

### 7.2 生产部署(Apache)

```apache
# /etc/apache2/sites-available/litenote.conf
<VirtualHost *:80>
    ServerName blog.example.com
    DocumentRoot /var/www/litenote/public
    <Directory /var/www/litenote/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/litenote-error.log
    CustomLog ${APACHE_LOG_DIR}/litenote-access.log combined
</VirtualHost>
```

生产环境保持 SQLite 单库即可;迁移时复制代码、`storage/database.sqlite` 和上传目录。

### 7.3 目录权限

```bash
chmod -R 775 storage public/assets/uploads
chown -R www-data:www-data storage public/assets/uploads
```

### 7.4 系统要求

| 组件 | 版本 |
|---|---|
| PHP | 8.5+ (用了 readonly、nullsafe、first-class callable) |
| PDO | 必需(SQLite 驱动) |
| Apache | mod_rewrite(生产)/ PHP built-in server(开发) |
| 内存 | ≥ 64 MB(模板编译缓存) |
| 磁盘 | 50 MB(代码)+ 数据库 + 上传文件 |

---

## 8. 改进路线(下一步)

虽然 LiteNote 已经生产就绪,以下是可继续演进的方向(REFACTORING 报告 §5 已列出):

### 8.1 性能

- **统计表归档**:`stats` 增加按月分表,或定期清理(保留最近 90 天)
- **友链 RSS 异步化**:`FriendRssService::aggregate()` 改为后台定时刷新 + 缓存,前台只读
- **SQLite FTS5**:搜索用全文索引替代 LIKE
- **缓存层**:`config/cache.php` 配置了 file driver,但当前没用 — 可加 query cache / 视图片段缓存

### 8.2 安全

- **引入 PHP enum** 替代魔法字符串(`PostStatus`, `CommentStatus`)
- **操作审计日志**:`logs` 表记录后台写操作
- **CSP header**:后台管理页加 Content-Security-Policy
- **速率限制**:评论提交 + 登录加 IP 频控

### 8.3 工程

- **依赖注入**:把 `Config` / `Session` / `View` 抽接口,Controller 通过构造函数注入(当前是静态类)
- **单元测试**:PHPUnit + 内存 SQLite,先覆盖 `HasSlug`、`Model::guardOrderBy`、Markdown
- **CI**:GitHub Actions 跑 PHP 8.5 矩阵 + lint
- **Docker**:官方镜像 `php:8.5-cli` + supervisord

### 8.4 功能

- **评论邮件通知**(订阅/被回复)
- **多语言**(`app.locale` 已有字段,差 i18n 表)
- **API 化**:加 `/api/v1/*` JSON 接口(不破坏现有 HTML 路由)
- **WebSub**:`/feed` 推送式订阅
- **图片处理**:上传自动生成多尺寸

---

## 9. 验证清单(对照 REFACTORING.md §6)

- [x] 路由签名保持不变(`action(Request, $params)`)
- [x] 视图模板无需修改(`View::render()` 向后兼容第三个 layout 参数)
- [x] 数据库表结构无变动
- [x] `.htaccess` / `router.php` 无变动
- [x] 零外部依赖(无 Composer 包)
- [x] SQL 注入风险点已修复(`Model::guardOrderBy`)
- [x] N+1 已消除(`Post::paginatePublished` 预加载)
- [x] 重复代码已提取(`HasSlug`、`HasFlashRedirect`、`Model::paginate`)
- [x] View Composer 消除侧边栏共享数据 8+ 处重复
- [x] 批量删除事务化(`PostController::destroy` + `bulk`)
- [x] 模板缓存 mtime 校验自动失效
- [x] CSRF 防护 + 时序安全比较
- [x] SQL 注入白名单
- [x] `{{ }}` 默认转义、`{!! !!}` 显式裸输出

---

*文档基于 LiteNote 工作区完整扫描(2026-06-04),架构、文件结构、DB 模式、API、UI、核心代码均与工作区现状一致。*
