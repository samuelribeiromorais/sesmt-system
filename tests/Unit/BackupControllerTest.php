<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do BackupController.
 * Verifica a estrutura do controller sem necessidade de banco de dados.
 */
class BackupControllerTest extends TestCase
{
    private string $controllerFile;
    private string $controllerContent;

    protected function setUp(): void
    {
        $this->controllerFile = dirname(__DIR__, 2) . '/app/Controllers/BackupController.php';
        $this->controllerContent = file_get_contents($this->controllerFile);
    }

    // ── Estrutura do controller ─────────────────────────────────────────────

    public function testControllerExiste(): void
    {
        $this->assertFileExists($this->controllerFile);
    }

    public function testControllerExtendeController(): void
    {
        $this->assertStringContainsString('extends Controller', $this->controllerContent);
    }

    public function testControllerUsaRoleMiddleware(): void
    {
        $this->assertStringContainsString('use App\Middleware\RoleMiddleware', $this->controllerContent);
    }

    public function testControllerUsaLogAcesso(): void
    {
        $this->assertStringContainsString('use App\Models\LogAcesso', $this->controllerContent);
    }

    // ── Metodos existem ─────────────────────────────────────────────────────

    public function testMetodoIndexExiste(): void
    {
        $this->assertStringContainsString('public function index()', $this->controllerContent);
    }

    public function testMetodoExportarExiste(): void
    {
        $this->assertStringContainsString('public function exportar()', $this->controllerContent);
    }

    // ── Metodos removidos (nao devem existir) ───────────────────────────────

    public function testMetodoExecutarNaoExiste(): void
    {
        $this->assertStringNotContainsString('public function executar()', $this->controllerContent,
            'O metodo executar() deveria ter sido removido do controller.');
    }

    public function testMetodoDownloadNaoExiste(): void
    {
        $this->assertStringNotContainsString('public function download(', $this->controllerContent,
            'O metodo download() deveria ter sido removido do controller.');
    }

    public function testMetodoExcluirNaoExiste(): void
    {
        $this->assertStringNotContainsString('public function excluir(', $this->controllerContent,
            'O metodo excluir() deveria ter sido removido do controller.');
    }

    public function testMetodoConfigurarCronNaoExiste(): void
    {
        $this->assertStringNotContainsString('public function configurarCron()', $this->controllerContent,
            'O metodo configurarCron() deveria ter sido removido do controller.');
    }

    // ── Segurança ───────────────────────────────────────────────────────────

    public function testIndexRequerAdmin(): void
    {
        $this->assertStringContainsString('RoleMiddleware::requireAdmin()', $this->controllerContent,
            'O metodo index() deve exigir perfil admin.');
    }

    public function testExportarRequerAdmin(): void
    {
        // Verifica que requireAdmin aparece no metodo exportar
        preg_match('/public function exportar\(\).*?\{(.*?)\n    \}/s', $this->controllerContent, $matches);
        $this->assertNotEmpty($matches, 'Metodo exportar() nao encontrado.');
        $this->assertStringContainsString('requireAdmin', $matches[1],
            'O metodo exportar() deve exigir perfil admin.');
    }

    // ── Sem credenciais hardcoded ────────────────────────────────────────────

    public function testSemCredenciaisHardcoded(): void
    {
        $this->assertStringNotContainsString('sesmt2026', $this->controllerContent,
            'Controller nao deve conter senhas hardcoded.');
        $this->assertStringNotContainsString('-psesmt', $this->controllerContent,
            'Controller nao deve conter senhas no comando mysqldump.');
    }

    public function testSemMysqldump(): void
    {
        $this->assertStringNotContainsString('mysqldump', $this->controllerContent,
            'Controller de log nao deve executar mysqldump.');
    }

    // ── Exportacao CSV ──────────────────────────────────────────────────────

    public function testExportarGeraCSV(): void
    {
        $this->assertStringContainsString('text/csv', $this->controllerContent,
            'Exportar deve gerar arquivo CSV.');
    }

    public function testExportarTemBomUtf8(): void
    {
        $this->assertStringContainsString('chr(0xEF)', $this->controllerContent,
            'Exportar deve incluir BOM UTF-8 para compatibilidade com Excel.');
    }

    // ── Filtro por acao backup ───────────────────────────────────────────────

    public function testIndexFiltraPorAcaoBackup(): void
    {
        $this->assertStringContainsString("'backup'", $this->controllerContent,
            'Index deve filtrar logs pela acao backup.');
    }
}
