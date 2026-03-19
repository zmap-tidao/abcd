# `zhanshi_server_64.php` static analysis

Target URL: `https://www.3tyx.top/2/zhanshi_server_64.php`

## What the endpoint returns

- HTTP status: `200`
- `Content-Type`: `text/plain;charset=UTF-8`
- `Content-Disposition`: `inline; filename="encrypted_data.txt"`

That header strongly suggests the response is intended to be treated as encrypted data, not as directly readable PHP source.

## Stable findings

The response body is a single Base64-looking blob.

After one Base64 decode:

- Encoded length: `6040`
- Decoded length: `4528`
- Decoded length is an exact multiple of 16 bytes
- Shannon entropy: about `7.9581`
- Printable byte ratio: about `0.3898`
- Duplicate 16-byte blocks: `0`

These properties are consistent with a binary ciphertext or another high-entropy packed payload. They are **not** consistent with plain PHP source code, and they do not match common one-layer `gzip` / `zlib` / `bz2` / `lzma` wrappers.

## What was ruled out

- Plain Base64-wrapped PHP
- Plain `gzip` / `zlib` / `bz2` / `lzma` compressed text
- Simple nearby-file discovery such as `zhanshi_server.php` or `.txt` variants on the same host
- Common weak-key guesses derived from the domain, file name, and similar obvious strings

## Practical conclusion

From this URL alone, the payload can be unpacked only one layer:

1. fetch the response
2. Base64-decode it
3. obtain a 4528-byte high-entropy binary blob

There is **not enough information in the response itself** to recover the original PHP source with confidence.

To fully decode it, at least one of the following is still needed:

- the original PHP file before server-side execution
- the loader/decryptor script that consumes this blob
- the encryption key and IV (or the derivation logic)
- another endpoint or sample from the same deployment that performs the decryption step

## Reproducible tooling

Use the helper script in this repository:

```bash
python3 analysis/inspect_zhanshi_server_64.py
```

It saves the fetched payload under `analysis/output/` and prints a JSON summary of the structure and decoding checks.
