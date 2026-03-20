<?php
/**
 * AUDITORIA COMPLETA DO DOSSIÊ
 *
 * Varre TODOS os colaboradores no DOSSIÊ físico, cruza com o banco de dados,
 * e importa qualquer documento que esteja faltando.
 *
 * Uso: docker exec sesmt-system-web-1 php /var/www/html/scripts/auditoria-completa-dossie.php [--dry-run]
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ── Config ──────────────────────────────────────────────────
$possiblePaths = [
    '/var/www/dossie/1. DOSSIÊ/1. COLABORADORES ATIVOS',
    '/var/www/html/../SESMT/1. DOSSIÊ/1. COLABORADORES ATIVOS',
    dirname(__DIR__, 2) . '/SESMT/1. DOSSIÊ/1. COLABORADORES ATIVOS',
];

$dossieBase = null;
foreach ($possiblePaths as $p) {
    if (is_dir($p)) { $dossieBase = $p; break; }
}

$storagePath = dirname(__DIR__) . '/storage/uploads';
$dryRun = in_array('--dry-run', $argv ?? []);

// ── Conexão DB ──────────────────────────────────────────────
$envVars = [];
foreach (file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (str_contains($line, '=')) {
        [$key, $val] = explode('=', $line, 2);
        $envVars[trim($key)] = trim($val, '"\'');
    }
}
$db = new PDO(
    "mysql:host=" . ($envVars['DB_HOST'] ?? 'db') . ";port=" . ($envVars['DB_PORT'] ?? '3306') . ";dbname=" . ($envVars['DB_NAME'] ?? 'sesmt_tse') . ";charset=utf8mb4",
    $envVars['DB_USER'] ?? 'sesmt',
    $envVars['DB_PASS'] ?? 'sesmt2026',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

if (!$dossieBase) {
    echo "ERRO: DOSSIÊ não encontrado!\n";
    exit(1);
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          AUDITORIA COMPLETA DO DOSSIÊ                       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "DOSSIÊ: {$dossieBase}\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (simulação)" : "EXECUÇÃO REAL") . "\n\n";

// ── Carregar TODOS os hashes do banco ───────────────────────
echo "Carregando hashes existentes do banco...\n";
$existingHashes = [];
$stmt = $db->query("SELECT arquivo_hash, colaborador_id FROM documentos WHERE excluido_em IS NULL");
while ($row = $stmt->fetch()) {
    $existingHashes[$row['colaborador_id'] . ':' . $row['arquivo_hash']] = true;
    $existingHashes['global:' . $row['arquivo_hash']] = true;
}
echo "  → " . count($existingHashes) . " registros carregados\n";

// ── Carregar TODOS os colaboradores do banco (index por nome) ──
echo "Carregando colaboradores do banco...\n";
$colabsByName = [];
$colabsById = [];
$stmt = $db->query("SELECT id, nome_completo, status, excluido_em FROM colaboradores WHERE excluido_em IS NULL");
while ($row = $stmt->fetch()) {
    $key = mb_strtoupper(trim($row['nome_completo']));
    $colabsByName[$key] = $row;
    $colabsById[$row['id']] = $row;
}
echo "  → " . count($colabsByName) . " colaboradores no banco\n";

// ── Carregar validade_meses por tipo ────────────────────────
$tiposDoc = $db->query("SELECT id, nome, validade_meses FROM tipos_documento")->fetchAll();
$validadePorTipo = [];
foreach ($tiposDoc as $t) { $validadePorTipo[$t['id']] = $t['validade_meses']; }

// ── Mapeamentos ─────────────────────────────────────────────
$treinamentoMap = [
    'NR.?06'           => 15, 'NR.?10.*RECICL'   => 17, 'NR.?10.*SEP'      => 18,
    'NR.?10.*BAS'      => 16, 'NR.?10'           => 16, 'NR.?11.*MUNCK'    => 21,
    'NR.?11.*PONTE'    => 22, 'NR.?11.*RIGGER'   => 23, 'NR.?11.*SINAL'    => 24,
    'NR.?12.*RECICL'   => 26, 'NR.?12'           => 25, 'NR.?18.*ANDAIM'   => 28,
    'NR.?18.*PLATAF'   => 29, 'NR.?18'           => 27, 'NR.?20'           => 30,
    'NR.?33.*SUPER'    => 32, 'NR.?33'           => 31, 'NR.?34.*SOLD'     => 34,
    'NR.?34.*OBSERV'   => 35, 'NR.?34.*QUENTE'   => 36, 'NR.?34'           => 33,
    'NR.?35'           => 37, 'LOTO'             => 38, 'DIRECAO.?DEFEN'   => 39,
    'INTEGRACAO'       => 41, 'AUTORIZACAO.*NR.?10' => 20,
];
$anuenciaMap = ['NR.?10' => 11, 'NR.?33' => 12, 'NR.?35' => 13];

// ── Varrer TODAS as pastas do DOSSIÊ ────────────────────────
$stats = [
    'pastas_total' => 0, 'pastas_match' => 0, 'pastas_no_match' => 0,
    'arquivos_total' => 0, 'ja_no_banco' => 0, 'novos_importados' => 0,
    'erros' => 0, 'obsoletos_ignorados' => 0, 'colabs_com_gaps' => 0,
];
$noMatch = [];
$gapDetails = [];

$letters = scandir($dossieBase);
sort($letters);

foreach ($letters as $letter) {
    if ($letter === '.' || $letter === '..' || $letter === '1. MODELO PASTA') continue;
    $letterPath = "{$dossieBase}/{$letter}";
    if (!is_dir($letterPath)) continue;

    $colabFolders = scandir($letterPath);
    sort($colabFolders);

    foreach ($colabFolders as $folderName) {
        if ($folderName === '.' || $folderName === '..') continue;
        $colabPath = "{$letterPath}/{$folderName}";
        if (!is_dir($colabPath)) continue;

        $stats['pastas_total']++;

        // Tentar match com banco
        $nameUpper = mb_strtoupper(trim($folderName));
        $colab = $colabsByName[$nameUpper] ?? null;

        // Tentar match parcial se nome truncado
        if (!$colab) {
            foreach ($colabsByName as $dbName => $dbColab) {
                if (strlen($nameUpper) >= 40 && str_starts_with($dbName, substr($nameUpper, 0, 40))) {
                    $colab = $dbColab;
                    break;
                }
                if (strlen($dbName) >= 40 && str_starts_with($nameUpper, substr($dbName, 0, 40))) {
                    $colab = $dbColab;
                    break;
                }
            }
        }

        // Tentar match ignorando acentos
        if (!$colab) {
            $nameNorm = removeAccents($nameUpper);
            foreach ($colabsByName as $dbName => $dbColab) {
                if (removeAccents($dbName) === $nameNorm) {
                    $colab = $dbColab;
                    break;
                }
            }
        }

        if (!$colab) {
            $stats['pastas_no_match']++;
            $fileCount = countFiles($colabPath);
            $noMatch[] = ['pasta' => $folderName, 'arquivos' => $fileCount];
            continue;
        }

        $stats['pastas_match']++;
        $colabId = $colab['id'];

        // Varrer todos os arquivos do colaborador
        $allFiles = varrerPastaCompleta($colabPath);
        $newFilesForColab = 0;

        foreach ($allFiles as $fileInfo) {
            $arqPath = $fileInfo['path'];
            $arqNome = $fileInfo['name'];
            $subdir = $fileInfo['folder'];
            $isObsolete = $fileInfo['obsolete'];

            $stats['arquivos_total']++;

            // Ignorar obsoletos
            if ($isObsolete) {
                $stats['obsoletos_ignorados']++;
                continue;
            }

            // Calcular hash
            $fileSize = filesize($arqPath);
            if ($fileSize < 100) continue; // Muito pequeno

            $hash = hash_file('sha256', $arqPath);

            // Já existe no banco para este colaborador?
            $key = $colabId . ':' . $hash;
            if (isset($existingHashes[$key])) {
                $stats['ja_no_banco']++;
                continue;
            }

            // Mesmo hash mas em outro colaborador? (pode ser doc genérico compartilhado)
            // Ainda importar para este colaborador
            $tipoDocId = determinarTipo($subdir, $arqNome, $treinamentoMap, $anuenciaMap);
            $dataEmissao = extrairData($arqNome) ?: date('Y-m-d', filemtime($arqPath));
            $ext = strtolower(pathinfo($arqNome, PATHINFO_EXTENSION));

            $validadeMeses = $validadePorTipo[$tipoDocId] ?? null;
            $dataValidade = $validadeMeses ? date('Y-m-d', strtotime("{$dataEmissao} +{$validadeMeses} months")) : null;
            $status = calcularStatus($dataValidade);

            $safeName = $hash . '.' . $ext;
            $relativePath = $colabId . '/' . $safeName;

            if (!$dryRun) {
                $destDir = $storagePath . '/' . $colabId;
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0750, true);
                    @chown($destDir, 'www-data');
                    @chgrp($destDir, 'www-data');
                }
                $destPath = $destDir . '/' . $safeName;
                if (!file_exists($destPath)) {
                    copy($arqPath, $destPath);
                    chmod($destPath, 0640);
                    @chown($destPath, 'www-data');
                    @chgrp($destPath, 'www-data');
                }

                $stmt2 = $db->prepare("
                    INSERT INTO documentos (colaborador_id, tipo_documento_id, arquivo_nome, arquivo_path, arquivo_hash, arquivo_tamanho, data_emissao, data_validade, status, observacoes, enviado_por, versao)
                    VALUES (:cid, :tid, :nome, :path, :hash, :size, :emissao, :validade, :status, :obs, 2, 1)
                ");
                $stmt2->execute([
                    'cid' => $colabId, 'tid' => $tipoDocId, 'nome' => $arqNome,
                    'path' => $relativePath, 'hash' => $hash, 'size' => $fileSize,
                    'emissao' => $dataEmissao, 'validade' => $dataValidade, 'status' => $status,
                    'obs' => 'Auditoria completa DOSSIÊ - ' . date('d/m/Y H:i'),
                ]);

                // Registrar no cache local para evitar re-importar no mesmo run
                $existingHashes[$key] = true;
            }

            $stats['novos_importados']++;
            $newFilesForColab++;

            if (!isset($gapDetails[$folderName])) {
                $gapDetails[$folderName] = ['id' => $colabId, 'novos' => [], 'total_existente' => 0];
            }
            $gapDetails[$folderName]['novos'][] = "[{$tipoDocId}] {$arqNome} ({$dataEmissao})";
        }

        if ($newFilesForColab > 0) {
            $stats['colabs_com_gaps']++;
        }
    }

    // Progresso por letra
    echo "  Letra {$letter}: processada ({$stats['novos_importados']} gaps até agora)\n";
    gc_collect_cycles(); // Liberar memória
    flush();
}

// ── RELATÓRIO ───────────────────────────────────────────────
echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    RESULTADO DA AUDITORIA                    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "PASTAS NO DOSSIÊ:\n";
echo "  Total varridas:           {$stats['pastas_total']}\n";
echo "  Com match no banco:       {$stats['pastas_match']}\n";
echo "  SEM match no banco:       {$stats['pastas_no_match']}\n\n";

echo "ARQUIVOS:\n";
echo "  Total varridos:           {$stats['arquivos_total']}\n";
echo "  Já existiam no banco:     {$stats['ja_no_banco']}\n";
echo "  NOVOS importados:         {$stats['novos_importados']}\n";
echo "  Obsoletos ignorados:      {$stats['obsoletos_ignorados']}\n";
echo "  Erros:                    {$stats['erros']}\n\n";

echo "GAPS ENCONTRADOS:\n";
echo "  Colaboradores com gaps:   {$stats['colabs_com_gaps']}\n\n";

if (!empty($gapDetails)) {
    echo "─── DETALHES DOS GAPS (documentos novos por colaborador) ───\n\n";
    foreach ($gapDetails as $nome => $info) {
        echo "  ► {$nome} (ID {$info['id']}) — " . count($info['novos']) . " novos\n";
        foreach ($info['novos'] as $doc) {
            echo "      + {$doc}\n";
        }
        echo "\n";
    }
}

if (!empty($noMatch)) {
    echo "─── PASTAS SEM MATCH NO BANCO ───\n";
    echo "(Colaboradores no DOSSIÊ que NÃO existem no sistema)\n\n";
    $noMatchWithFiles = array_filter($noMatch, fn($n) => $n['arquivos'] > 0);
    $noMatchEmpty = array_filter($noMatch, fn($n) => $n['arquivos'] === 0);

    if (!empty($noMatchWithFiles)) {
        echo "  COM ARQUIVOS (atenção — possível nome diferente):\n";
        foreach ($noMatchWithFiles as $n) {
            echo "    ⚠ {$n['pasta']} ({$n['arquivos']} arquivos)\n";
        }
        echo "\n";
    }
    if (!empty($noMatchEmpty)) {
        echo "  SEM ARQUIVOS (pastas vazias):\n";
        foreach ($noMatchEmpty as $n) {
            echo "    ○ {$n['pasta']}\n";
        }
        echo "\n";
    }
}

if ($dryRun) {
    echo "\n⚠ MODO DRY-RUN: nenhuma alteração foi feita.\n";
    echo "Execute sem --dry-run para aplicar as importações.\n";
}

echo "\nAuditoria concluída em " . date('Y-m-d H:i:s') . "\n";

// ── Funções ─────────────────────────────────────────────────

function varrerPastaCompleta(string $dir, string $currentFolder = '', bool $isObsolete = false): array
{
    $files = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'Thumbs.db' || $entry === 'desktop.ini') continue;

        $path = "{$dir}/{$entry}";
        $entryUpper = mb_strtoupper($entry);

        if (is_dir($path)) {
            $obsolete = $isObsolete || str_contains($entryUpper, 'OBSOLET') || str_contains($entryUpper, 'ABSOLET');
            $folder = $currentFolder ?: $entry;
            $files = array_merge($files, varrerPastaCompleta($path, $folder, $obsolete));
            continue;
        }

        if (preg_match('/\.(pdf|jpg|jpeg|png)$/i', $entry)) {
            $files[] = [
                'path' => $path,
                'name' => $entry,
                'folder' => $currentFolder ?: basename($dir),
                'obsolete' => $isObsolete,
            ];
        }
    }
    return $files;
}

function countFiles(string $dir): int
{
    $count = 0;
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = "{$dir}/{$entry}";
        if (is_dir($path)) {
            $count += countFiles($path);
        } elseif (preg_match('/\.(pdf|jpg|jpeg|png)$/i', $entry)) {
            $count++;
        }
    }
    return $count;
}

function determinarTipo(string $folder, string $filename, array $treinamentoMap, array $anuenciaMap): int
{
    $folderUpper = mb_strtoupper($folder);
    $fileUpper = mb_strtoupper($filename);

    if (str_contains($folderUpper, 'ASO')) {
        if (str_contains($fileUpper, 'PERIODIC')) return 2;
        if (str_contains($fileUpper, 'DEMISSION') || str_contains($fileUpper, 'DEMISSIONAL')) return 3;
        if (str_contains($fileUpper, 'RETORNO')) return 4;
        if (str_contains($fileUpper, 'MUDANCA') || str_contains($fileUpper, 'MUD') && str_contains($fileUpper, 'RISCO')) return 5;
        return 1; // Admissional default
    }
    if (str_contains($folderUpper, 'EPI')) return 6;
    if (str_contains($folderUpper, 'O.S') || $folderUpper === 'OS') return 7;
    if (str_contains($folderUpper, 'ANUENCIA') || str_contains($folderUpper, 'ANUÊNCIA')) {
        foreach ($anuenciaMap as $pat => $tid) {
            if (preg_match('/' . $pat . '/i', $fileUpper)) return $tid;
        }
        return 11;
    }
    if (str_contains($folderUpper, 'TREINAMENTO') || str_contains($folderUpper, 'CERTIFICADO')) {
        foreach ($treinamentoMap as $pat => $tid) {
            if (preg_match('/' . $pat . '/i', $fileUpper)) return $tid;
        }
        return 9;
    }
    if (str_contains($folderUpper, 'PROGRAMA')) return 14;

    // Tentar pelo nome do arquivo independente da pasta
    if (str_contains($fileUpper, 'ASO') || str_contains($fileUpper, 'PRONTUARIO') || str_contains($fileUpper, 'FICHA CLINICA')) return 1;
    if (str_contains($fileUpper, 'EPI')) return 6;
    if (str_contains($fileUpper, 'ORDEM') || str_contains($fileUpper, 'O.S') || (str_contains($fileUpper, 'OS ') && str_contains($fileUpper, '.PDF'))) return 7;
    if (str_contains($fileUpper, 'ANUENCIA') || str_contains($fileUpper, 'ANUÊNCIA')) {
        foreach ($anuenciaMap as $pat => $tid) {
            if (preg_match('/' . $pat . '/i', $fileUpper)) return $tid;
        }
        return 11;
    }
    foreach ($treinamentoMap as $pat => $tid) {
        if (preg_match('/' . $pat . '/i', $fileUpper)) return $tid;
    }

    return 14; // Outro
}

function extrairData(string $nomeArquivo): ?string
{
    $nome = mb_strtoupper($nomeArquivo);

    // DD.MM.YYYY ou DD-MM-YYYY ou DD/MM/YYYY
    if (preg_match('/(\d{2})[.\-\/](\d{2})[.\-\/](\d{4})/', $nome, $m)) {
        $d = (int)$m[1]; $mth = (int)$m[2]; $y = (int)$m[3];
        if ($y < 2000 || $y > 2030) return null;
        if ($d > 12 && $mth <= 12) return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        if ($d <= 12 && $mth > 12) return sprintf('%04d-%02d-%02d', $y, $d, $mth);
        return sprintf('%04d-%02d-%02d', $y, $mth, $d);
    }

    // DD-MM-YY
    if (preg_match('/(\d{2})[.\-\/](\d{2})[.\-\/](\d{2})(?!\d)/', $nome, $m)) {
        $y = (int)$m[3];
        $y = $y > 50 ? 1900 + $y : 2000 + $y;
        return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
    }

    // Mês por extenso: JAN, FEV, MAR, ABR, MAI, JUN, JUL, AGO, SET, OUT, NOV, DEZ + YYYY
    $meses = ['JAN'=>1,'FEV'=>2,'MAR'=>3,'MARS'=>3,'ABR'=>4,'ABRIL'=>4,'MAI'=>5,'MAIO'=>5,'JUN'=>6,'JUL'=>7,'AGO'=>8,'AGOS'=>8,'AGOSTO'=>8,'SET'=>9,'SEPT'=>9,'OUT'=>10,'NOV'=>11,'DEZ'=>12,'DEZ\.'=>12];
    foreach ($meses as $mesNome => $mesNum) {
        // Padrão: MÊS-YYYY ou MÊS YYYY
        if (preg_match('/' . $mesNome . '[.\-\s\/]*(\d{4})/i', $nome, $m)) {
            return sprintf('%04d-%02d-01', (int)$m[1], $mesNum);
        }
        // Padrão: DD MÊS YYYY
        if (preg_match('/(\d{1,2})[.\-\s]*' . $mesNome . '[.\-\s]*(\d{4})/i', $nome, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[2], $mesNum, (int)$m[1]);
        }
    }

    // MM-YYYY (sem dia)
    if (preg_match('/(\d{2})[.\-\/](\d{4})/', $nome, $m)) {
        $mth = (int)$m[1]; $y = (int)$m[2];
        if ($mth >= 1 && $mth <= 12 && $y >= 2000 && $y <= 2030) {
            return sprintf('%04d-%02d-01', $y, $mth);
        }
    }

    return null;
}

function calcularStatus(?string $dataValidade): string
{
    if (!$dataValidade) return 'vigente';
    $hoje = date('Y-m-d');
    if ($dataValidade < $hoje) return 'vencido';
    if ($dataValidade <= date('Y-m-d', strtotime('+30 days'))) return 'proximo_vencimento';
    return 'vigente';
}

function removeAccents(string $str): string
{
    $map = [
        'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
        'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
        'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
        'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
        'Ç'=>'C','Ñ'=>'N','Ý'=>'Y',
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n','ý'=>'y',
    ];
    return strtr($str, $map);
}
