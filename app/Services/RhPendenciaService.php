<?php

namespace App\Services;

use App\Core\Database;

/**
 * Motor de detecção de pendências de reprotocolo (RF-02 do ETF).
 *
 * Para cada combinação (colaborador, cliente, tipo de documento exigido),
 * compara a versão vigente do documento com o último protocolo confirmado.
 * Se o documento é mais novo que o último protocolo, cria/atualiza a pendência.
 *
 * Regras (RN-02-*):
 *  - 02-01: pendência só se doc atual é mais recente que último protocolo confirmado
 *  - 02-02: não duplica pendências abertas — atualiza referência
 *  - 02-03: docs sem data_validade não geram pendência
 *  - 02-04: tipos desativados não geram pendência
 *  - 02-05: colaboradores isentos não geram pendência
 */
class RhPendenciaService
{
    /**
     * Recalcula pendências de TODOS os colaboradores ativos.
     * Retorna ['criadas'=>int, 'atualizadas'=>int, 'mantidas'=>int].
     */
    public static function recalcularTudo(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT id FROM colaboradores WHERE excluido_em IS NULL AND status = 'ativo'"
        );
        $stats = ['criadas' => 0, 'atualizadas' => 0, 'mantidas' => 0];
        while ($id = $stmt->fetchColumn()) {
            $r = self::recalcularColaborador((int)$id);
            $stats['criadas']     += $r['criadas'];
            $stats['atualizadas'] += $r['atualizadas'];
            $stats['mantidas']    += $r['mantidas'];
        }
        return $stats;
    }

    /**
     * Recalcula pendências de um único colaborador.
     * Chamado por: trigger de DocumentoController::substituir, criação de vínculo,
     * cron diário, e botão "Recalcular agora" na UI.
     */
    public static function recalcularColaborador(int $colabId): array
    {
        $db    = Database::getInstance();
        $stats = ['criadas' => 0, 'atualizadas' => 0, 'mantidas' => 0];

        // RN-02-05: colaborador isento não gera pendência
        $colab = $db->prepare(
            "SELECT id, isento, status, excluido_em FROM colaboradores WHERE id = :id"
        );
        $colab->execute(['id' => $colabId]);
        $c = $colab->fetch(\PDO::FETCH_ASSOC);
        if (!$c || $c['excluido_em'] !== null || $c['status'] !== 'ativo' || (int)$c['isento'] === 1) {
            return $stats;
        }

        // 1. Clientes ativos do colaborador (primário do GCO + adicionais)
        $clientes = \App\Models\RhVinculoObra::clientesAtivos($colabId);
        if (empty($clientes)) {
            return $stats;
        }

        // 2. Documentos vigentes do colaborador (última versão de cada tipo, com data_validade)
        // RN-02-03: data_validade IS NULL não entra
        // RN-02-04: tipos.ativo = 0 não entra
        $docs = $db->prepare(
            "SELECT d.id AS doc_id, d.tipo_documento_id, d.data_validade
             FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             INNER JOIN (
                 SELECT colaborador_id, tipo_documento_id, MAX(id) AS max_id
                 FROM (
                     SELECT id, colaborador_id, tipo_documento_id,
                            ROW_NUMBER() OVER (
                                PARTITION BY colaborador_id, tipo_documento_id
                                ORDER BY data_emissao DESC, id DESC
                            ) AS rn
                     FROM documentos
                     WHERE colaborador_id = :c1
                       AND status != 'obsoleto'
                       AND excluido_em IS NULL
                 ) ranked
                 WHERE rn = 1
                 GROUP BY colaborador_id, tipo_documento_id
             ) best ON d.id = best.max_id
             WHERE d.colaborador_id = :c2
               AND d.aprovacao_status = 'aprovado'
               AND d.data_validade IS NOT NULL
               AND td.ativo = 1"
        );
        $docs->execute(['c1' => $colabId, 'c2' => $colabId]);
        $documentos = $docs->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($documentos)) {
            return $stats;
        }

        // 3. Para cada cliente × cada doc, verifica se tipo é exigido e se há pendência
        $slaDias = self::carregarSlaDias($db);

        foreach ($clientes as $cliente) {
            $clienteId = (int)$cliente['id'];

            // Tipos exigidos por este cliente (config_cliente_docs sem obra_id ou da obra primária)
            $exigStmt = $db->prepare(
                "SELECT DISTINCT tipo_documento_id
                 FROM config_cliente_docs
                 WHERE cliente_id = :c
                   AND tipo_documento_id IS NOT NULL
                   AND obrigatorio = 1"
            );
            $exigStmt->execute(['c' => $clienteId]);
            $exigidos = array_map('intval', $exigStmt->fetchAll(\PDO::FETCH_COLUMN));

            // Se cliente não tem catálogo configurado, considera TODOS os tipos como exigidos
            // (modo permissivo até o RH configurar — ver decisão pendente no ETF).
            $catalogoVazio = empty($exigidos);

            foreach ($documentos as $doc) {
                $tipoId = (int)$doc['tipo_documento_id'];
                if (!$catalogoVazio && !in_array($tipoId, $exigidos, true)) {
                    continue; // tipo não exigido por este cliente
                }

                $docId = (int)$doc['doc_id'];

                // Verifica se já existe rh_protocolos para esta combinação
                $rp = $db->prepare(
                    "SELECT id, documento_id, status FROM rh_protocolos
                     WHERE cliente_id = :cl AND colaborador_id = :co AND tipo_documento_id = :ti
                     ORDER BY id DESC LIMIT 1"
                );
                $rp->execute(['cl' => $clienteId, 'co' => $colabId, 'ti' => $tipoId]);
                $protocolo = $rp->fetch(\PDO::FETCH_ASSOC);

                if (!$protocolo) {
                    // Não existe nenhum registro → CRIA pendência nova
                    self::criarPendencia($db, $docId, $colabId, $clienteId, $tipoId, $slaDias);
                    $stats['criadas']++;
                } elseif ((int)$protocolo['documento_id'] === $docId) {
                    // Já existe pendência/protocolo para a versão atual → mantém
                    $stats['mantidas']++;
                } else {
                    // Existe protocolo mas para versão antiga → cria nova pendência
                    // (RN-02-01: doc atual é mais recente que último protocolo)
                    // Se a última estava 'pendente_envio', atualiza in-place.
                    if ($protocolo['status'] === 'pendente_envio') {
                        $upd = $db->prepare(
                            "UPDATE rh_protocolos
                             SET documento_id = :d, atualizado_em = NOW()
                             WHERE id = :id"
                        );
                        $upd->execute(['d' => $docId, 'id' => $protocolo['id']]);
                        $stats['atualizadas']++;
                    } else {
                        // Estava enviado/confirmado/rejeitado em versão antiga → cria nova pendência
                        self::criarPendencia($db, $docId, $colabId, $clienteId, $tipoId, $slaDias);
                        $stats['criadas']++;
                    }
                }
            }
        }

        return $stats;
    }

    private static function criarPendencia(\PDO $db, int $docId, int $colabId, int $clienteId, int $tipoId, int $slaDias): void
    {
        // Descobre obra_id "padrão" do colaborador (do GCO) para denormalizar
        $obra = $db->prepare("SELECT obra_id FROM colaboradores WHERE id = :c");
        $obra->execute(['c' => $colabId]);
        $obraId = $obra->fetchColumn() ?: null;

        $prazo = self::calcularPrazoSla($slaDias);

        $db->prepare(
            "INSERT IGNORE INTO rh_protocolos
                (documento_id, colaborador_id, cliente_id, obra_id, tipo_documento_id,
                 status, prazo_sla)
             VALUES
                (:d, :co, :cl, :ob, :ti, 'pendente_envio', :pr)"
        )->execute([
            'd' => $docId, 'co' => $colabId, 'cl' => $clienteId,
            'ob' => $obraId ?: null, 'ti' => $tipoId, 'pr' => $prazo,
        ]);
    }

    private static function carregarSlaDias(\PDO $db): int
    {
        $dias = (int)$db->query("SELECT sla_reprotocolo_dias_uteis FROM rh_alertas_config WHERE id = 1")->fetchColumn();
        return $dias > 0 ? $dias : 5;
    }

    private static function calcularPrazoSla(int $diasUteis): string
    {
        $date  = new \DateTime();
        $added = 0;
        while ($added < $diasUteis) {
            $date->modify('+1 day');
            if ($date->format('N') < 6) $added++;
        }
        return $date->format('Y-m-d');
    }
}
