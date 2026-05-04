<?php

namespace App\Models;

use App\Core\Database;

class RhVinculoObra
{
    // ------------------------------------------------------------------
    // Lista vínculos do colaborador (primário do GCO + adicionais).
    // Retorna [['origem'=>'gco'|'rh', 'obra_id', 'obra_nome', 'cliente_nome',
    //           'desde', 'ate_quando', 'funcao_no_site', 'vinculo_id']]
    // ------------------------------------------------------------------
    public static function listarDoColaborador(int $colabId): array
    {
        $db = Database::getInstance();

        // Vínculo primário (do GCO, somente leitura)
        $primario = $db->prepare(
            "SELECT c.obra_id, o.nome AS obra_nome, cl.nome_fantasia AS cliente_nome,
                    c.data_admissao AS desde, c.cargo AS funcao_no_site
             FROM colaboradores c
             JOIN obras o     ON c.obra_id = o.id
             JOIN clientes cl ON o.cliente_id = cl.id
             WHERE c.id = :id"
        );
        $primario->execute(['id' => $colabId]);
        $p = $primario->fetch(\PDO::FETCH_ASSOC);

        $linhas = [];
        if ($p && $p['obra_id']) {
            $linhas[] = [
                'origem'         => 'gco',
                'vinculo_id'     => null,
                'obra_id'        => (int)$p['obra_id'],
                'obra_nome'      => $p['obra_nome'],
                'cliente_nome'   => $p['cliente_nome'],
                'desde'          => $p['desde'],
                'ate_quando'     => null,
                'funcao_no_site' => $p['funcao_no_site'],
            ];
        }

        // Vínculos adicionais
        $stmt = $db->prepare(
            "SELECT v.id AS vinculo_id, v.obra_id, v.desde, v.ate_quando, v.funcao_no_site,
                    o.nome AS obra_nome, cl.nome_fantasia AS cliente_nome
             FROM rh_vinculos_obra v
             JOIN obras o     ON v.obra_id = o.id
             JOIN clientes cl ON o.cliente_id = cl.id
             WHERE v.colaborador_id = :id AND v.excluido_em IS NULL
             ORDER BY v.desde DESC"
        );
        $stmt->execute(['id' => $colabId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $row['origem'] = 'rh';
            $row['obra_id'] = (int)$row['obra_id'];
            $linhas[] = $row;
        }

        return $linhas;
    }

    // ------------------------------------------------------------------
    // Lista os clientes onde o colaborador tem vínculo ATIVO hoje.
    // Combina vínculo primário do GCO + adicionais sem ate_quando.
    // Retorna lista de cliente_id distintos.
    // ------------------------------------------------------------------
    public static function clientesAtivos(int $colabId): array
    {
        $db = Database::getInstance();
        $hoje = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT DISTINCT cl.id, cl.nome_fantasia
             FROM (
                -- Vínculo primário
                SELECT o.cliente_id
                FROM colaboradores c
                JOIN obras o ON c.obra_id = o.id
                WHERE c.id = :id1 AND c.excluido_em IS NULL
                UNION
                -- Vínculos adicionais ativos
                SELECT o.cliente_id
                FROM rh_vinculos_obra v
                JOIN obras o ON v.obra_id = o.id
                WHERE v.colaborador_id = :id2
                  AND v.excluido_em IS NULL
                  AND v.desde <= :hoje
                  AND (v.ate_quando IS NULL OR v.ate_quando >= :hoje2)
             ) s
             JOIN clientes cl ON s.cliente_id = cl.id
             WHERE cl.ativo = 1"
        );
        $stmt->execute(['id1' => $colabId, 'id2' => $colabId, 'hoje' => $hoje, 'hoje2' => $hoje]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Cria vínculo. Lança RuntimeException se já existe vínculo aberto idêntico.
    // ------------------------------------------------------------------
    public static function criar(int $colabId, int $obraId, string $desde, ?string $ateQuando, ?string $funcao, int $userId): int
    {
        $db = Database::getInstance();

        // RN-01-02: data_admissao do colaborador
        $admissao = $db->prepare("SELECT data_admissao FROM colaboradores WHERE id = :id");
        $admissao->execute(['id' => $colabId]);
        $dataAdmissao = $admissao->fetchColumn();
        if ($dataAdmissao && $desde < $dataAdmissao) {
            throw new \RuntimeException("Data de início ({$desde}) anterior à admissão do colaborador ({$dataAdmissao}).");
        }

        // RN-01-03: ate_quando >= desde
        if ($ateQuando && $ateQuando < $desde) {
            throw new \RuntimeException("Data de término anterior à data de início.");
        }

        // RN-01-01: vínculo aberto duplicado
        if ($ateQuando === null) {
            $dup = $db->prepare(
                "SELECT id FROM rh_vinculos_obra
                 WHERE colaborador_id = :c AND obra_id = :o AND ate_quando IS NULL AND excluido_em IS NULL"
            );
            $dup->execute(['c' => $colabId, 'o' => $obraId]);
            if ($dup->fetchColumn()) {
                throw new \RuntimeException("Vínculo já existe (mesmo colaborador, mesma obra, em aberto).");
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO rh_vinculos_obra (colaborador_id, obra_id, desde, ate_quando, funcao_no_site, criado_por)
             VALUES (:c, :o, :d, :a, :f, :u)"
        );
        $stmt->execute([
            'c' => $colabId, 'o' => $obraId, 'd' => $desde,
            'a' => $ateQuando, 'f' => $funcao, 'u' => $userId,
        ]);
        return (int)$db->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Encerrar vínculo (preencher ate_quando).
    // ------------------------------------------------------------------
    public static function encerrar(int $vinculoId, string $ateQuando): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE rh_vinculos_obra SET ate_quando = :a
             WHERE id = :id AND ate_quando IS NULL AND excluido_em IS NULL"
        );
        $stmt->execute(['a' => $ateQuando, 'id' => $vinculoId]);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Soft-delete do vínculo.
    // ------------------------------------------------------------------
    public static function excluir(int $vinculoId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE rh_vinculos_obra SET excluido_em = NOW()
             WHERE id = :id AND excluido_em IS NULL"
        );
        $stmt->execute(['id' => $vinculoId]);
        return $stmt->rowCount() > 0;
    }
}
