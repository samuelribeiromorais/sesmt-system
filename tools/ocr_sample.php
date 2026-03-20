<?php
// OCR sample - test reading PDFs with poppler-utils + tesseract
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

// Get sample documents from different collaborators - only "Declaração de Treinamentos" type (the problematic ones)
$rows = $db->query("SELECT d.id, d.arquivo_nome, d.arquivo_path, d.data_emissao, d.data_validade, d.status,
    td.nome as tipo_nome, td.categoria, c.nome_completo, c.id as colab_id, c.status as colab_status
    FROM documentos d
    JOIN tipos_documento td ON d.tipo_documento_id=td.id
    JOIN colaboradores c ON d.colaborador_id=c.id
    WHERE d.status != 'obsoleto'
    AND td.nome = 'Declaração de Treinamentos'
    AND c.status = 'ativo'
    ORDER BY RAND()
    LIMIT 10")->fetchAll();

$uploadPath = '/var/www/html/storage/uploads';

function extractTextFromPdf($filePath) {
    // Try pdftotext first (for text-based PDFs)
    $tmpTxt = tempnam(sys_get_temp_dir(), 'ocr_');
    exec("pdftotext -layout " . escapeshellarg($filePath) . " " . escapeshellarg($tmpTxt) . " 2>&1", $output, $ret);

    $text = '';
    if ($ret === 0 && file_exists($tmpTxt)) {
        $text = file_get_contents($tmpTxt);
    }
    @unlink($tmpTxt);

    // If pdftotext gave substantial text, use it
    if (strlen(trim($text)) > 30) {
        return ['method' => 'pdftotext', 'text' => $text];
    }

    // Otherwise try OCR: convert first page to image then OCR
    $tmpBase = tempnam(sys_get_temp_dir(), 'pdf_');
    exec("pdftoppm -png -r 300 -f 1 -l 1 " . escapeshellarg($filePath) . " " . escapeshellarg($tmpBase) . " 2>&1", $out2, $ret2);

    // Find generated image
    $possibleImgs = glob($tmpBase . '*.png');
    if (empty($possibleImgs)) {
        // Try with -singlefile
        exec("pdftoppm -png -r 300 -singlefile " . escapeshellarg($filePath) . " " . escapeshellarg($tmpBase) . " 2>&1");
        $possibleImgs = glob($tmpBase . '*.png');
    }

    if (!empty($possibleImgs)) {
        $imgFile = $possibleImgs[0];
        $ocrOutput = [];
        exec("tesseract " . escapeshellarg($imgFile) . " stdout -l por 2>/dev/null", $ocrOutput, $ocrRet);
        $text = implode(PHP_EOL, $ocrOutput);
        foreach ($possibleImgs as $img) @unlink($img);
    }

    @unlink($tmpBase);
    return ['method' => 'tesseract-ocr', 'text' => $text];
}

echo "=== TESTANDO OCR EM 10 DOCUMENTOS ALEATORIOS (Declaração de Treinamentos) ===" . PHP_EOL . PHP_EOL;

foreach ($rows as $r) {
    echo "========================================" . PHP_EOL;
    echo "Doc ID: {$r['id']} | Colab: {$r['nome_completo']} ({$r['colab_status']})" . PHP_EOL;
    echo "Arquivo: {$r['arquivo_nome']}" . PHP_EOL;
    echo "Tipo atual: {$r['tipo_nome']} | Emissao: {$r['data_emissao']} | Val: " . ($r['data_validade'] ?? 'N/A') . PHP_EOL;

    $filePath = $uploadPath . '/' . $r['arquivo_path'];
    if (!file_exists($filePath)) {
        echo "ARQUIVO NAO ENCONTRADO!" . PHP_EOL . PHP_EOL;
        continue;
    }

    $result = extractTextFromPdf($filePath);
    echo "Metodo: {$result['method']} | Chars: " . strlen($result['text']) . PHP_EOL;
    echo "--- TEXTO ---" . PHP_EOL;
    echo substr(trim($result['text']), 0, 600) . PHP_EOL;
    echo PHP_EOL;
}

// Also check active vs inactive stats
echo PHP_EOL . "=== FILTRO ATIVOS/INATIVOS ===" . PHP_EOL;
$r = $db->query("SELECT c.status as colab_status, COUNT(d.id) as total_docs
    FROM documentos d
    JOIN colaboradores c ON d.colaborador_id = c.id
    WHERE d.status != 'obsoleto'
    GROUP BY c.status")->fetchAll();
foreach ($r as $row) {
    echo "Colaboradores {$row['colab_status']}: {$row['total_docs']} documentos" . PHP_EOL;
}
echo PHP_EOL;
$r = $db->query("SELECT status, COUNT(*) as total FROM colaboradores GROUP BY status")->fetchAll();
foreach ($r as $row) {
    echo "Colaboradores {$row['status']}: {$row['total']}" . PHP_EOL;
}
