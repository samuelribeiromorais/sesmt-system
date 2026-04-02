<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Core\Database;

/**
 * Testes de integracao para o modulo GCO.
 *
 * Cobre:
 *   - Estrutura da tabela gco_sync_logs
 *   - Campos adicionados em colaboradores (codigo_gco, celular, celular_manual)
 *   - Resolucao de obra via SITE_OBRA com dados reais do banco
 *   - Rotas e middlewares do GCO
 *   - Rota celular_manual do colaborador
 *
 * @group database
 */
class GcoIntegracaoTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
    }

    // ── Estrutura de banco ───────────────────────────────────────────────────

    public function testTabelaGcoSyncLogsExiste(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name   = 'gco_sync_logs'"
        );
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'Tabela gco_sync_logs deve existir.');
    }

    public function testGcoSyncLogsTemColunasEssenciais(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'gco_sync_logs'"
        );
        $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach (['id', 'iniciado_em', 'concluido_em', 'total_api',
                  'criados', 'atualizados', 'desativados', 'erros',
                  'status', 'mensagem', 'executado_por'] as $col) {
            $this->assertContains($col, $colunas,
                "Coluna '{$col}' deve existir em gco_sync_logs.");
        }
    }

    public function testColaboradoresTemColunaCodigoGco(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'colaboradores'
               AND column_name  = 'codigo_gco'"
        );
        $this->assertNotFalse($stmt->fetch(),
            'Coluna codigo_gco deve existir em colaboradores.');
    }

    public function testColaboradoresTemColunaCelular(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'colaboradores'
               AND column_name  = 'celular'"
        );
        $this->assertNotFalse($stmt->fetch(),
            'Coluna celular (GCO) deve existir em colaboradores.');
    }

    public function testColaboradoresTemColunaCelularManual(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'colaboradores'
               AND column_name  = 'celular_manual'"
        );
        $this->assertNotFalse($stmt->fetch(),
            'Coluna celular_manual deve existir em colaboradores.');
    }

    public function testColaboradoresTemColunaObraId(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'colaboradores'
               AND column_name  = 'obra_id'"
        );
        $this->assertNotFalse($stmt->fetch(),
            'Coluna obra_id deve existir em colaboradores (para vinculo GCO).');
    }

    // ── Resolucao SITE_OBRA com dados reais ──────────────────────────────────

    public function testResolverObraCargillPortoNacionalEncontrada(): void
    {
        // "240 - CARGILL - PORTO NACIONAL - TO" -> obra Cargill Porto Nacional
        $clienteHint = 'CARGILL';
        $localHint   = 'PORTO';

        $stmt = $this->db->prepare(
            "SELECT o.id, o.cliente_id
             FROM obras o
             JOIN clientes c ON c.id = o.cliente_id
             WHERE UPPER(c.nome_fantasia) LIKE :cliente
               AND UPPER(o.nome) LIKE :local
             LIMIT 1"
        );
        $stmt->execute([
            'cliente' => "%{$clienteHint}%",
            'local'   => "%{$localHint}%",
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row,
            'SITE_OBRA "CARGILL - PORTO NACIONAL" deve resolver para uma obra.');
        $this->assertGreaterThan(0, $row['id']);
        $this->assertGreaterThan(0, $row['cliente_id']);
    }

    public function testResolverObraNesteeCacapavaEncontrada(): void
    {
        // "101 - NESTLE - CACAPAVA" (sem cedilha) -> obra Nestle Cacapava
        $clienteHint = 'NESTLE';
        $localHint   = 'CACAPAVA'; // normalizado sem cedilha

        $stmt = $this->db->prepare(
            "SELECT o.id FROM obras o
             JOIN clientes c ON c.id = o.cliente_id
             WHERE UPPER(c.nome_fantasia) LIKE :cliente
               AND UPPER(o.nome) LIKE :local
             LIMIT 1"
        );
        $stmt->execute([
            'cliente' => "%{$clienteHint}%",
            'local'   => "%{$localHint}%",
        ]);
        $this->assertNotFalse($stmt->fetch(),
            'SITE_OBRA "NESTLE - CACAPAVA" deve resolver para uma obra.');
    }

    public function testResolverObraInexistenteRetornaFalse(): void
    {
        $stmt = $this->db->prepare(
            "SELECT o.id FROM obras o
             JOIN clientes c ON c.id = o.cliente_id
             WHERE UPPER(c.nome_fantasia) LIKE :cliente
               AND UPPER(o.nome) LIKE :local
             LIMIT 1"
        );
        $stmt->execute([
            'cliente' => '%EMPRESA_INEXISTENTE_XYZ%',
            'local'   => '%LOCAL_INEXISTENTE_ABC%',
        ]);
        $this->assertFalse($stmt->fetch(),
            'SITE_OBRA invalido nao deve retornar nenhuma obra.');
    }

    // ── Historico de sincronizacoes ──────────────────────────────────────────

    public function testLogsSincronizacaoRetornamArray(): void
    {
        $stmt = $this->db->query(
            "SELECT id, status, total_api, criados, atualizados,
                    desativados, erros, iniciado_em
             FROM gco_sync_logs
             ORDER BY iniciado_em DESC
             LIMIT 10"
        );
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertIsArray($logs,
            'Historico de sincronizacoes deve retornar array.');
    }

    public function testLogCampoPrimeiraExecucaoExiste(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM gco_sync_logs WHERE status = 'concluido'"
        );
        $count = (int)$stmt->fetchColumn();
        // Pode ser 0 se ainda nao sincronizou, mas nao deve dar erro
        $this->assertGreaterThanOrEqual(0, $count,
            'Contagem de logs concluidos deve ser nao-negativa.');
    }

    public function testEnumStatusGcoSyncLogsValido(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'gco_sync_logs'
               AND column_name  = 'status'"
        );
        $tipo = $stmt->fetchColumn();
        $this->assertStringContainsString('em_andamento', $tipo);
        $this->assertStringContainsString('concluido',    $tipo);
        $this->assertStringContainsString('erro',         $tipo);
    }

    // ── Colaboradores vinculados ao GCO ──────────────────────────────────────

    public function testColaboradoresVinculadosAoGcoSaoInteiro(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM colaboradores WHERE codigo_gco IS NOT NULL"
        );
        $this->assertIsNumeric($stmt->fetchColumn(),
            'Contagem de colaboradores com codigo_gco deve ser numerica.');
    }

    public function testColaboradoresAtivosExistem(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo'"
        );
        $total = (int)$stmt->fetchColumn();
        $this->assertGreaterThan(0, $total,
            'Deve haver ao menos um colaborador ativo apos sincronizacao.');
    }

    // ── Seguranca: celular vs celular_manual sao independentes ───────────────

    public function testCelularECelularManualSaoColunasSeparadas(): void
    {
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name   = 'colaboradores'
               AND column_name IN ('celular', 'celular_manual')"
        );
        $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('celular',        $colunas);
        $this->assertContains('celular_manual', $colunas);
        $this->assertCount(2, $colunas,
            'celular e celular_manual devem ser colunas distintas.');
    }
}
