<?php
/**
 * Corrige documentos com data_emissao = 2025-01-01 (placeholder)
 * Tenta extrair data real do arquivo_nome (nome original do PDF).
 * Mantém aprovacao_status = 'pendente' para revisão humana.
 */

$dsn = 'mysql:host=db;port=3306;dbname=sesmt_tse;charset=utf8mb4';
$pdo = new PDO($dsn, 'sesmt', 'sesmt2026', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Buscar documentos com data placeholder
$stmt = $pdo->query("
    SELECT d.id, d.arquivo_nome, d.arquivo_path, d.tipo_documento_id, d.colaborador_id,
           td.nome as tipo_nome, td.validade_meses,
           c.obra_id
    FROM documentos d
    JOIN tipos_documento td ON d.tipo_documento_id = td.id
    JOIN colaboradores c ON d.colaborador_id = c.id
    WHERE d.data_emissao = '2025-01-01'
      AND d.excluido_em IS NULL
      AND d.status != 'obsoleto'
    ORDER BY d.id
");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total documentos com data placeholder: " . count($docs) . PHP_EOL;

// EPI validade por obra
$stmtObra = $pdo->prepare("SELECT epi_validade_meses FROM obras WHERE id = :oid AND epi_validade_meses IS NOT NULL");

$meses = [
    'JAN' => '01', 'FEV' => '02', 'MAR' => '03', 'ABR' => '04',
    'MAI' => '05', 'JUN' => '06', 'JUL' => '07', 'AGO' => '08',
    'SET' => '09', 'OUT' => '10', 'NOV' => '11', 'DEZ' => '12',
    'JANEIRO' => '01', 'FEVEREIRO' => '02', 'MARCO' => '03', 'MARÇO' => '03',
    'ABRIL' => '04', 'MAIO' => '05', 'JUNHO' => '06', 'JULHO' => '07',
    'AGOSTO' => '08', 'SETEMBRO' => '09', 'OUTUBRO' => '10',
    'NOVEMBRO' => '11', 'DEZEMBRO' => '12',
];

function extrairData(string $texto, array $meses): ?string {
    $texto = mb_strtoupper($texto);

    // DD-MM-YYYY ou DD.MM.YYYY ou DD/MM/YYYY
    if (preg_match('/(\d{2})[\-\.\/](\d{2})[\-\.\/](\d{4})/', $texto, $m)) {
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
        if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31 && $y >= 2020 && $y <= 2030) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
    }

    // DD-MM-YY
    if (preg_match('/(\d{2})[\-\.\/](\d{2})[\-\.\/](\d{2})(?!\d)/', $texto, $m)) {
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3] + 2000;
        if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31 && $y >= 2020 && $y <= 2030) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
    }

    // MES-YYYY ou MES YYYY (ex: FEV-2026, JULHO-2025)
    foreach ($meses as $nome => $num) {
        if (preg_match('/\b' . $nome . '[\s\-\.]*(\d{4})\b/', $texto, $m)) {
            $y = (int)$m[1];
            if ($y >= 2020 && $y <= 2030) {
                return sprintf('%04d-%s-01', $y, $num);
            }
        }
    }

    // MM-YYYY (ex: 02-2026)
    if (preg_match('/\b(\d{2})[\-\.\/](\d{4})\b/', $texto, $m)) {
        $mo = (int)$m[1]; $y = (int)$m[2];
        if ($mo >= 1 && $mo <= 12 && $y >= 2020 && $y <= 2030) {
            return sprintf('%04d-%02d-01', $y, $mo);
        }
    }

    // Apenas YYYY (ex: "NR 10 2026")
    if (preg_match('/\b(202[0-9])\b/', $texto, $m)) {
        return $m[1] . '-01-01';
    }

    return null;
}

$updated = 0;
$notFound = 0;
$stmtUpdate = $pdo->prepare("
    UPDATE documentos SET
        data_emissao = :emissao,
        data_validade = :validade,
        status = :status,
        aprovacao_status = 'pendente'
    WHERE id = :id
");

foreach ($docs as $doc) {
    // Tentar extrair data do arquivo_nome (nome original)
    $dataReal = extrairData($doc['arquivo_nome'] ?? '', $meses);

    // Se não encontrou, tentar do arquivo_path (nome renomeado)
    if (!$dataReal) {
        $dataReal = extrairData($doc['arquivo_path'] ?? '', $meses);
    }

    if (!$dataReal || $dataReal === '2025-01-01') {
        $notFound++;
        continue;
    }

    // Calcular validade
    $validadeMeses = (int)($doc['validade_meses'] ?? 12);

    // EPI: verificar obra
    if ($doc['tipo_documento_id'] == 6 && $doc['obra_id']) {
        $stmtObra->execute(['oid' => $doc['obra_id']]);
        $obraEpi = $stmtObra->fetchColumn();
        if ($obraEpi) $validadeMeses = (int)$obraEpi;
    }

    $dataValidade = null;
    if ($validadeMeses > 0) {
        $dataValidade = date('Y-m-d', strtotime("{$dataReal} + {$validadeMeses} months"));
    }

    // Calcular status
    $status = 'vigente';
    if ($dataValidade) {
        $hoje = date('Y-m-d');
        $em30 = date('Y-m-d', strtotime('+30 days'));
        if ($dataValidade < $hoje) $status = 'vencido';
        elseif ($dataValidade <= $em30) $status = 'proximo_vencimento';
    }

    $stmtUpdate->execute([
        'emissao' => $dataReal,
        'validade' => $dataValidade,
        'status' => $status,
        'id' => $doc['id'],
    ]);
    $updated++;
}

echo "Atualizados: {$updated}" . PHP_EOL;
echo "Sem data encontrada: {$notFound}" . PHP_EOL;

// Verificar resultado
$stmt = $pdo->query("SELECT COUNT(*) FROM documentos WHERE data_emissao = '2025-01-01' AND excluido_em IS NULL AND status != 'obsoleto'");
echo "Restantes com placeholder: " . $stmt->fetchColumn() . PHP_EOL;
