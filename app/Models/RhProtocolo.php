<?php

namespace App\Models;

use App\Core\Database;

class RhProtocolo
{
    // ------------------------------------------------------------------
    // Leitura: lista pendências (documentos aprovados sem protocolo
    // confirmado no cliente principal do colaborador).
    // ------------------------------------------------------------------
    public static function listarComFiltros(array $filtros = []): array
    {
        $db = Database::getInstance();

        // Sub-select: último documento por (colaborador, tipo) — não obsoleto
        $latestSub = "
            SELECT d2.*
            FROM documentos d2
            INNER JOIN (
                SELECT colaborador_id, tipo_documento_id, MAX(id) AS max_id
                FROM (
                    SELECT id, colaborador_id, tipo_documento_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY colaborador_id, tipo_documento_id
                               ORDER BY data_emissao DESC, id DESC
                           ) AS rn
                    FROM documentos
                    WHERE status != 'obsoleto' AND excluido_em IS NULL
                ) ranked
                WHERE rn = 1
                GROUP BY colaborador_id, tipo_documento_id
            ) best ON d2.id = best.max_id
        ";

        $where  = ["c.excluido_em IS NULL", "td.ativo = 1",
                   "d.aprovacao_status = 'aprovado'", "c.obra_id IS NOT NULL"];
        $params = [];

        // Filtro por status do protocolo
        $statusFiltro = $filtros['status'] ?? 'pendente';
        if ($statusFiltro === 'pendente') {
            $where[] = "(rp.id IS NULL OR rp.status = 'pendente_envio')";
        } elseif ($statusFiltro === 'enviado') {
            $where[] = "rp.status = 'enviado'";
        } elseif ($statusFiltro === 'confirmado') {
            $where[] = "rp.status = 'confirmado'";
        } elseif ($statusFiltro === 'rejeitado') {
            $where[] = "rp.status = 'rejeitado'";
        }
        // 'todos' → sem filtro de status

        if (!empty($filtros['cliente_id'])) {
            $where[]              = "cl.id = :cliente_id";
            $params['cliente_id'] = (int)$filtros['cliente_id'];
        }

        if (!empty($filtros['tipo_id'])) {
            $where[]           = "td.id = :tipo_id";
            $params['tipo_id'] = (int)$filtros['tipo_id'];
        }

        if (!empty($filtros['q'])) {
            $where[]    = "c.nome_completo LIKE :q";
            $params['q'] = '%' . $filtros['q'] . '%';
        }

        $whereStr = implode(' AND ', $where);

        $sql = "
            SELECT
                d.id                   AS doc_id,
                d.arquivo_nome,
                d.data_emissao,
                d.data_validade,
                d.status               AS doc_status,
                c.id                   AS colaborador_id,
                c.nome_completo,
                c.matricula,
                c.obra_id              AS colab_obra_id,
                td.id                  AS tipo_id,
                td.nome                AS tipo_nome,
                td.categoria,
                o.id                   AS obra_id,
                o.nome                 AS obra_nome,
                cl.id                  AS cliente_id,
                cl.nome_fantasia       AS cliente_nome,
                rp.id                  AS protocolo_id,
                rp.status              AS protocolo_status,
                rp.numero_protocolo,
                rp.protocolado_em,
                rp.enviado_em,
                rp.confirmado_em,
                rp.sem_comprovante,
                rp.observacoes         AS protocolo_obs,
                rp.motivo_rejeicao,
                u.nome                 AS enviado_por_nome,
                (SELECT COUNT(*) FROM rh_protocolo_comprovantes rc WHERE rc.protocolo_id = rp.id) AS n_comprovantes
            FROM ({$latestSub}) d
            JOIN colaboradores   c  ON d.colaborador_id    = c.id
            JOIN tipos_documento td ON d.tipo_documento_id = td.id
            JOIN obras           o  ON c.obra_id           = o.id
            JOIN clientes        cl ON o.cliente_id        = cl.id
            LEFT JOIN rh_protocolos rp
                   ON rp.documento_id = d.id AND rp.cliente_id = cl.id
            LEFT JOIN usuarios u ON rp.enviado_por = u.id
            WHERE {$whereStr}
            ORDER BY cl.nome_fantasia, c.nome_completo, td.nome
            LIMIT 500
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Contadores para KPI
    // ------------------------------------------------------------------
    public static function contadores(): array
    {
        $db = Database::getInstance();

        $base = "
            SELECT d2.*
            FROM documentos d2
            INNER JOIN (
                SELECT colaborador_id, tipo_documento_id, MAX(id) AS max_id
                FROM (
                    SELECT id, colaborador_id, tipo_documento_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY colaborador_id, tipo_documento_id
                               ORDER BY data_emissao DESC, id DESC
                           ) AS rn
                    FROM documentos
                    WHERE status != 'obsoleto' AND excluido_em IS NULL
                ) ranked
                WHERE rn = 1
                GROUP BY colaborador_id, tipo_documento_id
            ) best ON d2.id = best.max_id
        ";

