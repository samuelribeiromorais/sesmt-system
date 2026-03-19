<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Usuario;

class UsuarioController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Usuario();
        $this->view('config/usuarios', [
            'usuarios'  => $model->all([], 'nome ASC'),
            'pageTitle' => 'Usuarios',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $nome = trim($this->input('nome', ''));
        $email = trim($this->input('email', ''));
        $senha = $this->input('senha', '');
        $perfil = $this->input('perfil', 'rh');

        if (empty($nome) || empty($email) || empty($senha)) {
            $this->flash('error', 'Preencha todos os campos.');
            $this->redirect('/usuarios');
        }

        $model = new Usuario();
        if ($model->findByEmail($email)) {
            $this->flash('error', 'Este email ja esta cadastrado.');
            $this->redirect('/usuarios');
        }

        $id = $model->create([
            'nome'      => $nome,
            'email'     => $email,
            'senha_hash' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
            'perfil'    => $perfil,
        ]);

        LoggerMiddleware::log('usuario', "Usuario criado: {$nome} ({$email}) - Perfil: {$perfil}");
        $this->flash('success', "Usuario {$nome} criado com sucesso.");
        $this->redirect('/usuarios');
    }

    public function resetPassword(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $novaSenha = $this->input('nova_senha', '');
        if (empty($novaSenha)) {
            $this->flash('error', 'Informe a nova senha.');
            $this->redirect('/usuarios');
        }

        $model = new Usuario();
        $user = $model->find((int)$id);
        if (!$user) {
            $this->redirect('/usuarios');
        }

        $model->update((int)$id, [
            'senha_hash'        => password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]),
            'tentativas_login'  => 0,
            'bloqueado_ate'     => null,
        ]);

        LoggerMiddleware::log('usuario', "Senha resetada: {$user['nome']} ({$user['email']})");
        $this->flash('success', "Senha de {$user['nome']} alterada.");
        $this->redirect('/usuarios');
    }

    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Usuario();
        $user = $model->find((int)$id);
        if ($user) {
            $model->delete((int)$id);
            LoggerMiddleware::log('usuario', "Usuario excluido: {$user['nome']} ({$user['email']})");
            $this->flash('success', "Usuario {$user['nome']} excluido.");
        }
        $this->redirect('/usuarios');
    }
}
