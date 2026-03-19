<?php

namespace App\Models;

use App\Core\Model;

class Documento extends Model
{
    protected string $table = 'documentos';

    public function findByColaborador(int $colaboradorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, td.nome as tipo_nome, td.categoria, u.nome as enviado_por_nome
             FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN usuarios u ON d.enviado_por = u.id
             WHERE d.colaborador_id = :cid AND d.status != 'obsoleto'
             ORDER BY td.categoria, td.nome, d.data_emissao DESC"
        );
        $stmt->execute(['cid' => $colaboradorId]);
        return $stmt->fetchAll();
    }

    public function countByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as total FROM documentos WHERE status != 'obsoleto' GROUP BY status"
        );
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    public function getExpiring(int $days = 30, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, c.nome_completo, td.nome as tipo_nome,
                    DATEDIFF(d.data_validade, CURDATE()) as dias_restantes
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.status IN ('vigente','proximo_vencimento')
               AND d.data_validade IS NOT NULL
               AND d.data_validade <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
               AND d.data_validade >= CURDATE()
             ORDER BY d.data_validade ASC
             LIMIT :lim"
        );
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getExpired(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, c.nome_completo, td.nome as tipo_nome,
                    DATEDIFF(CURDATE(), d.data_validade) as dias_vencido
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.data_validade IS NOT NULL
               AND d.data_validade < CURDATE()
               AND d.status != 'obsoleto'
             ORDER BY d.data_validade ASC
             LIMIT :lim"
        );
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markAsObsolete(int $colaboradorId, int $tipoDocumentoId): void
    {
        $this->db->prepare(
            "UPDATE documentos SET status = 'obsoleto', atualizado_em = NOW()
             WHERE colaborador_id = :cid AND tipo_documento_id = :tid AND status != 'obsoleto'"
        )->execute(['cid' => $colaboradorId, 'tid' => $tipoDocumentoId]);
    }
}
