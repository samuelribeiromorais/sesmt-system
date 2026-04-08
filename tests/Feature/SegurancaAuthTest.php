<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Testes de integracao de seguranca: 2FA obrigatorio, politica de senha,
 * e configuracoes de autenticacao.
 */
class SegurancaAuthTest extends TestCase
{
    private string $authController;
    private string $usuarioController;
    private string $viewSetup2fa;
    private string $viewAlterarSenha;

    protected function setUp(): void
    {
        $base = dirname(__DIR__, 2);
        $this->authController = file_get_contents($base . '/app/Controllers/AuthController.php');
        $this->usuarioController = file_get_contents($base . '/app/Controllers/UsuarioController.php');
        $this->viewSetup2fa = file_get_contents($base . '/app/Views/config/2fa-setup.php');
        $this->viewAlterarSenha = file_get_contents($base . '/app/Views/config/alterar-senha.php');
    }

    // ── 2FA obrigatorio ─────────────────────────────────────────────────────

    public function test2faObrigatorioParaAdmin(): void
    {
        $this->assertStringContainsString("'admin'", $this->authController);
        $this->assertStringContainsString('totp_ativo', $this->authController);
        $this->assertStringContainsString('/usuarios/2fa/setup', $this->authController,
            'Admin sem 2FA deve ser redirecionado para setup.');
    }

    public function test2faObrigatorioParaSesmt(): void
    {
        $this->assertStringContainsString("'sesmt'", $this->authController);
        // Verifica que admin e sesmt estao na mesma condicao
        $this->assertMatchesRegularExpression(
            "/in_array.*perfil.*admin.*sesmt.*totp_ativo/s",
            $this->authController,
            '2FA deve ser obrigatorio para admin e sesmt.'
        );
    }

    public function test2faNaoObrigatorioParaRh(): void
    {
        // A condicao so deve incluir admin e sesmt, nao rh
        preg_match("/in_array\(\\\$user\['perfil'\],\s*\[([^\]]+)\]\)\s*&&\s*empty\(\\\$user\['totp_ativo'\]\)/", $this->authController, $matches);
        $this->assertNotEmpty($matches, 'Condicao de 2FA obrigatorio nao encontrada.');
        $this->assertStringNotContainsString("'rh'", $matches[1],
            '2FA nao deve ser obrigatorio para perfil RH.');
    }

    // ── View 2FA ────────────────────────────────────────────────────────────

    public function testViewReferenciasMicrosoftAuthenticator(): void
    {
        $this->assertStringContainsString('Microsoft Authenticator', $this->viewSetup2fa);
    }

    public function testViewLinkGooglePlay(): void
    {
        $this->assertStringContainsString('play.google.com', $this->viewSetup2fa);
        $this->assertStringContainsString('azure.authenticator', $this->viewSetup2fa);
    }

    public function testViewLinkAppStore(): void
    {
        $this->assertStringContainsString('apps.apple.com', $this->viewSetup2fa);
    }

    public function testViewMostraObrigatoriedade(): void
    {
        $this->assertStringContainsString('obrigatorio', $this->viewSetup2fa,
            'View deve informar que 2FA e obrigatorio para admin/sesmt.');
    }

    public function testViewBloqueiaDesativacaoParaObrigatorios(): void
    {
        $this->assertStringContainsString('nao pode ser desativado', $this->viewSetup2fa,
            'View deve informar que admin/sesmt nao podem desativar o 2FA.');
    }

    public function testViewUsaQrServerEmVezDeGoogle(): void
    {
        $this->assertStringContainsString('api.qrserver.com', $this->viewSetup2fa,
            'QR Code deve usar qrserver.com em vez da API descontinuada do Google.');
        $this->assertStringNotContainsString('chart.googleapis.com', $this->viewSetup2fa,
            'Nao deve usar a API de QR Code do Google (descontinuada).');
    }

    // ── Politica de senha no controller ─────────────────────────────────────

    public function testSenhaMinimo12Caracteres(): void
    {
        $this->assertStringContainsString('12', $this->usuarioController);
        $this->assertStringNotContainsString("strlen(\$senha) < 8", $this->usuarioController,
            'Minimo de senha deve ser 12, nao 8.');
    }

