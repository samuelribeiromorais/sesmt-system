<?php

namespace App\Models;

use App\Core\Model;

class SessaoAtiva extends Model
{
    protected string $table = 'sessoes_ativas';

    /**
     * Busca todas as sessões ativas de um usuário.
     */
    public function findByUsuario(int $usuarioId): array
    {
        return $this->query(
            "SELECT * FROM sessoes_ativas WHERE usuario_id = :uid ORDER BY ultimo_acesso DESC",
            ['uid' => $usuarioId]
        );
    }

    /**
     * Busca sessão por session_id do PHP.
     */
    public function findBySessionId(string $sessionId): ?array
    {
        $results = $this->query(
            "SELECT * FROM sessoes_ativas WHERE session_id = :sid LIMIT 1",
            ['sid' => $sessionId]
        );
        return $results[0] ?? null;
    }

    /**
     * Registra uma nova sessão ativa.
     */
    public function registrar(int $usuarioId, string $sessionId): int
    {
        return $this->create([
            'usuario_id'    => $usuarioId,
            'session_id'    => $sessionId,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'ultimo_acesso' => date('Y-m-d H:i:s'),
            'criado_em'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Atualiza o timestamp de último acesso.
     */
    public function atualizarAcesso(string $sessionId): void
    {
        $this->execute(
            "UPDATE sessoes_ativas SET ultimo_acesso = NOW() WHERE session_id = :sid",
            ['sid' => $sessionId]
        );
    }

    /**
     * Remove sessão pelo session_id.
     */
    public function removerPorSessionId(string $sessionId): void
    {
        $this->execute(
            "DELETE FROM sessoes_ativas WHERE session_id = :sid",
            ['sid' => $sessionId]
        );
    }

    /**
     * Remove sessão pelo ID do registro.
     */
    public function removerPorId(int $id): void
    {
        $this->delete($id);
    }
}
