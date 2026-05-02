<?php

namespace App\Models;

use App\Core\Model;

class Certificado extends Model
{
    protected string $table = 'certificados';

    public function findByColaborador(int $colaboradorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cert.*, tc.codigo, tc.titulo, tc.duracao, tc.validade_meses,
                    tc.tem_anuencia, tc.tem_diego, tc.tem_diego_responsavel, tc.conteudo_no_verso, tc.conteudo_programatico,
                    tc.ministrante_id as tipo_ministrante_id,
                    COALESCE(m.nome, mt.nome) as ministrante_nome,
                    COALESCE(m.cargo_titulo, mt.cargo_titulo) as ministrante_cargo,
                    COALESCE(m.registro, mt.registro) as ministrante_registro
             FROM certificados cert
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             LEFT JOIN ministrantes m ON cert.ministrante_id = m.id
             LEFT JOIN ministrantes mt ON tc.ministrante_id = mt.id
             WHERE cert.colaborador_id = :cid
               AND cert.excluido_em IS NULL
             ORDER BY tc.codigo, cert.data_emissao DESC"
        );
        $stmt->execute(['cid' => $colaboradorId]);
        return $stmt->fetchAll();
    }

    public function getLatestByColaborador(int $colaboradorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cert.*, tc.codigo, tc.titulo, tc.duracao, tc.validade_meses
             FROM certificados cert
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE cert.colaborador_id = :cid
               AND cert.status IN ('vigente', 'proximo_vencimento')
               AND cert.excluido_em IS NULL
               AND cert.id = (
                   SELECT MAX(c2.id) FROM certificados c2
                   WHERE c2.colaborador_id = cert.colaborador_id
                     AND c2.tipo_certificado_id = cert.tipo_certificado_id
                     AND c2.status IN ('vigente', 'proximo_vencimento')
                     AND c2.excluido_em IS NULL
               )
             ORDER BY tc.codigo"
        );
        $stmt->execute(['cid' => $colaboradorId]);
        return $stmt->fetchAll();
    }

    public function countByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT cert.status, COUNT(*) as total FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             WHERE cert.excluido_em IS NULL
               AND c.status = 'ativo'
               AND cert.arquivo_assinado IS NOT NULL
             GROUP BY cert.status"
        );
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    public function countAllByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT cert.status, COUNT(*) as total FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             WHERE cert.excluido_em IS NULL AND c.status = 'ativo'
             GROUP BY cert.status"
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
            "SELECT cert.*, c.nome_completo, c.cliente_id, cl.nome_fantasia as cliente_nome,
                    tc.codigo, tc.titulo,
                    DATEDIFF(cert.data_validade, CURDATE()) as dias_restantes
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             LEFT JOIN clientes cl ON c.cliente_id = cl.id
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE cert.status IN ('vigente','proximo_vencimento')
               AND cert.data_validade <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
               AND cert.data_validade >= CURDATE()
               AND cert.excluido_em IS NULL
               AND c.status = 'ativo'
             ORDER BY cert.data_validade ASC
             LIMIT :lim"
        );
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get all soft-deleted certificates.
     */
    public function getDeleted(): array
    {
        $stmt = $this->db->query(
            "SELECT cert.*, c.nome_completo, tc.codigo, tc.titulo
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE cert.excluido_em IS NOT NULL
             ORDER BY cert.excluido_em DESC"
        );
        return $stmt->fetchAll();
    }
}
