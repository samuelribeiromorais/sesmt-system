<?php

namespace App\Services;

class TotpService
{
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ALGORITHM = 'sha1';
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Gera um segredo aleatório em Base32 (160 bits = 32 caracteres).
     */
    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[ord($bytes[$i]) % 32];
        }
        return $secret;
    }

    /**
     * Retorna o código TOTP atual para o segredo fornecido.
     */
    public function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = intdiv($timestamp, self::PERIOD);

        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $key = $this->base32Decode($secret);

        $hash = hash_hmac(self::ALGORITHM, $timeBytes, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary =
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % (10 ** self::DIGITS);

        return str_pad((string)$otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica o código TOTP com tolerância de ±1 janela (30s antes/depois).
     */
    public function verify(string $secret, string $code): bool
    {
        $now = time();

        for ($window = -1; $window <= 1; $window++) {
            $checkTime = $now + ($window * self::PERIOD);
            $expected = $this->getCode($secret, $checkTime);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna a URI otpauth:// para geração de QR Code.
     */
    public function getQrUrl(string $email, string $secret): string
    {
        $issuer = 'SESMT-TSE';
        $label = rawurlencode($issuer . ':' . $email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Decodifica string Base32 para binário.
     */
    private function base32Decode(string $input): string
    {
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos(self::BASE32_CHARS, $input[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
