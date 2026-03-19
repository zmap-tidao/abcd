#!/usr/bin/env python3
"""
传世展示页 (zhanshi_server_64.php) 解码工具

用法:
    python3 decode.py <密钥> [加密方式]

例:
    python3 decode.py "your_secret_key"
    python3 decode.py "your_secret_key" aes-256-cbc

需要安装: pip install pycryptodome requests
"""

import sys
import base64
import hashlib
import os

try:
    from Crypto.Cipher import AES, Blowfish, DES3, ARC4
    from Crypto.Util.Padding import unpad
except ImportError:
    print("请先安装 pycryptodome: pip install pycryptodome", file=sys.stderr)
    sys.exit(1)

URL = "https://www.3tyx.top/2/zhanshi_server_64.php"
LOCAL_CACHE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "encrypted_data.txt")


def fetch_data():
    try:
        import requests
        resp = requests.get(URL, timeout=10)
        resp.raise_for_status()
        return resp.text.strip()
    except Exception:
        pass
    try:
        import urllib.request
        resp = urllib.request.urlopen(URL, timeout=10)
        return resp.read().decode().strip()
    except Exception:
        pass
    if os.path.exists(LOCAL_CACHE):
        print("使用本地缓存文件", file=sys.stderr)
        return open(LOCAL_CACHE).read().strip()
    return None


def try_decrypt_aes(decoded, key_bytes, mode, iv=None):
    try:
        if mode == "cbc":
            cipher = AES.new(key_bytes, AES.MODE_CBC, iv)
        elif mode == "ecb":
            cipher = AES.new(key_bytes, AES.MODE_ECB)
        else:
            return None
        result = cipher.decrypt(decoded)
        try:
            result = unpad(result, AES.block_size)
        except ValueError:
            pass
        return result
    except Exception:
        return None


def is_readable(data, threshold=0.5):
    if not data or len(data) < 10:
        return False
    sample = data[:min(200, len(data))]
    printable = sum(1 for b in sample if 32 <= b < 127 or b in (10, 13, 9))
    return (printable / len(sample)) > threshold


def main():
    if len(sys.argv) < 2:
        print("用法: python3 decode.py <密钥> [加密方式]", file=sys.stderr)
        print("示例: python3 decode.py \"my_secret_key\"", file=sys.stderr)
        sys.exit(1)

    key = sys.argv[1]
    specified_method = sys.argv[2].lower() if len(sys.argv) > 2 else None

    print("正在获取加密数据...", file=sys.stderr)
    raw = fetch_data()
    if not raw:
        print("错误: 无法获取数据", file=sys.stderr)
        sys.exit(1)

    decoded = base64.b64decode(raw)
    print(f"Base64 解码后数据长度: {len(decoded)} 字节", file=sys.stderr)

    key_variants = {
        "raw": key.encode("utf-8"),
        "md5_raw": hashlib.md5(key.encode()).digest(),
        "md5_hex": hashlib.md5(key.encode()).hexdigest().encode(),
        "sha256_raw_16": hashlib.sha256(key.encode()).digest()[:16],
        "sha256_raw_32": hashlib.sha256(key.encode()).digest(),
        "sha256_hex": hashlib.sha256(key.encode()).hexdigest().encode(),
    }

    # Also try if key itself is hex
    try:
        key_variants["hex_decoded"] = bytes.fromhex(key)
    except ValueError:
        pass

    methods = (
        [specified_method] if specified_method
        else ["aes-128-cbc", "aes-256-cbc", "aes-128-ecb", "aes-256-ecb", "rc4", "bf-cbc"]
    )

    for method in methods:
        for var_name, key_bytes in key_variants.items():
            result = None

            if method == "aes-128-cbc" and len(key_bytes) >= 16:
                k = key_bytes[:16]
                if len(decoded) > 16 and len(decoded[16:]) % 16 == 0:
                    iv = decoded[:16]
                    result = try_decrypt_aes(decoded[16:], k, "cbc", iv)
                    if result and is_readable(result):
                        print(f"解密成功! 方式={method}, 密钥变体={var_name}, IV=数据前16字节", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return
                # zero IV
                if len(decoded) % 16 == 0:
                    result = try_decrypt_aes(decoded, k, "cbc", b'\x00' * 16)
                    if result and is_readable(result):
                        print(f"解密成功! 方式={method}, 密钥变体={var_name}, IV=全零", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return

            elif method == "aes-256-cbc" and len(key_bytes) >= 32:
                k = key_bytes[:32]
                if len(decoded) > 16 and len(decoded[16:]) % 16 == 0:
                    iv = decoded[:16]
                    result = try_decrypt_aes(decoded[16:], k, "cbc", iv)
                    if result and is_readable(result):
                        print(f"解密成功! 方式={method}, 密钥变体={var_name}, IV=数据前16字节", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return
                if len(decoded) % 16 == 0:
                    result = try_decrypt_aes(decoded, k, "cbc", b'\x00' * 16)
                    if result and is_readable(result):
                        print(f"解密成功! 方式={method}, 密钥变体={var_name}, IV=全零", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return

            elif method == "aes-128-ecb" and len(key_bytes) >= 16:
                k = key_bytes[:16]
                if len(decoded) % 16 == 0:
                    result = try_decrypt_aes(decoded, k, "ecb")
                    if result and is_readable(result):
                        print(f"解密成功! 方式={method}, 密钥变体={var_name}", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return

            elif method == "aes-256-ecb" and len(key_bytes) >= 32:
                k = key_bytes[:32]
                if len(decoded) % 16 == 0:
                    result = try_decrypt_aes(decoded, k, "ecb")
                    if result and is_readable(result):
                        print(f"解密成功! 方式={method}, 密钥变体={var_name}", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return

            elif method == "rc4":
                try:
                    cipher = ARC4.new(key_bytes)
                    result = cipher.decrypt(decoded)
                    if is_readable(result):
                        print(f"解密成功! 方式=RC4, 密钥变体={var_name}", file=sys.stderr)
                        sys.stdout.buffer.write(result)
                        return
                except Exception:
                    pass

            elif method == "bf-cbc" and 4 <= len(key_bytes) <= 56:
                if len(decoded) > 8 and len(decoded[8:]) % 8 == 0:
                    try:
                        iv = decoded[:8]
                        cipher = Blowfish.new(key_bytes, Blowfish.MODE_CBC, iv)
                        result = cipher.decrypt(decoded[8:])
                        if is_readable(result):
                            print(f"解密成功! 方式=Blowfish-CBC, 密钥变体={var_name}", file=sys.stderr)
                            sys.stdout.buffer.write(result)
                            return
                    except Exception:
                        pass

    # Last resort: try decompression
    import zlib, gzip
    for name, fn in [("gzinflate", lambda d: zlib.decompress(d, -15)),
                     ("gzuncompress", zlib.decompress),
                     ("gzdecode", gzip.decompress)]:
        try:
            result = fn(decoded)
            print(f"{name} 解压成功!", file=sys.stderr)
            sys.stdout.buffer.write(result)
            return
        except Exception:
            pass

    print("\n所有方式均未成功解密。", file=sys.stderr)
    print("请检查密钥是否正确，或指定正确的加密方式。", file=sys.stderr)
    print(f"数据特征:", file=sys.stderr)
    print(f"  - 长度: {len(decoded)} 字节 (16字节对齐)", file=sys.stderr)
    print(f"  - 前8字节 ASCII: {decoded[:8]}", file=sys.stderr)
    print(f"  - 前16字节 HEX: {decoded[:16].hex()}", file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
