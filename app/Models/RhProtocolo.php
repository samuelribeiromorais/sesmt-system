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

        // Após a Fase 2, o motor de pendências cria rh_protocolos para CADA
        // cliente onde o colaborador tem vínculo ativo (incluindo N:N de
        // rh_vinculos_obra). Por isso a query parte de rh_protocolos.
        $where  = ["c.excluido_em IS NULL"];
        $params = [];

        $statusMap = [
            'pendente'   => 'pendente_envio',
            'enviado'    => 'enviado',
            'confirmado' => 'confirmado',
            'rejeitado'  => 'rejeitado',
        ];
        $statusFiltro = $filtros['status'] ?? 'pendente';
        if (isset($statusMap[$statusFiltro])) {
            $where[]            = "rp.status = :status";
            $params['status']   = $statusMap[$statusFiltro];
        }

        if (!empty($filtros['cliente_id'])) {
            $where[]              = "rp.cliente_id = :cliente_id";
            $params['cliente_id'] = (int)$filtros['cliente_id'];
        }
        if (!empty($filtros['tipo_id'])) {
            $where[]           = "rp.tipo_documento_id = :tipo_id";
            $params['tipo_id'] = (int)$filtros['tipo_id'];
        }
        if (!empty($filtros['q'])) {
            $where[]     = "c.nome_completo LIKE :q";
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
                rp.obra_id             AS obra_id,
                obra_p.nome            AS obra_nome,
                rp.cliente_id          AS cliente_id,
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
            FROM rh_protocolos rp
            JOIN colaboradores   c  ON rp.colaborador_id    = c.id
            JOIN tipos_documento td ON rp.tipo_documento_id = td.id
            JOIN clientes        cl ON rp.cliente_id        = cl.id
            JOIN documentos      d  ON rp.documento_id      = d.id
            LEFT JOIN obras      obra_p ON rp.obra_id       = obra_p.id
            LEFT JOIN usuarios   u  ON rp.enviado_por       = u.id
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

        // Pós-Fase 2: pendências são pré-criadas em rh_protocolos pelo motor.
        $row = $db->query(
            "SELECT
                SUM(rp.status='pendente_envio') AS pendentes,
                SUM(rp.status='enviado')        AS enviados,
                SUM(rp.status='confirmado')     AS confirmados,
                SUM(rp.status='rejeitado')      AS rejeitados
             FROM rh_protocolos rp
             JOIN colaboradores c ON rp.colaborador_id = c.id
             WHERE c.excluido_em IS NULL"
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'pendentes'   => (int)($row['pendentes']   ?? 0),
            'enviados'    => (int)($row['enviados']    ?? 0),
            'confirmados' => (int)($row['confirmados'] ?? 0),
            'rejeitados'  => (int)($row['rejeitados']  ?? 0),
        ];
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
