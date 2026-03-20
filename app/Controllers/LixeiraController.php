<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Certificado;

class LixeiraController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdmin();

        $colabModel = new Colaborador();
        $docModel = new Documento();
        $certModel = new Certificado();

        $this->view('lixeira/index', [
            'colaboradores' => $colabModel->getDeleted(),
            'documentos'    => $docModel->getDeleted(),
            'certificados'  => $certModel->getDeleted(),
            'pageTitle'     => 'Lixeira',
        ]);
    }

    public function restaurar(string $tipo, string $id): void
    {
        RoleMiddleware::requireAdmin();

        $itemId = (int) $id;

        switch ($tipo) {
            case 'colaborador':
                $model = new Colaborador();
                $item = $model->find($itemId);
                if ($item) {
                    $model->update($itemId, ['excluido_em' => null]);
                    LoggerMiddleware::log('restaurar', "Colaborador restaurado: {$item['nome_completo']} (ID: {$itemId})");
                    $this->flash('success', 'Colaborador restaurado com sucesso.');
                }
                break;

            case 'documento':
                $model = new Documento();
                $item = $model->find($itemId);
                if ($item) {
                    $model->update($itemId, ['excluido_em' => null]);
                    LoggerMiddleware::log('restaurar', "Documento restaurado: {$item['arquivo_nome']} (ID: {$itemId})");
                    $this->flash('success', 'Documento restaurado com sucesso.');
                }
                break;

            case 'certificado':
                $model = new Certificado();
                $item = $model->find($itemId);
                if ($item) {
                    $model->update($itemId, ['excluido_em' => null]);
                    LoggerMiddleware::log('restaurar', "Certificado restaurado (ID: {$itemId})");
                    $this->flash('success', 'Certificado restaurado com sucesso.');
                }
                break;

            default:
                $this->flash('error', 'Tipo invalido.');
        }

        $this->redirect('/lixeira');
    }

    public function excluirPermanente(string $tipo, string $id): void
    {
        RoleMiddleware::requireAdmin();

        $itemId = (int) $id;

        switch ($tipo) {
            case 'colaborador':
                $model = new Colaborador();
                $item = $model->find($itemId);
                if ($item && $item['excluido_em']) {
                    $model->delete($itemId);
                    LoggerMiddleware::log('excluir_permanente', "Colaborador excluido permanentemente: {$item['nome_completo']} (ID: {$itemId})");
                    $this->flash('success', 'Colaborador excluido permanentemente.');
                }
                break;

            case 'documento':
                $model = new Documento();
                $item = $model->find($itemId);
                if ($item && $item['excluido_em']) {
                    // Delete physical file
                    $config = require dirname(__DIR__) . '/config/app.php';
                    $filePath = $config['upload']['path'] . '/' . $item['arquivo_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $model->delete($itemId);
                    LoggerMiddleware::log('excluir_permanente', "Documento excluido permanentemente: {$item['arquivo_nome']} (ID: {$itemId})");
                    $this->flash('success', 'Documento excluido permanentemente.');
                }
                break;

            case 'certificado':
                $model = new Certificado();
                $item = $model->find($itemId);
                if ($item && $item['excluido_em']) {
                    $model->delete($itemId);
                    LoggerMiddleware::log('excluir_permanente', "Certificado excluido permanentemente (ID: {$itemId})");
                    $this->flash('success', 'Certificado excluido permanentemente.');
                }
                break;

            default:
                $this->flash('error', 'Tipo invalido.');
        }

        $this->redirect('/lixeira');
    }
}
