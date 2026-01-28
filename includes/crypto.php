<?php
require_once __DIR__ . '/config.php';


function vm_get_encryption_key(): string
{
    $key = VISITOR_CRYPTO_KEY;
    $binary = base64_decode($key, true);
    if ($binary === false) {
        $binary = $key;
    }
    if (strlen($binary) < 32) {
        $binary = str_pad($binary, 32, "\0");
    }
    return substr($binary, 0, 32);
}

function vm_encrypt(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'AES-256-CBC', vm_get_encryption_key(), OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Failed to encrypt value.');
    }
    return base64_encode($iv . $ciphertext);
}


function vm_decrypt(?string $encoded): ?string
{
    if ($encoded === null || $encoded === '') {
        return null;
    }
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 17) {
        return null;
    }
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', vm_get_encryption_key(), OPENSSL_RAW_DATA, $iv);
    return $plaintext === false ? null : $plaintext;
}
