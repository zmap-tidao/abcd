# QQ群发帖通知插件

Discuz! X3.5 论坛插件，实现论坛发帖时自动推送通知到QQ群。

## 功能特性

- **QQ群对接**：通过QQ群WebHook将发帖通知推送到指定QQ群
- **板块选择**：支持设置部分板块或全部板块发帖时通知
- **通知类型**：可独立开关新主题通知和回复通知
- **消息内容**：包含板块名、标题、作者、内容摘要、直达链接

## 安装方法

### 1. 上传插件

将 `qqgroup_notify` 文件夹上传到 Discuz 的 `source/plugin/` 目录下。

### 2. 安装插件

登录 Discuz 后台 → 应用 → 插件 → 找到「QQ群发帖通知」→ 安装

### 3. 配置QQ群WebHook

**方式一：QQ官方 HOO!K 机器人（如可用）**

1. 添加机器人QQ：`2854196399` 为好友
2. 创建新群聊并邀请该机器人加入
3. 在群设置中开启「消息推送」并生成 WebHook 链接
4. 复制链接中 `key=` 后面的参数，填入插件配置

**方式二：第三方QQ机器人**

如使用 go-cqhttp、Mirai 等自建机器人，需配置其 WebHook 接收地址，并修改插件中的 `send_to_qqgroup` 方法以适配对应 API。

### 4. 应用钩子补丁（重要）

Discuz X3.5 默认可能不包含发帖成功钩子，需在 `source/module/forum/forum_post.php` 中添加钩子调用。

详见 `patches/forum_post_patch.txt` 中的说明，在发帖成功、跳转前添加：

```php
if(function_exists('hookscript')) {
    $thread = C::t('forum_thread')->fetch($tid);
    $post = C::t('forum_post')->fetch_post($tableid, $pid); // 根据实际API调整
    hookscript('forum_post_newthread_succeed', array('tid'=>$tid, 'pid'=>$pid, 'fid'=>$fid, 'thread'=>$thread, 'post'=>$post));
}
```

回复成功处同理添加 `forum_post_reply_succeed` 钩子。

## 配置说明

| 配置项 | 说明 |
|-------|------|
| QQ群WebHook Key | 从QQ群消息推送设置中获取的 key 参数 |
| 通知板块 | 留空=全部板块；填写板块ID如 `1,2,3`=仅这些板块 |
| 新主题通知 | 发布新主题时是否通知 |
| 回复通知 | 回复帖子时是否通知 |

## 目录结构

```
qqgroup_notify/
├── plugin_qqgroup_notify.xml   # 插件安装配置
├── qqgroup_notify.class.php    # 核心逻辑
├── install.php                 # 安装脚本
├── uninstall.php               # 卸载脚本
├── README.md                   # 说明文档
└── patches/
    └── forum_post_patch.txt    # 钩子补丁说明
```

## 兼容性

- Discuz! X3.5
- PHP 7.2+
- 需支持 file_get_contents 发起 HTTPS 请求

## 注意事项

1. QQ官方 HOO!K 机器人曾暂停测试，如无法使用请考虑自建机器人方案
2. 修改 Discuz 源文件前请备份
3. 部分主机可能限制外网请求，需确保可访问 `app.qun.qq.com`
