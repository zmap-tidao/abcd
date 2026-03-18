# QQ群通知插件（Discuz! X3.5）

Discuz! X3.5 论坛插件：当论坛指定板块（或全部板块）有新帖子/新回复时，自动将消息推送到 QQ 群。

---

## 功能特性

- 新帖发布时通知 QQ 群
- 新回复发布时通知 QQ 群（可单独开关）
- 支持指定监控特定板块，或监控全部板块
- 支持同时通知多个 QQ 群
- 消息模板可自定义（支持 `{forumname}`、`{subject}`、`{author}`、`{url}` 等占位符）
- 使用标准 **OneBot v11 HTTP API** 协议，兼容主流 QQ Bot 框架
- 后台管理页面附带**一键连通性测试**功能

---

## 兼容的 QQ Bot 框架

本插件通过 OneBot v11 HTTP API 与 QQ 通信，需要在服务器上部署以下任意一款 QQ Bot 框架：

| 框架 | 说明 | 链接 |
|------|------|------|
| go-cqhttp | 最广泛使用的实现 | https://docs.go-cqhttp.org/ |
| Lagrange.Core | 基于 NTQQ 协议 | https://github.com/LagrangeDev/Lagrange.Core |
| LLOneBot | 基于 NTQQ | https://github.com/LLOneBot/LLOneBot |
| NapCatQQ | 基于 NTQQ | https://github.com/NapNeko/NapCatQQ |

> **注意**：Bot 账号必须已加入目标 QQ 群，且具有发言权限。

---

## 目录结构

```
qqnotify-plugin/
├── plugin.xml                                  # 插件清单（由 Discuz! 读取）
└── upload/
    └── source/
        └── plugin/
            └── qqnotify/
                ├── qqnotify.class.php           # 核心类（HTTP 通信、消息格式化）
                ├── qqnotify_newthread.php       # 新帖钩子（Hook: newthread_postsave）
                ├── qqnotify_newreply.php        # 新回复钩子（Hook: newreply_postsave）
                └── admin.inc.php               # 后台管理设置页
```

---

## 安装步骤

### 1. 部署 QQ Bot 框架

以 **go-cqhttp** 为例（其他框架类似）：

```bash
# 下载并启动 go-cqhttp
./go-cqhttp
```

在 `config.yml` 中开启 HTTP 服务器：

```yaml
servers:
  - http:
      host: 127.0.0.1
      port: 5700
      access-token: 'your_token_here'   # 可留空
```

将配置的 QQ 账号添加到目标 QQ 群。

### 2. 打包插件

将本仓库中的 `plugin.xml` 和 `upload/` 目录打包为 ZIP 文件：

```bash
zip -r qqnotify.zip plugin.xml upload/
```

### 3. 上传安装到 Discuz!

1. 登录论坛后台管理中心
2. 进入 **应用** → **插件** → **插件管理**
3. 点击 **上传安装** 按钮，选择 `qqnotify.zip` 上传
4. 安装成功后，在插件列表中找到 **QQ群通知**，点击 **启用**

### 4. 配置插件

1. 点击插件旁的 **设置** 按钮进入配置页
2. 填写各项参数（详见下方说明）
3. 保存后点击 **发送测试消息** 验证是否可用

---

## 配置项说明

| 配置项 | 说明 | 示例 |
|--------|------|------|
| 启用插件 | 总开关 | 启用 / 禁用 |
| 通知新发主题 | 是否在新帖发布时推送 | 是 |
| 通知新回复 | 是否在回复发布时推送 | 否 |
| API 地址 | OneBot HTTP API 监听地址 | `http://127.0.0.1:5700` |
| Access Token | Bot 鉴权令牌，未设置留空 | `mytoken123` |
| 目标QQ群号 | 接收通知的群号，多个用英文逗号分隔 | `123456789,987654321` |
| 监控板块（FID） | 留空=全部板块；指定时填写版块 FID，逗号分隔 | `2,5,8` |
| 新帖消息模板 | 自定义推送文本，支持占位符 | 见默认值 |
| 新回复消息模板 | 自定义推送文本，支持占位符 | 见默认值 |

### 消息模板占位符

| 占位符 | 说明 |
|--------|------|
| `{forumname}` | 板块名称 |
| `{subject}` | 主题标题 |
| `{author}` | 发帖人昵称 |
| `{tid}` | 主题 ID |
| `{pid}` | 帖子 ID |
| `{url}` | 帖子/回复直链 |
| `{sitename}` | 站点名称 |

### 默认消息模板

**新帖：**
```
【新帖】{forumname}
标题：{subject}
作者：{author}
链接：{url}
```

**新回复：**
```
【回帖】{forumname}
主题：{subject}
作者：{author}
链接：{url}
```

---

## 查找板块 FID

在 Discuz! 后台 → **论坛** → **版块管理** 中，每个版块链接中的 `fid` 参数即为版块 ID。  
插件设置页面也会列出所有版块的 FID 供参考。

---

## 工作原理

```
用户发帖/回帖
    ↓
Discuz! 触发 Hook（newthread_postsave / newreply_postsave）
    ↓
插件检查：是否启用、板块是否在监控范围
    ↓
格式化消息（替换模板占位符）
    ↓
调用 OneBot HTTP API（POST /send_group_msg）
    ↓
Bot 账号向 QQ 群发送消息
```

HTTP 请求格式（OneBot v11）：

```http
POST http://127.0.0.1:5700/send_group_msg
Content-Type: application/json
Authorization: Bearer your_token_here   （有 token 时）

{
  "group_id": 123456789,
  "message": "【新帖】技术交流\n标题：如何使用本插件\n作者：admin\n链接：https://yourforum.com/thread-1-1-1.html"
}
```

---

## 服务器要求

- PHP ≥ 5.6（推荐 7.x / 8.x）
- cURL 扩展（可选，无 cURL 时自动回退到 `file_get_contents`）
- Discuz! X3.5（其他 3.x 版本可能兼容，未经测试）
- 论坛服务器能够访问 OneBot API 地址（同机或局域网推荐，公网注意防火墙）

---

## 常见问题

**Q: 消息发不到群里？**  
A: 请确认：① Bot 框架已运行；② 群号填写正确；③ Bot 账号已在群内；④ 点击后台"测试发送"查看是否有错误。

**Q: 发帖后有延迟？**  
A: HTTP 请求超时为 5 秒，若 Bot API 响应慢会稍微影响发帖流程。建议 Bot 与论坛同机部署。

**Q: 如何只监控特定板块？**  
A: 在"监控板块（FID）"中填写版块 FID，多个用英文逗号分隔。留空则监控全部板块。

**Q: 支持图片/CQ码消息吗？**  
A: 支持。在消息模板中直接写入 CQ 码即可，例如：`[CQ:image,file=https://example.com/img.png]`。

---

## License

MIT License
