<?php

namespace App\Models;

use App\Core\Model;

class EsocialEvento extends Model
{
    protected string $table = 'esocial_eventos';

    public function allWithColaborador(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT e.*, c.nome_completo as colaborador_nome
                FROM esocial_eventos e
                JOIN colaboradores c ON e.colaborador_id = c.id";
        $params = [];
        $where = [];

        if (!empty($filters['tipo_evento'])) {
            $where[] = "e.tipo_evento = :tipo_evento";
            $params['tipo_evento'] = $filters['tipo_evento'];
        }

        if (!empty($filters['status'])) {
            $where[] = "e.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY e.criado_em DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countWithFilters(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM esocial_eventos e";
        $params = [];
        $where = [];

        if (!empty($filters['tipo_evento'])) {
            $where[] = "e.tipo_evento = :tipo_evento";
            $params['tipo_evento'] = $filters['tipo_evento'];
        }

        if (!empty($filters['status'])) {
            $where[] = "e.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findWithColaborador(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, c.nome_completo as colaborador_nome, c.cargo, c.funcao, c.matricula
             FROM esocial_eventos e
             JOIN colaboradores c ON e.colaborador_id = c.id
             WHERE e.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
