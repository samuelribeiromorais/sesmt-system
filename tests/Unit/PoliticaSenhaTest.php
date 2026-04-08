<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes da politica de senha reforçada.
 * Usa reflexao para testar o metodo privado validarPoliticaSenha().
 */
class PoliticaSenhaTest extends TestCase
{
    private \ReflectionMethod $validar;
    private object $controller;

    protected function setUp(): void
    {
        $this->controller = new \App\Controllers\UsuarioController();
        $this->validar = new \ReflectionMethod($this->controller, 'validarPoliticaSenha');
        $this->validar->setAccessible(true);
    }

    private function validarSenha(string $senha): ?string
    {
        return $this->validar->invoke($this->controller, $senha);
    }

    // ── Senhas validas ──────────────────────────────────────────────────────

    public function testSenhaForteValida(): void
    {
        $this->assertNull($this->validarSenha('Minha$enha2026!'));
    }

    public function testSenhaComplexaValida(): void
    {
        $this->assertNull($this->validarSenha('T$e#Eng3nharia!'));
    }

    public function testSenha12CaracteresValida(): void
    {
        $this->assertNull($this->validarSenha('Klm!8206xYzW'));
    }

    // ── Tamanho minimo ──────────────────────────────────────────────────────

    public function testSenhaCurta8CaracteresRejeita(): void
    {
        $erro = $this->validarSenha('Abc!1234');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('12 caracteres', $erro);
    }

    public function testSenhaCurta11CaracteresRejeita(): void
    {
        $erro = $this->validarSenha('Abc!1234567');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('12 caracteres', $erro);
    }

    // ── Tamanho maximo ──────────────────────────────────────────────────────

    public function testSenhaMuitoLongaRejeita(): void
    {
        $senha = str_repeat('Aa1!', 33); // 132 chars
        $erro = $this->validarSenha($senha);
        $this->assertNotNull($erro);
        $this->assertStringContainsString('128 caracteres', $erro);
    }

    // ── Complexidade ────────────────────────────────────────────────────────

    public function testSemMaiusculaRejeita(): void
    {
        $erro = $this->validarSenha('minha$enha2026!');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('maiuscula', $erro);
    }

    public function testSemMinusculaRejeita(): void
    {
        $erro = $this->validarSenha('MINHA$ENHA2026!');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('minuscula', $erro);
    }

    public function testSemNumeroRejeita(): void
    {
        $erro = $this->validarSenha('Minha$enhaTSE!');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('numero', $erro);
    }

    public function testSemCaractereEspecialRejeita(): void
    {
        $erro = $this->validarSenha('MinhaSenha2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('especial', $erro);
    }

    // ── Sequencias proibidas ────────────────────────────────────────────────

    public function testSequenciaCaracteresIguaisRejeita(): void
    {
        $erro = $this->validarSenha('Minha!aaaa2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('iguais seguidos', $erro);
    }

    public function testSequencia1111Rejeita(): void
    {
        $erro = $this->validarSenha('Minha!1111Teste');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('iguais seguidos', $erro);
    }

    public function testSequencia1234Rejeita(): void
    {
        $erro = $this->validarSenha('Minha!1234Teste');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('sequencias numericas', $erro);
    }

    public function testSequencia4321Rejeita(): void
    {
        $erro = $this->validarSenha('Minha!4321Teste');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('sequencias numericas', $erro);
    }

    public function testSequenciaAbcdRejeita(): void
    {
        $erro = $this->validarSenha('Minha!abcd2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('alfabeticas', $erro);
    }

    public function testSequenciaQwerRejeita(): void
    {
        $erro = $this->validarSenha('Minha!qwer2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('alfabeticas', $erro);
    }

    public function testSequenciaAsdfRejeita(): void
    {
        $erro = $this->validarSenha('Minha!asdf2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('alfabeticas', $erro);
    }

    // ── Palavras comuns ─────────────────────────────────────────────────────

    public function testPalavraSenhaRejeita(): void
    {
        $erro = $this->validarSenha('Minhasenha!2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('comuns', $erro);
    }

    public function testPalavraPasswordRejeita(): void
    {
        $erro = $this->validarSenha('MyPassword!2026');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('comuns', $erro);
    }

    public function testPalavraAdminRejeita(): void
    {
        $erro = $this->validarSenha('MeuAdmin!20266');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('comuns', $erro);
    }

    public function testPalavraSesmtRejeita(): void
    {
        $erro = $this->validarSenha('MeuSesmt!20266');
        $this->assertNotNull($erro);
        $this->assertStringContainsString('comuns', $erro);
    }

    // ── Caracteres especiais aceitos ────────────────────────────────────────

    /**
     * @dataProvider caracteresEspeciaisProvider
     */
    public function testDiversosCaracteresEspeciaisAceitos(string $char): void
    {
        $senha = "Klmno8206x{$char}Zw";
        $this->assertNull($this->validarSenha($senha),
            "Caractere especial '{$char}' deveria ser aceito.");
    }

    public static function caracteresEspeciaisProvider(): array
    {
        return [
            ['!'], ['@'], ['#'], ['$'], ['%'], ['^'], ['&'], ['*'],
            ['('], [')'], ['-'], ['_'], ['+'], ['='], ['.'], [','],
        ];
    }
}
