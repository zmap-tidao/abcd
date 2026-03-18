# qqgroupnotify（Discuz! X3.5）

## 触发时机

- 新主题发布成功后触发（`post_newthread_succeed`）
- 通过 `post_newthread_succeed_message` + `global_showmessage` 双兜底，提升兼容性

## 配置项

- `enabled`：是否开启通知
- `notify_all`：是否通知全部版块
- `forum_ids`：指定版块 ID，多个用逗号分隔
- `webhook_urls`：Webhook 地址，多个每行一个（或逗号分隔）
- `message_template`：消息模板，支持占位符：
  - `{forum}` 版块名
  - `{title}` 主题标题
  - `{author}` 发帖用户
  - `{time}` 发帖时间
  - `{url}` 帖子链接

## 注意

1. 本插件通过 HTTP POST 推送消息，请确保 Discuz 服务器可访问目标 Webhook。
2. 如果 Webhook 有签名、token、时间戳等额外要求，请在 `sendWebhookMessage()` 内按网关协议补充。
3. 帖子链接使用 `$_G['siteurl']` 组装，请先在站点后台正确配置站点 URL。
