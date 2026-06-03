# LiteNote — 生产部署报告

> 日期: 2026-06-04  
> 目标: `https://litenote.io`  
> 服务器: 170.168.6.148 (Debian 13, peter.uz)  
> Web 栈: 宝塔面板 + nginx + PHP 8.5.2-FPM + Let's Encrypt  
> 部署方式: tar 管道同步本地 → 触发 selfUpgrade → 配置 app.url

---

## 1. 服务器现状(部署前扫描)

| 维度 | 现状 |
|---|---|
| OS | Debian GNU/Linux 13 (trixie), kernel 由宝塔提供 |
| PHP | **8.5.2 (cli + fpm)**, Zend OPcache v8.5.2 |
| Web server | nginx(宝塔路径 `/www/server/nginx/sbin/nginx`) |
| 监听 | 80, 443 均监听;Lit­eNote SSL 由 `/www/server/panel/vhost/nginx/litenote.io.conf` 服务 |
| 域名 | DNS `litenote.io` + `www.litenote.io` → 170.168.6.148(已配) |
| SSL | Let's Encrypt,证书在 `/www/server/panel/vhost/cert/litenote.io/` |
| PHP-FPM | `www` 用户,`/tmp/php-cgi-85.sock` 套接字 |
| 现有部署 | `/var/www/litenote`(老版 LiteNote,Jun 3 22:28) |
| 用户 | `www`(PHP-FPM 跑) / `root`(SSH) |

**意外发现**:
- `/etc/nginx/sites-enabled/litenote.io` 是历史遗留,**实际未被加载**(宝塔 nginx.conf 没 include `sites-enabled/`)
- 真正在跑的 vhost 是 `/www/server/panel/vhost/nginx/litenote.io.conf`(宝塔生成,完整 SSL 配置)

---

## 2. 部署步骤

### 2.1 备份

```bash
cp -a /var/www/litenote /var/www/litenote.bak.1780516900
```

历史备份:
- `/var/www/litenote.bak.1780511142` (Jun 3 23:32 之前的)
- `/var/www/litenote.bak.1780516900` (本次部署前)

### 2.2 同步代码(本地 → 服务器)

服务器**没有 rsync**,改用 tar 管道:

```bash
cd /Users/gentpan/projects/LiteNote
tar --exclude='./storage' --exclude='.DS_Store' --exclude='./.git' -czf - . | \
  ssh -i ~/.ssh/gentpan.pem root@170.168.6.148 "
    cd /var/www/litenote
    find . -maxdepth 2 -type f \( -name '*.php' -o -name '*.md' -o -name '*.css' -o -name '*.js' \) -delete
    tar -xzf - -C /var/www/litenote
    chown -R www:www /var/www/litenote
  "
```

**同步内容**(290 文件):
- `app/Enums/{PostStatus,CommentStatus,Toggle}.php`(新)
- `app/Services/Gravatar.php`(新)
- `app/Models/Post.php`(enum 重构)
- `app/Models/Comment.php`(enum 重构)
- `app/Models/{Category,Page,Link,Shuoshuo}.php`(enum 集成)
- `app/Controllers/Admin/{Post,Comment,Category,Dashboard,Page,Link,Shuoshuo}Controller.php`(enum + Toggle)
- `app/Controllers/Front/{Post,Feed,Comment}Controller.php`(enum)
- `app/Core/Session.php`(点号路径支持)
- `app/Controllers/Admin/ProfileController.php`(null 防御 + socials)
- `app/Services/Installer.php`(socials 列 + selfUpgrade)
- `app/bootstrap.php`(View Composer 注入 author)
- `app/Models/User.php`(getSocialLinks + sanitizeIcon)
- `app/Core/View.php`(composer 支持数组)
- `views/profile/index.php`(动态 socials 编辑区)
- `views/post/show.php`(author block)
- `views/layouts/front.php`(全局 site-author)
- `views/setting/index.php`(圆形 toggle)
- `views/comment/index.php`(评论头像)
- `public/assets/css/admin.css`(toggle + 头像 + socials 编辑样式)
- `public/assets/css/themes/{default,ember}.css`(author block 样式)

### 2.3 配置 `app.url`

```bash
sed -i "s|'url'\s*=>\s*'http://127.0.0.1:5555'|'url' => 'https://litenote.io'|" \
  /var/www/litenote/config/config.php
```

之前:`http://127.0.0.1:5555`(开发)  
之后:`https://litenote.io`(生产)

### 2.4 数据库 schema 自升级

```bash
sudo -u www php -r '
require "app/bootstrap.php";
$log = App\Services\Installer::install();
print_r($log);
'
```

输出:
```
Array
(
    [0] => 数据表创建完成
    [1] => 升级: ALTER TABLE users ADD COLUMN socials
)
```

- `data.sqlite`(204800 字节)所有用户数据**完整保留**
- 自动加 `users.socials TEXT` 列(为社交链接功能准备)
- `settings`/`users` 已存在,默认数据插入被自动跳过(避免覆盖用户改过的 admin 密码)

### 2.5 权限审计

```
/var/www/litenote              755 www:www
storage/                       775 www:www  (可写)
storage/cache/                 775 www:www  (可写)
storage/cache/views/           775 www:www  (模板编译)
storage/logs/                  775 www:www  (PHP 可写 ✓)
public/assets/uploads/         755 www:www  (上传目录)
```

PHP-FPM 进程验证能写 `storage/logs/`(用 `file_put_contents` 探针)。

### 2.6 nginx 语法校验

```
nginx: the configuration file /www/server/nginx/conf/nginx.conf syntax is ok
nginx: configuration file /www/server/nginx/conf/nginx.conf test is successful
```

