<?php
/**
 * Reclassificação em massa de documentos via OCR + análise de nome de arquivo
 * Foco: Converter "Declaração de Treinamentos" genéricos em tipos específicos
 * E corrigir datas de emissão/validade
 */
ini_set('memory_limit', '512M');
set_time_limit(0);

chdir('/var/www/html');
require 'vendor/autoload.php';
$lines = file('/var/www/html/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2);
    putenv(trim($k) . '=' . trim($v, " \"'"));
}
spl_autoload_register(function(string $c) {
    $p = 'App' . chr(92);
    if (!str_starts_with($c, $p)) return;
    $f = '/var/www/html/app/' . str_replace(chr(92), '/', substr($c, strlen($p))) . '.php';
    if (file_exists($f)) require $f;
});

$db = App\Core\Database::getInstance();
$uploadPath = '/var/www/html/storage/uploads';

$mode = $argv[1] ?? 'scan';
$batchSize = (int)($argv[2] ?? 100);
$offsetStart = (int)($argv[3] ?? 0);

// Build tipo_documento lookup from DB
$tiposMap = [];
$stmt = $db->query("SELECT id, nome, categoria, validade_meses FROM tipos_documento WHERE ativo=1");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) $tiposMap[$t['id']] = $t;

// ============================================================
// MAPEAMENTO DE PADRÕES -> tipo_documento_id
// Ordem importa: mais específico primeiro
// ============================================================
$patterns = [
    // --- ASO (reclassificar de treinamento para ASO) ---
    ['rx' => '/ASO\s*ADMISSIONAL|ADMISSIONAL.*ASO/iu', 'id' => 1],
    ['rx' => '/ASO\s*PERI[OÓ]DICO|PERI[OÓ]DICO.*ASO/iu', 'id' => 2],
    ['rx' => '/ASO\s*DEMISSIONAL|DEMISSIONAL.*ASO/iu', 'id' => 3],
    ['rx' => '/ASO\s*RETORNO/iu', 'id' => 4],
    ['rx' => '/ASO\s*MUDAN[CÇ]A/iu', 'id' => 5],
    ['rx' => '/ATESTADO\s*DE\s*SA[UÚ]DE\s*OCUPACIONAL/iu', 'id' => 2],
    ['rx' => '/PRONTU[AÁ]RIO\s*M[EÉ]DICO/iu', 'id' => 8],

    // --- EPI ---
    ['rx' => '/FICHA\s*DE\s*EPI/iu', 'id' => 6],

    // --- OS ---
    ['rx' => '/ORDEM\s*DE\s*SERVI[CÇ]O/iu', 'id' => 7],

    // --- Anuências ---
    ['rx' => '/ANU[EÊ]NCIA\s*[\-_\s]*NR[\s\-_]*10|ANUENCIA[\s\-_]*NR[\s\-_]*10/iu', 'id' => 11],
    ['rx' => '/ANU[EÊ]NCIA\s*[\-_\s]*NR[\s\-_]*33|ANUENCIA[\s\-_]*NR[\s\-_]*33/iu', 'id' => 12],
    ['rx' => '/ANU[EÊ]NCIA\s*[\-_\s]*NR[\s\-_]*35|ANUENCIA[\s\-_]*NR[\s\-_]*35/iu', 'id' => 13],

    // --- Treinamentos Específicos (NR-10) ---
    ['rx' => '/NR[\s\-_]*10.*RECICL.*SEP|SEP.*RECICL.*NR[\s\-_]*10/iu', 'id' => 19],
    ['rx' => '/NR[\s\-_]*10.*SEP|SEP.*NR[\s\-_]*10/iu', 'id' => 18],
    ['rx' => '/NR[\s\-_]*10.*RECICL.*20\s*[hH]|RECICLAGEM.*20\s*[hH]oras.*NR[\s\-_]*10/iu', 'id' => 42],
    ['rx' => '/NR[\s\-_]*10.*RECICLAGEM|RECICLAGEM.*NR[\s\-_]*10/iu', 'id' => 17],
    ['rx' => '/TERMO\s*NR[\s\-_]*10/iu', 'id' => 20],
    ['rx' => '/NR[\s\-_]*10.*B[AÁ]SICO|B[AÁ]SICO.*NR[\s\-_]*10/iu', 'id' => 16],
    ['rx' => '/NR[\s\-_]*10.*40\s*[hH]|40\s*[hH]oras.*NR[\s\-_]*10/iu', 'id' => 16],

    // --- NR-11 ---
    ['rx' => '/NR[\s\-_]*11.*MUN[CK]|MUN[CK].*NR[\s\-_]*11/iu', 'id' => 21],
    ['rx' => '/NR[\s\-_]*11.*PONTE\s*ROLANTE|PONTE\s*ROLANTE/iu', 'id' => 22],
    ['rx' => '/NR[\s\-_]*11.*RIGGER|RIGGER/iu', 'id' => 23],
    ['rx' => '/NR[\s\-_]*11.*SINALEIRO|SINALEIRO/iu', 'id' => 24],

    // --- NR-12 ---
    ['rx' => '/NR[\s\-_]*12.*RECICL/iu', 'id' => 26],
    ['rx' => '/NR[\s\-_]*12/iu', 'id' => 25],

    // --- NR-18 ---
    ['rx' => '/NR[\s\-_]*18.*ANDAIME|ANDAIME/iu', 'id' => 28],
    ['rx' => '/NR[\s\-_]*18.*PLATAFORMA|PLATAFORMA\s*ELEVAT/iu', 'id' => 29],
    ['rx' => '/NR[\s\-_]*18/iu', 'id' => 27],

    // --- NR-20 ---
    ['rx' => '/NR[\s\-_]*20/iu', 'id' => 30],

    // --- NR-33 ---
    ['rx' => '/NR[\s\-_]*33.*SUPERVISOR/iu', 'id' => 32],
    ['rx' => '/NR[\s\-_]*33.*TRABALHADOR|NR[\s\-_]*33.*AUTORIZADO/iu', 'id' => 31],
    ['rx' => '/NR[\s\-_]*33/iu', 'id' => 31],

    // --- NR-34 ---
    ['rx' => '/NR[\s\-_]*34.*SOLDADOR|SOLDADOR.*NR[\s\-_]*34/iu', 'id' => 34],
    ['rx' => '/NR[\s\-_]*34.*OBSERVADOR|OBSERVADOR.*NR[\s\-_]*34/iu', 'id' => 35],
    ['rx' => '/NR[\s\-_]*34.*QUENTE|TRABALHO\s*A?\s*QUENTE/iu', 'id' => 36],
    ['rx' => '/NR[\s\-_]*34/iu', 'id' => 33],

    // --- NR-35 ---
    ['rx' => '/NR[\s\-_]*35|TRABALHO\s*EM\s*ALTURA/iu', 'id' => 37],

    // --- NR-06 (certificado, não ficha EPI) ---
    ['rx' => '/CERTIF.*NR[\s\-_]*06|NR[\s\-_]*06.*CERTIF|NR[\s\-_]*0?6.*USO.*GUARDA|NR[\s\-_]*0?6.*EPI.*TREINAMENTO/iu', 'id' => 15],

    // --- LOTO ---
    ['rx' => '/LOTO|BLOQUEIO.*ETIQUETAGEM|LOCK\s*OUT/iu', 'id' => 38],

    // --- Direção Defensiva ---
    ['rx' => '/DIRE[CÇ][AÃ]O\s*DEFENSIVA/iu', 'id' => 39],

    // --- Integração ---
    ['rx' => '/INTEGRA[CÇ][AÃ]O\s*(DE\s*)?SEGURAN[CÇ]A/iu', 'id' => 41],

    // --- Declaração (de não realizar atividade) ---
    ['rx' => '/DECLARA[CÇ][AÃ]O.*N[AÃ]O\s*IR[AÁ]\s*DESENVOLVER|DECLARA[CÇ][AÃ]O.*ATIVIDADE/iu', 'id' => 40],

    // --- Kit / CTPS / PGR ---
    ['rx' => '/KIT\s*ADMISSIONAL|CTPS|CARTEIRA\s*DE\s*TRABALHO|PGR\s*.*GERENCIAMENTO/iu', 'id' => 14],

    // Fallback NR-10 genérico (quando só diz "NR 10" sem mais contexto)
    ['rx' => '/CERTIF.*NR[\s\-_]*10(?!\s*SEP)|NR[\s\-_]*10(?!\s*SEP|\s*RECICL)/iu', 'id' => 16],
    // Fallback NR-06
    ['rx' => '/NR[\s\-_]*0?6\b(?!.*EPI\b)/iu', 'id' => 15],
];

