<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Models\Certificado;
use App\Models\Documento;
use App\Models\Cliente;
use App\Models\Obra;
use App\Services\CryptoService;

class ColaboradorController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAny();

        $model = new Colaborador();
        $search = trim($this->input('q', ''));
        $status = $this->input('status', '');
        $format = $this->input('format', '');
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        // JSON response for live search
        if ($format === 'json') {
            $results = $model->search($search, $status, 10, 0);
            $this->json(array_map(fn($c) => [
                'id' => $c['id'],
                'nome_completo' => $c['nome_completo'],
                'cargo' => $c['cargo'] ?? $c['funcao'] ?? '',
                'cliente_nome' => $c['cliente_nome'] ?? '',
            ], $results));
            return;
        }

        if ($search) {
            $colaboradores = $model->search($search, $status, $perPage, $offset);
            $total = $model->searchCount($search, $status);
        } else {
            $colaboradores = $model->allWithRelations(
                $status ? ['status' => $status] : [],
                'c.nome_completo ASC',
                $perPage,
                $offset
            );
            $total = $model->count($status ? ['status' => $status] : []);
        }

        $this->view('colaboradores/index', [
            'colaboradores' => $colaboradores,
            'search'        => $search,
            'status'        => $status,
            'page'          => $page,
            'totalPages'    => max(1, ceil($total / $perPage)),
            'total'         => $total,
            'pageTitle'     => 'Colaboradores',
            'isReadOnly'    => Session::get('user_perfil') === 'rh',
        ]);
    }

    public function show(string $id): void
    {
        RoleMiddleware::requireAny();

        $model = new Colaborador();
        $colab = $model->findWithRelations((int)$id);
        if (!$colab) {
            $this->redirect('/colaboradores');
        }

        // Decrypt CPF for display (masked)
        $cpfDisplay = '***.***.***-**';
        if (!empty($colab['cpf_encrypted'])) {
            try {
                $cpf = CryptoService::decrypt($colab['cpf_encrypted']);
                $cpfDisplay = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.***-' . substr($cpf, -2);
            } catch (\Exception $e) {}
        }

        $certModel = new Certificado();
        $docModel = new Documento();

        $this->view('colaboradores/show', [
            'colab'         => $colab,
            'cpfDisplay'    => $cpfDisplay,
            'certificados'  => $certModel->getLatestByColaborador((int)$id),
            'documentos'    => $docModel->findByColaborador((int)$id),
            'pageTitle'     => 'Colaboradores',
            'isReadOnly'    => Session::get('user_perfil') === 'rh',
        ]);
    }

    public function create(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $clienteModel = new Cliente();
        $obraModel = new Obra();

        $this->view('colaboradores/form', [
            'colab'     => null,
            'clientes'  => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'     => $obraModel->all(['status' => 'ativa'], 'nome ASC'),
            'pageTitle' => 'Colaboradores',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cpf = preg_replace('/\D/', '', $this->input('cpf', ''));

        $data = [
            'nome_completo'  => trim($this->input('nome_completo', '')),
            'cpf_encrypted'  => $cpf ? CryptoService::encrypt($cpf) : null,
            'cpf_hash'       => $cpf ? CryptoService::hash($cpf) : null,
            'matricula'      => trim($this->input('matricula', '')),
            'cargo'          => trim($this->input('cargo', '')),
            'funcao'         => trim($this->input('funcao', '')),
            'setor'          => trim($this->input('setor', '')),
            'cliente_id'     => $this->input('cliente_id') ?: null,
            'obra_id'        => $this->input('obra_id') ?: null,
            'data_admissao'  => $this->input('data_admissao') ?: null,
            'status'         => $this->input('status', 'ativo'),
            'unidade'        => trim($this->input('unidade', '')),
        ];

        $model = new Colaborador();
        $id = $model->create($data);

        LoggerMiddleware::log('criar', "Colaborador criado: {$data['nome_completo']} (ID: {$id})");
        $this->flash('success', 'Colaborador cadastrado com sucesso.');
        $this->redirect("/colaboradores/{$id}");
    }

    public function edit(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Colaborador();
        $colab = $model->find((int)$id);
        if (!$colab) {
            $this->redirect('/colaboradores');
        }

        // Decrypt CPF for edit form
        if (!empty($colab['cpf_encrypted'])) {
            try {
                $colab['cpf_plain'] = CryptoService::decrypt($colab['cpf_encrypted']);
            } catch (\Exception $e) {
                $colab['cpf_plain'] = '';
            }
        }

        $clienteModel = new Cliente();
        $obraModel = new Obra();

        $this->view('colaboradores/form', [
            'colab'     => $colab,
            'clientes'  => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'     => $obraModel->all(['status' => 'ativa'], 'nome ASC'),
            'pageTitle' => 'Colaboradores',
        ]);
    }

    public function update(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cpf = preg_replace('/\D/', '', $this->input('cpf', ''));

        $data = [
            'nome_completo'  => trim($this->input('nome_completo', '')),
            'matricula'      => trim($this->input('matricula', '')),
            'cargo'          => trim($this->input('cargo', '')),
            'funcao'         => trim($this->input('funcao', '')),
            'setor'          => trim($this->input('setor', '')),
            'cliente_id'     => $this->input('cliente_id') ?: null,
            'obra_id'        => $this->input('obra_id') ?: null,
            'data_admissao'  => $this->input('data_admissao') ?: null,
            'data_demissao'  => $this->input('data_demissao') ?: null,
            'status'         => $this->input('status', 'ativo'),
            'unidade'        => trim($this->input('unidade', '')),
        ];

        if ($cpf) {
            $data['cpf_encrypted'] = CryptoService::encrypt($cpf);
            $data['cpf_hash'] = CryptoService::hash($cpf);
        }

        $model = new Colaborador();
        $model->update((int)$id, $data);

        LoggerMiddleware::log('editar', "Colaborador atualizado: {$data['nome_completo']} (ID: {$id})");
        $this->flash('success', 'Colaborador atualizado com sucesso.');
        $this->redirect("/colaboradores/{$id}");
    }

    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Colaborador();
        $colab = $model->find((int)$id);
        if ($colab) {
            $model->delete((int)$id);
            LoggerMiddleware::log('excluir', "Colaborador excluido: {$colab['nome_completo']} (ID: {$id})");
            $this->flash('success', 'Colaborador excluido.');
        }
        $this->redirect('/colaboradores');
    }
}
