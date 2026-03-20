<?php

namespace App\Models;

use App\Core\Model;

class LogAcesso extends Model
{
    protected string $table = 'logs_acesso';

    public function search(string $acao = '', int $usuarioId = 0, string $dataInicio = '', string $dataFim = '', int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT l.*, u.nome as usuario_nome, u.email as usuario_email
                FROM logs_acesso l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                WHERE 1=1";
        $params = [];

        if ($acao) {
            $sql .= " AND l.acao = :acao";
            $params['acao'] = $acao;
        }
        if ($usuarioId) {
            $sql .= " AND l.usuario_id = :uid";
            $params['uid'] = $usuarioId;
        }
        if ($dataInicio) {
            $sql .= " AND l.criado_em >= :di";
            $params['di'] = $dataInicio . ' 00:00:00';
        }
        if ($dataFim) {
            $sql .= " AND l.criado_em <= :df";
            $params['df'] = $dataFim . ' 23:59:59';
        }

        $limit = (int)$limit;
        $offset = (int)$offset;
        $sql .= " ORDER BY l.criado_em DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