$monthNames = [
    'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'marco' => '03',
    'abril' => '04', 'maio' => '05', 'junho' => '06', 'julho' => '07',
    'agosto' => '08', 'setembro' => '09', 'outubro' => '10',
    'novembro' => '11', 'dezembro' => '12',
];
$monthAbbr = [
    'JAN' => '01', 'FEV' => '02', 'MAR' => '03', 'ABR' => '04',
    'MAI' => '05', 'JUN' => '06', 'JUL' => '07', 'AGO' => '08',
    'SET' => '09', 'OUT' => '10', 'NOV' => '11', 'DEZ' => '12',
];

function ocrPdf($filePath) {
    $tmpTxt = tempnam(sys_get_temp_dir(), 'ocr_');
    exec("pdftotext -layout " . escapeshellarg($filePath) . " " . escapeshellarg($tmpTxt) . " 2>&1", $o, $r);
    $text = ($r === 0 && file_exists($tmpTxt)) ? file_get_contents($tmpTxt) : '';
    @unlink($tmpTxt);
    if (strlen(trim($text)) > 30) return $text;

    $tmpB = tempnam(sys_get_temp_dir(), 'pdf_');
    exec("pdftoppm -png -r 200 -f 1 -l 1 " . escapeshellarg($filePath) . " " . escapeshellarg($tmpB) . " 2>&1");
    $imgs = glob($tmpB . '*.png');
    if (empty($imgs)) {
        exec("pdftoppm -png -r 200 -singlefile " . escapeshellarg($filePath) . " " . escapeshellarg($tmpB) . " 2>&1");
        $imgs = glob($tmpB . '*.png');
    }
    $text = '';
    if (!empty($imgs)) {
        $out = [];
        exec("tesseract " . escapeshellarg($imgs[0]) . " stdout -l por 2>/dev/null", $out);
        $text = implode("\n", $out);
        foreach ($imgs as $i) @unlink($i);
    }
    @unlink($tmpB);
    return $text;
}

