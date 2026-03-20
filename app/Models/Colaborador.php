<?php

namespace App\Models;

use App\Core\Model;

class Colaborador extends Model
{
    protected string $table = 'colaboradores';

    public function search(string $term, string $status = '', int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT c.*, cl.nome_fantasia as cliente_nome, o.nome as obra_nome
                FROM colaboradores c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                LEFT JOIN obras o ON c.obra_id = o.id
                WHERE (c.nome_completo LIKE :term OR c.matricula LIKE :term2 OR c.cargo LIKE :term3)
                  AND c.excluido_em IS NULL";
        $params = ['term' => "%{$term}%", 'term2' => "%{$term}%", 'term3' => "%{$term}%"];

        if ($status) {
            $sql .= " AND c.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY c.nome_completo ASC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function searchCount(string $term, string $status = ''): int
    {
        $sql = "SELECT COUNT(*) FROM colaboradores c
                WHERE (c.nome_completo LIKE :term OR c.matricula LIKE :term2 OR c.cargo LIKE :term3)
                  AND c.excluido_em IS NULL";
        $params = ['term' => "%{$term}%", 'term2' => "%{$term}%", 'term3' => "%{$term}%"];

        if ($status) {
            $sql .= " AND c.status = :status";
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function allWithRelations(array $conditions = [], string $orderBy = 'c.nome_completo ASC', int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT c.*, cl.nome_fantasia as cliente_nome, o.nome as obra_nome
                FROM colaboradores c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                LEFT JOIN obras o ON c.obra_id = o.id
                WHERE c.excluido_em IS NULL";
        $params = [];

        if (!empty($conditions)) {
            foreach ($conditions as $col => $val) {
                $sql .= " AND c.{$col} = :{$col}";
                $params[$col] = $val;
            }
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, cl.nome_fantasia as cliente_nome, o.nome as obra_nome
             FROM colaboradores c
             LEFT JOIN clientes cl ON c.cliente_id = cl.id
             LEFT JOIN obras o ON c.obra_id = o.id
             WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function countByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as total FROM colaboradores WHERE excluido_em IS NULL GROUP BY status"
        );
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    /**
     * Get all soft-deleted collaborators.
     */
    public function getDeleted(): array
    {
        $stmt = $this->db->query(
            "SELECT c.*, cl.nome_fantasia as cliente_nome
             FROM colaboradores c
             LEFT JOIN clientes cl ON c.cliente_id = cl.id
             WHERE c.excluido_em IS NOT NULL
             ORDER BY c.excluido_em DESC"
        );
        return $stmt->fetchAll();
    }
}
