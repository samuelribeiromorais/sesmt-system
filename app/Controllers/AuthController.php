<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;
use App\Middleware\LoggerMiddleware;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Session::isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        $error = $_GET['error'] ?? '';
        $expired = $_GET['expired'] ?? '';
        View::render('auth/login', [
            'error' => $error,
            'expired' => $expired,
        ], 'auth');
    }

    public function login(): void
    {
        $this->requirePost();

        $token = $_POST['_csrf_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            $this->redirect('/login?error=csrf');
        }

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $this->redirect('/login?error=empty');
        }

        $userModel = new Usuario();
        $user = $userModel->findByEmail($email);

        if (!$user) {
            $this->redirect('/login?error=invalid');
        }

        if (!$user['ativo']) {
            $this->redirect('/login?error=inactive');
        }

        if ($userModel->isLocked($user)) {
            $minutos = ceil((strtotime($user['bloqueado_ate']) - time()) / 60);
            $this->redirect('/login?error=locked&min=' . $minutos);
        }

        if (!password_verify($senha, $user['senha_hash'])) {
            $userModel->incrementLoginAttempts($user['id']);
            LoggerMiddleware::log('login_falha', "Tentativa de login falha para: {$email}");
            $this->redirect('/login?error=invalid');
        }

        // Login OK
        $userModel->resetLoginAttempts($user['id']);
        Session::generateCsrfToken();

        Session::set('user_id', $user['id']);
        Session::set('user_nome', $user['nome']);
        Session::set('user_email', $user['email']);
        Session::set('user_perfil', $user['perfil']);

        LoggerMiddleware::log('login', "Login realizado: {$user['nome']} ({$user['email']})");

        if ($user['perfil'] === 'rh') {
            $this->redirect('/colaboradores');
        }
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        LoggerMiddleware::log('logout', 'Logout realizado');
        Session::destroy();
        $this->redirect('/login');
    }
}
