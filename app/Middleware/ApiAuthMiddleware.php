<?php

namespace App\Middleware;

use App\Models\ApiToken;
use App\Middleware\RateLimitMiddleware;

class ApiAuthMiddleware
{
    private static ?array $authenticatedUser = null;
    private static ?int $tokenId = null;

    public static function check(): void
    {
        // Verificar rate limit antes da autenticacao
        RateLimitMiddleware::check();

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            self::unauthorized('Token de autenticacao nao fornecido.');
        }

        $token = trim($matches[1]);
        $model = new ApiToken();
        $record = $model->findByToken($token);

        if (!$record) {
            self::unauthorized('Token invalido ou desativado.');
        }

        $model->updateLastUse((int) $record['id']);

        self::$tokenId = (int) $record['id'];
        self::$authenticatedUser = [
            'id'     => (int) $record['usuario_id'],
            'nome'   => $record['usuario_nome'],
            'email'  => $record['usuario_email'],
            'perfil' => $record['usuario_perfil'],
        ];
    }

    public static function user(): ?array
    {
        return self::$authenticatedUser;
    }

    public static function tokenId(): ?int
    {
        return self::$tokenId;
    }

    private static function unauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'   => 'Unauthorized',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