        $filter = "
            JOIN colaboradores c2   ON d_all.colaborador_id = c2.id
            JOIN tipos_documento td2 ON d_all.tipo_documento_id = td2.id
            JOIN obras o2            ON c2.obra_id = o2.id
            WHERE c2.excluido_em IS NULL
              AND td2.ativo = 1
              AND d_all.aprovacao_status = 'aprovado'
              AND c2.obra_id IS NOT NULL
        ";

        $pendentes  = (int)$db->query(
            "SELECT COUNT(*) FROM ({$base}) d_all
             LEFT JOIN rh_protocolos rp2 ON rp2.documento_id = d_all.id
                AND rp2.cliente_id = (SELECT o3.cliente_id FROM colaboradores c3
                                      JOIN obras o3 ON c3.obra_id = o3.id
                                      WHERE c3.id = d_all.colaborador_id LIMIT 1)
             {$filter}
             AND (rp2.id IS NULL OR rp2.status = 'pendente_envio')"
        )->fetchColumn();

        $enviados   = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status = 'enviado'")->fetchColumn();
        $confirmados = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status = 'confirmado'")->fetchColumn();
        $rejeitados  = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status = 'rejeitado'")->fetchColumn();

        return compact('pendentes', 'enviados', 'confirmados', 'rejeitados');
    }

    // ------------------------------------------------------------------
    // Marcar como "enviado"
    // ------------------------------------------------------------------
    public static function marcarEnviado(
        int    $docId,
        int    $clienteId,
        int    $colaboradorId,
        int    $tipoId,
        int    $obraId,
        int    $userId,
        string $numeroProtocolo,
        string $dataProtocolo,
        string $observacoes,
        bool   $semComprovante
    ): int {
        $db  = Database::getInstance();
        $sla = self::calcularPrazoSla($db);

        $stmt = $db->prepare(
            "INSERT INTO rh_protocolos
                (documento_id, colaborador_id, cliente_id, obra_id, tipo_documento_id,
                 status, numero_protocolo, protocolado_em, enviado_por, enviado_em,
                 observacoes, sem_comprovante, prazo_sla)
             VALUES
                (:doc_id, :colab_id, :cliente_id, :obra_id, :tipo_id,
                 'enviado', :num_prot, :prot_em, :user_id, NOW(),
                 :obs, :sem_comp, :prazo)
             ON DUPLICATE KEY UPDATE
                status             = 'enviado',
                numero_protocolo   = VALUES(numero_protocolo),
                protocolado_em     = VALUES(protocolado_em),
                enviado_por        = VALUES(enviado_por),
                enviado_em         = NOW(),
                observacoes        = VALUES(observacoes),
                sem_comprovante    = VALUES(sem_comprovante)"
        );
        $stmt->execute([
            'doc_id'     => $docId,
            'colab_id'   => $colaboradorId,
            'cliente_id' => $clienteId,
            'obra_id'    => $obraId,
            'tipo_id'    => $tipoId,
            'num_prot'   => $numeroProtocolo ?: null,
            'prot_em'    => $dataProtocolo ?: date('Y-m-d'),
            'user_id'    => $userId,
            'obs'        => $observacoes ?: null,
            'sem_comp'   => $semComprovante ? 1 : 0,
            'prazo'      => $sla,
        ]);

        // Retorna o id do registro (INSERT ou UPDATE)
        $id = (int)$db->query(
            "SELECT id FROM rh_protocolos WHERE documento_id = {$docId} AND cliente_id = {$clienteId}"
        )->fetchColumn();

        // Sincroniza flag legado no documento
        $db->prepare("UPDATE documentos SET enviado_cliente = 1, enviado_cliente_em = NOW(), enviado_cliente_por = :uid WHERE id = :did")
           ->execute(['uid' => $userId, 'did' => $docId]);

        return $id;
    }

    // ------------------------------------------------------------------
    // Confirmar protocolo (RH recebe confirmação do cliente)
    // ------------------------------------------------------------------
    public static function confirmar(int $protocoloId, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE rh_protocolos
             SET status = 'confirmado', confirmado_em = NOW()
             WHERE id = :id AND status = 'enviado'"
        );
        $stmt->execute(['id' => $protocoloId]);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Rejeitar protocolo (cliente recusou)
    // ------------------------------------------------------------------
    public static function rejeitar(int $protocoloId, string $motivo, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE rh_protocolos
             SET status = 'rejeitado', motivo_rejeicao = :motivo
             WHERE id = :id AND status = 'enviado'"
        );
        $stmt->execute(['id' => $protocoloId, 'motivo' => $motivo]);

        if ($stmt->rowCount() === 0) return false;

        // Cria nova pendência a partir do mesmo documento
        $row = $db->query("SELECT * FROM rh_protocolos WHERE id = {$protocoloId}")->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare(
                "INSERT IGNORE INTO rh_protocolos
                    (documento_id, colaborador_id, cliente_id, obra_id, tipo_documento_id, status)
                 VALUES (:did, :cid, :kid, :oid, :tid, 'pendente_envio')"
            )->execute([
                'did' => $row['documento_id'],
                'cid' => $row['colaborador_id'],
                'kid' => $row['cliente_id'],
                'oid' => $row['obra_id'],
                'tid' => $row['tipo_documento_id'],
            ]);
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Salvar comprovante
    // ------------------------------------------------------------------
    public static function salvarComprovante(
        int    $protocoloId,
        string $path,
        string $nome,
        string $hash,
        int    $tamanho,
        int    $userId
    ): void {
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO rh_protocolo_comprovantes
                (protocolo_id, arquivo_path, arquivo_nome, arquivo_hash, arquivo_tamanho, enviado_por)
             VALUES (:pid, :path, :nome, :hash, :tam, :uid)"
        )->execute([
            'pid'  => $protocoloId,
            'path' => $path,
            'nome' => $nome,
            'hash' => $hash,
            'tam'  => $tamanho,
            'uid'  => $userId,
        ]);
    }

    // ------------------------------------------------------------------
    // Buscar comprovantes de um protocolo
    // ------------------------------------------------------------------
    public static function comprovantes(int $protocoloId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM rh_protocolo_comprovantes WHERE protocolo_id = :pid ORDER BY criado_em"
        );
        $stmt->execute(['pid' => $protocoloId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Protocolo por ID (para confirmar/rejeitar)
    // ------------------------------------------------------------------
    public static function findById(int $id): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rh_protocolos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private static function calcularPrazoSla(\PDO $db): string
    {
        $dias = (int)$db->query("SELECT sla_reprotocolo_dias_uteis FROM rh_alertas_config WHERE id = 1")->fetchColumn();
        if ($dias <= 0) $dias = 5;
        $date = new \DateTime();
        $added = 0;
        while ($added < $dias) {
            $date->modify('+1 day');
            if ($date->format('N') < 6) $added++; // Mon–Fri
        }
        return $date->format('Y-m-d');
    }
}
