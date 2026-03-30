<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifica que strings de exibição críticas nos arquivos de view
 * possuem acentuação gráfica correta em português.
 */
class AcentuacaoTest extends TestCase
{
    private string $viewsDir;
    private string $controllersDir;

    protected function setUp(): void
    {
        $this->viewsDir       = dirname(__DIR__, 2) . '/app/Views';
        $this->controllersDir = dirname(__DIR__, 2) . '/app/Controllers';
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function fileContains(string $relPath, string $needle): bool
    {
        $content = file_get_contents($this->viewsDir . '/' . $relPath);
        return str_contains($content, $needle);
    }

    private function viewContent(string $relPath): string
    {
        return file_get_contents($this->viewsDir . '/' . $relPath);
    }

    private function controllerContent(string $name): string
    {
        return file_get_contents($this->controllersDir . '/' . $name);
    }

    // ── palavras que NÃO devem aparecer sem acento em exibição ──────────────

    public function testDashboardTemProducaoComAcento(): void
    {
        $content = $this->viewContent('dashboard/index.php');
        $this->assertStringContainsString('Produção', $content,
            '"Producao" deveria ser "Produção" no dashboard.');
    }

    public function testDashboardTemDistribuicaoComAcento(): void
    {
        $content = $this->viewContent('dashboard/index.php');
        $this->assertStringContainsString('Distribuição', $content,
            '"Distribuicao" deveria ser "Distribuição" no dashboard.');
    }

    public function testDashboardTemAprovacaoComAcento(): void
    {
        $content = $this->viewContent('dashboard/index.php');
        $this->assertStringContainsString('Aprovações', $content,
            '"Aprovacoes" deveria ser "Aprovações" no dashboard.');
    }

    public function testDashboardTemProximoComAcento(): void
    {
        $content = $this->viewContent('dashboard/index.php');
        // "Próximo" deve aparecer em algum texto de exibição
        $this->assertStringContainsString('Próximo', $content,
            '"Proximo" deveria ser "Próximo" no dashboard.');
    }

    public function testDashboardNaoContemProducaoSemAcento(): void
    {
        $content = $this->viewContent('dashboard/index.php');
        // "Producao" sem acento não deve aparecer em contexto de exibição
        // (pode aparecer em proximo_vencimento - snake_case é aceitável)
        $this->assertStringNotContainsString('>Producao<', $content);
        $this->assertStringNotContainsString('"Producao"', $content);
        $this->assertStringNotContainsString("'Producao'", $content);
    }

    public function testRelatorioLayoutTemAcento(): void
    {
        $content = $this->viewContent('layouts/app.php');
        $this->assertStringContainsString('Relatórios', $content,
            'Menu "Relatorios" deveria ser "Relatórios" no layout.');
    }

    public function testRelatorioMensalTemAcento(): void
    {
        $content = $this->viewContent('relatorios/mensal.php');
        $this->assertStringContainsString('Relatório', $content);
    }

    public function testRelatorioMensalTemMarcoCorreto(): void
    {
        $content = $this->viewContent('relatorios/mensal.php');
        $this->assertStringContainsString('Março', $content,
            'Mês "Marco" deveria ser "Março" no array de meses.');
        // Não deve existir como string isolada sem acento
        $this->assertStringNotContainsString("'Marco'", $content);
    }

    public function testRelatorioTipoDocTemAcento(): void
    {
        $content = $this->viewContent('relatorios/tipo-documento.php');
        $this->assertStringContainsString('Relatório', $content);
        $this->assertStringContainsString('Emissão', $content);
        $this->assertStringContainsString('Próximo', $content);
    }

    public function testKitPjImprimirTemTermosMedicos(): void
    {
        $content = $this->viewContent('kit-pj/imprimir.php');
        $this->assertStringContainsString('Clínica', $content,
            '"Clinica" deveria ser "Clínica" no Kit PJ.');
        $this->assertStringContainsString('Médico', $content,
            '"Medico" deveria ser "Médico" no Kit PJ.');
        $this->assertStringContainsString('Ergonômicos', $content,
            '"Ergonomicos" deveria ser "Ergonômicos" no Kit PJ.');
    }

    public function testColaboradoresIndexTemObrigatorio(): void
    {
        $content = $this->viewContent('colaboradores/index.php');
        // Se a view usa "obrigatorio" em algum texto de exibição, deve ter acento
        if (preg_match('/(?<![a-z_])obrigatorio(?![_a-z])/i', $content)) {
            $this->assertStringContainsString('obrigatório', $content,
                '"obrigatorio" deveria ser "obrigatório".');
        }
        $this->assertTrue(true); // passa se a palavra não aparecer
    }

    public function testConfiguracoesTemAcento(): void
    {
        $content = $this->viewContent('config/index.php');
        $this->assertStringContainsString('Configuração', $content);
    }

    // ── garante que snake_case do banco NÃO foi alterado ─────────────────────

    public function testSnakeCaseProximoVencimentoIntacto(): void
    {
        // Verifica um controller que usa o status enum do banco
        $content = $this->controllerContent('DocumentoController.php');
        $this->assertStringContainsString('proximo_vencimento', $content,
            'O status enum "proximo_vencimento" do banco não deve ter acento.');
    }

    public function testFuncaoPHPDateIntacta(): void
    {
        // A função PHP date() não deve ter sido alterada
        foreach (['AgendaExamesController.php', 'BackupController.php', 'RelatorioController.php'] as $file) {
            $content = $this->controllerContent($file);
            $this->assertStringNotContainsString('daté(', $content,
                "Função PHP date() foi corrompida em $file.");
        }
    }

    public function testFuncaoPHPCreateIntacta(): void
    {
        foreach (['ColaboradorController.php', 'ClienteController.php'] as $file) {
            $content = $this->controllerContent($file);
            $this->assertStringNotContainsString('creaté', $content,
                "Método create() foi corrompido em $file.");
        }
    }

    public function testClassesCSSHifenadasIntactas(): void
    {
        // Classes CSS badge-proximo, badge-vencido não devem ter acento
        // pois são definidas no CSS sem acento
        $content = $this->viewContent('dashboard/index.php');
        $this->assertStringNotContainsString('badge-próximo', $content,
            'Classe CSS badge-proximo não deve ter acento.');
        $this->assertStringNotContainsString('badge-vencído', $content,
            'Classe CSS badge-vencido não deve ter acento.');
    }

    public function testControllerRelatorioTemAcento(): void
    {
        $content = $this->controllerContent('RelatorioController.php');
        $this->assertStringContainsString('Relatório', $content,
            'pageTitle "Relatorios" deveria ser "Relatório" no controller.');
    }

    public function testFlashMessagesNaoTemNaoSemAcento(): void
    {
        // Mensagens flash de erro/sucesso devem usar "não" com acento
        foreach (['ColaboradorController.php', 'DocumentoController.php', 'ClienteController.php'] as $file) {
            $content = $this->controllerContent($file);
            // Busca mensagens flash que contenham 'nao' sem acento
            preg_match_all("/flash\(['\"][^'\"]+['\"],\s*['\"]([^'\"]+)['\"]\)/", $content, $matches);
            foreach ($matches[1] as $msg) {
                if (preg_match('/\bnao\b/i', $msg)) {
                    $this->fail("Mensagem flash em $file contém 'nao' sem acento: \"$msg\"");
                }
            }
        }
        $this->assertTrue(true);
    }
}
