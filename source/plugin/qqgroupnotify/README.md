# qqgroupnotify（Discuz! X3.5）

## 触发时机

- 新主题发布成功后触发（`post_newthread_succeed`）
- 通过 `post_newthread_succeed_message` + `global_showmessage` 双兜底，提升兼容性

## 配置项

- `enabled`：是否开启通知
- `notify_all`：是否通知全部版块
- `forum_ids`：指定版块 ID，多个用逗号分隔
- `push_mode`：推送模式（`napcat` / `webhook`）
- `napcat_api_url`：NapCat HTTP API 地址（支持填基础地址或完整 `.../send_group_msg`）
- `napcat_access_token`：NapCat 鉴权 token（可留空）
- `napcat_group_ids`：NapCat 群号，多个用逗号分隔
- `webhook_urls`：Webhook 地址（仅 `push_mode=webhook` 时生效）
- `message_template`：消息模板，支持占位符：
  - `{forum}` 版块名
  - `{title}` 主题标题
  - `{author}` 发帖用户
  - `{time}` 发帖时间
  - `{url}` 帖子链接

## 注意

1. 默认推荐 `push_mode=napcat`，接口为 `POST /send_group_msg`，请求体字段 `group_id`、`message`。
2. `napcat_api_url` 若只填 `http://127.0.0.1:3000`，插件会自动补成 `.../send_group_msg`。
3. `napcat_access_token` 非必填；若开启鉴权，插件会加 `Authorization: Bearer <token>` 请求头。
4. 帖子链接使用 `$_G['siteurl']` 组装，请先在站点后台正确配置站点 URL。
5. 旧版升级到 NapCat 版本时，插件升级流程会执行 `upgrade.php` 自动补齐新配置项。
