# Discuz! X3.5 QQ 群发帖通知插件

本仓库提供一个 Discuz! X3.5 插件示例：`qqgroupnotify`，用于在论坛发新帖后，把通知推送到 QQ 群机器人 Webhook。

## 功能

- 支持“通知全部版块”或“仅通知指定版块（fid）”
- 支持配置多个 Webhook 地址（每行一个，或逗号分隔）
- 支持自定义消息模板（占位符：`{forum}` `{title}` `{author}` `{time}` `{url}`）
- 对 `post_newthread_succeed` 做通知，避免草稿、隐藏贴等异常场景误推送

## 目录

```text
source/plugin/qqgroupnotify/
├── qqgroupnotify.class.php   # 核心钩子与推送逻辑
├── install.php               # 安装时写入插件变量
└── uninstall.php             # 卸载时清理插件变量
```

## 安装说明（开发环境）

1. 将 `source/plugin/qqgroupnotify` 放到 Discuz 站点对应目录下。
2. 进入 Discuz 后台插件管理，安装并启用 `qqgroupnotify`。
3. 在插件设置页填写：
   - 开启通知：`开启`
   - 通知全部版块：`是/否`
   - 指定版块 ID：例如 `2,36,58`（仅在“通知全部版块=否”时生效）
   - Webhook 地址：填写 QQ 群机器人推送地址
   - 消息模板：可按需自定义
4. 在目标版块发布新主题，检查群消息是否到达。

## Webhook 负载说明

插件默认发送 `application/json`，内容包含以下字段（方便适配不同机器人网关）：

- `msg_type: "text"`
- `text.content: "<消息正文>"`
- `content: "<消息正文>"`
- `message: "<消息正文>"`

如果你使用的 QQ 机器人网关字段不同，可在 `sendWebhookMessage()` 中调整。
