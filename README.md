# Discuz! X3.5 QQ 群发帖通知插件

这是一个可直接放入 Discuz! X3.5 `source/plugin/` 目录的插件包。启用后，你可以配置一个或多个 QQ 群，当论坛指定板块或全部板块发布新主题时，自动把通知推送到群里。

## 功能

- 支持 Discuz! X3.5 新主题发帖通知
- 支持全站板块通知，或只通知指定 FID 板块
- 支持同时推送到多个 QQ 群
- 支持两种接口模式
  - `cqhttp`：适配 go-cqhttp / NapCat HTTP API
  - `webhook`：适配自定义桥接服务
- 支持自定义消息模板
- 支持从首帖正文提取摘要

## 目录结构

```text
source/
  plugin/
    forum_qqgroup_notify/
      discuz_plugin_forum_qqgroup_notify.xml
      forum_qqgroup_notify.class.php
      install.php
      uninstall.php
```

## 安装方法

1. 将本仓库中的 `source/plugin/forum_qqgroup_notify` 整个目录复制到你的 Discuz! 站点对应路径：

   ```text
   论坛根目录/source/plugin/forum_qqgroup_notify
   ```

2. 进入 Discuz! 后台，安装或导入插件：
   - 如果你的后台支持本地导入插件包，导入：

     ```text
     source/plugin/forum_qqgroup_notify/discuz_plugin_forum_qqgroup_notify.xml
     ```

   - 或者将文件放入站点后，在插件列表中安装并启用。

3. 在插件设置中填写接口和群号配置。

## 后台配置说明

### 1. 启用通知

- `开启`：插件开始监听新主题发布并推送消息
- `关闭`：不发送任何通知

### 2. 接口类型

#### cqhttp

用于 go-cqhttp / NapCat HTTP API。

- 接口地址可以填写：
  - `http://127.0.0.1:3000`
  - 或完整地址 `http://127.0.0.1:3000/send_group_msg`
- 插件会自动向 `send_group_msg` 发送 JSON 请求

#### webhook

用于你自己的中间层服务。插件会向你提供的 URL 发出 POST JSON，请求体中会包含：

- `event`
- `group_id`
- `forum_id`
- `forum_name`
- `thread_id`
- `subject`
- `author`
- `author_id`
- `publish_time`
- `excerpt`
- `thread_url`
- `message`

### 3. 访问令牌

如果你的机器人接口要求 Bearer Token，可直接填写。插件会自动在请求头中加入：

```http
Authorization: Bearer <token>
```

### 4. QQ 群号

支持填写多个群号，使用英文逗号、中文逗号或空格分隔：

```text
123456,234567 345678
```

### 5. 通知范围

- `all`：全部板块发新主题都推送
- `selected`：只有指定 FID 才推送

### 6. 板块 FID 列表

当通知范围为 `selected` 时填写，例如：

```text
2,5,9
```

### 7. 消息模板

支持这些变量：

- `{forum_name}`
- `{subject}`
- `{author}`
- `{authorid}`
- `{publish_time}`
- `{excerpt}`
- `{thread_url}`
- `{fid}`
- `{tid}`

默认模板：

```text
【论坛新帖通知】
板块：{forum_name}
标题：{subject}
作者：{author}
摘要：{excerpt}
时间：{publish_time}
链接：{thread_url}
```

## go-cqhttp / NapCat 请求示例

插件在 `cqhttp` 模式下发送的请求大致如下：

```http
POST /send_group_msg HTTP/1.1
Content-Type: application/json
Authorization: Bearer your_token
```

```json
{
  "group_id": "123456",
  "message": "【论坛新帖通知】\n板块：综合讨论\n标题：欢迎使用\n作者：admin\n摘要：这是首帖内容摘要...\n时间：2026-03-18 12:00:00\n链接：https://example.com/forum.php?mod=viewthread&tid=100",
  "auto_escape": false
}
```

## 实现说明

插件当前会在“新主题发布成功”相关钩子里发起通知，并做了同请求内的重复发送保护，避免因为多个成功钩子同时触发而重复推送。

为了兼容常见 Discuz! 部署，HTTP 请求会优先使用 `curl`，没有 `curl` 时回退到 `file_get_contents`。

## 注意事项

1. 普通 QQ 群本身没有开放的官方论坛 Webhook；通常需要：
   - NapCat
   - go-cqhttp
   - 或你自己的桥接服务

2. 如果你的论坛开启了发帖审核，是否在审核前或审核后通知，取决于站点当前发帖流程触发到的成功钩子；本插件会尽量在主题成功创建后推送。

3. 如果你的 Discuz! 做过深度二开，建议先在测试环境验证一次新帖通知流程。
