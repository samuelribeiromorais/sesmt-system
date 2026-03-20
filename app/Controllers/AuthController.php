<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;
use App\Models\SessaoAtiva;
use App\Middleware\LoggerMiddleware;
use App\Services\TotpService;
use App\Services\CryptoService;

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

        // Regenerar session ID para prevenir session fixation
        session_regenerate_id(true);

        // Senha correta - verificar se 2FA está ativo
        if (!empty($user['totp_ativo'])) {
            // Armazenar dados temporários na sessão para o fluxo 2FA
            Session::set('2fa_user_id', $user['id']);
            Session::set('2fa_pending', true);
            Session::generateCsrfToken();
            $this->redirect('/login/2fa');
        }

        // Login completo (sem 2FA)
        $this->completeLogin($user, $userModel);
    }

    /**
     * Exibe o formulário de verificação 2FA.
     */
    public function twoFactorForm(): void
    {
        if (!Session::has('2fa_pending')) {
            $this->redirect('/login');
        }

        $error = $_GET['error'] ?? '';
        View::render('auth/2fa', [
            'error' => $error,
        ], 'auth');
    }

    /**
     * Verifica o código TOTP e completa o login.
     */
    public function twoFactorVerify(): void
    {
        $this->requirePost();

        if (!Session::has('2fa_pending')) {
            $this->redirect('/login');
        }

        $token = $_POST['_csrf_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            $this->redirect('/login/2fa?error=csrf');
        }

        $code = trim($_POST['totp_code'] ?? '');
        if (empty($code) || strlen($code) !== 6) {
            $this->redirect('/login/2fa?error=empty');
        }

        $userId = Session::get('2fa_user_id');
        $userModel = new Usuario();
        $user = $userModel->find($userId);

        if (!$user) {
            Session::remove('2fa_pending');
            Session::remove('2fa_user_id');
            $this->redirect('/login?error=invalid');
        }

        $totp = new TotpService();
        $decryptedSecret = CryptoService::decrypt($user['totp_secret']);
        if (!$totp->verify($decryptedSecret, $code)) {
            LoggerMiddleware::log('2fa_falha', "Codigo 2FA invalido para: {$user['email']}");
            $this->redirect('/login/2fa?error=invalid');
        }

        // Limpar dados temporários do 2FA
        Session::remove('2fa_pending');
        Session::remove('2fa_user_id');

        // Regenerar session ID após 2FA bem-sucedido
        session_regenerate_id(true);

        // Completar login
        $this->completeLogin($user, $userModel);
    }

    /**
     * Finaliza o processo de login: seta sessão, registra sessão ativa, verifica senha expirada.
     */
    private function completeLogin(array $user, Usuario $userModel): void
    {
        $userModel->resetLoginAttempts($user['id']);
        Session::generateCsrfToken();

        Session::set('user_id', $user['id']);
        Session::set('user_nome', $user['nome']);
        Session::set('user_email', $user['email']);
        Session::set('user_perfil', $user['perfil']);
        Session::set('user_tema', $user['tema'] ?? 'light');

        // Registrar sessão ativa
        $sessaoModel = new SessaoAtiva();
        $sessaoModel->registrar($user['id'], session_id());

        LoggerMiddleware::log('login', "Login realizado: {$user['nome']} ({$user['email']})");

        // Verificar se a senha expirou (mais de 90 dias)
        if (!empty($user['senha_alterada_em'])) {
            $diasDesdeAlteracao = (time() - strtotime($user['senha_alterada_em'])) / 86400;
            if ($diasDesdeAlteracao > 90) {
                $this->flash('warning', 'Sua senha expirou. Por favor, altere sua senha para continuar.');
                $this->redirect('/usuarios/alterar-senha');
            }
        }

        if ($user['perfil'] === 'rh') {
            $this->redirect('/colaboradores');
        }
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        // Remover sessão ativa do banco
        $sessaoModel = new SessaoAtiva();
        $sessaoModel->removerPorSessionId(session_id());

        LoggerMiddleware::log('logout', 'Logout realizado');
        Session::destroy();
        $this->redirect('/login');
    }
}
