<?php

namespace App\Middleware;

class RateLimitMiddleware
{
    private static int $maxRequests = 60;
    private static int $windowSeconds = 60;

    /**
     * Verifica o rate limit para o IP atual.
     * Retorna true se permitido, ou envia 429 e encerra se excedido.
     */
    public static function check(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        // Limpar entradas antigas periodicamente (1% de chance por request)
        if (mt_rand(1, 100) === 1) {
            self::cleanup($cacheDir);
        }

        $file = $cacheDir . '/' . md5($ip) . '.json';
        $now = time();
        $windowStart = $now - self::$windowSeconds;

        $requests = [];
        if (file_exists($file)) {
            $data = @json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                // Manter apenas requests dentro da janela
                $requests = array_filter($data, fn($ts) => $ts > $windowStart);
            }
        }

        if (count($requests) >= self::$maxRequests) {
            $retryAfter = self::$windowSeconds - ($now - min($requests));
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . max(1, $retryAfter));
            echo json_encode([
                'error'   => 'Too Many Requests',
                'message' => 'Limite de requisicoes excedido. Tente novamente em alguns segundos.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Registrar request atual
        $requests[] = $now;
        file_put_contents($file, json_encode(array_values($requests)), LOCK_EX);
    }

    /**
     * Remove arquivos de rate limit mais antigos que a janela de tempo.
     */
    private static function cleanup(string $cacheDir): void
    {
        $expiry = time() - (self::$windowSeconds * 2);
        $files = glob($cacheDir . '/*.json');
        if (!$files) return;

        foreach ($files as $file) {
            if (filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }
}
