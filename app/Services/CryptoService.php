<?php

namespace App\Services;

class CryptoService
{
    private static string $cipher = 'aes-256-cbc';

    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($data, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data): string
    {
        $key = self::getKey();
        $raw = base64_decode($data);
        $ivLen = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($raw, 0, $ivLen);
        $encrypted = substr($raw, $ivLen);
        return openssl_decrypt($encrypted, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
    }

    public static function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    private static function getKey(): string
    {
        $key = $_ENV['AES_KEY'] ?? '';
        if (empty($key)) {
            throw new \RuntimeException('AES_KEY não configurada no .env');
        }
        return hash('sha256', $key, true);
    }
}
