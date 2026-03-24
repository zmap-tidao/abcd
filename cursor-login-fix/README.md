# Cursor 电脑版登录不跳转网页修复方案（Linux）

## 问题描述

在 Linux 系统上，点击 Cursor IDE 的"登录"按钮后，浏览器没有弹出，也没有任何反应，导致无法完成登录。

## 根本原因

Cursor 的登录流程使用了 OAuth2 授权码模式：

1. Cursor 打开浏览器，跳转到 `https://cursor.sh/login` 进行授权。
2. 授权完成后，浏览器会被重定向回 `cursor://...` 格式的自定义协议链接。
3. 操作系统收到这个 `cursor://` 链接，将其转发给 Cursor 应用，完成登录。

**问题在于**：Linux 系统默认不认识 `cursor://` 这个协议，没有为它注册处理程序（handler）。浏览器拿到这个回调 URL 后不知道交给谁处理，登录流程就此卡住。

## 修复方法

### 方法一：运行自动修复脚本（推荐）

```bash
# 进入脚本目录
cd cursor-login-fix

# 给脚本添加可执行权限
chmod +x fix_cursor_login.sh

# 运行脚本（会自动查找 Cursor 安装路径）
./fix_cursor_login.sh

# 如果自动查找失败，手动指定 Cursor 的路径：
./fix_cursor_login.sh /path/to/your/cursor
```

脚本会自动完成以下步骤：
1. 在 `~/.local/share/applications/` 创建 `cursor-url-handler.desktop` 文件
2. 向系统注册 `cursor://` 协议处理器
3. 更新桌面应用数据库
4. 验证注册是否成功

### 方法二：手动修复

**第一步：找到 Cursor 的安装路径**

```bash
# 如果通过 AppImage 安装，通常在：
ls ~/Applications/cursor*.appimage
ls ~/.local/bin/cursor

# 也可以用 which 命令：
which cursor
```

**第二步：创建 .desktop 文件**

```bash
mkdir -p ~/.local/share/applications

cat > ~/.local/share/applications/cursor-url-handler.desktop << 'EOF'
[Desktop Entry]
Name=Cursor URL Handler
Comment=Handles cursor:// protocol links for Cursor IDE login
Exec=/path/to/cursor --open-url %u
Type=Application
Terminal=false
NoDisplay=true
MimeType=x-scheme-handler/cursor;
EOF
```

> 将 `/path/to/cursor` 替换为你实际的 Cursor 路径，例如：
> `Exec=/home/your_username/Applications/cursor-0.47.8.appimage --open-url %u`

**第三步：注册 cursor:// 协议**

```bash
xdg-mime default cursor-url-handler.desktop x-scheme-handler/cursor
```

**第四步：刷新桌面数据库**

```bash
update-desktop-database ~/.local/share/applications
```

**第五步：测试**

```bash
xdg-open "cursor://test"
```

如果 Cursor 窗口被唤起，说明注册成功。再次点击 Cursor 的登录按钮即可正常完成登录。

---

## 常见问题

### 脚本运行后仍然不行

- 尝试注销并重新登录桌面会话（logout/login），让桌面环境重新加载注册表。
- 确认 `xdg-open "cursor://test"` 能正常唤起 Cursor。

### 使用 Snap 或 Flatpak 安装的 Cursor

部分 Snap/Flatpak 应用有沙盒隔离，可能需要额外配置。建议改用 AppImage 或官方 `.deb`/`.rpm` 包安装 Cursor，再运行修复脚本。

### Windows / macOS 用户

Windows 和 macOS 会在安装 Cursor 时自动注册协议处理器，通常不会遇到这个问题。如果在 Windows 上遇到类似问题，请检查 Cursor 是否已正确安装（而非解压使用）。

---

## 技术背景

Linux 使用 `xdg-open` 工具来决定如何打开不同类型的文件和链接。通过在 `.desktop` 文件中声明 `MimeType=x-scheme-handler/cursor`，并用 `xdg-mime` 将其设为默认处理器，就等于在系统的"转发规则表"中加入了一条记录：

> 所有 `cursor://` 开头的链接 → 转发给 Cursor 应用处理

这样浏览器在收到 OAuth 回调 URL 时，就能正确地将其交回给 Cursor，完成登录流程。
