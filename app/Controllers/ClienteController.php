<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Cliente;
use App\Models\Obra;
use App\Models\TipoDocumento;
use App\Models\TipoCertificado;
use App\Services\ValidationService;

class ClienteController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Cliente();
        $clientes = $model->all([], 'nome_fantasia ASC');

        $db = Database::getInstance();

        // Count active collaborators per client
        $colabStmt = $db->query(
            "SELECT cliente_id, COUNT(*) as total FROM colaboradores WHERE status = 'ativo' AND excluido_em IS NULL GROUP BY cliente_id"
        );
        $colabCounts = [];
        foreach ($colabStmt->fetchAll() as $row) {
            $colabCounts[(int)$row['cliente_id']] = (int)$row['total'];
        }

        // Compliance per client: check for expired docs and expiring docs/certs
        $expiredStmt = $db->query(
            "SELECT c.cliente_id, COUNT(*) as total
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             WHERE d.data_validade IS NOT NULL
               AND d.data_validade < CURDATE()
               AND d.status != 'obsoleto'
               AND d.excluido_em IS NULL
               AND c.status = 'ativo'
             GROUP BY c.cliente_id"
        );
        $docsExpired = [];
        foreach ($expiredStmt->fetchAll() as $row) {
            $docsExpired[(int)$row['cliente_id']] = (int)$row['total'];
        }

        $expiringStmt = $db->query(
            "SELECT c.cliente_id, COUNT(*) as total
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             WHERE d.data_validade IS NOT NULL
               AND d.data_validade >= CURDATE()
               AND d.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               AND d.status IN ('vigente','proximo_vencimento')
               AND d.excluido_em IS NULL
               AND c.status = 'ativo'
             GROUP BY c.cliente_id"
        );
        $docsExpiring = [];
        foreach ($expiringStmt->fetchAll() as $row) {
            $docsExpiring[(int)$row['cliente_id']] = (int)$row['total'];
        }

        $this->view('clientes/index', [
            'clientes'      => $clientes,
            'colabCounts'   => $colabCounts,
            'docsExpired'   => $docsExpired,
            'docsExpiring'  => $docsExpiring,
            'pageTitle'     => 'Clientes',
        ]);
    }

    public function create(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->view('clientes/form', ['cliente' => null, 'pageTitle' => 'Clientes']);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cnpj = trim($this->input('cnpj', ''));
        if (!empty($cnpj) && !ValidationService::validarCNPJ($cnpj)) {
            $this->flash('error', 'CNPJ inválido.');
            $this->redirect('/clientes/novo');
        }

        $model = new Cliente();
        $data = [
            'razao_social'   => trim($this->input('razao_social', '')),
            'nome_fantasia'  => trim($this->input('nome_fantasia', '')),
            'cnpj'           => $cnpj,
            'contato_nome'   => trim($this->input('contato_nome', '')),
            'contato_email'  => trim($this->input('contato_email', '')),
            'contato_telefone' => trim($this->input('contato_telefone', '')),
        ];
        $id = $model->create($data);
        LoggerMiddleware::log('criar', "Cliente criado: {$data['nome_fantasia']} (ID: {$id})");
        $this->flash('success', 'Cliente cadastrado.');
        $this->redirect('/clientes');
    }

    public function show(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Cliente();
        $obraModel = new Obra();
        $cliente = $model->find((int)$id);
        if (!$cliente) $this->redirect('/clientes');

        $db = Database::getInstance();

        // Load client requirements
        $reqStmt = $db->prepare(
            "SELECT ccd.*, td.nome as doc_nome, td.categoria as doc_categoria,
                    tc.codigo as cert_codigo
             FROM config_cliente_docs ccd
             LEFT JOIN tipos_documento td ON ccd.tipo_documento_id = td.id
             LEFT JOIN tipos_certificado tc ON ccd.tipo_certificado_id = tc.id
             WHERE ccd.cliente_id = :cid
             ORDER BY td.categoria, td.nome, tc.codigo"
        );
        $reqStmt->execute(['cid' => (int)$id]);
        $requisitos = $reqStmt->fetchAll();

        // Load available types for the form
        $tipoDocModel = new TipoDocumento();
        $tiposDocs = $tipoDocModel->all(['ativo' => 1], 'categoria, nome ASC');

        $tipoCertModel = new TipoCertificado();
        $tiposCerts = $tipoCertModel->all(['ativo' => 1], 'codigo ASC');

        // Compliance summary
        $colabStmt = $db->prepare(
            "SELECT COUNT(*) FROM colaboradores WHERE cliente_id = :cid AND status = 'ativo' AND excluido_em IS NULL"
        );
        $colabStmt->execute(['cid' => (int)$id]);
        $totalColabs = (int)$colabStmt->fetchColumn();

        $this->view('clientes/show', [
            'cliente'    => $cliente,
            'obras'      => $obraModel->all(['cliente_id' => (int)$id], 'nome ASC'),
            'requisitos' => $requisitos,
            'tiposDocs'  => $tiposDocs,
            'tiposCerts' => $tiposCerts,
            'totalColabs' => $totalColabs,
            'pageTitle'  => 'Clientes',
        ]);
    }

    public function edit(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Cliente();
        $this->view('clientes/form', ['cliente' => $model->find((int)$id), 'pageTitle' => 'Clientes']);
    }

    public function update(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cnpj = trim($this->input('cnpj', ''));
        if (!empty($cnpj) && !ValidationService::validarCNPJ($cnpj)) {
            $this->flash('error', 'CNPJ inválido.');
            $this->redirect("/clientes/{$id}/editar");
        }

        $model = new Cliente();
        $data = [
            'razao_social'   => trim($this->input('razao_social', '')),
            'nome_fantasia'  => trim($this->input('nome_fantasia', '')),
            'cnpj'           => $cnpj,
            'contato_nome'   => trim($this->input('contato_nome', '')),
            'contato_email'  => trim($this->input('contato_email', '')),
            'contato_telefone' => trim($this->input('contato_telefone', '')),
            'ativo'          => (int)$this->input('ativo', 1),
        ];
        $model->update((int)$id, $data);
        LoggerMiddleware::log('editar', "Cliente atualizado: {$data['nome_fantasia']} (ID: {$id})");
        $this->flash('success', 'Cliente atualizado.');
        $this->redirect("/clientes/{$id}");
    }

    public function addRequisito(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $tipoDocId = $this->input('tipo_documento_id') ?: null;
        $tipoCertId = $this->input('tipo_certificado_id') ?: null;
        $obrigatorio = (int)$this->input('obrigatorio', 1);
        $observacoes = trim($this->input('observacoes', ''));

        if (!$tipoDocId && !$tipoCertId) {
            $this->flash('error', 'Selecione um tipo de documento ou certificado.');
            $this->redirect("/clientes/{$id}");
            return;
        }

        $db = Database::getInstance();

        // Check duplicate
        $checkSql = "SELECT COUNT(*) FROM config_cliente_docs WHERE cliente_id = :cid";
        $params = ['cid' => (int)$id];
        if ($tipoDocId) {
            $checkSql .= " AND tipo_documento_id = :tdid";
            $params['tdid'] = (int)$tipoDocId;
        } else {
            $checkSql .= " AND tipo_certificado_id = :tcid";
            $params['tcid'] = (int)$tipoCertId;
        }
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute($params);
        if ((int)$checkStmt->fetchColumn() > 0) {
            $this->flash('warning', 'Este requisito ja esta cadastrado para este cliente.');
            $this->redirect("/clientes/{$id}");
            return;
        }

        $stmt = $db->prepare(
            "INSERT INTO config_cliente_docs (cliente_id, tipo_documento_id, tipo_certificado_id, obrigatorio, observacoes)
             VALUES (:cid, :tdid, :tcid, :obr, :obs)"
        );
        $stmt->execute([
            'cid'  => (int)$id,
            'tdid' => $tipoDocId ? (int)$tipoDocId : null,
            'tcid' => $tipoCertId ? (int)$tipoCertId : null,
            'obr'  => $obrigatorio,
            'obs'  => $observacoes,
        ]);

        LoggerMiddleware::log('criar', "Requisito adicionado ao cliente ID: {$id}");
        $this->flash('success', 'Requisito adicionado.');
        $this->redirect("/clientes/{$id}");
    }

    public function removeRequisito(string $id, string $reqId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM config_cliente_docs WHERE id = :rid AND cliente_id = :cid");
        $stmt->execute(['rid' => (int)$reqId, 'cid' => (int)$id]);

        LoggerMiddleware::log('excluir', "Requisito removido do cliente ID: {$id}");
        $this->flash('success', 'Requisito removido.');
        $this->redirect("/clientes/{$id}");
    }
}
