<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Core\Database;

/**
 * Testes de integração dos Relatórios.
 *
 * @group database
 */
class RelatorioTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
    }

    // ── Relatório Mensal — queries ────────────────────────────────────────

    public function testQueryRelatorioMensalRetornaArray(): void
    {
        $mes = (int)date('m');
        $ano = (int)date('Y');

        $stmt = $this->db->prepare(
            "SELECT d.id, d.status, d.data_emissao, d.criado_em,
                    c.nome_completo, td.nome as tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE MONTH(d.criado_em) = :mes AND YEAR(d.criado_em) = :ano
               AND d.excluido_em IS NULL
             ORDER BY d.criado_em DESC
             LIMIT 500"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result,
            'Query do relatório mensal deve retornar um array.');
    }

    public function testQueryRelatorioMensalCamposCorretos(): void
    {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.status, d.data_emissao, d.criado_em,
                    c.nome_completo, td.nome as tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.excluido_em IS NULL
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $this->markTestSkipped('Nenhum documento no banco para testar campos.');
        }

        $expected = ['id', 'status', 'data_emissao', 'criado_em', 'nome_completo', 'tipo_nome', 'categoria'];
        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $row,
                "Campo '{$field}' ausente no resultado do relatório mensal.");
        }
    }

    public function testQueryResumoCategoriaMensal(): void
    {
        $mes = (int)date('m');
        $ano = (int)date('Y');

        $stmt = $this->db->prepare(
            "SELECT td.categoria, COUNT(*) as total
             FROM documentos d
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE MONTH(d.criado_em) = :mes AND YEAR(d.criado_em) = :ano
               AND d.excluido_em IS NULL
             GROUP BY td.categoria"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $this->assertIsArray($result);
        foreach ($result as $categoria => $total) {
            $this->assertIsInt((int)$total,
                "Total da categoria '{$categoria}' deve ser numérico.");
            $this->assertGreaterThan(0, (int)$total);
        }
    }

    // ── Relatório por Tipo de Documento ─────────────────────────────────

    public function testQueryRelatorioTipoDocumentoSemFiltros(): void
    {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.status, d.data_emissao, d.data_validade,
                    c.nome_completo, c.cargo,
                    td.nome as tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.excluido_em IS NULL
             ORDER BY d.data_validade ASC
             LIMIT 50"
        );
        $stmt->execute();
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
    }

    public function testQueryRelatorioTipoDocumentoComFiltroStatus(): void
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM documentos d
             WHERE d.status = :status AND d.excluido_em IS NULL"
        );
        $stmt->execute(['status' => 'vigente']);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($row['total'] ?? 0);

        $this->assertGreaterThanOrEqual(0, $total,
            'Contagem de documentos vigentes deve ser >= 0.');
    }

    // ── Navegação mês/ano — limites ──────────────────────────────────────

    public function testMesLimitadoEntre1e12(): void
    {
        $mesInvalido1 = 0;
        $mesInvalido2 = 13;
        $mesFixado1   = max(1, min(12, $mesInvalido1));
        $mesFixado2   = max(1, min(12, $mesInvalido2));

        $this->assertEquals(1,  $mesFixado1, 'Mês 0 deve ser corrigido para 1.');
        $this->assertEquals(12, $mesFixado2, 'Mês 13 deve ser corrigido para 12.');
    }

    public function testAnoLimitadoEntre2020eAtual(): void
    {
        $anoMin     = 2020;
        $anoMax     = (int)date('Y');
        $anoInvalido = 1999;
        $anoFuturo   = $anoMax + 5;

        $anoFixado1 = max($anoMin, min($anoMax, $anoInvalido));
        $anoFixado2 = max($anoMin, min($anoMax, $anoFuturo));

        $this->assertEquals($anoMin, $anoFixado1, 'Ano anterior a 2020 deve ser corrigido para 2020.');
        $this->assertEquals($anoMax, $anoFixado2, 'Ano futuro deve ser limitado ao ano atual.');
    }

    // ── Documentos do mês corrente — preview do dashboard ───────────────

    public function testQueryDocsMesCorrenteTemLimiteDe10(): void
    {
        $stmt = $this->db->query(
            "SELECT d.id, d.status, d.data_emissao, d.criado_em,
                    c.nome_completo, td.nome as tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE MONTH(d.criado_em) = MONTH(CURDATE())
               AND YEAR(d.criado_em) = YEAR(CURDATE())
               AND d.excluido_em IS NULL
             ORDER BY d.criado_em DESC
             LIMIT 10"
        );
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertLessThanOrEqual(10, count($result),
            'Preview de documentos deve ser no máximo 10 registros.');
    }

    // ── Contagem docs mês passado ────────────────────────────────────────

    public function testContagemDocsMesPassadoRetornaInteiro(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM documentos
             WHERE MONTH(criado_em) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
               AND YEAR(criado_em) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
               AND excluido_em IS NULL"
        );
        $count = (int)$stmt->fetchColumn();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ── Docs vencendo próximo mês ────────────────────────────────────────

    public function testContagemDocsVencendoProximoMesRetornaInteiro(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM documentos
             WHERE data_validade >= DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
               AND data_validade < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
               AND excluido_em IS NULL"
        );
        $count = (int)$stmt->fetchColumn();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ── Tipos de documento disponíveis ──────────────────────────────────

    public function testTiposDocumentoExistem(): void
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM tipos_documento WHERE ativo = 1");
        $total = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $total,
            'Deve haver ao menos um tipo de documento ativo no banco.');
    }
}
