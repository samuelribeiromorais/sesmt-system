<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Testa que as rotas críticas estão registradas no arquivo de rotas
 * e possuem os middlewares corretos.
 */
class RotasTest extends TestCase
{
    private string $routesFile;
    private string $routesContent;

    protected function setUp(): void
    {
        $this->routesFile    = dirname(__DIR__, 2) . '/public/index.php';
        $this->routesContent = file_get_contents($this->routesFile);
    }

    // ── helper ───────────────────────────────────────────────────────────────

    private function assertRouteExists(string $method, string $path, string $controller = ''): void
    {
        $method = strtolower($method);
        $pattern = "/router->{$method}\('" . preg_quote($path, '/') . "'/";
        $this->assertMatchesRegularExpression(
            $pattern,
            $this->routesContent,
            "Rota {$method} '{$path}' não encontrada."
        );

        if ($controller) {
            $this->assertStringContainsString($controller, $this->routesContent,
                "Controller '{$controller}' não encontrado.");
        }
    }

    private function assertRouteHasMiddleware(string $path, string $middleware): void
    {
        // Localiza o bloco da rota e verifica que o middleware está presente
        preg_match_all(
            "/router->\w+\('" . preg_quote($path, '/') . "',[^\)]+\)/s",
            $this->routesContent,
            $matches
        );
        $this->assertNotEmpty($matches[0], "Rota '{$path}' não encontrada.");
        foreach ($matches[0] as $routeDecl) {
            $this->assertStringContainsString($middleware, $routeDecl,
                "Rota '{$path}' deveria ter middleware '{$middleware}'.");
        }
    }

    // ── rotas públicas ───────────────────────────────────────────────────────

    public function testRotaLoginExiste(): void
    {
        $this->assertRouteExists('GET',  '/login', 'AuthController');
        $this->assertRouteExists('POST', '/login', 'AuthController');
    }

    public function testRotaLogoutExiste(): void
    {
        $this->assertRouteExists('GET', '/logout', 'AuthController');
    }

    // ── rotas autenticadas ───────────────────────────────────────────────────

    public function testRotaDashboardExiste(): void
    {
        $this->assertRouteExists('GET', '/dashboard', 'DashboardController');
        $this->assertRouteHasMiddleware('/dashboard', 'AuthMiddleware');
    }

    public function testRotaColaboradoresExiste(): void
    {
        $this->assertRouteExists('GET',  '/colaboradores',         'ColaboradorController');
        $this->assertRouteExists('GET',  '/colaboradores/novo',    'ColaboradorController');
        $this->assertRouteExists('POST', '/colaboradores/salvar',  'ColaboradorController');
    }

    public function testRotaColaboradoresSalvarTemCsrf(): void
    {
        $this->assertRouteHasMiddleware('/colaboradores/salvar', 'CsrfMiddleware');
    }

    public function testRotaDocumentosExiste(): void
    {
        $this->assertRouteExists('GET', '/documentos', 'DocumentoController');
    }

    public function testRotaCertificadosExiste(): void
    {
        $this->assertRouteExists('GET', '/certificados', 'CertificadoController');
    }

    public function testRotaTreinamentosExiste(): void
    {
        $this->assertRouteExists('GET', '/treinamentos',         'TreinamentoController');
        $this->assertRouteExists('GET', '/treinamentos/novo',    'TreinamentoController');
        $this->assertRouteExists('GET', '/treinamentos/calendario', 'TreinamentoController');
    }

    // ── Kit PJ ───────────────────────────────────────────────────────────────

    public function testRotaKitPjExiste(): void
    {
        $this->assertRouteExists('GET',  '/kit-pj',        'KitPjController');
        $this->assertRouteExists('GET',  '/kit-pj/novo',   'KitPjController');
        $this->assertRouteExists('POST', '/kit-pj/salvar', 'KitPjController');
    }

