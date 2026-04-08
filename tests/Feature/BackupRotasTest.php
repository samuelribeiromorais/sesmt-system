<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Testes de integracao das rotas de backup.
 * Verifica que as rotas corretas estao registradas e as removidas nao existem.
 */
class BackupRotasTest extends TestCase
{
    private string $routesContent;

    protected function setUp(): void
    {
        $this->routesContent = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
    }

    // ── Rotas que devem existir ──────────────────────────────────────────────

    public function testRotaBackupIndexExiste(): void
    {
        $this->assertMatchesRegularExpression(
            "/router->get\('\/backup'/",
            $this->routesContent,
            "Rota GET /backup nao encontrada."
        );
    }

    public function testRotaBackupExportarExiste(): void
    {
        $this->assertMatchesRegularExpression(
            "/router->get\('\/backup\/exportar'/",
            $this->routesContent,
            "Rota GET /backup/exportar nao encontrada."
        );
    }

    public function testRotaBackupIndexUsaAuthMiddleware(): void
    {
        preg_match("/router->get\('\/backup',[^\)]+\)/s", $this->routesContent, $matches);
        $this->assertNotEmpty($matches, "Rota /backup nao encontrada.");
        $this->assertStringContainsString('AuthMiddleware', $matches[0],
            "Rota /backup deve ter AuthMiddleware.");
    }

    public function testRotaBackupExportarUsaAuthMiddleware(): void
    {
        preg_match("/router->get\('\/backup\/exportar',[^\)]+\)/s", $this->routesContent, $matches);
        $this->assertNotEmpty($matches, "Rota /backup/exportar nao encontrada.");
        $this->assertStringContainsString('AuthMiddleware', $matches[0],
            "Rota /backup/exportar deve ter AuthMiddleware.");
    }

    public function testRotaBackupUsaBackupController(): void
    {
        preg_match("/router->get\('\/backup',[^\)]+\)/s", $this->routesContent, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('BackupController', $matches[0]);
    }

    // ── Rotas que NAO devem existir (removidas) ─────────────────────────────

    public function testRotaExecutarRemovida(): void
    {
        $this->assertStringNotContainsString("/backup/executar", $this->routesContent,
            "Rota /backup/executar deveria ter sido removida.");
    }

    public function testRotaDownloadRemovida(): void
    {
        $this->assertStringNotContainsString("/backup/download", $this->routesContent,
            "Rota /backup/download deveria ter sido removida.");
    }

    public function testRotaExcluirRemovida(): void
    {
        $this->assertStringNotContainsString("/backup/excluir", $this->routesContent,
            "Rota /backup/excluir deveria ter sido removida.");
    }

    public function testRotaConfigurarCronRemovida(): void
    {
        $this->assertStringNotContainsString("/backup/configurar-cron", $this->routesContent,
            "Rota /backup/configurar-cron deveria ter sido removida.");
    }

    public function testRotaLogsAntigaRemovida(): void
    {
        $this->assertStringNotContainsString("/backup/logs", $this->routesContent,
            "Rota /backup/logs deveria ter sido removida (agora e o index).");
    }

    // ── Nenhuma rota POST de backup ─────────────────────────────────────────

    public function testNenhumaRotaPostDeBackup(): void
    {
        preg_match_all("/router->post\('\/backup/", $this->routesContent, $matches);
        $this->assertEmpty($matches[0],
            "Nao deve existir nenhuma rota POST /backup/* (backup e somente leitura).");
    }

    // ── Comentario de documentacao ──────────────────────────────────────────

    public function testComentarioIndicaSomenteLogs(): void
    {
        $this->assertStringContainsString('somente log', $this->routesContent,
            "Comentario nas rotas deve indicar que backup e somente log.");
    }
}
