<?php
/**
 * 传世展示页 (zhanshi_server_64.php) 解码工具
 *
 * 用法:
 *   php decode.php <密钥> [加密方式]
 *
 * 例:
 *   php decode.php "your_secret_key"
 *   php decode.php "your_secret_key" aes-256-cbc
 *   php decode.php "your_secret_key" aes-128-cbc
 *
 * 支持的加密方式 (默认尝试全部):
 *   aes-128-cbc, aes-256-cbc, aes-128-ecb, aes-256-ecb
 */

$url = 'https://www.3tyx.top/2/zhanshi_server_64.php';

if ($argc < 2) {
    fwrite(STDERR, "用法: php decode.php <密钥> [加密方式]\n");
    fwrite(STDERR, "示例: php decode.php \"my_secret_key\"\n");
    fwrite(STDERR, "      php decode.php \"my_secret_key\" aes-256-cbc\n\n");
    fwrite(STDERR, "支持的加密方式: aes-128-cbc, aes-256-cbc, aes-128-ecb, aes-256-ecb\n");
    fwrite(STDERR, "不指定加密方式则自动尝试所有常见方式。\n");
    exit(1);
}

$key = $argv[1];
$specifiedMethod = isset($argv[2]) ? strtolower(trim($argv[2])) : null;

fwrite(STDERR, "正在获取加密数据...\n");

$raw = @file_get_contents($url);
if ($raw === false) {
    $localFile = __DIR__ . '/encrypted_data.txt';
    if (file_exists($localFile)) {
        $raw = file_get_contents($localFile);
        fwrite(STDERR, "使用本地缓存文件 encrypted_data.txt\n");
    } else {
        fwrite(STDERR, "错误: 无法获取数据\n");
        exit(1);
    }
}

$raw = trim($raw);
$decoded = base64_decode($raw, true);
if ($decoded === false) {
    fwrite(STDERR, "错误: Base64 解码失败\n");
    exit(1);
}

fwrite(STDERR, "Base64 解码后数据长度: " . strlen($decoded) . " 字节\n");

$methods = $specifiedMethod
    ? [$specifiedMethod]
    : ['aes-128-cbc', 'aes-256-cbc', 'aes-128-ecb', 'aes-256-ecb',
       'aes-128-cfb', 'aes-256-cfb', 'aes-128-ofb', 'aes-256-ofb',
       'bf-cbc', 'bf-ecb', 'des-ede3-cbc', 'des-ede3-ecb', 'rc4'];

$keyVariants = [
    'raw'          => $key,
    'md5_hex'      => md5($key),
    'md5_raw'      => md5($key, true),
    'sha256_hex'   => hash('sha256', $key),
    'sha256_raw'   => hash('sha256', $key, true),
];

$success = false;

foreach ($methods as $method) {
    if (!in_array($method, openssl_get_cipher_methods(true))) {
        continue;
    }

    $ivLen = openssl_cipher_iv_length($method);

    foreach ($keyVariants as $varName => $keyVal) {

        // Strategy A: IV prepended to ciphertext
        if ($ivLen > 0 && strlen($decoded) > $ivLen) {
            $iv = substr($decoded, 0, $ivLen);
            $ct = substr($decoded, $ivLen);

            $result = @openssl_decrypt($ct, $method, $keyVal, OPENSSL_RAW_DATA, $iv);
            if ($result !== false && strlen($result) > 10) {
                fwrite(STDERR, "解密成功! 方式=$method, 密钥变体=$varName, IV=数据前{$ivLen}字节\n");
                echo $result;
                $success = true;
                break 2;
            }

            // Try with base64 input (openssl default)
            $result = @openssl_decrypt(base64_encode($ct), $method, $keyVal, 0, $iv);
            if ($result !== false && strlen($result) > 10) {
                fwrite(STDERR, "解密成功! 方式=$method, 密钥变体=$varName (base64模式)\n");
                echo $result;
                $success = true;
                break 2;
            }
        }

        // Strategy B: zero IV
        if ($ivLen > 0) {
            $iv = str_repeat("\0", $ivLen);
            $result = @openssl_decrypt($decoded, $method, $keyVal, OPENSSL_RAW_DATA, $iv);
            if ($result !== false && strlen($result) > 10) {
                fwrite(STDERR, "解密成功! 方式=$method, 密钥变体=$varName, IV=全零\n");
                echo $result;
                $success = true;
                break 2;
            }

            $result = @openssl_decrypt($raw, $method, $keyVal, 0, $iv);
            if ($result !== false && strlen($result) > 10) {
                fwrite(STDERR, "解密成功! 方式=$method, 密钥变体=$varName (base64输入, 零IV)\n");
                echo $result;
                $success = true;
                break 2;
            }
        }

        // Strategy C: ECB / stream ciphers (no IV)
        if ($ivLen === 0) {
            $result = @openssl_decrypt($decoded, $method, $keyVal, OPENSSL_RAW_DATA);
            if ($result !== false && strlen($result) > 10) {
                fwrite(STDERR, "解密成功! 方式=$method, 密钥变体=$varName (无IV)\n");
                echo $result;
                $success = true;
                break 2;
            }

            $result = @openssl_decrypt($raw, $method, $keyVal, 0);
            if ($result !== false && strlen($result) > 10) {
                fwrite(STDERR, "解密成功! 方式=$method, 密钥变体=$varName (base64输入)\n");
                echo $result;
                $success = true;
                break 2;
            }
        }
    }
}

if (!$success) {
    // Try gzinflate / gzuncompress on the decoded data
    $gz = @gzinflate($decoded);
    if ($gz !== false) {
        fwrite(STDERR, "gzinflate 解压成功!\n");
        echo $gz;
        exit(0);
    }

    $gz = @gzuncompress($decoded);
    if ($gz !== false) {
        fwrite(STDERR, "gzuncompress 解压成功!\n");
        echo $gz;
        exit(0);
    }

    $gz = @gzdecode($decoded);
    if ($gz !== false) {
        fwrite(STDERR, "gzdecode 解压成功!\n");
        echo $gz;
        exit(0);
    }

    fwrite(STDERR, "\n所有方式均未成功解密。\n");
    fwrite(STDERR, "请检查密钥是否正确，或指定正确的加密方式。\n");
    fwrite(STDERR, "数据特征:\n");
    fwrite(STDERR, "  - 长度: " . strlen($decoded) . " 字节 (16字节对齐)\n");
    fwrite(STDERR, "  - 前8字节 ASCII: " . substr($decoded, 0, 8) . "\n");
    fwrite(STDERR, "  - 前16字节 HEX: " . bin2hex(substr($decoded, 0, 16)) . "\n");
    exit(1);
}