    public function testRotaKitPjSalvarTemCsrfEAuth(): void
    {
        $this->assertRouteHasMiddleware('/kit-pj/salvar', 'CsrfMiddleware');
        $this->assertRouteHasMiddleware('/kit-pj/salvar', 'AuthMiddleware');
    }

    // ── Relatórios ───────────────────────────────────────────────────────────

    public function testRotaRelatoriosExiste(): void
    {
        $this->assertRouteExists('GET', '/relatorios',                'RelatorioController');
        $this->assertRouteHasMiddleware('/relatorios', 'AuthMiddleware');
    }

    public function testRotaRelatorioMensalExiste(): void
    {
        $this->assertRouteExists('GET', '/relatorios/mensal', 'RelatorioController');
        $this->assertRouteHasMiddleware('/relatorios/mensal', 'AuthMiddleware');
    }

    public function testRotaRelatorioTipoDocumentoExiste(): void
    {
        $this->assertRouteExists('GET', '/relatorios/tipo-documento', 'RelatorioController');
        $this->assertRouteHasMiddleware('/relatorios/tipo-documento', 'AuthMiddleware');
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function testRotasApiTemApiAuthMiddleware(): void
    {
        preg_match_all(
            "/router->\w+\('\/api\/v1\/[^']+',\s*\[[^\]]+\](?:,\s*\[([^\]]*)\])?\)/",
            $this->routesContent,
            $matches,
            PREG_SET_ORDER
        );
        $this->assertNotEmpty($matches, 'Nenhuma rota /api/v1/ encontrada.');
        foreach ($matches as $match) {
            $this->assertStringContainsString('ApiAuthMiddleware', $match[0],
                'Rota API sem ApiAuthMiddleware: ' . $match[0]);
        }
    }

    // ── Notificações / Config ─────────────────────────────────────────────────

    public function testRotaNotificacoesExiste(): void
    {
        $this->assertRouteExists('GET', '/notificacoes', 'NotificacaoController');
    }

    public function testRotaConfigExiste(): void
    {
        $this->assertRouteExists('GET', '/configuracoes', 'ConfigController');
    }

    // ── E-social ─────────────────────────────────────────────────────────────

    public function testRotaEsocialExiste(): void
    {
        $this->assertRouteExists('GET', '/esocial', 'EsocialController');
    }

    // ── Lixeira ──────────────────────────────────────────────────────────────

    public function testRotaLixeiraExiste(): void
    {
        $this->assertRouteExists('GET', '/lixeira', 'LixeiraController');
    }

    // ── Integracao GCO ───────────────────────────────────────────────────────

    public function testRotaGcoExiste(): void
    {
        $this->assertRouteExists('GET', '/gco', 'GcoController');
        $this->assertRouteHasMiddleware('/gco', 'AuthMiddleware');
    }

    public function testRotaGcoSincronizarTemCsrfEAuth(): void
    {
        $this->assertRouteExists('POST', '/gco/sincronizar', 'GcoController');
        $this->assertRouteHasMiddleware('/gco/sincronizar', 'CsrfMiddleware');
        $this->assertRouteHasMiddleware('/gco/sincronizar', 'AuthMiddleware');
    }

    // ── Celular manual do colaborador ────────────────────────────────────────

    public function testRotaCelularColaboradorExiste(): void
    {
        $this->assertRouteExists('POST', '/colaboradores/{id}/celular', 'ColaboradorController');
    }

    public function testRotaCelularColaboradorTemCsrf(): void
    {
        $this->assertRouteHasMiddleware('/colaboradores/{id}/celular', 'CsrfMiddleware');
    }

    // ── Relatorio vencidos ───────────────────────────────────────────────────

    public function testRotaRelatorioVencidosExiste(): void
    {
        $this->assertRouteExists('GET', '/relatorios/vencidos', 'RelatorioController');
        $this->assertRouteHasMiddleware('/relatorios/vencidos', 'AuthMiddleware');
    }
}
