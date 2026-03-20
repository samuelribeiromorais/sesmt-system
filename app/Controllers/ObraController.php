<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Obra;
use App\Models\Cliente;

class ObraController extends Controller
{
    /**
     * Show obra details with collaborators and compliance status.
     */
    public function show(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Obra();
        $obra = $model->find((int)$id);
        if (!$obra) $this->redirect('/clientes');

        $clienteModel = new Cliente();
        $cliente = $clienteModel->find($obra['cliente_id']);
        $db = Database::getInstance();

        // Get all active collaborators assigned to this obra
        $colabStmt = $db->prepare(
            "SELECT c.id, c.nome_completo, c.cpf, c.matricula, c.cargo, c.funcao, c.setor, c.data_admissao, c.status
             FROM colaboradores c
             WHERE c.obra_id = :oid AND c.status = 'ativo' AND c.excluido_em IS NULL
             ORDER BY c.nome_completo"
        );
        $colabStmt->execute(['oid' => (int)$id]);
        $colaboradores = $colabStmt->fetchAll(\PDO::FETCH_ASSOC);

        // For each collaborator, get doc/cert status summary
        foreach ($colaboradores as &$colab) {
            // Count docs by status
            $dStmt = $db->prepare(
                "SELECT d.status, COUNT(*) as total
                 FROM documentos d
                 WHERE d.colaborador_id = :cid AND d.status != 'obsoleto' AND d.excluido_em IS NULL
                 GROUP BY d.status"
            );
            $dStmt->execute(['cid' => $colab['id']]);
            $colab['docs'] = ['vigente' => 0, 'proximo_vencimento' => 0, 'vencido' => 0];
            foreach ($dStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $colab['docs'][$r['status']] = (int)$r['total'];
            }

            // Count certs by status
            $cStmt = $db->prepare(
                "SELECT status, COUNT(*) as total
                 FROM certificados
                 WHERE colaborador_id = :cid AND excluido_em IS NULL
                 GROUP BY status"
            );
            $cStmt->execute(['cid' => $colab['id']]);
            $colab['certs'] = ['vigente' => 0, 'proximo_vencimento' => 0, 'vencido' => 0];
            foreach ($cStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $colab['certs'][$r['status']] = (int)$r['total'];
            }

            // Overall status
            if ($colab['docs']['vencido'] > 0 || $colab['certs']['vencido'] > 0) {
                $colab['conformidade'] = 'irregular';
            } elseif ($colab['docs']['proximo_vencimento'] > 0 || $colab['certs']['proximo_vencimento'] > 0) {
                $colab['conformidade'] = 'atencao';
            } else {
                $colab['conformidade'] = 'regular';
            }
        }
        unset($colab);

        // Summary counts
        $totalRegular = count(array_filter($colaboradores, fn($c) => $c['conformidade'] === 'regular'));
        $totalAtencao = count(array_filter($colaboradores, fn($c) => $c['conformidade'] === 'atencao'));
        $totalIrregular = count(array_filter($colaboradores, fn($c) => $c['conformidade'] === 'irregular'));

        $this->view('obras/show', [
            'obra'            => $obra,
            'cliente'         => $cliente,
            'colaboradores'   => $colaboradores,
            'totalRegular'    => $totalRegular,
            'totalAtencao'    => $totalAtencao,
            'totalIrregular'  => $totalIrregular,
            'pageTitle'       => 'Obras',
        ]);
    }

    /**
     * Download all docs of collaborators in this obra as a ZIP.
     * Structure: ObraNome/ColaboradorNome/files...
     */
    public function downloadZip(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Obra();
        $obra = $model->find((int)$id);
        if (!$obra) $this->redirect('/clientes');

        $db = Database::getInstance();
        $uploadPath = dirname(dirname(__DIR__)) . '/storage/uploads';

        // Get active collaborators
        $colabStmt = $db->prepare(
            "SELECT c.id, c.nome_completo
             FROM colaboradores c
             WHERE c.obra_id = :oid AND c.status = 'ativo' AND c.excluido_em IS NULL
             ORDER BY c.nome_completo"
        );
        $colabStmt->execute(['oid' => (int)$id]);
        $colaboradores = $colabStmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($colaboradores)) {
            $this->flash('warning', 'Nenhum colaborador ativo nesta obra.');
            $this->redirect("/obras/{$id}");
            return;
        }

