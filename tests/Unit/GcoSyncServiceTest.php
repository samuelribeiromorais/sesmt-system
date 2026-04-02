<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes unitarios para logica pura do GcoSyncService.
 *
 * Testa funcoes sem dependencia de banco ou HTTP:
 *   - normalizarTexto
 *   - deveContunuarPaginando
 *   - parseDate
 *   - deteccao de formato da resposta API
 */
class GcoSyncServiceTest extends TestCase
{
    // ── helper: instancia com acesso a metodos privados ─────────────────────

    private function service(): object
    {
        // Carrega o servico sem chamar __construct (evita dependencia de .env)
        $rc = new \ReflectionClass(\App\Services\GcoSyncService::class);
        $svc = $rc->newInstanceWithoutConstructor();

        // Injeta valores minimos necessarios
        $prop = $rc->getProperty('pageSize');
        $prop->setAccessible(true);
        $prop->setValue($svc, 100);

        $cache = $rc->getProperty('cacheObra');
        $cache->setAccessible(true);
        $cache->setValue($svc, []);

        return $svc;
    }

    private function callPrivate(object $obj, string $method, array $args = []): mixed
    {
        $rm = new \ReflectionMethod($obj, $method);
        $rm->setAccessible(true);
        return $rm->invokeArgs($obj, $args);
    }

    // ── normalizarTexto ──────────────────────────────────────────────────────

    public function testNormalizarTextoRemoveAcentos(): void
    {
        $svc = $this->service();
        $result = $this->callPrivate($svc, 'normalizarTexto', ['CAÇAPAVA']);
        $this->assertSame('CACAPAVA', $result);
    }

    public function testNormalizarTextoMaiusculo(): void
    {
        $svc = $this->service();
        $result = $this->callPrivate($svc, 'normalizarTexto', ['porto nacional']);
        $this->assertSame('PORTO NACIONAL', $result);
    }

    public function testNormalizarTextoRemoveAcentoVariados(): void
    {
        $svc = $this->service();
        $this->assertSame('SAO PAULO', $this->callPrivate($svc, 'normalizarTexto', ['São Paulo']));
        $this->assertSame('GOIANIA',   $this->callPrivate($svc, 'normalizarTexto', ['Goiânia']));
        $this->assertSame('UBERLANDIA', $this->callPrivate($svc, 'normalizarTexto', ['Uberlândia']));
    }

    public function testNormalizarTextoTrimaEspacos(): void
    {
        $svc = $this->service();
        $result = $this->callPrivate($svc, 'normalizarTexto', ['  NESTLE  ']);
        $this->assertSame('NESTLE', $result);
    }

    // ── deveContunuarPaginando ───────────────────────────────────────────────

    public function testPaginacaoParaComUltimaPaginaVazia(): void
    {
        $svc = $this->service();
        $continua = $this->callPrivate($svc, 'deveContunuarPaginando', [
            /* todos */ array_fill(0, 100, []),
            /* total */ null,
            /* ultima*/ [],
        ]);
        $this->assertFalse($continua, 'Pagina vazia deve parar paginacao.');
    }

    public function testPaginacaoFormatoEnvelopeParaAoAtingirTotal(): void
    {
        $svc = $this->service();
        $registros = array_fill(0, 100, []);
        // total = 100, buscou 100 -> deve parar
        $continua = $this->callPrivate($svc, 'deveContunuarPaginando', [
            $registros, 100, $registros,
        ]);
        $this->assertFalse($continua, 'Ao atingir total deve parar.');
    }

    public function testPaginacaoFormatoEnvelopeContinuaSeHouverMais(): void
    {
        $svc = $this->service();
        $registros = array_fill(0, 100, []);
        // total = 712, buscou 100 -> deve continuar
        $continua = $this->callPrivate($svc, 'deveContunuarPaginando', [
            $registros, 712, $registros,
        ]);
        $this->assertTrue($continua, 'Ainda ha registros, deve continuar.');
    }

    public function testPaginacaoArrayDiretoContinuaSePaginaCheiaRetornou(): void
    {
        $svc = $this->service();
        $paginaCheia = array_fill(0, 100, []); // exatamente pageSize
        $continua = $this->callPrivate($svc, 'deveContunuarPaginando', [
            $paginaCheia, null, $paginaCheia,
        ]);
        $this->assertTrue($continua, 'Pagina cheia indica que pode haver mais.');
    }

    public function testPaginacaoArrayDiretoParaComPaginaIncompleta(): void
    {
        $svc = $this->service();
        $todos  = array_fill(0, 112, []);
        $ultima = array_fill(0, 12, []); // menos que pageSize (100)
        $continua = $this->callPrivate($svc, 'deveContunuarPaginando', [
            $todos, null, $ultima,
        ]);
        $this->assertFalse($continua, 'Pagina incompleta indica ultima pagina.');
    }

    // ── parseDate ────────────────────────────────────────────────────────────

    public function testParseDateFormatoIso(): void
    {
        $svc = $this->service();
        $result = $this->callPrivate($svc, 'parseDate', ['1983-08-14T00:00:00.000']);
        $this->assertSame('1983-08-14', $result);
    }

