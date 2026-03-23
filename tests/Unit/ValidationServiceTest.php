<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\ValidationService;

class ValidationServiceTest extends TestCase
{
    public function testValidarCpfValido(): void
    {
        $this->assertTrue(ValidationService::validarCPF('12345678909'));
        $this->assertTrue(ValidationService::validarCPF('123.456.789-09'));
    }

    public function testValidarCpfInvalido(): void
    {
        $this->assertFalse(ValidationService::validarCPF('00000000000'));
        $this->assertFalse(ValidationService::validarCPF('11111111111'));
        $this->assertFalse(ValidationService::validarCPF('12345678900'));
        $this->assertFalse(ValidationService::validarCPF('123'));
        $this->assertFalse(ValidationService::validarCPF(''));
    }

    public function testValidarEmailValido(): void
    {
        $this->assertTrue(ValidationService::validarEmail('user@example.com'));
        $this->assertTrue(ValidationService::validarEmail('samuel.morais@tsea.com.br'));
    }

    public function testValidarEmailInvalido(): void
    {
        $this->assertFalse(ValidationService::validarEmail(''));
        $this->assertFalse(ValidationService::validarEmail('invalid'));
        $this->assertFalse(ValidationService::validarEmail('@domain.com'));
    }

    public function testValidarDataValida(): void
    {
        $this->assertTrue(ValidationService::validarData('2026-03-15'));
        $this->assertTrue(ValidationService::validarData('2025-12-31'));
    }

    public function testValidarDataInvalida(): void
    {
        $this->assertFalse(ValidationService::validarData(''));
        $this->assertFalse(ValidationService::validarData('2026-13-01'));
        $this->assertFalse(ValidationService::validarData('2026-02-30'));
        $this->assertFalse(ValidationService::validarData('not-a-date'));
    }
}