        // Create ZIP
        $zip = new \ZipArchive();
        $obraNome = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $obra['nome']);
        $tmpFile = tempnam(sys_get_temp_dir(), 'obra_') . '.zip';

        if ($zip->open($tmpFile, \ZipArchive::CREATE) !== true) {
            $this->flash('error', 'Erro ao criar arquivo ZIP.');
            $this->redirect("/obras/{$id}");
            return;
        }

        $fileCount = 0;
        foreach ($colaboradores as $colab) {
            $colabNome = preg_replace('/[^a-zA-Z0-9\s\-_áéíóúâêîôûàãõçÁÉÍÓÚÂÊÎÔÛÀÃÕÇ]/', '', $colab['nome_completo']);

            // Get all active docs
            $docStmt = $db->prepare(
                "SELECT d.arquivo_nome, d.arquivo_path, td.nome as tipo_nome
                 FROM documentos d
                 JOIN tipos_documento td ON d.tipo_documento_id = td.id
                 WHERE d.colaborador_id = :cid AND d.status != 'obsoleto' AND d.excluido_em IS NULL
                 ORDER BY td.categoria, d.data_emissao DESC"
            );
            $docStmt->execute(['cid' => $colab['id']]);
            $docs = $docStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($docs as $doc) {
                $filePath = $uploadPath . '/' . $doc['arquivo_path'];
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, "{$obraNome}/{$colabNome}/{$doc['arquivo_nome']}");
                    $fileCount++;
                }
            }
        }

        $zip->close();

        if ($fileCount === 0) {
            @unlink($tmpFile);
            $this->flash('warning', 'Nenhum documento encontrado para os colaboradores desta obra.');
            $this->redirect("/obras/{$id}");
            return;
        }

        LoggerMiddleware::log('download', "ZIP obra '{$obra['nome']}' com {$fileCount} docs de " . count($colaboradores) . " colaboradores");

        // Send ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $obraNome . '.zip"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Pragma: no-cache');
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    public function create(string $clienteId): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $clienteModel = new Cliente();
        $this->view('obras/form', [
            'obra'      => null,
            'cliente'   => $clienteModel->find((int)$clienteId),
            'pageTitle' => 'Clientes',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Obra();
        $data = [
            'cliente_id'  => (int)$this->input('cliente_id'),
            'nome'        => trim($this->input('nome', '')),
            'local_obra'  => trim($this->input('local_obra', '')),
            'data_inicio' => $this->input('data_inicio') ?: null,
            'data_fim'    => $this->input('data_fim') ?: null,
            'status'      => $this->input('status', 'ativa'),
        ];
        $id = $model->create($data);
        LoggerMiddleware::log('criar', "Obra criada: {$data['nome']} (ID: {$id})");
        $this->flash('success', 'Obra cadastrada.');
        $this->redirect("/clientes/{$data['cliente_id']}");
    }

    public function edit(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Obra();
        $obra = $model->find((int)$id);
        if (!$obra) $this->redirect('/clientes');
        $clienteModel = new Cliente();
        $this->view('obras/form', [
            'obra'      => $obra,
            'cliente'   => $clienteModel->find($obra['cliente_id']),
            'pageTitle' => 'Clientes',
        ]);
    }

    public function update(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Obra();
        $data = [
            'nome'        => trim($this->input('nome', '')),
            'local_obra'  => trim($this->input('local_obra', '')),
            'data_inicio' => $this->input('data_inicio') ?: null,
            'data_fim'    => $this->input('data_fim') ?: null,
            'status'      => $this->input('status', 'ativa'),
        ];
        $model->update((int)$id, $data);
        $obra = $model->find((int)$id);
        LoggerMiddleware::log('editar', "Obra atualizada: {$data['nome']} (ID: {$id})");
        $this->flash('success', 'Obra atualizada.');
        $this->redirect("/clientes/{$obra['cliente_id']}");
    }
}
