<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class SqlInjectionTest extends TestCase
{
    /**
     * Test that sanitizeOrderBy blocks SQL injection payloads
     */
    public function testSanitizeOrderByBlocksInjection(): void
    {
        $model = new class extends \App\Core\Model {
            protected string $table = 'colaboradores';
            public function testSanitize(string $input): string
            {
                return $this->sanitizeOrderBy($input);
            }
        };

        // Valid inputs should pass through
        $this->assertEquals('id DESC', $model->testSanitize('id DESC'));
        $this->assertEquals('nome_completo ASC', $model->testSanitize('nome_completo ASC'));
        $this->assertEquals('c.nome_completo ASC, td.nome ASC', $model->testSanitize('c.nome_completo ASC, td.nome ASC'));

        // SQL injection payloads should be stripped
        $this->assertEquals('id DESC', $model->testSanitize("id; DROP TABLE colaboradores; --"));
        $this->assertEquals('id DESC', $model->testSanitize("1 UNION SELECT * FROM usuarios"));
        $this->assertEquals('id DESC', $model->testSanitize("id DESC; DELETE FROM documentos"));
        $this->assertEquals('id DESC', $model->testSanitize("SLEEP(5)"));
        $this->assertEquals('id DESC', $model->testSanitize("1=1"));
    }

    /**
     * Test that sanitizeOrderBy accepts valid column names
     */
    public function testSanitizeOrderByAcceptsValidColumns(): void
    {
        $model = new class extends \App\Core\Model {
            protected string $table = 'colaboradores';
            public function testSanitize(string $input): string
            {
                return $this->sanitizeOrderBy($input);
            }
        };

        $this->assertEquals('nome_completo', $model->testSanitize('nome_completo'));
        $this->assertEquals('criado_em DESC', $model->testSanitize('criado_em DESC'));
        $this->assertEquals('c.id ASC', $model->testSanitize('c.id ASC'));
        $this->assertEquals('data_emissao DESC, id ASC', $model->testSanitize('data_emissao DESC, id ASC'));
    }
}
