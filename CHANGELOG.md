# Changelog

本文件记录 LiteNote 的版本变更。格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [1.1.0] - 2026-07-25

### Added
- 文章 / 标题字体：从 BlueCDN manifest 同步，支持分别设置与后台 Tabs 预览
- Ember：接入 lucide-animated 风格动态图标
- Ember：自研 LiteZoom 灯箱，替换 Fancybox
- 活动流：Spotify OAuth 集成；时间线布局优化
- 评论：验证码白名单体系（身份校验通过后免验）
- 滔客页：hero（标题 + 活跃热力图 + 关键词）
- 移动端：底部导航图标 Tab Bar、「更多」上浮菜单
- X 卡片：推文头像与图片本地下载缓存，改善国内加载
- 安全与性能：审计加固、公开缓存、资源压缩、欢迎文种子
- 补充 MIT `LICENSE`，重写 README 项目说明

### Changed
- Ember 导航：黑色磨砂玻璃、深色模式流光边框、侧边 dock 交互与命中区域
- Ember 媒体与卡片：音乐唱臂 / 歌词窗口、文章 feature 卡、版权与评论区排版
- 分类页 hero 布局压缩与对齐
- Font Awesome 7.2.0 → 7.3.0；字体 CSS 路径规范为 `/fonts/<name>.css`
- 后台：说说编辑与社交资料 UI、草稿列表空发布时间仍可操作
- 首页 title 使用站点副标题，避免标题重复

### Fixed
- Ember 侧边 dock：Chrome 光标闪烁、命中穿透、热区非整圆等问题
- 音乐播放：进度 / 时间 / 歌词跨 IIFE 不更新
- 分类 Unicode slug 编码支持
- 删除音乐时级联清理关联说说、评论与点赞
- 统计脚本域名白名单限制（支持自建统计地址）
- CI：`composer.json` 补充 MIT license 以通过严格校验

### Removed
- 活动集成中的 OpenAI / Claude API 用量同步（仅能看 token 用量，无法反映订阅）
- 不正确的 CNB 同步工作流

## [1.0.0] - 2026-06-06

### Added
- 以 PHP 8.5 + SQLite 重新整理的轻量自托管个人发布系统
- 根目录应用布局：`index.php`、`config.php`、`.env`、`themes/`、`plugins/`、`uploads/`、`runtime/`
- 独立前台主题：模板、`functions.php`、`inc/`、`pages/`、`assets/main.css` / `assets/main.js`
- 内置 Ember（默认）与 Kami 主题
- Caddy 本地开发配置（`Caddyfile`）
- X 卡片、音乐、滔客、活动同步、RSS、评论与完整后台管理

### Changed
- 公开版本重置为 `1.0.0`
- 框架代码迁入 `core/`，后台 UI 迁入 `admin/`，主题 / 插件 / 上传 / 运行时数据分区存放
- 配置迁至根目录 `config.php`；密钥仍使用 `.env`
- 移除 Web 安装向导，改由 CLI `Installer::install()` 负责建库与升级
- 移除旧 `default` 主题，Ember 作为系统默认主题

### Removed
- 旧版 changelog 历史（本版本为新基线）
- 旧 demo 订阅与 PHP 内置服务器 `router.php` 支持

[1.1.0]: https://github.com/gentpan/LiteNote/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/gentpan/LiteNote/releases/tag/v1.0.0
