#!/usr/bin/env python3
"""Static inspector for the zhanshi_server_64 payload.

This script intentionally stops at structural analysis:
- fetch the remote response
- verify whether it is base64
- decode one layer
- report entropy, printability, and block-alignment hints
- test common compression formats

It does not attempt intrusive execution or runtime loading.
"""

from __future__ import annotations

import argparse
import base64
import bz2
import gzip
import json
import lzma
import math
import pathlib
import re
import urllib.request
import zlib
from collections import Counter


DEFAULT_URL = "https://www.3tyx.top/2/zhanshi_server_64.php"


def shannon_entropy(data: bytes) -> float:
    if not data:
        return 0.0
    counts = Counter(data)
    size = len(data)
    return -sum((count / size) * math.log2(count / size) for count in counts.values())


def printable_ratio(data: bytes) -> float:
    if not data:
        return 0.0
    printable = sum(32 <= byte < 127 or byte in (9, 10, 13) for byte in data)
    return printable / len(data)


def try_compression(data: bytes) -> dict[str, str]:
    attempts = {
        "zlib": lambda raw: zlib.decompress(raw),
        "zlib_raw": lambda raw: zlib.decompress(raw, -15),
        "gzip": gzip.decompress,
        "bz2": bz2.decompress,
        "lzma": lzma.decompress,
    }
    results: dict[str, str] = {}
    for name, fn in attempts.items():
        try:
            out = fn(data)
            preview = repr(out[:80])
            results[name] = f"ok: {len(out)} bytes, preview={preview}"
        except Exception as exc:  # noqa: BLE001 - keep raw failure text for analysts
            results[name] = f"fail: {exc}"
    return results


def fetch_payload(url: str) -> tuple[bytes, dict[str, str]]:
    request = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(request, timeout=30) as response:
        body = response.read()
        headers = {key.lower(): value for key, value in response.headers.items()}
    return body, headers


def analyze_blob(encoded_text: str) -> dict[str, object]:
    is_base64 = bool(re.fullmatch(r"[A-Za-z0-9+/=\r\n]+", encoded_text))
    decoded = base64.b64decode(encoded_text) if is_base64 else b""
    blocks = [decoded[index:index + 16] for index in range(0, len(decoded), 16)] if decoded else []
    block_counts = Counter(blocks)

    return {
        "encoded_length": len(encoded_text),
        "looks_like_base64": is_base64,
        "decoded_length": len(decoded),
        "decoded_prefix_hex": decoded[:32].hex(),
        "decoded_prefix_repr": repr(decoded[:64]),
        "decoded_entropy": round(shannon_entropy(decoded), 4),
        "decoded_printable_ratio": round(printable_ratio(decoded), 4),
        "multiple_of_16": bool(decoded) and len(decoded) % 16 == 0,
        "block_count_16": len(blocks),
        "duplicate_16_byte_blocks": sum(count - 1 for count in block_counts.values() if count > 1),
        "compression_checks": try_compression(decoded) if decoded else {},
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Inspect the zhanshi_server_64 payload")
    parser.add_argument("--url", default=DEFAULT_URL, help="Remote payload URL")
    parser.add_argument(
        "--save-dir",
        default="analysis/output",
        help="Directory for raw and decoded payload snapshots",
    )
    args = parser.parse_args()

    save_dir = pathlib.Path(args.save_dir)
    save_dir.mkdir(parents=True, exist_ok=True)

    body, headers = fetch_payload(args.url)
    encoded_text = body.decode("utf-8", errors="replace").strip()
    report = analyze_blob(encoded_text)
    report["url"] = args.url
    report["response_headers"] = headers

    (save_dir / "zhanshi_server_64.txt").write_text(encoded_text, encoding="utf-8")
    if report["looks_like_base64"]:
        decoded = base64.b64decode(encoded_text)
        (save_dir / "zhanshi_server_64.decoded.bin").write_bytes(decoded)

    print(json.dumps(report, ensure_ascii=True, indent=2, sort_keys=True))


if __name__ == "__main__":
    main()
