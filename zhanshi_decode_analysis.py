#!/usr/bin/env python3
"""
zhanshi_server_64.php 解码分析脚本

URL: https://www.3tyx.top/2/zhanshi_server_64.php

分析结论：
- 该 URL 返回纯 Base64 编码的二进制数据（约 6040 字符，解码后 4528 字节）
- Base64 解码后的数据具有高熵（~7.96 bits/byte），表明经过加密
- 数据长度可被 16 整除，符合 AES 等块加密特征
- 已尝试的解密方式均未成功（密钥未知）：
  - 单字节 XOR
  - 多字节 XOR（zhanshi、pass、Godzilla、Behinder 默认密钥）
  - Gzip/Bzip2/Zlib 解压
  - AES-ECB/CBC（Godzilla、Behinder、常见密钥）

若获得正确密钥，可使用本脚本的解密函数。
"""

import base64
import zlib
import hashlib
import sys
from urllib.request import urlopen

URL = "https://www.3tyx.top/2/zhanshi_server_64.php"


def fetch_content(url: str = URL) -> bytes:
    """从 URL 获取并 Base64 解码"""
    with urlopen(url, timeout=10) as r:
        b64 = r.read().decode("utf-8").strip()
    return base64.b64decode(b64)


def try_xor_decrypt(data: bytes, key: bytes, offset: int = 0) -> bytes:
    """XOR 解密，key 循环使用。offset: 0 表示 key[i%len], 1 表示 key[(i+1)&15]（需16字节key）"""
    k = key.ljust(16, b"\0")[:16] if len(key) != 16 else key
    if offset and len(k) == 16:
        return bytes([data[i] ^ k[(i + 1) & 15] for i in range(len(data))])
    return bytes([data[i] ^ key[i % len(key)] for i in range(len(data))])


def try_aes_decrypt(data: bytes, key: bytes, mode: str = "ECB") -> bytes | None:
    """AES 解密"""
    try:
        from Crypto.Cipher import AES
        from Crypto.Util.Padding import unpad
    except ImportError:
        return None

    key = key[:16].ljust(16, b"\0") if len(key) < 16 else key[:16]
    try:
        if mode == "ECB":
            cipher = AES.new(key, AES.MODE_ECB)
            dec = cipher.decrypt(data)
        else:  # CBC, IV 取前 16 字节
            iv = data[:16]
            cipher = AES.new(key, AES.MODE_CBC, iv)
            dec = cipher.decrypt(data[16:])
        return unpad(dec, 16)
    except Exception:
        return None


def try_decompress(data: bytes) -> bytes | None:
    """尝试 gzip/zlib 解压"""
    for wbits in (16 + 15, 15, -15):
        try:
            return zlib.decompress(data, wbits)
        except zlib.error:
            continue
    return None


def analyze(data: bytes) -> None:
    """输出数据分析"""
    from collections import Counter
    import math

    freq = Counter(data)
    entropy = -sum((c / len(data)) * math.log2(c / len(data)) for c in freq.values())
    print(f"数据长度: {len(data)} 字节")
    print(f"熵: {entropy:.2f} bits/byte (随机≈8)")
    print(f"唯一字节数: {len(freq)}/256")
    print(f"长度 mod 16: {len(data) % 16}")
    print(f"前 20 字节 hex: {data[:20].hex()}")


def main():
    print("正在获取数据...")
    data = fetch_content()
    print(f"Base64 解码成功，得到 {len(data)} 字节\n")

    print("=== 数据分析 ===")
    analyze(data)
    print()

    # 尝试已知密钥
    keys_to_try = [
        (b"3c6e0b8a9c15224a", "Godzilla (md5('key')[:16])"),
        (b"e45e329feb5d925b", "Behinder (md5('rebeyond')[:16])"),
        (b"zhanshi", "zhanshi"),
        (b"zhanshi1234567890", "zhanshi16"),
    ]

    print("=== 尝试 XOR 解密 ===")
    for key, name in keys_to_try:
        for offset in (0, 1):
            dec = try_xor_decrypt(data, key, offset)
            if b"<?php" in dec or b"<?=" in dec:
                print(f"成功! 密钥={name}, offset={offset}")
                print(dec[:500].decode("utf-8", errors="replace"))
                return
            dec_gz = try_decompress(dec)
            if dec_gz and (b"<?php" in dec_gz or b"function" in dec_gz):
                print(f"成功! XOR+解压, 密钥={name}")
                print(dec_gz[:500].decode("utf-8", errors="replace"))
                return
    print("XOR 解密未找到有效结果\n")

    print("=== 尝试 AES 解密 ===")
    for key, name in keys_to_try:
        for mode in ("ECB", "CBC"):
            dec = try_aes_decrypt(data, key, mode)
            if dec and (b"<?php" in dec or b"function" in dec):
                print(f"成功! 密钥={name}, 模式={mode}")
                print(dec[:800].decode("utf-8", errors="replace"))
                return
    print("AES 解密未找到有效结果\n")

    print("=== 结论 ===")
    print("数据已加密，当前无法解密。若您有正确密钥，可修改本脚本添加密钥后重试。")
    print("解密逻辑已封装在 try_xor_decrypt / try_aes_decrypt 中。")


if __name__ == "__main__":
    main()
