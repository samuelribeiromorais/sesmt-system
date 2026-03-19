<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Cliente;
use App\Models\Obra;

class ClienteController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $model = new Cliente();
        $this->view('clientes/index', [
            'clientes'  => $model->all([], 'nome_fantasia ASC'),
            'pageTitle' => 'Clientes',
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
        $model = new Cliente();
        $data = [
            'razao_social'   => trim($this->input('razao_social', '')),
            'nome_fantasia'  => trim($this->input('nome_fantasia', '')),
            'cnpj'           => trim($this->input('cnpj', '')),
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

        $this->view('clientes/show', [
            'cliente'   => $cliente,
            'obras'     => $obraModel->all(['cliente_id' => (int)$id], 'nome ASC'),
            'pageTitle' => 'Clientes',
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
        $model = new Cliente();
        $data = [
            'razao_social'   => trim($this->input('razao_social', '')),
            'nome_fantasia'  => trim($this->input('nome_fantasia', '')),
            'cnpj'           => trim($this->input('cnpj', '')),
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
}
