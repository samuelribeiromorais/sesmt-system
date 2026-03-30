<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\DashboardService;

/**
 * Testes de integração do DashboardService.
 * Requer conexão real com o banco de dados (configurado em phpunit.xml).
 *
 * @group database
 */
class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        $this->service = new DashboardService();
    }

    // ── estrutura do getAllData() ──────────────────────────────────────────

    public function testGetAllDataRetornaArrayNaoVazio(): void
    {
        $data = $this->service->getAllData();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function testGetAllDataContemChavesEstatisticasBasicas(): void
    {
        $data = $this->service->getAllData();

        $expectedKeys = [
            'colaboradores_status',
            'documentos_status',
            'certificados_status',
            'docs_expiring',
            'docs_expired',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data,
                "Chave '{$key}' não encontrada no retorno de getAllData().");
        }
    }

    public function testGetAllDataContemChavesProducaoMensal(): void
    {
        $data = $this->service->getAllData();

        $this->assertArrayHasKey('docs_mes_corrente_count', $data,
            "Chave 'docs_mes_corrente_count' não encontrada — widget Produção Mensal depende dela.");

        $this->assertArrayHasKey('docs_mes_passado_count', $data,
            "Chave 'docs_mes_passado_count' não encontrada — widget Produção Mensal depende dela.");

        $this->assertArrayHasKey('docs_vencendo_proximo_mes_count', $data,
            "Chave 'docs_vencendo_proximo_mes_count' não encontrada — widget Produção Mensal depende dela.");

        $this->assertArrayHasKey('docs_mes_corrente', $data,
            "Chave 'docs_mes_corrente' (preview) não encontrada.");
    }

    public function testContagensProducaoMensalSaoInteiros(): void
    {
        $data = $this->service->getAllData();

        $this->assertIsInt($data['docs_mes_corrente_count'],
            "'docs_mes_corrente_count' deve ser inteiro.");
        $this->assertIsInt($data['docs_mes_passado_count'],
            "'docs_mes_passado_count' deve ser inteiro.");
        $this->assertIsInt($data['docs_vencendo_proximo_mes_count'],
            "'docs_vencendo_proximo_mes_count' deve ser inteiro.");
    }

    public function testContagensProducaoMensalSaoNaoNegativas(): void
    {
        $data = $this->service->getAllData();

        $this->assertGreaterThanOrEqual(0, $data['docs_mes_corrente_count']);
        $this->assertGreaterThanOrEqual(0, $data['docs_mes_passado_count']);
        $this->assertGreaterThanOrEqual(0, $data['docs_vencendo_proximo_mes_count']);
    }

    public function testDocsPreviewLimitadoA10(): void
    {
        $data = $this->service->getAllData();

        $this->assertIsArray($data['docs_mes_corrente'],
            "'docs_mes_corrente' deve ser array.");
        $this->assertLessThanOrEqual(10, count($data['docs_mes_corrente']),
            "Preview de docs do mês corrente deve ser no máximo 10 registros.");
    }

    public function testDocsPreviewContemCamposEsperados(): void
    {
        $data = $this->service->getAllData();

        if (empty($data['docs_mes_corrente'])) {
            $this->markTestSkipped('Nenhum documento no mês corrente — preview vazio.');
        }

        $firstDoc = $data['docs_mes_corrente'][0];
        $requiredFields = ['id', 'status', 'nome_completo', 'tipo_nome', 'categoria'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $firstDoc,
                "Campo '{$field}' não encontrado no preview dos docs do mês.");
        }
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────

    public function testGetAllDataContemKpis(): void
    {
        $data = $this->service->getAllData();

        $this->assertArrayHasKey('kpi_conformidade_atual', $data);
        $this->assertArrayHasKey('kpi_tempo_renovacao', $data);
        $this->assertArrayHasKey('kpi_tendencia_vencimentos', $data);
    }

    public function testKpiConformidadeEstaEntre0e100(): void
    {
        $data = $this->service->getAllData();
        $kpi  = $data['kpi_conformidade_atual'];

        $this->assertIsFloat($kpi);
        $this->assertGreaterThanOrEqual(0.0,   $kpi, 'Conformidade não pode ser negativa.');
        $this->assertLessThanOrEqual(   100.0, $kpi, 'Conformidade não pode passar de 100%.');
    }

    // ── docs_by_category ────────────────────────────────────────────────────

    public function testDocsByCategoryRetornaArray(): void
    {
        $data = $this->service->getAllData();
        $this->assertArrayHasKey('docs_by_category', $data);
        $this->assertIsArray($data['docs_by_category']);
    }

    public function testDocsByCategoryContemCamposCorretos(): void
    {
        $data = $this->service->getAllData();

        if (empty($data['docs_by_category'])) {
            $this->markTestSkipped('Nenhum documento ativo no banco.');
        }

        $row = $data['docs_by_category'][0];
        $this->assertArrayHasKey('categoria', $row);
        $this->assertArrayHasKey('status',    $row);
        $this->assertArrayHasKey('total',     $row);
    }

    // ── aprovações ───────────────────────────────────────────────────────────

    public function testAprovacoesPendentesEhArray(): void
    {
        $data = $this->service->getAllData();
        $this->assertArrayHasKey('aprovacoes_pendentes', $data);
        $this->assertIsArray($data['aprovacoes_pendentes']);
    }

    public function testTotalAprovacoesPendentesEhInteiro(): void
    {
        $data = $this->service->getAllData();
        $this->assertArrayHasKey('total_aprovacoes_pendentes', $data);
        $this->assertIsInt($data['total_aprovacoes_pendentes']);
        $this->assertGreaterThanOrEqual(0, $data['total_aprovacoes_pendentes']);
    }
}