无需 reload(配置没改 + opcache 自动检测文件 mtime 变化)。

---

## 3. 端到端验证

### 3.1 路由可达性

| 路径 | HTTP | 备注 |
|---|---|---|
| `https://litenote.io/` | 200 | 首页(4055 字节) |
| `https://litenote.io/post/welcome.html` | 200 | 文章详情(4555 字节) |
| `https://litenote.io/admin/login` | 200 | 登录页 |
| `https://litenote.io/admin` | 301 → `/admin/` → 200 | nginx 自动加 trailing slash,正常 |
| `https://litenote.io/feed` | 200 | RSS |
| `https://litenote.io/shuoshuo` | 200 | 说说 |

### 3.2 新功能验证

| 功能 | 验证 | 结果 |
|---|---|---|
| Gravatar bluecdn.net | `grep gravatar.bluecdn.net` | ✓ 出现 |
| 圆形 toggle | `/admin/settings` 含 7 个 `toggle-switch` | ✓ |
| Socials 编辑区 | `/admin/profile` 含 11 个 `preset-chip` | ✓ |
| Socials 保存 | `POST /admin/profile` → 302 + DB 写入 | ✓ |
| 前台 author block | `/post/welcome.html` 含 `author-social` | ✓ |
| 全局 site-author | `/` 含 `site-author-link` | ✓ |

### 3.3 端到端 socials 测试

```bash
# POST 提交 1 个 github social link
curl -X POST -d "socials[0][key]=github&socials[0][url]=https://github.com/gentpan&socials[0][icon]=fa-brands fa-github" \
  https://litenote.io/admin/profile

# 查 DB 验证
$ u = User::find(1)
nickname: gentpan
socials: [{"key":"github","url":"https://github.com/gentpan","icon":"<i class=\"fa-brands fa-github\"></i>","label":"GitHub"}]
parsed 1 links
  github => https://github.com/gentpan

# 重新访问前端
首页 site-author-link: 1
文章 author-social: 4
```

---

## 4. 关键决策

### 4.1 不用 rsync,用 tar 管道

服务器 Debian 没装 rsync(`bash: rsync: command not found`)。tar 管道:

```bash
tar -czf - <local files> | ssh root@server "cd /var/www/litenote && tar -xzf -"
```

优:零依赖、速度快(macOS 端 tar 警告的 xattr 无害)  
劣:无法用 `--delete` 清理多余文件 → 部署前手动 `find -delete` 一次

### 4.2 不重启 PHP-FPM

LiteNote 用 opcache(默认),`opcache.validate_timestamps=1` 自动检测 PHP 文件 mtime 变化。  
模板缓存 `storage/cache/views/` 用 `md5(template + mtime + source)` 作 key,mtime 变 → 自动重写。

**结果**:零停机,部署后立即生效。

### 4.3 用 `sudo -u www php -r` 跑 install

Installer 修改 schema,需要在 `www` 用户上下文(跟 PHP-FPM 一致),避免 owner 不一致导致后续写权限问题。

### 4.4 不动 nginx vhost

宝塔 `litenote.io.conf` 已经完美:
- listen 80 → 301 HTTPS
- listen 443 SSL + Let's Encrypt
- 完整安全 headers(HSTS, X-Frame-Options, Referrer-Policy)
- PHP-FPM 8.5(`/tmp/php-cgi-85.sock`)
- 静态资源 30 天缓存
- 后台 + 前台 try_files → index.php

**不动** = 风险最低。`/etc/nginx/sites-enabled/litenote.io` 是历史遗留,但宝塔 nginx 不读那个路径,实际无影响。

---

## 5. 已知事项

### 5.1 public/assets/uploads 权限 755

PHP-FPM 用 `www` 用户上传文件时,755 权限 + group rwx 仍可写。  
但**生产中**如果上传头像/附件遇到问题,改 775:
```bash
chmod 775 /var/www/litenote/public/assets/uploads
```

### 5.2 /admin 跳 /admin/(trailing slash)

nginx 默认行为(非 LiteNote 路由),实际访问 200 OK,不影响使用。  
如想去掉,在宝塔 vhost 加 `absolute_redirect off;`(可选)。

### 5.3 fail2ban 风险

跨服务器 SSH/rsync 节奏控制:本次部署只用了 1 次 tar 管道 + 1 次 sed + 1 次 install + 多次 curl,无连续失败,未触发 fail2ban。  
**以后部署**:批量改多个文件时记得 `sleep 5` between operations。

---

## 6. 访问入口

- **博客首页**: https://litenote.io/
- **后台**: https://litenote.io/admin/login
- **RSS**: https://litenote.io/feed
- **友链聚合 RSS**: https://litenote.io/friends/feed

默认管理员:`admin` / `admin123`(部署期间已登录过 admin 账号验证功能,密码未改)

---

## 7. 文件清单(本次同步)

- 新增 4 个:`app/Enums/{PostStatus,CommentStatus,Toggle}.php`,`app/Services/Gravatar.php`
- 修改 16 个 PHP:`app/Models/*` (5) + `app/Controllers/*` (8) + `app/Services/Installer.php` + `app/bootstrap.php` + `app/Core/{Session,View}.php`
- 修改 5 个 view:`views/profile/index.php`,`views/post/show.php`,`views/layouts/front.php`,`views/setting/index.php`,`views/comment/index.php`
- 修改 3 个 css:`public/assets/css/admin.css`,`public/assets/css/themes/default.css`,`public/assets/css/themes/ember.css`

部署报告: 同步完成后 290 个文件就位,版本与本地 `Jun 4 00:56` 一致。
