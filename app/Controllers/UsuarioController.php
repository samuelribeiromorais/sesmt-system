<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\ApiToken;
use App\Models\Usuario;
use App\Models\SessaoAtiva;
use App\Services\TotpService;
use App\Services\CryptoService;

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

    // ========================================================================
    // Tema (Dark Mode)
    // ========================================================================

    public function salvarTema(): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        if (!$userId) {
            $this->json(['error' => 'Nao autenticado'], 401);
            return;
        }

        $tema = $this->input('tema', 'light');
        if (!in_array($tema, ['light', 'dark'])) {
            $tema = 'light';
        }

        Session::set('user_tema', $tema);

        // Save to DB
        try {
            $model = new Usuario();
            $model->update((int)$userId, ['tema' => $tema]);
        } catch (\Exception $e) {
            // Column may not exist yet, just save in session
        }

        $this->json(['success' => true, 'tema' => $tema]);
    }

    // ========================================================================
    // 2FA - Autenticação em Duas Etapas
    // ========================================================================

    /**
     * Exibe a página de configuração do 2FA.
     */
    public function setup2fa(): void
    {
        $userId = Session::get('user_id');
        $model = new Usuario();
        $user = $model->find($userId);

        if (!$user) {
            $this->redirect('/dashboard');
        }

        $totp = new TotpService();

        // Se já tem 2FA ativo, mostrar opção de desativar
        if (!empty($user['totp_ativo'])) {
            $this->view('config/2fa-setup', [
                'pageTitle'  => 'Autenticacao 2FA',
                'totp_ativo' => true,
                'secret'     => '',
                'qr_url'     => '',
            ]);
            return;
        }

        // Gerar novo segredo
        $secret = $totp->generateSecret();
        $qrUrl = $totp->getQrUrl($user['email'], $secret);

        $this->view('config/2fa-setup', [
            'pageTitle'  => 'Autenticacao 2FA',
            'totp_ativo' => false,
            'secret'     => $secret,
            'qr_url'     => $qrUrl,
        ]);
    }

    /**
     * Ativa o 2FA após verificar o código.
     */
    public function enable2fa(): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        $secret = trim($this->input('secret', ''));
        $code = trim($this->input('totp_code', ''));

        if (empty($secret) || empty($code)) {
            $this->flash('error', 'Informe o codigo de verificacao.');
            $this->redirect('/usuarios/2fa/setup');
        }

        $totp = new TotpService();
        if (!$totp->verify($secret, $code)) {
            $this->flash('error', 'Codigo invalido. Tente novamente.');
            $this->redirect('/usuarios/2fa/setup');
        }

        $model = new Usuario();
        $model->update($userId, [
            'totp_secret' => CryptoService::encrypt($secret),
            'totp_ativo'  => 1,
        ]);

        LoggerMiddleware::log('2fa', '2FA ativado pelo usuario');
        $this->flash('success', 'Autenticacao em duas etapas ativada com sucesso.');
        $this->redirect('/usuarios/2fa/setup');
    }

    /**
     * Desativa o 2FA para o usuário logado.
     */
    public function disable2fa(): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        $model = new Usuario();

        $model->update($userId, [
            'totp_secret' => null,
            'totp_ativo'  => 0,
        ]);

        LoggerMiddleware::log('2fa', '2FA desativado pelo usuario');
        $this->flash('success', 'Autenticacao em duas etapas desativada.');
        $this->redirect('/usuarios/2fa/setup');
    }

    // ========================================================================
    // Sessões Ativas
    // ========================================================================

    /**
     * Lista as sessões ativas do usuário logado.
     */
    public function sessoes(): void
    {
        $userId = Session::get('user_id');
        $model = new SessaoAtiva();
        $sessoes = $model->findByUsuario($userId);

        $this->view('config/sessoes', [
            'pageTitle' => 'Sessoes Ativas',
            'sessoes'   => $sessoes,
        ]);
    }

    /**
     * Encerra uma sessão específica do usuário.
     */
    public function encerrarSessao(string $id): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        $model = new SessaoAtiva();
        $sessao = $model->find((int)$id);

        // Verificar se a sessão pertence ao usuário logado
        if (!$sessao || (int)$sessao['usuario_id'] !== $userId) {
            $this->flash('error', 'Sessao nao encontrada.');
            $this->redirect('/usuarios/sessoes');
        }

        // Não permitir encerrar a sessão atual
        if ($sessao['session_id'] === session_id()) {
            $this->flash('error', 'Voce nao pode encerrar sua sessao atual. Use o logout.');
            $this->redirect('/usuarios/sessoes');
        }

        // Destruir o arquivo de sessão do PHP
        $sessionSavePath = session_save_path();
        if (!empty($sessionSavePath)) {
            $sessionFile = $sessionSavePath . '/sess_' . $sessao['session_id'];
            if (file_exists($sessionFile)) {
                unlink($sessionFile);
            }
        }

        // Remover do banco
        $model->removerPorId((int)$id);

        LoggerMiddleware::log('sessao', "Sessao remota encerrada (IP: {$sessao['ip_address']})");
        $this->flash('success', 'Sessao encerrada com sucesso.');
        $this->redirect('/usuarios/sessoes');
    }

    // ========================================================================
    // Política de Senha
    // ========================================================================

    /**
     * Exibe o formulário de alteração de senha.
     */
    public function alterarSenhaForm(): void
    {
        $userId = Session::get('user_id');
        $model = new Usuario();
        $user = $model->find($userId);

        $senhaExpirada = false;
        if (!empty($user['senha_alterada_em'])) {
            $dias = (time() - strtotime($user['senha_alterada_em'])) / 86400;
            $senhaExpirada = $dias > 90;
        }

        $this->view('config/alterar-senha', [
            'pageTitle'      => 'Alterar Senha',
            'senha_expirada' => $senhaExpirada,
        ]);
    }

    /**
     * Processa a alteração de senha com validação de política.
     */
    public function alterarSenha(): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        $senhaAtual = $this->input('senha_atual', '');
        $novaSenha = $this->input('nova_senha', '');
        $confirmarSenha = $this->input('confirmar_senha', '');

        // Validar campos preenchidos
        if (empty($senhaAtual) || empty($novaSenha) || empty($confirmarSenha)) {
            $this->flash('error', 'Preencha todos os campos.');
            $this->redirect('/usuarios/alterar-senha');
        }

        // Validar confirmação
        if ($novaSenha !== $confirmarSenha) {
            $this->flash('error', 'A nova senha e a confirmacao nao conferem.');
            $this->redirect('/usuarios/alterar-senha');
        }

        // Validar política de senha
        $erroPolicy = $this->validarPoliticaSenha($novaSenha);
        if ($erroPolicy) {
            $this->flash('error', $erroPolicy);
            $this->redirect('/usuarios/alterar-senha');
        }

        $model = new Usuario();
        $user = $model->find($userId);

        if (!$user) {
            $this->redirect('/dashboard');
        }

        // Verificar senha atual
        if (!password_verify($senhaAtual, $user['senha_hash'])) {
            $this->flash('error', 'Senha atual incorreta.');
            $this->redirect('/usuarios/alterar-senha');
        }

        // Verificar histórico de senhas (últimas 5)
        $historico = [];
        if (!empty($user['senha_historico'])) {
            $historico = json_decode($user['senha_historico'], true) ?: [];
        }

        foreach ($historico as $senhaAntiga) {
            if (password_verify($novaSenha, $senhaAntiga)) {
                $this->flash('error', 'A nova senha nao pode ser igual a uma das 5 ultimas senhas utilizadas.');
                $this->redirect('/usuarios/alterar-senha');
            }
        }

        // Também verificar contra a senha atual
        if (password_verify($novaSenha, $user['senha_hash'])) {
            $this->flash('error', 'A nova senha nao pode ser igual a senha atual.');
            $this->redirect('/usuarios/alterar-senha');
        }

        // Atualizar histórico (manter últimas 5)
        $historico[] = $user['senha_hash'];
        if (count($historico) > 5) {
            $historico = array_slice($historico, -5);
        }

        // Salvar nova senha
        $novoHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
        $model->update($userId, [
            'senha_hash'       => $novoHash,
            'senha_alterada_em' => date('Y-m-d H:i:s'),
            'senha_historico'  => json_encode($historico),
        ]);

        LoggerMiddleware::log('senha', 'Senha alterada pelo usuario');
        $this->flash('success', 'Senha alterada com sucesso.');
        $this->redirect('/dashboard');
    }

    /**
     * Valida a política de complexidade da senha.
     * Retorna mensagem de erro ou null se válida.
     */
    private function validarPoliticaSenha(string $senha): ?string
    {
        if (strlen($senha) < 8) {
            return 'A senha deve ter no minimo 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $senha)) {
            return 'A senha deve conter pelo menos uma letra maiuscula.';
        }
        if (!preg_match('/[a-z]/', $senha)) {
            return 'A senha deve conter pelo menos uma letra minuscula.';
        }
        if (!preg_match('/[0-9]/', $senha)) {
            return 'A senha deve conter pelo menos um numero.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            return 'A senha deve conter pelo menos um caractere especial.';
        }
        return null;
    }

    // ========================================================================
    // API Tokens
    // ========================================================================

    /**
     * Lista os tokens de API do usuario logado.
     */
    public function apiTokens(): void
    {
        $userId = Session::get('user_id');
        $model = new ApiToken();
        $tokens = $model->findByUsuario($userId);

        $this->view('config/api-tokens', [
            'pageTitle' => 'Tokens de API',
            'tokens'    => $tokens,
        ]);
    }

    /**
     * Gera um novo token de API para o usuario logado.
     */
    public function createApiToken(): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        $nome = trim($this->input('nome', ''));

        if (empty($nome)) {
            $this->flash('error', 'Informe um nome para o token.');
            $this->redirect('/usuarios/api-tokens');
            return;
        }

        $token = bin2hex(random_bytes(32)); // 64 hex chars
        $tokenHash = hash('sha256', $token);

        $model = new ApiToken();
        $model->create([
            'usuario_id' => $userId,
            'token_hash' => $tokenHash,
            'nome'       => $nome,
            'ativo'      => 1,
            'criado_em'  => date('Y-m-d H:i:s'),
        ]);

        LoggerMiddleware::log('api_token', "Token de API criado: {$nome}");

        // Show the token once
        $this->flash('success', "Token criado com sucesso. Copie agora, ele nao sera exibido novamente: {$token}");
        $this->redirect('/usuarios/api-tokens');
    }

    /**
     * Revoga (desativa) um token de API do usuario logado.
     */
    public function revokeApiToken(string $id): void
    {
        $this->requirePost();

        $userId = Session::get('user_id');
        $model = new ApiToken();
        $tokenRecord = $model->find((int) $id);

        if (!$tokenRecord || (int) $tokenRecord['usuario_id'] !== $userId) {
            $this->flash('error', 'Token nao encontrado.');
            $this->redirect('/usuarios/api-tokens');
            return;
        }

        $model->update((int) $id, ['ativo' => 0]);

        LoggerMiddleware::log('api_token', "Token de API revogado: {$tokenRecord['nome']}");
        $this->flash('success', "Token '{$tokenRecord['nome']}' revogado com sucesso.");
        $this->redirect('/usuarios/api-tokens');
    }
}
