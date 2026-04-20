<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Session;

class LoggerMiddleware
{
    /**
     * Log an action to the database with structured context.
     *
     * @param string $acao Action category (criar, editar, excluir, login, download, etc.)
     * @param string $descricao Human-readable description
     * @param array  $context Optional structured context (entity_type, entity_id, changes, etc.)
     */
    public static function log(string $acao, string $descricao = '', array $context = []): void
    {
        $user = Session::user();
        $db = Database::getInstance();

        // Build structured context
        $contextData = array_merge([
            'timestamp'  => date('c'),
            'request_id' => self::getRequestId(),
            'method'     => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri'        => $_SERVER['REQUEST_URI'] ?? '',
            'referer'    => $_SERVER['HTTP_REFERER'] ?? '',
        ], $context);

        try {
            $stmt = $db->prepare(
                "INSERT INTO logs_acesso (usuario_id, acao, descricao, ip_address, user_agent, contexto, criado_em)
                 VALUES (:usuario_id, :acao, :descricao, :ip, :ua, :contexto, NOW())"
            );

            $stmt->execute([
                'usuario_id' => $user['id'] ?? null,
                'acao'       => $acao,
                'descricao'  => $descricao,
                'ip'         => self::getClientIp(),
                'ua'         => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'contexto'   => !empty($context) ? json_encode($contextData, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('LoggerMiddleware DB error: ' . $e->getMessage());
        }

        // Also write to file log for CLI/cron access
        self::writeFileLog($acao, $descricao, $contextData, $user);
    }

    /**
     * Get client IP, handling proxies.
     */
    private static function getClientIp(): string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Generate a unique request ID for correlating log entries.
     */
    private static function getRequestId(): string
    {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = substr(bin2hex(random_bytes(8)), 0, 16);
        }
        return $requestId;
    }

    /**
     * Write structured log to file.
     */
    private static function writeFileLog(string $acao, string $descricao, array $context, ?array $user): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
        $entry = json_encode([
            'level'       => self::actionToLevel($acao),
            'action'      => $acao,
            'message'     => $descricao,
            'user_id'     => $user['id'] ?? null,
            'user_name'   => $user['nome'] ?? null,
            'ip'          => self::getClientIp(),
            'request_id'  => $context['request_id'] ?? '',
            'method'      => $context['method'] ?? '',
            'uri'         => $context['uri'] ?? '',
            'timestamp'   => $context['timestamp'] ?? date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Map action types to log levels.
     */
    private static function actionToLevel(string $acao): string
    {
        return match ($acao) {
            'login_falha', 'bloqueio' => 'warning',
            'excluir', 'excluir_permanente' => 'notice',
            'erro', 'falha' => 'error',
            default => 'info',
        };
    }
}
