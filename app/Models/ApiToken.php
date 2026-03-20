<?php

namespace App\Models;

use App\Core\Model;

class ApiToken extends Model
{
    protected string $table = 'api_tokens';

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, u.nome as usuario_nome, u.email as usuario_email, u.perfil as usuario_perfil
             FROM api_tokens t
             JOIN usuarios u ON t.usuario_id = u.id
             WHERE t.token = :token AND t.ativo = 1
             LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function updateLastUse(int $id): void
    {
        $this->execute(
            "UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = :id",
            ['id' => $id]
        );
    }

    public function findByUsuario(int $usuarioId): array
    {
        return $this->all(['usuario_id' => $usuarioId], 'criado_em DESC');
    }
}
