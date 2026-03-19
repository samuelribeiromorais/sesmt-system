<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Session;

class LoggerMiddleware
{
    public static function log(string $acao, string $descricao = ''): void
    {
        $user = Session::user();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "INSERT INTO logs_acesso (usuario_id, acao, descricao, ip_address, user_agent, criado_em)
             VALUES (:usuario_id, :acao, :descricao, :ip, :ua, NOW())"
        );

        $stmt->execute([
            'usuario_id' => $user['id'] ?? null,
            'acao'       => $acao,
            'descricao'  => $descricao,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua'         => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }
}
