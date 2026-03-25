<?php
/**
 * Corrige documentos restantes com data_emissao = 2025-01-01
 * Usa pdftotext para extrair texto e buscar datas reais.
 * Mantém aprovacao_status = 'pendente'.
 */

$dsn = 'mysql:host=db;port=3306;dbname=sesmt_tse;charset=utf8mb4';
$pdo = new PDO($dsn, 'sesmt', 'sesmt2026', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmt = $pdo->query("
    SELECT d.id, d.arquivo_path, d.tipo_documento_id, d.colaborador_id,
           td.validade_meses, c.obra_id
    FROM documentos d
    JOIN tipos_documento td ON d.tipo_documento_id = td.id
    JOIN colaboradores c ON d.colaborador_id = c.id
    WHERE d.data_emissao = '2025-01-01'
      AND d.excluido_em IS NULL AND d.status != 'obsoleto'
      AND d.arquivo_path IS NOT NULL AND d.arquivo_path != ''
    ORDER BY d.id
");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Documentos restantes: " . count($docs) . PHP_EOL;

$stmtObra = $pdo->prepare("SELECT epi_validade_meses FROM obras WHERE id = :oid AND epi_validade_meses IS NOT NULL");
$stmtUpdate = $pdo->prepare("UPDATE documentos SET data_emissao = :emissao, data_validade = :validade, status = :status, aprovacao_status = 'pendente' WHERE id = :id");

$meses = [
    'JANEIRO'=>1,'FEVEREIRO'=>2,'MARÇO'=>3,'MARCO'=>3,'ABRIL'=>4,'MAIO'=>5,
    'JUNHO'=>6,'JULHO'=>7,'AGOSTO'=>8,'SETEMBRO'=>9,'OUTUBRO'=>10,
    'NOVEMBRO'=>11,'DEZEMBRO'=>12,
    'JAN'=>1,'FEV'=>2,'MAR'=>3,'ABR'=>4,'MAI'=>5,'JUN'=>6,
    'JUL'=>7,'AGO'=>8,'SET'=>9,'OUT'=>10,'NOV'=>11,'DEZ'=>12,
];

function extrairDataTexto(string $texto): ?string {
    global $meses;
    $texto = mb_strtoupper($texto);

    // Padrões de data
    $padroes = [
        // "Data: DD/MM/YYYY" ou "Emissão: DD/MM/YYYY" ou "Realização: DD/MM/YYYY"
        '/(?:DATA|EMISS[AÃ]O|REALIZA[CÇ][AÃ]O|VALIDADE)\s*[:]\s*(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})/i',
        // DD de MONTH de YYYY
        '/(\d{1,2})\s+DE\s+([A-ZÇÃ]+)\s+DE\s+(\d{4})/i',
        // DD/MM/YYYY genérico
        '/(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})/',
    ];

    foreach ($padroes as $i => $padrao) {
        if (preg_match($padrao, $texto, $m)) {
            if ($i === 1) {
                $mesNome = trim($m[2]);
                $mesNum = $meses[$mesNome] ?? null;
                if ($mesNum) {
                    $y = (int)$m[3]; $d = (int)$m[1];
                    if ($y >= 2020 && $y <= 2030 && $d >= 1 && $d <= 31) {
                        return sprintf('%04d-%02d-%02d', $y, $mesNum, $d);
                    }
                }
            } else {
                $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
                if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31 && $y >= 2020 && $y <= 2030) {
                    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
            }
        }
    }
    return null;
}

$basePath = '/var/www/html/storage/uploads/';
$updated = 0;
$notFound = 0;
$errors = 0;
$batch = 0;

foreach ($docs as $doc) {
    $batch++;
    $filePath = $basePath . $doc['arquivo_path'];

    if (!file_exists($filePath)) {
        $notFound++;
        continue;
    }

    // Extrair texto com pdftotext (apenas primeira página)
    $cmd = sprintf('pdftotext -l 1 %s - 2>/dev/null', escapeshellarg($filePath));
    $texto = shell_exec($cmd);

    if (empty($texto)) {
        $notFound++;
        continue;
    }

    $dataReal = extrairDataTexto($texto);
    if (!$dataReal || $dataReal === '2025-01-01') {
        $notFound++;
        continue;
    }

    // Calcular validade
    $validadeMeses = (int)($doc['validade_meses'] ?? 12);
    if ($doc['tipo_documento_id'] == 6 && $doc['obra_id']) {
        $stmtObra->execute(['oid' => $doc['obra_id']]);
        $obraEpi = $stmtObra->fetchColumn();
        if ($obraEpi) $validadeMeses = (int)$obraEpi;
    }

    $dataValidade = $validadeMeses > 0 ? date('Y-m-d', strtotime("{$dataReal} + {$validadeMeses} months")) : null;

    $status = 'vigente';
    if ($dataValidade) {
        if ($dataValidade < date('Y-m-d')) $status = 'vencido';
        elseif ($dataValidade <= date('Y-m-d', strtotime('+30 days'))) $status = 'proximo_vencimento';
    }

    $stmtUpdate->execute([
        'emissao' => $dataReal,
        'validade' => $dataValidade,
        'status' => $status,
        'id' => $doc['id'],
    ]);
    $updated++;

    if ($batch % 200 === 0) {
        echo "  Processados: {$batch} / " . count($docs) . " (corrigidos: {$updated})" . PHP_EOL;
        gc_collect_cycles();
    }
}

echo PHP_EOL . "=== RESULTADO ===" . PHP_EOL;
echo "Processados: {$batch}" . PHP_EOL;
echo "Corrigidos via OCR: {$updated}" . PHP_EOL;
echo "Sem data encontrada: {$notFound}" . PHP_EOL;

$stmt = $pdo->query("SELECT COUNT(*) FROM documentos WHERE data_emissao = '2025-01-01' AND excluido_em IS NULL AND status != 'obsoleto'");
echo "Restantes com placeholder: " . $stmt->fetchColumn() . PHP_EOL;
