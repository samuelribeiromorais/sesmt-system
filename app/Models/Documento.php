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
             WHERE d.colaborador_id = :cid AND d.status != 'obsoleto' AND d.excluido_em IS NULL
             ORDER BY td.categoria, td.nome, d.data_emissao DESC"
        );
        $stmt->execute(['cid' => $colaboradorId]);
        return $stmt->fetchAll();
    }

    public function countByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as total FROM documentos WHERE status != 'obsoleto' AND excluido_em IS NULL GROUP BY status"
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
               AND d.excluido_em IS NULL
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
               AND d.excluido_em IS NULL
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

    /**
     * Find all versions of a document chain.
     */
    public function findVersions(int $documentoId): array
    {
        // First, find the root document (documento_pai_id)
        $doc = $this->find($documentoId);
        if (!$doc) {
            return [];
        }

        $rootId = $doc['documento_pai_id'] ?? $doc['id'];

        $stmt = $this->db->prepare(
            "SELECT d.*, u.nome as enviado_por_nome
             FROM documentos d
             LEFT JOIN usuarios u ON d.enviado_por = u.id
             WHERE (d.id = :rootId OR d.documento_pai_id = :rootId2)
             ORDER BY d.versao ASC"
        );
        $stmt->execute(['rootId' => $rootId, 'rootId2' => $rootId]);
        return $stmt->fetchAll();
    }

    /**
     * Get the latest version number for a document chain.
     */
    public function getLatestVersion(int $documentoPaiId): int
    {
        $stmt = $this->db->prepare(
            "SELECT MAX(versao) as max_versao FROM documentos
             WHERE id = :pid OR documento_pai_id = :pid2"
        );
        $stmt->execute(['pid' => $documentoPaiId, 'pid2' => $documentoPaiId]);
        $result = $stmt->fetch();
        return (int) ($result['max_versao'] ?? 1);
    }

    /**
     * Find the original (root) document for a collaborator and document type.
     */
    public function findOriginal(int $colaboradorId, int $tipoDocumentoId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM documentos
             WHERE colaborador_id = :cid AND tipo_documento_id = :tid
               AND documento_pai_id IS NULL AND status != 'obsoleto' AND excluido_em IS NULL
             ORDER BY criado_em ASC LIMIT 1"
        );
        $stmt->execute(['cid' => $colaboradorId, 'tid' => $tipoDocumentoId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all soft-deleted documents.
     */
    public function getDeleted(): array
    {
        $stmt = $this->db->query(
            "SELECT d.*, c.nome_completo, td.nome as tipo_nome
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.excluido_em IS NOT NULL
             ORDER BY d.excluido_em DESC"
        );
        return $stmt->fetchAll();
    }
}
