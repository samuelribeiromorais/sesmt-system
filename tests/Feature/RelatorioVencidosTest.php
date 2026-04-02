<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Core\Database;

/**
 * Testes de integracao para o relatorio de Documentos Vencidos.
 *
 * @group database
 */
class RelatorioVencidosTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
    }

    // ── Query principal de documentos vencidos ───────────────────────────────

    public function testQueryDocumentosVencidosRetornaArray(): void
    {
        $stmt = $this->db->query(
            "SELECT d.id, d.data_validade, d.status,
                    c.nome_completo, c.status as colab_status,
                    td.nome as tipo_nome, td.categoria,
                    DATEDIFF(CURDATE(), d.data_validade) AS dias_vencido
             FROM documentos d
             JOIN colaboradores c  ON d.colaborador_id  = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.data_validade < CURDATE()
               AND d.status IN ('vencido','vigente')
               AND c.status = 'ativo'
               AND d.excluido_em IS NULL
             ORDER BY dias_vencido DESC
             LIMIT 50"
        );
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertIsArray($result,
            'Query de documentos vencidos deve retornar array.');
    }

    public function testQueryDocumentosVencidosCamposCorretos(): void
    {
        $stmt = $this->db->query(
            "SELECT d.id, d.data_validade,
                    c.nome_completo,
                    td.nome as tipo_nome, td.categoria,
                    DATEDIFF(CURDATE(), d.data_validade) AS dias_vencido
             FROM documentos d
             JOIN colaboradores c  ON d.colaborador_id  = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.data_validade < CURDATE()
               AND c.status = 'ativo'
               AND d.excluido_em IS NULL
             LIMIT 1"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            $this->markTestSkipped('Sem documentos vencidos para validar campos.');
        }

        foreach (['id', 'data_validade', 'nome_completo',
                  'tipo_nome', 'categoria', 'dias_vencido'] as $campo) {
            $this->assertArrayHasKey($campo, $row,
                "Campo '{$campo}' deve estar presente no resultado de vencidos.");
        }
    }

    public function testDiasVencidoEhPositivo(): void
    {
        $stmt = $this->db->query(
            "SELECT DATEDIFF(CURDATE(), d.data_validade) AS dias_vencido
             FROM documentos d
             WHERE d.data_validade < CURDATE()
               AND d.excluido_em IS NULL
             LIMIT 10"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $this->assertGreaterThan(0, (int)$row['dias_vencido'],
                'dias_vencido deve ser positivo para docs com data_validade passada.');
        }
    }

    // ── Resumo por tipo ───────────────────────────────────────────────────────

    public function testQueryResumoPorTipoRetornaArray(): void
    {
        $stmt = $this->db->query(
            "SELECT td.id, td.nome, td.categoria,
                    COUNT(d.id) AS total,
                    MAX(DATEDIFF(CURDATE(), d.data_validade)) AS max_dias
             FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             JOIN colaboradores c    ON d.colaborador_id    = c.id
             WHERE d.data_validade < CURDATE()
               AND c.status = 'ativo'
               AND d.excluido_em IS NULL
             GROUP BY td.id, td.nome, td.categoria
             ORDER BY total DESC"
        );
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertIsArray($result,
            'Resumo por tipo deve retornar array.');
    }

    public function testQueryResumoPorTipoCamposCorretos(): void
    {
        $stmt = $this->db->query(
            "SELECT td.id, td.nome, td.categoria,
                    COUNT(d.id) AS total
             FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             JOIN colaboradores c    ON d.colaborador_id    = c.id
             WHERE d.data_validade < CURDATE()
               AND c.status = 'ativo'
               AND d.excluido_em IS NULL
             GROUP BY td.id, td.nome, td.categoria
             LIMIT 1"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            $this->markTestSkipped('Sem documentos vencidos para validar resumo.');
        }

        foreach (['id', 'nome', 'categoria', 'total'] as $campo) {
            $this->assertArrayHasKey($campo, $row,
                "Campo '{$campo}' deve estar presente no resumo por tipo.");
        }
        $this->assertGreaterThan(0, (int)$row['total'],
            'Total de vencidos por tipo deve ser positivo.');
    }

    public function testTotalVencidosPorTipoEhInteiro(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(d.id) AS total
             FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             JOIN colaboradores c    ON d.colaborador_id    = c.id
             WHERE d.data_validade < CURDATE()
               AND c.status = 'ativo'
               AND d.excluido_em IS NULL
             GROUP BY td.id
             LIMIT 10"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $this->assertIsNumeric($row['total'],
                'Total por tipo deve ser numerico.');
        }
    }

    // ── Filtro por tipo_documento_id ─────────────────────────────────────────

    public function testFiltroTipoDocumentoIdFunciona(): void
    {
        // Pega um tipo_documento existente com docs vencidos
        $stmtTipo = $this->db->query(
            "SELECT td.id FROM tipos_documento td
             JOIN documentos d ON d.tipo_documento_id = td.id
             WHERE d.data_validade < CURDATE()
               AND d.excluido_em IS NULL
             LIMIT 1"
        );
        $tipoId = $stmtTipo->fetchColumn();

        if (!$tipoId) {
            $this->markTestSkipped('Nenhum tipo de documento com vencidos para testar filtro.');
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM documentos d
             WHERE d.tipo_documento_id = :tipo
               AND d.data_validade < CURDATE()
               AND d.excluido_em IS NULL"
        );
        $stmt->execute(['tipo' => $tipoId]);
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count,
            'Filtro por tipo_documento_id deve retornar resultados.');
    }

    // ── Rota vencidos ────────────────────────────────────────────────────────

    public function testRotaRelatorioVencidosExiste(): void
    {
        $routesContent = file_get_contents(
            dirname(__DIR__, 2) . '/public/index.php'
        );
        $this->assertMatchesRegularExpression(
            "/router->get\('\/relatorios\/vencidos'/",
            $routesContent,
            "Rota GET '/relatorios/vencidos' nao encontrada."
        );
    }

    public function testRotaRelatorioVencidosTemAuthMiddleware(): void
    {
        $routesContent = file_get_contents(
            dirname(__DIR__, 2) . '/public/index.php'
        );
        preg_match(
            "/router->get\('\/relatorios\/vencidos',[^\)]+\)/s",
            $routesContent,
            $matches
        );
        $this->assertNotEmpty($matches, "Rota '/relatorios/vencidos' nao encontrada.");
        $this->assertStringContainsString('AuthMiddleware', $matches[0],
            "Rota '/relatorios/vencidos' deve ter AuthMiddleware.");
    }

    // ── View de vencidos ─────────────────────────────────────────────────────

    public function testViewVencidosExiste(): void
    {
        $viewPath = dirname(__DIR__, 2) . '/app/Views/relatorios/vencidos.php';
        $this->assertFileExists($viewPath,
            'View relatorios/vencidos.php deve existir.');
    }

    public function testViewVencidosContemTabelaDocs(): void
    {
        $view = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/relatorios/vencidos.php'
        );
        $this->assertStringContainsString('dias_vencido', $view,
            'View de vencidos deve exibir o campo dias_vencido.');
        $this->assertStringContainsString('tipo_nome', $view,
            'View de vencidos deve exibir o tipo do documento.');
    }

    public function testViewVencidosContemFiltro(): void
    {
        $view = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/relatorios/vencidos.php'
        );
        $this->assertStringContainsString('tipo_documento_id', $view,
            'View deve ter filtro por tipo_documento_id.');
    }
}
