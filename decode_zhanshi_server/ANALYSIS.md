# zhanshi_server_64.php 数据分析报告

## 数据来源

- **URL**: `https://www.3tyx.top/2/zhanshi_server_64.php`
- **Content-Type**: `text/plain; charset=UTF-8`
- **Content-Disposition**: `inline; filename="encrypted_data.txt"`
- **内容**: 静态 Base64 编码字符串（多次请求内容一致）

## Base64 解码后数据特征

| 属性 | 值 |
|------|------|
| Base64 原始长度 | 6040 字节 |
| 解码后长度 | 4528 字节 |
| Shannon 熵 | 7.9581 bits/byte（最大 8.0） |
| 长度 mod 16 | 0（AES 块对齐） |
| 前 8 字节 (ASCII) | `4meJnPyl` |
| 前 16 字节 (HEX) | `346d654a6e50796c943b5aac7dad46c8` |

## 分析结论

### 1. 加密数据

- 极高的 Shannon 熵（7.96/8.0）表明数据已加密或使用强压缩
- 数据长度为 16 字节的整数倍（4528 / 16 = 283），与 AES 加密的块大小一致
- 服务器返回的 `Content-Disposition` 明确标注文件名为 `encrypted_data.txt`

### 2. 推测加密方式

最可能的加密方式为 **AES-128-CBC** 或 **AES-256-CBC**：
- 前 16 字节可能是 IV（初始化向量）
- 剩余 4512 字节为密文（4512 / 16 = 282 个完整块）
- PHP 常见做法：`openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv)`

### 3. 数据用途

根据上下文分析（996引擎/传世游戏服务器相关），此文件可能是：
- 游戏服务器列表数据（开区列表/展示页）
- 服务器配置信息
- 加密后的游戏参数

### 4. 已尝试的解密方法

以下方法均未成功（因缺少正确的加密密钥）：

| 方法 | 结果 |
|------|------|
| Base64 双重解码 | 失败 |
| gzinflate / gzuncompress / gzdecode | 失败 |
| ROT13 + Base64 | 失败 |
| 反转 Base64 | 失败 |
| AES-128/256-CBC/ECB + 常见密钥 | 失败 |
| Blowfish / DES / 3DES / RC4 | 失败 |
| XOR（单字节/多字节/自身前缀） | 失败 |
| MD5/SHA256 派生密钥 + AES | 失败 |
| LZMA / BZ2 / Zstandard 解压 | 失败 |

### 5. 尝试的密钥

- 域名相关：`3tyx.top`, `www.3tyx.top`
- 文件名相关：`zhanshi`, `zhanshi_server`, `zhanshi_server_64`
- 数据前缀：`4meJnPyl`（前8字节ASCII）
- 通用密钥：`admin`, `root`, `password`, `123456`, `secret`, `key`
- 游戏相关：`996`, `996m2`, `woool`, `chuanshi`
- 中文关键词：`传世`, `传奇`, `展示`, `战石`
- 以上所有的 MD5 和 SHA256 变体

## 解密需要的信息

要成功解密此数据，需要：

1. **加密密钥** — 通常硬编码在服务端 PHP 源码中
2. **加密算法** — 大概率为 AES-128-CBC 或 AES-256-CBC
3. **IV 获取方式** — 是否为数据前 16 字节，或单独存储

## 使用解码工具

提供了 PHP 和 Python 两个解码脚本：

```bash
# PHP 解码
php decode.php "正确的密钥"
php decode.php "正确的密钥" aes-256-cbc

# Python 解码
pip install pycryptodome
python3 decode.py "正确的密钥"
python3 decode.py "正确的密钥" aes-128-cbc
```

解码结果将输出到标准输出，可重定向到文件：

```bash
php decode.php "密钥" > decoded_output.txt
```