    public function testValidacaoNaCriacaoDeUsuario(): void
    {
        // Verifica que validarPoliticaSenha e chamado no metodo store()
        preg_match('/public function store\(\).*?public function/s', $this->usuarioController, $storeBlock);
        $this->assertNotEmpty($storeBlock);
        $this->assertStringContainsString('validarPoliticaSenha', $storeBlock[0],
            'Criacao de usuario deve validar politica de senha.');
    }

    public function testValidacaoNoResetDeSenha(): void
    {
        preg_match('/public function resetPassword\(.*?public function/s', $this->usuarioController, $resetBlock);
        $this->assertNotEmpty($resetBlock);
        $this->assertStringContainsString('validarPoliticaSenha', $resetBlock[0],
            'Reset de senha deve validar politica de senha.');
    }

    public function testValidacaoNaAlteracaoDeSenha(): void
    {
        preg_match('/public function alterarSenha\(\).*?public function|private function/s', $this->usuarioController, $alterarBlock);
        $this->assertNotEmpty($alterarBlock);
        $this->assertStringContainsString('validarPoliticaSenha', $alterarBlock[0],
            'Alteracao de senha deve validar politica de senha.');
    }

    public function testBloqueioSequencias(): void
    {
        $this->assertStringContainsString('1234', $this->usuarioController);
        $this->assertStringContainsString('qwer', $this->usuarioController);
        $this->assertStringContainsString('asdf', $this->usuarioController);
    }

    public function testBloqueioPalavrasComuns(): void
    {
        $this->assertStringContainsString("'senha'", $this->usuarioController);
        $this->assertStringContainsString("'password'", $this->usuarioController);
        $this->assertStringContainsString("'admin'", $this->usuarioController);
    }

    // ── View alterar senha ──────────────────────────────────────────────────

    public function testViewAlterarSenhaMostra12Caracteres(): void
    {
        $this->assertStringContainsString('12 caracteres', $this->viewAlterarSenha);
    }

    public function testViewAlterarSenhaMinlength12(): void
    {
        $this->assertStringContainsString('minlength="12"', $this->viewAlterarSenha,
            'Input de nova senha deve ter minlength=12.');
    }

    public function testViewAlterarSenhaMostraRegraSequencias(): void
    {
        $this->assertStringContainsString('sequencias', $this->viewAlterarSenha,
            'View deve mencionar bloqueio de sequencias.');
    }

    public function testViewAlterarSenhaMostraRegraPalavrasComuns(): void
    {
        $this->assertStringContainsString('palavras comuns', $this->viewAlterarSenha,
            'View deve mencionar bloqueio de palavras comuns.');
    }

    // ── TOTP padrao compativel ──────────────────────────────────────────────

    public function testTotpUsaSha1Padrao(): void
    {
        $totpService = file_get_contents(dirname(__DIR__, 2) . '/app/Services/TotpService.php');
        $this->assertStringContainsString("'sha1'", $totpService,
            'TOTP deve usar SHA1 (padrao RFC 6238, compativel com Microsoft Authenticator).');
    }

    public function testTotp6Digitos(): void
    {
        $totpService = file_get_contents(dirname(__DIR__, 2) . '/app/Services/TotpService.php');
        $this->assertStringContainsString('DIGITS = 6', $totpService);
    }

    public function testTotp30Segundos(): void
    {
        $totpService = file_get_contents(dirname(__DIR__, 2) . '/app/Services/TotpService.php');
        $this->assertStringContainsString('PERIOD = 30', $totpService);
    }

    public function testTotpIssuerSesmtTse(): void
    {
        $totpService = file_get_contents(dirname(__DIR__, 2) . '/app/Services/TotpService.php');
        $this->assertStringContainsString('SESMT-TSE', $totpService,
            'Issuer deve ser SESMT-TSE para identificacao no app.');
    }

    // ── Bcrypt cost ─────────────────────────────────────────────────────────

    public function testBcryptCost12(): void
    {
        $this->assertStringContainsString("'cost' => 12", $this->usuarioController,
            'Bcrypt deve usar cost 12.');
    }

    // ── Historico de senhas ─────────────────────────────────────────────────

    public function testHistorico5Senhas(): void
    {
        $this->assertStringContainsString('> 5', $this->usuarioController,
            'Deve manter historico de 5 senhas.');
    }

    // ── Expiracao 90 dias ───────────────────────────────────────────────────

    public function testSenhaExpira90Dias(): void
    {
        $this->assertStringContainsString('> 90', $this->authController,
            'Senha deve expirar apos 90 dias.');
    }
}
