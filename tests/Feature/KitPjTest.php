<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Core\Database;

/**
 * Testes de integração do Kit PJ.
 *
 * Cobre:
 *  - Transformação de arrays de riscos em string (implode)
 *  - Estrutura do formulário de criação (view)
 *  - Impressão: assinaturas corretas
 *  - Schema do banco: tabela kits_pj existe com campos esperados
 *
 * @group database
 */
class KitPjTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
    }

    // ── Schema / BD ──────────────────────────────────────────────────────

    public function testTabelaKitsPjExiste(): void
    {
        $stmt = $this->db->query("SHOW TABLES LIKE 'kits_pj'");
        $result = $stmt->fetch();
        $this->assertNotFalse($result, 'Tabela kits_pj não encontrada no banco.');
    }

    public function testTabelaKitsPjTemColunasDosRiscos(): void
    {
        $stmt  = $this->db->query("SHOW COLUMNS FROM kits_pj");
        $cols  = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        $expected = [
            'riscos_fisicos', 'riscos_quimicos', 'riscos_biologicos',
            'riscos_ergonomicos', 'riscos_acidentes',
        ];
        foreach ($expected as $col) {
            $this->assertContains($col, $cols,
                "Coluna '{$col}' não encontrada na tabela kits_pj.");
        }
    }

    public function testTabelaKitsPjTemColunasBasicas(): void
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM kits_pj");
        $cols = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');

        foreach (['id', 'colaborador_id', 'medico_nome', 'medico_crm', 'tipo_aso'] as $col) {
            $this->assertContains($col, $cols,
                "Coluna '{$col}' não encontrada na tabela kits_pj.");
        }
    }

    // ── Lógica de serialização dos riscos (implode) ──────────────────────

    public function testImplodeRiscosArrayMultiplo(): void
    {
        $input  = ['Ruído', 'Vibração', 'Temperatura'];
        $result = implode(', ', $input);
        $this->assertEquals('Ruído, Vibração, Temperatura', $result);
    }

    public function testImplodeRiscosArrayUnico(): void
    {
        $input  = ['Ruído'];
        $result = implode(', ', $input);
        $this->assertEquals('Ruído', $result);
    }

    public function testImplodeRiscosArrayVazio(): void
    {
        $input  = [];
        $result = implode(', ', $input);
        $this->assertEquals('', $result,
            'Array vazio deve resultar em string vazia.');
    }

    public function testKitPjStoreTransformaArrayEmString(): void
    {
        // Simula a lógica do KitPjController::store()
        $riscosFisicos = ['Ruído', 'Vibração'];
        $stored = is_array($riscosFisicos)
            ? implode(', ', $riscosFisicos)
            : $riscosFisicos;

        $this->assertIsString($stored);
        $this->assertStringContainsString('Ruído', $stored);
        $this->assertStringContainsString('Vibração', $stored);
        $this->assertStringContainsString(', ', $stored);
    }

    public function testKitPjStoreAceitaStringDireta(): void
    {
        // Se vier uma string em vez de array (form sem checkboxes)
        $riscosFisicos = 'Ruído, Vibração';
        $stored = is_array($riscosFisicos)
            ? implode(', ', $riscosFisicos)
            : $riscosFisicos;

        $this->assertEquals('Ruído, Vibração', $stored);
    }

    // ── View de impressão: assinaturas corretas ──────────────────────────

    public function testImprimirNaoContemAssinaturaMariana(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/imprimir.php'
        );
        $this->assertStringNotContainsString('Mariana Toscano', $content,
            'Assinatura de Mariana Toscano não deve aparecer no Kit PJ após refatoração.');
    }

    public function testImprimirContemAssinaturaMedico(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/imprimir.php'
        );
        $this->assertStringContainsString('Médico', $content,
            'Assinatura do médico examinador deve estar presente no Kit PJ.');
        $this->assertStringContainsString('CRM', $content,
            'Campo CRM deve estar presente na assinatura do médico.');
    }

    public function testImprimirContemColaboradorAssinatura(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/imprimir.php'
        );
        // Colaborador deve assinar
        $this->assertStringContainsString('colaborador', strtolower($content),
            'Assinatura do colaborador deve estar presente no Kit PJ.');
    }

    // ── View de criação: checkboxes de riscos ────────────────────────────

    public function testCriarContemCheckboxesRiscosFisicos(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/criar.php'
        );
        // Checkboxes são gerados dinamicamente via foreach; verificar a chave do array
        $this->assertStringContainsString("'riscos_fisicos'", $content,
            'O formulário de criação deve ter definição do grupo riscos_fisicos.');
        $this->assertStringContainsString('<?= $name ?>[]', $content,
            'O formulário deve usar $name dinamicamente para o atributo name.');
    }

    public function testCriarContemCheckboxesRiscosErgonomicos(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/criar.php'
        );
        $this->assertStringContainsString("'riscos_ergonomicos'", $content,
            'O formulário de criação deve ter definição do grupo riscos_ergonomicos.');
    }

    public function testCriarContemTodosGruposDeRisco(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/criar.php'
        );
        $grupos = ['riscos_fisicos', 'riscos_quimicos', 'riscos_biologicos',
                   'riscos_ergonomicos', 'riscos_acidentes'];
        foreach ($grupos as $grupo) {
            $this->assertStringContainsString("'{$grupo}'", $content,
                "Grupo de risco '{$grupo}' não encontrado no formulário.");
        }
    }

    // ── Ficha Clínica: campos da anamnese ────────────────────────────────

    public function testImprimirContemFichaClinicaSecoes(): void
    {
        $content = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/kit-pj/imprimir.php'
        );
        // Seções em maiúsculas sem acento conforme renderizado no documento impresso
        $secoes = [
            'HISTORIA PATOLOGICA',
            'SISTEMA NEUROLOGICO',
            'APARELHO DIGESTIVO',
            'APARELHO RESPIRATORIO',
            'APARELHO CARDIOVASCULAR',
            'ANTECEDENTES FAMILIARES',
        ];
        foreach ($secoes as $secao) {
            $this->assertStringContainsString($secao, $content,
                "Seção '{$secao}' não encontrada no documento impresso.");
        }
    }

    // ── Tipos de ASO ─────────────────────────────────────────────────────

    public function testTiposAsoValidos(): void
    {
        $tiposValidos = ['admissional', 'periodico', 'demissional', 'retorno', 'mudanca_risco'];
        $stmt = $this->db->query(
            "SELECT DISTINCT tipo_aso FROM kits_pj ORDER BY tipo_aso"
        );
        $tiposNoBanco = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tiposNoBanco as $tipo) {
            $this->assertContains($tipo, $tiposValidos,
                "Tipo de ASO '{$tipo}' não é um valor válido do enum.");
        }
    }
}
