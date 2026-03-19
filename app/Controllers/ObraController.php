<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Obra;
use App\Models\Cliente;

class ObraController extends Controller
{
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