function detectType($text, $filename, $patterns) {
    // Try filename first (more reliable for well-named files)
    foreach ($patterns as $p) {
        if (preg_match($p['rx'], $filename)) return $p['id'];
    }
    // Then OCR text
    foreach ($patterns as $p) {
        if (preg_match($p['rx'], $text)) return $p['id'];
    }
    return null;
}

function extractDate($text, $filename, $monthNames, $monthAbbr) {
    $mp = implode('|', array_keys($monthNames));

    // 1: "Data de realização: DD de MÊS de YYYY" or "realizado no dia..."
    if (preg_match('/(?:data\s*de\s*realiza|realizado\s*(?:no|nos|em)\s*(?:dia)?)[^0-9]*(\d{1,2})\s*(?:de\s*)?(' . $mp . ')\s*(?:de\s*)?(\d{4})/iu', $text, $m)) {
        if ((int)$m[3] >= 2020 && (int)$m[3] <= 2030)
            return $m[3] . '-' . $monthNames[mb_strtolower($m[2])] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }

    // 2: "Goiânia, DD de MÊS de YYYY"
    if (preg_match('/(?:Goi[aâ]nia|Pouso\s*Alegre)[,\s]+(\d{1,2})\s*(?:de\s*)?(' . $mp . ')\s*(?:de\s*)?(\d{4})/iu', $text, $m)) {
        if ((int)$m[3] >= 2020 && (int)$m[3] <= 2030)
            return $m[3] . '-' . $monthNames[mb_strtolower($m[2])] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }

    // 3: "Emitido em DD/MM/YYYY"
    if (preg_match('/(?:emitido|valido|v[aá]lido)\s*(?:em|ate|até)[:\s]*(\d{2})\/(\d{2})\/(\d{4})/iu', $text, $m)) {
        if ((int)$m[3] >= 2020 && (int)$m[3] <= 2030)
            return $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    // 4: DD-MM-YYYY or DD-MM-YY in filename
    if (preg_match('/(\d{2})[\-_](\d{2})[\-_](20\d{2}|\d{2})/', $filename, $m)) {
        $yr = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
        if ((int)$m[1] <= 31 && (int)$m[2] <= 12 && (int)$yr >= 2020 && (int)$yr <= 2030)
            return $yr . '-' . $m[2] . '-' . $m[1];
    }

    // 5: MMM-YYYY in filename
    $ap = implode('|', array_keys($monthAbbr));
    if (preg_match('/(' . $ap . ')[\-_\s]*(20\d{2})/i', $filename, $m)) {
        return $m[2] . '-' . $monthAbbr[strtoupper($m[1])] . '-01';
    }
    // MM-YYYY in filename
    if (preg_match('/(\d{2})[\-_](20\d{2})/', $filename, $m)) {
        if ((int)$m[1] >= 1 && (int)$m[1] <= 12)
            return $m[2] . '-' . $m[1] . '-01';
    }

    // 6: Generic "DD de MÊS de YYYY" in text (skip portaria/lei dates)
    if (preg_match_all('/(\d{1,2})\s*(?:de\s*)?(' . $mp . ')\s*(?:de\s*)?(\d{4})/iu', $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($all as $m) {
            $yr = (int)$m[3][0];
            if ($yr < 2020 || $yr > 2030) continue;
            $ctx = substr($text, max(0, $m[0][1] - 100), 200);
            if (preg_match('/portaria|lei\s*n|decreto|artigo/iu', $ctx)) continue;
            return $yr . '-' . $monthNames[mb_strtolower($m[2][0])] . '-' . str_pad($m[1][0], 2, '0', STR_PAD_LEFT);
        }
    }

    // 7: Generic DD/MM/YYYY in text
    if (preg_match_all('/(\d{2})\/(\d{2})\/(20\d{2})/', $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($all as $m) {
            $yr = (int)$m[3][0];
            if ($yr < 2020 || $yr > 2030) continue;
            $ctx = substr($text, max(0, $m[0][1] - 100), 200);
            if (preg_match('/portaria|lei\s*n|decreto/iu', $ctx)) continue;
            return $m[3][0] . '-' . $m[2][0] . '-' . $m[1][0];
            break;
        }
    }

    return null;
}

// ===== MAIN PROCESSING =====
echo "=== RECLASSIFICADOR DE DOCUMENTOS v2 ===" . PHP_EOL;
echo "Modo: {$mode} | Batch: {$batchSize} | Offset: {$offsetStart}" . PHP_EOL;

$totalDocs = (int)$db->query("SELECT COUNT(*) as t FROM documentos d
    JOIN colaboradores c ON c.id=d.colaborador_id
    WHERE d.status != 'obsoleto' AND c.status = 'ativo' AND d.excluido_em IS NULL")->fetch()['t'];

// TODOS os documentos de colaboradores ativos
$docs = $db->query("SELECT d.id, d.arquivo_nome, d.arquivo_path, d.data_emissao, d.data_validade,
    d.status, d.tipo_documento_id, d.colaborador_id, td.nome as tipo_nome, td.categoria, c.nome_completo
    FROM documentos d
    JOIN tipos_documento td ON d.tipo_documento_id=td.id
    JOIN colaboradores c ON c.id=d.colaborador_id
    WHERE d.status != 'obsoleto' AND c.status = 'ativo' AND d.excluido_em IS NULL
    ORDER BY d.id LIMIT " . (int)$batchSize . " OFFSET " . (int)$offsetStart)->fetchAll(PDO::FETCH_ASSOC);

echo "Total: {$totalDocs} | Batch: " . count($docs) . " (offset {$offsetStart})" . PHP_EOL . PHP_EOL;

$changed = 0; $unchanged = 0; $errors = 0; $needsOcr = 0;

foreach ($docs as $doc) {
    $filePath = $uploadPath . '/' . $doc['arquivo_path'];
    if (!file_exists($filePath)) { $errors++; continue; }

    // Step 1: Try type detection from FILENAME only (fast, no OCR needed)
    $filenameDetectedId = detectType('', $doc['arquivo_nome'], $patterns);
    $usedOcr = false;
    $text = '';
    $ocrDetectedId = null;

    // Step 2: If tipo is "Declaração de Treinamentos" (9) and filename didn't match, or need date fix, use OCR
    $needsDateFix = ($doc['data_emissao'] === '2025-01-01');
    $needsOcrForType = ($doc['tipo_documento_id'] == 9 && !$filenameDetectedId);

    if ($needsOcrForType || $needsDateFix) {
        $text = ocrPdf($filePath);
        $usedOcr = true;
        $needsOcr++;
        if ($needsOcrForType) {
            $ocrDetectedId = detectType($text, $doc['arquivo_nome'], $patterns);
        }
    }

    // Build updates
    $updates = [];
    $reasons = [];

    // Type change logic:
    // A) Filename detection: confiável para QUALQUER tipo (filename diz claramente o que é)
    // B) OCR detection: só para tipo_id=9 (ASOs/EPIs mencionam NRs como risco, falsos positivos)
    if ($filenameDetectedId && $filenameDetectedId != $doc['tipo_documento_id']) {
        // Filename claramente indica outro tipo - corrigir
        $newName = $tiposMap[$filenameDetectedId]['nome'] ?? "ID:{$filenameDetectedId}";
        $updates['tipo_documento_id'] = $filenameDetectedId;
        $reasons[] = "tipo(nome): {$doc['tipo_nome']} -> {$newName}";
    } elseif ($doc['tipo_documento_id'] == 9 && $ocrDetectedId && $ocrDetectedId != 9) {
        // Declaração genérica + OCR detectou tipo específico
        $newName = $tiposMap[$ocrDetectedId]['nome'] ?? "ID:{$ocrDetectedId}";
        $updates['tipo_documento_id'] = $ocrDetectedId;
        $reasons[] = "tipo(OCR): {$doc['tipo_nome']} -> {$newName}";
    }

    // Date fix
    if ($needsDateFix) {
        if (!$text) $text = ocrPdf($filePath);
        $extractedDate = extractDate($text, $doc['arquivo_nome'], $monthNames, $monthAbbr);
        if ($extractedDate && $extractedDate !== '2025-01-01') {
            // Validate date is real
            $parts = explode('-', $extractedDate);
            if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                $updates['data_emissao'] = $extractedDate;
                $reasons[] = "emissao: 2025-01-01 -> {$extractedDate}";
            }
        }
    }

    // Recalculate validade
    $fTipo = $updates['tipo_documento_id'] ?? $doc['tipo_documento_id'];
    $fEmissao = $updates['data_emissao'] ?? $doc['data_emissao'];
    $vm = $tiposMap[$fTipo]['validade_meses'] ?? null;
    if ($vm && $fEmissao) {
        $dt = new DateTime($fEmissao);
        $dt->modify("+{$vm} months");
        $newVal = $dt->format('Y-m-d');
        if ($newVal !== $doc['data_validade']) {
            $updates['data_validade'] = $newVal;
            $reasons[] = "validade: " . ($doc['data_validade'] ?? 'N/A') . " -> {$newVal}";
        }
    }

    // Recalculate status
    $fVal = $updates['data_validade'] ?? $doc['data_validade'];
    if ($fVal) {
        $diff = (int)(new DateTime())->diff(new DateTime($fVal))->format('%r%a');
        $newSt = $diff < 0 ? 'vencido' : ($diff <= 30 ? 'proximo_vencimento' : 'vigente');
        if ($newSt !== $doc['status']) {
            $updates['status'] = $newSt;
            $reasons[] = "status: {$doc['status']} -> {$newSt}";
        }
    }

    if (empty($updates)) { $unchanged++; continue; }
    $changed++;

    $num = $offsetStart + $changed + $unchanged + $errors;
    echo "[{$num}] Doc {$doc['id']} - {$doc['nome_completo']}" . PHP_EOL;
    echo "  {$doc['arquivo_nome']}" . ($usedOcr ? ' [OCR]' : ' [filename]') . PHP_EOL;
    foreach ($reasons as $r) echo "  -> {$r}" . PHP_EOL;

    if ($mode === 'apply') {
        $updates['atualizado_em'] = date('Y-m-d H:i:s');
        $set = []; $params = [];
        foreach ($updates as $c => $v) { $set[] = "{$c} = :{$c}"; $params[$c] = $v; }
        $params['id'] = $doc['id'];
        $db->prepare("UPDATE documentos SET " . implode(', ', $set) . " WHERE id = :id")->execute($params);
    }
    echo PHP_EOL;
}

echo "========================================" . PHP_EOL;
echo "Alterados: {$changed} | Sem mudanca: {$unchanged} | Erros: {$errors} | OCR usado: {$needsOcr}" . PHP_EOL;
if ($mode === 'scan') echo "*** SCAN - use 'apply' para aplicar ***" . PHP_EOL;
$next = $offsetStart + $batchSize;
if ($next < $totalDocs) echo "Proximo: php tools/reclassificar_docs.php {$mode} {$batchSize} {$next}" . PHP_EOL;
else echo "*** COMPLETO ***" . PHP_EOL;
