<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Testes da view de backup.
 * Verifica que a view exibe somente logs e nao contem elementos de execucao.
 */
class BackupViewTest extends TestCase
{
    private string $viewContent;
    private string $layoutContent;

    protected function setUp(): void
    {
        $this->viewContent = file_get_contents(dirname(__DIR__, 2) . '/app/Views/backup/index.php');
        $this->layoutContent = file_get_contents(dirname(__DIR__, 2) . '/app/Views/layouts/app.php');
    }

    // ── Elementos que devem existir ─────────────────────────────────────────

    public function testViewExiste(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/app/Views/backup/index.php');
    }

    public function testViewContemTituloLogDeBackup(): void
    {
        $this->assertStringContainsString('Log de Backup', $this->viewContent);
    }

    public function testViewContemTabelaDeLogs(): void
    {
        $this->assertStringContainsString('<table>', $this->viewContent);
        $this->assertStringContainsString('Data/Hora', $this->viewContent);
        $this->assertStringContainsString('Usuario', $this->viewContent);
        $this->assertStringContainsString('Operacao', $this->viewContent);
        $this->assertStringContainsString('IP', $this->viewContent);
    }

    public function testViewContemFiltroDeData(): void
    {
        $this->assertStringContainsString('data_inicio', $this->viewContent);
        $this->assertStringContainsString('data_fim', $this->viewContent);
        $this->assertStringContainsString('type="date"', $this->viewContent);
    }

    public function testViewContemBotaoExportarCSV(): void
    {
        $this->assertStringContainsString('/backup/exportar', $this->viewContent);
        $this->assertStringContainsString('Exportar CSV', $this->viewContent);
    }

    public function testViewContemBadgesDeStatus(): void
    {
        $this->assertStringContainsString('Executado', $this->viewContent);
        $this->assertStringContainsString('Falha', $this->viewContent);
        $this->assertStringContainsString('Download', $this->viewContent);
        $this->assertStringContainsString('Excluido', $this->viewContent);
    }

    public function testViewContemMensagemVazia(): void
    {
        $this->assertStringContainsString('Nenhum registro de backup encontrado', $this->viewContent);
    }

    public function testViewContemInfoBackupAutomatico(): void
    {
        $this->assertStringContainsString('automaticamente pelo container Docker', $this->viewContent);
    }

    // ── Elementos que NAO devem existir (removidos) ─────────────────────────

    public function testViewNaoContemBotaoExecutar(): void
    {
        $this->assertStringNotContainsString('Executar Backup Agora', $this->viewContent,
            'View nao deve conter botao de executar backup.');
    }

    public function testViewNaoContemFormularioCron(): void
    {
        $this->assertStringNotContainsString('configurar-cron', $this->viewContent,
            'View nao deve conter formulario de configuracao de cron.');
        $this->assertStringNotContainsString('Backup Automatico Diario', $this->viewContent,
            'View nao deve conter secao de backup automatico.');
    }

    public function testViewNaoContemListaDeArquivos(): void
    {
        $this->assertStringNotContainsString('Backups Disponiveis', $this->viewContent,
            'View nao deve listar arquivos de backup.');
    }

    public function testViewNaoContemBotaoBaixar(): void
    {
        $this->assertStringNotContainsString('/backup/download', $this->viewContent,
            'View nao deve conter links de download de backup.');
    }

    public function testViewNaoContemBotaoExcluir(): void
    {
        $this->assertStringNotContainsString('/backup/excluir', $this->viewContent,
            'View nao deve conter botoes de exclusao de backup.');
    }

    // ── View de logs separada foi removida ──────────────────────────────────

    public function testViewLogsSeparadaRemovida(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2) . '/app/Views/backup/logs.php',
            'A view backup/logs.php deveria ter sido removida (agora e o index).'
        );
    }

    // ── Sidebar ─────────────────────────────────────────────────────────────

    public function testSidebarExibeLogDeBackup(): void
    {
        $this->assertStringContainsString('Log de Backup', $this->layoutContent,
            'Sidebar deve exibir "Log de Backup" em vez de "Backup".');
    }

    public function testSidebarLinkBackupApenasParaAdmin(): void
    {
        // O link de backup deve estar dentro do bloco admin-only
        preg_match("/if\s*\(\\\$user\['perfil'\]\s*===\s*'admin'\).*?endif/s", $this->layoutContent, $adminBlock);
        $this->assertNotEmpty($adminBlock, 'Bloco admin-only nao encontrado no layout.');
        $this->assertStringContainsString('/backup', $adminBlock[0],
            'Link /backup deve estar dentro do bloco admin-only.');
    }
}
