# Kero Design

对齐 [kero.sh](https://kero.sh/) 落地页语言，**不是** Kami 纸面，也**不是**假 App 窗口（无侧栏 rail、无红绿灯 chrome）。

## Layout

```
.kero-site          max-width 680px，居中
├── .kero-top       品牌方标 + 文字导航 + 工具图标
├── .kero-main
│   ├── .kero-hero  大标题两行 + 说明 + 按钮 + chips
│   └── .kero-section / .kero-rows
│       └── .kero-row   grid: 190px label | body（官网 Features / FAQ 同构）
└── .kero-foot      细顶线 + 小号链接
```

## Visual

- 默认深色画布 `#08090d`，浅色为 GitHub Light
- Geist / Geist Mono
- 区块标题：13px、tracking、muted、lowercase（如 `Features` / `Feed`）
- 行：底部分割线 `border-b`，左 kind/time，右标题+描述
- 按钮：8px 圆角、描边次按钮 + 实心主按钮（近白/近黑）
- 品牌标：小黄文件夹感色块（官网 logo 气质）

## Anti-patterns

- 不要左侧固定导航轨
- 不要 macOS 交通灯 / 假窗口顶栏 / 底状态栏
- 不要羊皮纸大卡片流
