<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Services\CryptoService;

class ChecklistController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();

        // Listar obras ativas
        $stmt = $db->query(
            "SELECT o.*, c.nome_fantasia
             FROM obras o
             JOIN clientes c ON o.cliente_id = c.id
             WHERE o.status = 'ativa'
             ORDER BY c.nome_fantasia, o.nome"
        );
        $obras = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('checklist/index', [
            'obras' => $obras,
            'pageTitle' => 'Checklist',
        ]);
    }

    public function verificar(string $obraId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();

        // Obra + cliente
        $stmt = $db->prepare(
            "SELECT o.*, c.razao_social, c.nome_fantasia, c.id as cliente_id
             FROM obras o
             JOIN clientes c ON o.cliente_id = c.id
             WHERE o.id = :id"
        );
        $stmt->execute(['id' => (int)$obraId]);
        $obra = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$obra) {
            $this->flash('error', 'Obra não encontrada.');
            $this->redirect('/checklist');
            return;
        }

        // Colaboradores da obra
        $stmt = $db->prepare(
            "SELECT * FROM colaboradores
             WHERE obra_id = :oid AND status = 'ativo' AND excluido_em IS NULL
             ORDER BY nome_completo"
        );
        $stmt->execute(['oid' => (int)$obraId]);
        $colaboradores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Requisitos do cliente
        $stmt = $db->prepare(
            "SELECT ccd.*, td.nome as doc_nome, tc.codigo as cert_codigo
             FROM config_cliente_docs ccd
             LEFT JOIN tipos_documento td ON ccd.tipo_documento_id = td.id
             LEFT JOIN tipos_certificado tc ON ccd.tipo_certificado_id = tc.id
             WHERE ccd.cliente_id = :cid"
        );
        $stmt->execute(['cid' => $obra['cliente_id']]);
        $requisitos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $reqDocs = []; $reqCerts = [];
        foreach ($requisitos as $r) {
            if ($r['tipo_documento_id']) $reqDocs[] = $r;
            if ($r['tipo_certificado_id']) $reqCerts[] = $r;
        }

        // Verificar conformidade de cada colaborador
        $resultado = [];
        $totalOk = 0;
        $totalNok = 0;

        foreach ($colaboradores as $colab) {
            $itens = [];
            $conforme = true;

            // Verificar documentos obrigatórios
            foreach ($reqDocs as $req) {
                $stmt = $db->prepare(
                    "SELECT status, data_validade FROM documentos
                     WHERE colaborador_id = :cid AND tipo_documento_id = :tid
                       AND status != 'obsoleto' AND excluido_em IS NULL
                     ORDER BY data_emissao DESC LIMIT 1"
                );
                $stmt->execute(['cid' => $colab['id'], 'tid' => $req['tipo_documento_id']]);
                $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

                $status = 'ausente';
                $validade = null;
                if ($doc) {
                    $status = $doc['status'];
                    $validade = $doc['data_validade'];
                }

                $ok = ($status === 'vigente');
                if (!$ok) $conforme = false;

                $itens[] = [
                    'tipo' => 'doc',
                    'nome' => $req['doc_nome'],
                    'status' => $status,
                    'validade' => $validade,
                    'ok' => $ok,
                ];
            }

            // Verificar certificados obrigatórios
            foreach ($reqCerts as $req) {
                $stmt = $db->prepare(
                    "SELECT status, data_validade FROM certificados
                     WHERE colaborador_id = :cid AND tipo_certificado_id = :tid
                       AND excluido_em IS NULL
                     ORDER BY data_emissao DESC LIMIT 1"
                );
                $stmt->execute(['cid' => $colab['id'], 'tid' => $req['tipo_certificado_id']]);
                $cert = $stmt->fetch(\PDO::FETCH_ASSOC);

                $status = 'ausente';
                $validade = null;
                if ($cert) {
                    $status = $cert['status'];
                    $validade = $cert['data_validade'];
                }

                $ok = ($status === 'vigente');
                if (!$ok) $conforme = false;

                $itens[] = [
                    'tipo' => 'cert',
                    'nome' => $req['cert_codigo'],
                    'status' => $status,
                    'validade' => $validade,
                    'ok' => $ok,
                ];
            }

            // ASO sempre obrigatório
            $stmt = $db->prepare(
                "SELECT status, data_validade FROM documentos
                 WHERE colaborador_id = :cid AND tipo_documento_id IN (1,2,3,4,5)
                   AND status != 'obsoleto' AND excluido_em IS NULL
                 ORDER BY data_emissao DESC LIMIT 1"
            );
            $stmt->execute(['cid' => $colab['id']]);
            $aso = $stmt->fetch(\PDO::FETCH_ASSOC);
            $asoStatus = $aso ? $aso['status'] : 'ausente';
            $asoOk = ($asoStatus === 'vigente');
            if (!$asoOk) $conforme = false;

            // EPI sempre obrigatório
            $stmt = $db->prepare(
                "SELECT status, data_validade FROM documentos
                 WHERE colaborador_id = :cid AND tipo_documento_id = 6
                   AND status != 'obsoleto' AND excluido_em IS NULL
                 ORDER BY data_emissao DESC LIMIT 1"
            );
            $stmt->execute(['cid' => $colab['id']]);
            $epi = $stmt->fetch(\PDO::FETCH_ASSOC);
            $epiStatus = $epi ? $epi['status'] : 'ausente';
            $epiOk = ($epiStatus === 'vigente');
            if (!$epiOk) $conforme = false;

            if ($conforme) $totalOk++; else $totalNok++;

            $resultado[] = [
                'colaborador' => $colab,
                'aso' => ['status' => $asoStatus, 'validade' => $aso['data_validade'] ?? null, 'ok' => $asoOk],
                'epi' => ['status' => $epiStatus, 'validade' => $epi['data_validade'] ?? null, 'ok' => $epiOk],
                'itens' => $itens,
                'conforme' => $conforme,
            ];
        }

        // Ordenar: não conformes primeiro
        usort($resultado, fn($a, $b) => $a['conforme'] <=> $b['conforme']);

        $this->view('checklist/verificar', [
            'obra' => $obra,
            'resultado' => $resultado,
            'reqDocs' => $reqDocs,
            'reqCerts' => $reqCerts,
            'totalOk' => $totalOk,
            'totalNok' => $totalNok,
            'pageTitle' => 'Checklist',
        ]);
    }
}
