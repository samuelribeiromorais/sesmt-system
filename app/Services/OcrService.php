<?php

namespace App\Services;

class OcrService
{
    /**
     * Extrai texto de um PDF usando Tesseract OCR.
     * Converte PDF para imagem com Ghostscript, depois OCR com Tesseract.
     */
    public static function extrairTexto(string $pdfPath, int $maxPages = 3): string
    {
        if (!file_exists($pdfPath)) return '';

        $tempDir = sys_get_temp_dir() . '/sesmt_ocr_' . uniqid();
        mkdir($tempDir, 0750, true);

        try {
            // PDF -> PNG via Ghostscript (apenas primeiras páginas)
            $gsCmd = sprintf(
                'gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r200 -dFirstPage=1 -dLastPage=%d -sOutputFile=%s/page_%%d.png %s 2>/dev/null',
                $maxPages,
                escapeshellarg($tempDir),
                escapeshellarg($pdfPath)
            );
            exec($gsCmd);

            // OCR cada página
            $textoCompleto = '';
            for ($i = 1; $i <= $maxPages; $i++) {
                $imgPath = $tempDir . "/page_{$i}.png";
                if (!file_exists($imgPath)) break;

                $ocrCmd = sprintf(
                    'tesseract %s stdout -l por --psm 6 2>/dev/null',
                    escapeshellarg($imgPath)
                );
                $textoCompleto .= shell_exec($ocrCmd) . "\n";
            }

            return trim($textoCompleto);
        } finally {
            // Limpar arquivos temporários
            array_map('unlink', glob($tempDir . '/*'));
            @rmdir($tempDir);
        }
    }

    /**
     * Extrai dados de um ASO a partir do texto OCR.
     * Retorna: tipo_aso, data_exame, medico, apto
     */
    public static function extrairDadosASO(string $texto): array
    {
        $dados = [
            'tipo_aso' => null,
            'data_exame' => null,
            'medico' => null,
            'apto' => null,
        ];

        $textoUpper = mb_strtoupper($texto);

        // Tipo de ASO
        if (str_contains($textoUpper, 'ADMISSIONAL')) $dados['tipo_aso'] = 'admissional';
        elseif (str_contains($textoUpper, 'DEMISSIONAL')) $dados['tipo_aso'] = 'demissional';
        elseif (str_contains($textoUpper, 'PERIODICO') || str_contains($textoUpper, 'PERIÓDICO')) $dados['tipo_aso'] = 'periodico';
        elseif (str_contains($textoUpper, 'RETORNO')) $dados['tipo_aso'] = 'retorno';
        elseif (str_contains($textoUpper, 'MUDANCA') || str_contains($textoUpper, 'MUDANÇA')) $dados['tipo_aso'] = 'mudanca_risco';

        // Data do exame (formatos: DD/MM/YYYY, DD.MM.YYYY, DD-MM-YYYY)
        if (preg_match('/(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})/', $texto, $m)) {
            $dia = (int)$m[1]; $mes = (int)$m[2]; $ano = (int)$m[3];
            if ($mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31 && $ano >= 2020) {
                $dados['data_exame'] = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            }
        }

        // Aptidão
        if (str_contains($textoUpper, 'APTO PARA O TRABALHO') || str_contains($textoUpper, 'APTO(A)')) {
            $dados['apto'] = true;
        } elseif (str_contains($textoUpper, 'INAPTO')) {
            $dados['apto'] = false;
        }

        // Médico (padrão: "Dr." ou "Dra." seguido de nome, ou CRM)
        if (preg_match('/(?:DR[A]?\.?\s*)([A-ZÀ-Ú\s]{5,40})/i', $texto, $m)) {
            $dados['medico'] = trim($m[1]);
        }
        if (preg_match('/CRM[\s\-:]*(\d+)/i', $texto, $m)) {
            $dados['medico'] = ($dados['medico'] ?? '') . ' (CRM ' . $m[1] . ')';
        }

        return $dados;
    }

    /**
     * Extrai data de emissão genérica de qualquer documento.
     */
    public static function extrairDataEmissao(string $texto): ?string
    {
        // Procurar padrões de data
        $padroes = [
            // "Data: DD/MM/YYYY" ou "Emissão: DD/MM/YYYY"
            '/(?:data|emiss[aã]o|validade|realiza[cç][aã]o)\s*[:]\s*(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})/i',
            // DD de MONTH de YYYY
            '/(\d{1,2})\s+de\s+(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\s+de\s+(\d{4})/i',
            // Qualquer data DD/MM/YYYY
            '/(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})/',
        ];

        $meses = ['janeiro'=>1,'fevereiro'=>2,'março'=>3,'marco'=>3,'abril'=>4,'maio'=>5,
                   'junho'=>6,'julho'=>7,'agosto'=>8,'setembro'=>9,'outubro'=>10,'novembro'=>11,'dezembro'=>12];

        foreach ($padroes as $i => $padrao) {
            if (preg_match($padrao, $texto, $m)) {
                if ($i === 1) {
                    $mesNum = $meses[mb_strtolower($m[2])] ?? 1;
                    return sprintf('%04d-%02d-%02d', (int)$m[3], $mesNum, (int)$m[1]);
                }
                $dia = (int)$m[1]; $mes = (int)$m[2]; $ano = (int)$m[3];
                if ($mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31 && $ano >= 2020) {
                    return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                }
            }
        }

        return null;
    }
}
