<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\ApiAuthMiddleware;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Certificado;
use App\Models\Cliente;
use App\Models\Obra;

class ApiController extends Controller
{
    private function paginate(int $total, int $page, int $perPage): array
    {
        return [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function colaboradores(): void
    {
        ApiAuthMiddleware::check();

        $page    = max(1, (int) ($this->input('page', 1)));
        $perPage = min(100, max(1, (int) ($this->input('per_page', 30))));
        $status  = $this->input('status', '');
        $search  = trim($this->input('q', ''));
        $offset  = ($page - 1) * $perPage;

        $model = new Colaborador();

        if ($search) {
            $data  = $model->search($search, $status, $perPage, $offset);
            $total = $model->searchCount($search, $status);
        } else {
            $conditions = $status ? ['status' => $status] : [];
            $data  = $model->allWithRelations($conditions, 'c.nome_completo ASC', $perPage, $offset);
            $total = $model->count($conditions);
        }

        $this->json([
            'data' => $data,
            'meta' => $this->paginate($total, $page, $perPage),
        ]);
    }

    public function colaborador(string $id): void
    {
        ApiAuthMiddleware::check();

        $model = new Colaborador();
        $colab = $model->findWithRelations((int) $id);

        if (!$colab) {
            $this->json(['error' => 'Colaborador não encontrado.'], 404);
        }

        $certModel = new Certificado();
        $docModel  = new Documento();

        $colab['certificados'] = $certModel->getLatestByColaborador((int) $id);
        $colab['documentos']   = $docModel->findByColaborador((int) $id);

        $this->json(['data' => $colab]);
    }

    public function documentos(): void
    {
        ApiAuthMiddleware::check();

        $page    = max(1, (int) ($this->input('page', 1)));
        $perPage = min(100, max(1, (int) ($this->input('per_page', 30))));
        $status  = $this->input('status', '');
        $offset  = ($page - 1) * $perPage;

        $model = new Documento();
        $conditions = $status ? ['status' => $status] : [];
        $total = $model->count($conditions);
        $data  = $model->all($conditions, 'id DESC', $perPage, $offset);

        $this->json([
            'data' => $data,
            'meta' => $this->paginate($total, $page, $perPage),
        ]);
    }

    public function documento(string $id): void
    {
        ApiAuthMiddleware::check();

        $model = new Documento();
        $doc   = $model->find((int) $id);

        if (!$doc) {
            $this->json(['error' => 'Documento não encontrado.'], 404);
        }

        $this->json(['data' => $doc]);
    }

    public function certificados(): void
    {
        ApiAuthMiddleware::check();

        $page    = max(1, (int) ($this->input('page', 1)));
        $perPage = min(100, max(1, (int) ($this->input('per_page', 30))));
        $status  = $this->input('status', '');
        $offset  = ($page - 1) * $perPage;

        $model = new Certificado();
        $conditions = $status ? ['status' => $status] : [];
        $total = $model->count($conditions);
        $data  = $model->all($conditions, 'id DESC', $perPage, $offset);

        $this->json([
            'data' => $data,
            'meta' => $this->paginate($total, $page, $perPage),
        ]);
    }

    public function certificado(string $id): void
    {
        ApiAuthMiddleware::check();

        $model = new Certificado();
        $cert  = $model->find((int) $id);

        if (!$cert) {
            $this->json(['error' => 'Certificado não encontrado.'], 404);
        }

        $this->json(['data' => $cert]);
    }

    public function clientes(): void
    {
        ApiAuthMiddleware::check();

        $model = new Cliente();
        $data  = $model->all([], 'id ASC');

        $this->json(['data' => $data]);
    }

    public function obras(): void
    {
        ApiAuthMiddleware::check();

        $model = new Obra();
        $data  = $model->all([], 'id ASC');

        $this->json(['data' => $data]);
    }

    public function stats(): void
    {
        ApiAuthMiddleware::check();

        $colabModel = new Colaborador();
        $docModel   = new Documento();
        $certModel  = new Certificado();
        $clienteModel = new Cliente();
        $obraModel  = new Obra();

        $this->json([
            'data' => [
                'colaboradores' => [
                    'total'      => $colabModel->count(),
                    'por_status' => $colabModel->countByStatus(),
                ],
                'documentos' => [
                    'total'      => $docModel->count(),
                    'por_status' => $docModel->countByStatus(),
                ],
                'certificados' => [
                    'total'      => $certModel->count(),
                    'por_status' => $certModel->countByStatus(),
                ],
                'clientes' => [
                    'total' => $clienteModel->count(),
                ],
                'obras' => [
                    'total' => $obraModel->count(),
                ],
            ],
        ]);
    }
}
