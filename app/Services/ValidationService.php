<?php

namespace App\Services;

class ValidationService
{
    public static function validarCPF(string $cpf): bool
    {
        // Remove non-numeric
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) != 11) return false;
        // Reject all same digits
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        // Validate check digits
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$t] != $d) return false;
        }
        return true;
    }

    public static function validarCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) != 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
        // Validate check digits
        $tamanho = strlen($cnpj) - 2;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ($resultado != $digitos[0]) return false;
        $tamanho++;
        $numeros = substr($cnpj, 0, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        return $resultado == $digitos[1];
    }

    public static function formatarCPF(string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    public static function formatarCNPJ(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }

    public static function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validarData(string $data): bool
    {
        if (empty($data)) return false;
        $d = \DateTime::createFromFormat('Y-m-d', $data);
        return $d && $d->format('Y-m-d') === $data;
    }

    public static function validarCamposObrigatorios(array $data, array $campos): array
    {
        $erros = [];
        foreach ($campos as $campo) {
            if (!isset($data[$campo]) || trim((string)$data[$campo]) === '') {
                $erros[] = "O campo '{$campo}' e obrigatorio.";
            }
        }
        return $erros;
    }
}