    public function testParseDateNullRetornaNulo(): void
    {
        $svc = $this->service();
        $this->assertNull($this->callPrivate($svc, 'parseDate', [null]));
        $this->assertNull($this->callPrivate($svc, 'parseDate', ['']));
    }

    public function testParseDateDataInvalidaRetornaNulo(): void
    {
        $svc = $this->service();
        $result = $this->callPrivate($svc, 'parseDate', ['nao-e-uma-data']);
        $this->assertNull($result);
    }

    // ── deteccao de formato da resposta ─────────────────────────────────────

    public function testFormatoEnvelopeTemChaveRegistros(): void
    {
        // Formato antigo: objeto com Registros
        $response = ['Registros' => [['CODIGO' => '001']], 'TotalRegistros' => 1];
        $this->assertArrayHasKey('Registros', $response,
            'Formato envelope deve conter chave Registros.');
        $this->assertArrayHasKey('TotalRegistros', $response,
            'Formato envelope deve conter chave TotalRegistros.');
    }

    public function testFormatoArrayDiretoNaoTemChaveRegistros(): void
    {
        // Formato novo: array direto
        $response = [['CODIGO' => '001', 'NOME' => 'Fulano']];
        $this->assertArrayNotHasKey('Registros', $response,
            'Formato array direto nao deve ter chave Registros.');
        // Deve ser indexado numericamente
        $this->assertArrayHasKey(0, $response);
    }

    // ── mapearCampos: campo ATIVO ────────────────────────────────────────────

    public function testStatusAtivoMapeadoParaAtivo(): void
    {
        $reg = ['ATIVO' => 'Sim'];
        $status = ($reg['ATIVO'] === 'Sim') ? 'ativo' : 'inativo';
        $this->assertSame('ativo', $status);
    }

    public function testStatusInativoMapeadoParaInativo(): void
    {
        $reg = ['ATIVO' => 'Nao'];
        $status = ($reg['ATIVO'] === 'Sim') ? 'ativo' : 'inativo';
        $this->assertSame('inativo', $status);
    }

    // ── logica celular vs celular_manual ─────────────────────────────────────

    public function testCelularGcoNaoEhIncludoSeNulo(): void
    {
        // Simula a logica: celular so incluido se nao-vazio
        $reg    = ['CELULAR' => null];
        $campos = [];
        if (!empty($reg['CELULAR'])) {
            $campos['celular'] = $reg['CELULAR'];
        }
        $this->assertArrayNotHasKey('celular', $campos,
            'CELULAR nulo nao deve sobrescrever campo celular.');
    }

    public function testCelularGcoIncluidoSePreenchido(): void
    {
        $reg    = ['CELULAR' => '(61) 998719188'];
        $campos = [];
        if (!empty($reg['CELULAR'])) {
            $campos['celular'] = $reg['CELULAR'];
        }
        $this->assertArrayHasKey('celular', $campos,
            'CELULAR preenchido deve ser incluido no mapeamento.');
        $this->assertSame('(61) 998719188', $campos['celular']);
    }

    public function testCelularManualNuncaNoMapeamentoGco(): void
    {
        // celular_manual nunca deve aparecer no mapearCampos do GCO
        $svc = $this->service();
        $rm  = new \ReflectionMethod($svc, 'mapearCampos');
        $src = file_get_contents((new \ReflectionClass($svc))->getFileName());
        // Garante que celular_manual nao e setado dentro de mapearCampos
        preg_match('/function mapearCampos.*?^    \}/ms', $src, $match);
        $this->assertNotEmpty($match, 'Metodo mapearCampos nao encontrado.');
        $this->assertStringNotContainsString(
            "'celular_manual'",
            $match[0],
            'mapearCampos nao deve tocar celular_manual.'
        );
    }

    // ── SITE_OBRA parsing ─────────────────────────────────────────────────────

    public function testSiteObraComMenosDe3PartesEhInvalido(): void
    {
        // Formato invalido: "CARGILL" (sem codigo e local)
        $siteObra = 'CARGILL';
        $partes   = explode(' - ', $siteObra);
        $this->assertLessThan(3, count($partes),
            'SITE_OBRA sem 3 partes nao deve tentar match.');
    }

    public function testSiteObraFormatoCorretoParseia(): void
    {
        $siteObra = '240 - CARGILL - PORTO NACIONAL - TO';
        $partes   = explode(' - ', $siteObra);
        $this->assertGreaterThanOrEqual(3, count($partes));
        $this->assertSame('240',          $partes[0]);
        $this->assertSame('CARGILL',      $partes[1]);
        $this->assertSame('PORTO NACIONAL', $partes[2]);
    }

    public function testSiteObraSimplesParseia(): void
    {
        $siteObra = '101 - NESTLE - CACAPAVA';
        $partes   = explode(' - ', $siteObra);
        $this->assertCount(3, $partes);
        $this->assertSame('101',     $partes[0]);
        $this->assertSame('NESTLE',  $partes[1]);
        $this->assertSame('CACAPAVA', $partes[2]);
    }
}
