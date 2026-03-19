<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Models\Certificado;
use App\Models\Documento;
use App\Models\Cliente;
use App\Services\ReportService;

class RelatorioController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $clienteModel = new Cliente();

        $this->view('relatorios/index', [
            'colaboradores' => $colabModel->all(['status' => 'ativo'], 'nome_completo ASC'),
            'clientes'      => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'pageTitle'     => 'Relatorios',
        ]);
    }

    public function porColaborador(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $format = $this->input('format', 'html');

        if ($format === 'excel') {
            $this->exportExcelColaborador((int)$id);
            return;
        }

        $colabModel = new Colaborador();
        $colab = $colabModel->findWithRelations((int)$id);
        if (!$colab) $this->redirect('/relatorios');

        $certModel = new Certificado();
        $docModel = new Documento();

        $this->view('relatorios/colaborador', [
            'colab'        => $colab,
            'certificados' => $certModel->getLatestByColaborador((int)$id),
            'documentos'   => $docModel->findByColaborador((int)$id),
            'pageTitle'    => 'Relatorios',
        ]);
    }

    public function porCliente(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $format = $this->input('format', 'excel');

        try {
            $service = new ReportService();
            $filePath = $service->excelCliente((int)$id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatorio', "Relatorio de conformidade gerado para cliente ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatorio: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }

    private function exportExcelColaborador(int $id): void
    {
        try {
            $service = new ReportService();
            $filePath = $service->excelColaborador($id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatorio', "Relatorio Excel gerado para colaborador ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatorio: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }
}
