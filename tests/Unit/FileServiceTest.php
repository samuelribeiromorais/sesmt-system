<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FileServiceTest extends TestCase
{
    private \App\Services\FileService $service;

    protected function setUp(): void
    {
        $this->service = new \App\Services\FileService();
    }

    public function testGerarNomeArquivoFormatoCorreto(): void
    {
        $nome = $this->service->gerarNomeArquivo(
            'JOAO DA SILVA',
            'ASO Admissional',
            '2026-03-15',
            'pdf'
        );

        $this->assertEquals('JOAO DA SILVA - ASO Admissional - 15.03.2026.pdf', $nome);
    }

    public function testGerarNomeArquivoDataInvalida(): void
    {
        $nome = $this->service->gerarNomeArquivo(
            'MARIA SANTOS',
            'Ficha de EPI',
            '0000-00-00',
            'pdf'
        );

        $this->assertStringContainsString('00.00.0000', $nome);
    }

    public function testGerarNomeArquivoSemData(): void
    {
        $nome = $this->service->gerarNomeArquivo(
            'PEDRO OLIVEIRA',
            'Ordem de Servico',
            '',
            'pdf'
        );

        $this->assertStringContainsString('00.00.0000', $nome);
    }

    public function testGerarNomeArquivoRemoveCaracteresInvalidos(): void
    {
        $nome = $this->service->gerarNomeArquivo(
            'JOSE "ZECA" DA SILVA',
            'ASO: Periodico',
            '2026-01-10',
            'pdf'
        );

        // Não deve conter caracteres inválidos do Windows
        $this->assertStringNotContainsString('"', $nome);
        $this->assertStringNotContainsString(':', $nome);
        $this->assertStringNotContainsString('?', $nome);
        $this->assertStringNotContainsString('<', $nome);
        $this->assertStringNotContainsString('>', $nome);
    }

    public function testGerarNomeArquivoNomeLongoTruncado(): void
    {
        $nomeLongo = str_repeat('A', 200);
        $nome = $this->service->gerarNomeArquivo(
            $nomeLongo,
            'ASO Admissional',
            '2026-01-01',
            'pdf'
        );

        // O nome total (sem extensão) não deve exceder limites razoáveis
        $this->assertLessThan(300, strlen($nome));
    }

    public function testGetDiretorioColaboradorComNome(): void
    {
        // Use an ID that certainly doesn't exist in storage
        $dir = $this->service->getDiretorioColaborador(999999, 'TESTE COLABORADOR');
        $this->assertEquals('999999 - TESTE COLABORADOR', $dir);
    }

    public function testGetDiretorioColaboradorSemNome(): void
    {
        $dir = $this->service->getDiretorioColaborador(999999);
        $this->assertEquals('999999', $dir);
    }
}
