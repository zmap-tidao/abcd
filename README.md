# Discuz! X3.5 QQ 群发帖通知插件

本仓库提供一个 Discuz! X3.5 插件示例：`qqgroupnotify`，用于在论坛发新帖后，把通知推送到 QQ 群（默认走 NapCat HTTP）。

## 功能

- 支持“通知全部版块”或“仅通知指定版块（fid）”
- 默认支持 NapCat HTTP API（`send_group_msg`）
- 可切换为通用 Webhook 模式
- 支持自定义消息模板（占位符：`{forum}` `{title}` `{author}` `{time}` `{url}`）
- 对 `post_newthread_succeed` 做通知，避免草稿、隐藏贴等异常场景误推送

## 目录

```text
source/plugin/qqgroupnotify/
├── qqgroupnotify.class.php   # 核心钩子与推送逻辑
├── install.php               # 安装时写入插件变量
├── upgrade.php               # 升级时补齐新增配置项
└── uninstall.php             # 卸载时清理插件变量
```

## 安装说明（开发环境）

1. 将 `source/plugin/qqgroupnotify` 放到 Discuz 站点对应目录下。
2. 进入 Discuz 后台插件管理，安装并启用 `qqgroupnotify`。
3. 在插件设置页填写：
   - 开启通知：`开启`
   - 通知全部版块：`是/否`
   - 指定版块 ID：例如 `2,36,58`（仅在“通知全部版块=否”时生效）
   - 推送模式：`napcat`（推荐）
   - NapCat API 地址：例如 `http://127.0.0.1:3000` 或 `http://127.0.0.1:3000/send_group_msg`
   - NapCat 群号：例如 `123456789,987654321`
   - NapCat Access Token：按需填写
   - （可选）切到 `webhook` 模式后再填写 Webhook 地址
   - 消息模板：可按需自定义
4. 在目标版块发布新主题，检查群消息是否到达。

## NapCat 推送说明

NapCat 模式下会向 `POST /send_group_msg` 发送 JSON：

- `group_id`: 群号
- `message`: 消息正文
- `auto_escape`: `false`

若配置了 `napcat_access_token`，会自动带上请求头：

- `Authorization: Bearer <token>`

## Webhook 兼容模式说明

切换为 `push_mode=webhook` 后，插件会发送通用 JSON 字段：

- `msg_type: "text"`
- `text.content: "<消息正文>"`
- `content: "<消息正文>"`
- `message: "<消息正文>"`
