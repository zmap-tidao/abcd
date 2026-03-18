# Discuz! X3.5 QQ群发帖通知插件

论坛发帖自动通知到QQ群的 Discuz! X3.5 插件。支持选择指定板块或全部板块，当有新帖发布时自动将通知推送到指定的QQ群。

## 功能特性

- **新帖通知**：论坛有新帖发布时，自动发送通知到指定QQ群
- **回帖通知**（可选）：有新回帖时也可以发送通知
- **板块筛选**：可指定特定板块发帖才通知，也可以设置全部板块
- **自定义模板**：通知消息内容完全可自定义，支持标题、作者、板块、链接、摘要等变量
- **多群支持**：可同时通知多个QQ群
- **调试日志**：支持开启调试模式，记录通知日志方便排查问题
- **后台管理**：完整的后台设置界面，支持测试发送、查看通知记录
- **广泛兼容**：兼容所有支持 OneBot v11 协议的QQ机器人框架

## 兼容的QQ机器人框架

本插件使用 [OneBot v11](https://github.com/botuniverse/onebot-11) 协议标准的 HTTP API，兼容以下主流QQ机器人框架：

| 框架 | 说明 |
|------|------|
| [go-cqhttp](https://github.com/Mrs4s/go-cqhttp) | 最经典的 OneBot 实现 |
| [NapCat](https://github.com/NapNeko/NapCatQQ) | 基于 NTQQ 的现代实现 |
| [LLOneBot](https://github.com/LLOneBot/LLOneBot) | 基于 NTQQ 的 OneBot 实现 |
| [Lagrange](https://github.com/LagrangeDev/Lagrange.Core) | 纯 C# 实现的 NTQQ 协议 |
| [OpenShamrock](https://github.com/whitechi73/OpenShamrock) | 基于 Xposed 的实现 |

> 任何支持 OneBot v11 HTTP API `send_group_msg` 接口的框架均可使用。

## 安装方法

### 方式一：后台安装（推荐）

1. 下载本插件的完整目录
2. 将 `source/plugin/qqgroup_notify` 目录上传到你的 Discuz! 论坛对应目录
3. 登录论坛后台 → **应用** → **插件** → 找到 "QQ群发帖通知" → 点击 **安装**
4. 安装完成后点击 **启用**
5. 进入插件设置，配置相关参数

### 方式二：开发模式安装

1. 在 `config/config_global.php` 中开启开发模式：

```php
$_config['plugindeveloper'] = 1;
```

2. 上传插件文件到 `source/plugin/qqgroup_notify/`
3. 后台 → **应用** → **插件** → **安装新插件** → 选择 `qqgroup_notify`

## 配置说明

安装并启用插件后，在后台插件设置中配置以下参数：

| 设置项 | 说明 | 示例值 |
|--------|------|--------|
| 启用插件 | 是否开启通知功能 | 是 |
| 机器人API地址 | OneBot HTTP API 地址 | `http://127.0.0.1:5700` |
| Access Token | API 鉴权令牌（可选） | `your_token_here` |
| QQ群号 | 接收通知的群号，多个用逗号分隔 | `123456,789012` |
| 通知板块 | 板块FID，`all` 表示全部 | `2,5,8` 或 `all` |
| 通知消息模板 | 支持变量替换的模板 | 见下方 |
| 回帖也通知 | 是否通知回帖 | 否 |
| 摘要长度 | 内容摘要最大字符数 | `100` |
| 论坛网址 | 用于生成帖子链接 | `https://bbs.example.com` |
| 调试模式 | 开启写入日志 | 否 |

### 消息模板变量

| 变量 | 说明 |
|------|------|
| `{subject}` | 帖子/主题标题 |
| `{author}` | 发帖/回帖作者 |
| `{forum}` | 板块名称 |
| `{url}` | 帖子链接 |
| `{summary}` | 内容摘要 |

### 默认消息模板

**新帖通知：**
```
📢 论坛新帖通知
━━━━━━━━━━
📌 标题：{subject}
👤 作者：{author}
📂 板块：{forum}
🔗 链接：{url}
📝 摘要：{summary}
```

**回帖通知：**
```
💬 论坛新回帖通知
━━━━━━━━━━
📌 主题：{subject}
👤 回帖：{author}
📂 板块：{forum}
🔗 链接：{url}
📝 摘要：{summary}
```

## QQ机器人部署（以 NapCat 为例）

1. **安装 NapCat**：按照 [NapCat 官方文档](https://github.com/NapNeko/NapCatQQ) 安装并登录QQ账号

2. **启用 HTTP 服务**：在 NapCat 配置中启用 HTTP 服务，默认端口 `3000`（不同框架默认端口不同，go-cqhttp 默认 `5700`）

3. **确认 API 可用**：访问 `http://你的服务器IP:端口/get_login_info`，能返回JSON数据说明配置成功

4. **在插件中配置**：将 API 地址填入插件的"机器人API地址"设置中

5. **测试**：点击插件设置中的"发送测试消息"按钮验证

## 补充集成方式（可选）

如果自动 hookscript 钩子在你的环境中未能自动触发通知，可以使用手动集成方式。

在 Discuz 的发帖处理文件末尾添加一行 include：

**新帖通知** - 编辑 `source/include/post/post_newthread.inc.php`，在文件末尾 `?>` 之前添加：

```php
@include DISCUZ_ROOT.'source/plugin/qqgroup_notify/qqnotify.inc.php';
```

**回帖通知** - 编辑 `source/include/post/post_reply.inc.php`，在文件末尾 `?>` 之前添加：

```php
@include DISCUZ_ROOT.'source/plugin/qqgroup_notify/qqnotify.inc.php';
```

> 这种方式直接在发帖/回帖完成后触发通知，最为可靠。`qqnotify.inc.php` 内部有防重复机制，不会与 hookscript 冲突。

## 文件结构

```
source/plugin/qqgroup_notify/
├── discuz_plugin_qqgroup_notify.xml   # 插件描述文件
├── qqgroup_notify.class.php           # 钩子类（自动 hookscript）
├── qqnotify.inc.php                   # 独立通知工具（手动集成用）
├── install.inc.php                    # 安装脚本
├── uninstall.inc.php                  # 卸载脚本
├── setting.inc.php                    # 后台设置扩展
├── template/
│   └── setting_extra.htm              # 设置页面模板
└── language/
    ├── sc/
    │   └── lang_plugin.php            # 简体中文
    ├── tc/
    │   └── lang_plugin.php            # 繁体中文
    └── en/
        └── lang_plugin.php            # 英文
```

## 调试排查

1. **开启调试模式**：在插件设置中将"调试模式"设为"开启"
2. **查看日志**：日志文件位于 `data/log/qqnotify_YYYYMMDD.log`
3. **检查 API 连通性**：确保论坛服务器能访问到 QQ 机器人的 HTTP API 地址
4. **检查群号**：确保机器人已加入目标QQ群，且有发送消息权限
5. **发送测试**：在插件后台点击"发送测试消息"按钮

## 常见问题

**Q: 通知没有发出去？**
A: 请检查：① 插件是否已启用 ② 机器人API地址是否正确 ③ 机器人是否在线 ④ 是否已加入目标群

**Q: 只想通知某些板块怎么设置？**
A: 在"通知板块"中填入板块FID，多个用英文逗号分隔。板块FID可在论坛后台 → 论坛 → 版块管理 中查看。

**Q: 支持哪些QQ机器人？**
A: 所有支持 OneBot v11 HTTP API 的机器人框架均可使用，包括 go-cqhttp、NapCat、LLOneBot、Lagrange 等。

**Q: 是否支持发送图片/富文本？**
A: 消息模板中可以使用 OneBot CQ码发送图片等富文本内容，例如 `[CQ:image,file=https://example.com/logo.png]`。

## 协议

MIT License
