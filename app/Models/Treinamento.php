<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Treinamento extends Model
{
    protected string $table = 'treinamentos';

    public function allWithDetails(int $limit, int $offset, array $filters = []): array
    {
        $where = "1=1";
        $params = [];

        if (!empty($filters['tipo_certificado_id'])) {
            $where .= " AND t.tipo_certificado_id = :tipo";
            $params['tipo'] = $filters['tipo_certificado_id'];
        }

        if (!empty($filters['data_de'])) {
            $where .= " AND t.data_realizacao >= :data_de";
            $params['data_de'] = $filters['data_de'];
        }

        if (!empty($filters['data_ate'])) {
            $where .= " AND t.data_realizacao <= :data_ate";
            $params['data_ate'] = $filters['data_ate'];
        }

        if (!empty($filters['q'])) {
            $where .= " AND (tc.codigo LIKE :q OR tc.titulo LIKE :q2 OR m.nome LIKE :q3)";
            $params['q'] = "%{$filters['q']}%";
            $params['q2'] = "%{$filters['q']}%";
            $params['q3'] = "%{$filters['q']}%";
        }

        $sql = "SELECT t.*, tc.codigo as tipo_codigo, tc.titulo as tipo_titulo, tc.duracao,
                       m.nome as ministrante_nome, u.nome as criador_nome
                FROM treinamentos t
                JOIN tipos_certificado tc ON t.tipo_certificado_id = tc.id
                LEFT JOIN ministrantes m ON t.ministrante_id = m.id
                LEFT JOIN usuarios u ON t.criado_por = u.id
                WHERE {$where}
                ORDER BY t.criado_em DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countFiltered(array $filters = []): int
    {
        $where = "1=1";
        $params = [];

        if (!empty($filters['tipo_certificado_id'])) {
            $where .= " AND t.tipo_certificado_id = :tipo";
            $params['tipo'] = $filters['tipo_certificado_id'];
        }

        if (!empty($filters['data_de'])) {
            $where .= " AND t.data_realizacao >= :data_de";
            $params['data_de'] = $filters['data_de'];
        }

        if (!empty($filters['data_ate'])) {
            $where .= " AND t.data_realizacao <= :data_ate";
            $params['data_ate'] = $filters['data_ate'];
        }

        if (!empty($filters['q'])) {
            $where .= " AND (tc.codigo LIKE :q OR tc.titulo LIKE :q2 OR m.nome LIKE :q3)";
            $params['q'] = "%{$filters['q']}%";
            $params['q2'] = "%{$filters['q']}%";
            $params['q3'] = "%{$filters['q']}%";
        }

        $sql = "SELECT COUNT(*) FROM treinamentos t
                JOIN tipos_certificado tc ON t.tipo_certificado_id = tc.id
                LEFT JOIN ministrantes m ON t.ministrante_id = m.id
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, tc.codigo as tipo_codigo, tc.titulo as tipo_titulo, tc.duracao,
                    tc.validade_meses, tc.tem_anuencia, tc.tem_diego, tc.conteudo_no_verso,
                    tc.conteudo_programatico, tc.ministrante_id as tipo_ministrante_id,
                    m.nome as ministrante_nome, m.cargo_titulo as ministrante_cargo,
                    m.registro as ministrante_registro,
                    u.nome as criador_nome
             FROM treinamentos t
             JOIN tipos_certificado tc ON t.tipo_certificado_id = tc.id
             LEFT JOIN ministrantes m ON t.ministrante_id = m.id
             LEFT JOIN usuarios u ON t.criado_por = u.id
             WHERE t.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getParticipantes(int $treinamentoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cert.id as certificado_id, cert.status, cert.data_validade,
                    c.id as colaborador_id, c.nome_completo, c.cpf_encrypted,
                    c.cargo, c.funcao, c.data_admissao, c.setor
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             WHERE cert.treinamento_id = :tid
             ORDER BY c.nome_completo"
        );
        $stmt->execute(['tid' => $treinamentoId]);
        return $stmt->fetchAll();
    }

    public function getContadoresMes(): array
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) as total_treinamentos,
                    COALESCE(SUM(total_participantes), 0) as total_participantes
             FROM treinamentos
             WHERE MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())"
        );
        return $stmt->fetch() ?: ['total_treinamentos' => 0, 'total_participantes' => 0];
    }
}
