<?php

namespace App\Models;

use App\Core\Model;

class Notificacao extends Model
{
    protected string $table = 'notificacoes';

    public function getUnread(int $usuarioId, int $limit = 20): array
    {
        $limit = (int)$limit;
        return $this->query(
            "SELECT * FROM {$this->table} WHERE usuario_id = :uid AND lida = 0 ORDER BY criado_em DESC LIMIT {$limit}",
            ['uid' => $usuarioId]
        );
    }

    public function countUnread(int $usuarioId): int
    {
        $result = $this->query(
            "SELECT COUNT(*) as total FROM {$this->table} WHERE usuario_id = :uid AND lida = 0",
            ['uid' => $usuarioId]
        );
        return (int)($result[0]['total'] ?? 0);
    }

    public function markAsRead(int $id): void
    {
        $this->execute(
            "UPDATE {$this->table} SET lida = 1 WHERE id = :id",
            ['id' => $id]
        );
    }

    public function markAllRead(int $usuarioId): void
    {
        $this->execute(
            "UPDATE {$this->table} SET lida = 1 WHERE usuario_id = :uid AND lida = 0",
            ['uid' => $usuarioId]
        );
    }

    public function criar(int $usuarioId, string $tipo, string $titulo, string $mensagem, ?string $link = null): int
    {
        return $this->create([
            'usuario_id' => $usuarioId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensagem'   => $mensagem,
            'lida'       => 0,
            'link'       => $link,
            'criado_em'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function getAllForUser(int $usuarioId, int $limit = 50, int $offset = 0): array
    {
        $limit = (int)$limit;
        $offset = (int)$offset;
        return $this->query(
            "SELECT * FROM {$this->table} WHERE usuario_id = :uid ORDER BY criado_em DESC LIMIT {$limit} OFFSET {$offset}",
            ['uid' => $usuarioId]
        );
    }

    public function countForUser(int $usuarioId): int
    {
        $result = $this->query(
            "SELECT COUNT(*) as total FROM {$this->table} WHERE usuario_id = :uid",
            ['uid' => $usuarioId]
        );
        return (int)($result[0]['total'] ?? 0);
    }
}
