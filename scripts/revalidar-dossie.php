<?php
/**
 * Script de Revalidação: importa documentos do DOSSIÊ físico para o banco de dados
 *
 * Varre as pastas do DOSSIÊ de colaboradores ativos que NÃO têm documentos no banco,
 * copia os arquivos para storage/uploads e cria os registros correspondentes.
 *
 * Uso: docker exec sesmt-system-web-1 php /var/www/html/scripts/revalidar-dossie.php [--dry-run]
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
    if (is_dir($p)) {
        $dossieBase = $p;
        break;
    }
}

$storagePath = dirname(__DIR__) . '/storage/uploads';
$dryRun = in_array('--dry-run', $argv ?? []);
$verbose = in_array('--verbose', $argv ?? []) || in_array('-v', $argv ?? []);

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
$dbHost = $envVars['DB_HOST'] ?? getenv('DB_HOST') ?: 'db';
$dbPort = $envVars['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbName = $envVars['DB_NAME'] ?? getenv('DB_NAME') ?: 'sesmt_tse';
$dbUser = $envVars['DB_USER'] ?? getenv('DB_USER') ?: 'sesmt';
$dbPass = $envVars['DB_PASS'] ?? getenv('DB_PASS') ?: 'sesmt2026';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$db = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ── Mapeamento de pastas para tipo_documento_id ─────────────
$folderToTipo = [
    'ASO'              => 1,  // ASO Admissional (default)
    'FICHA DE EPI\'S'  => 6,  // Ficha de EPI
    'FICHA DE EPI'     => 6,
    'FICHA DE EPIS'    => 6,
    'O.S'              => 7,  // Ordem de Serviço
    'OS'               => 7,
    'ANUENCIA'         => 11, // Anuência NR-10 (default)
    'PROGRAMAS'        => 14, // Kit/Outro (programas de segurança)
];

// Mapeamento inteligente para treinamentos pelo nome do arquivo
$treinamentoMap = [
    'NR.?06'           => 15, // NR-06 EPI
    'NR.?10.*RECICL'   => 17, // NR-10 Reciclagem
    'NR.?10.*SEP'      => 18, // NR-10 SEP
    'NR.?10.*BAS'      => 16, // NR-10 Básico
    'NR.?10'           => 16, // NR-10 Básico (default)
    'NR.?11.*MUNCK'    => 21,
    'NR.?11.*PONTE'    => 22,
    'NR.?11.*RIGGER'   => 23,
    'NR.?11.*SINAL'    => 24,
    'NR.?12.*RECICL'   => 26,
    'NR.?12'           => 25,
    'NR.?18.*ANDAIM'   => 28,
    'NR.?18.*PLATAF'   => 29,
    'NR.?18'           => 27, // NR-18 Geral
    'NR.?20'           => 30,
    'NR.?33.*SUPER'    => 32,
    'NR.?33'           => 31,
    'NR.?34.*SOLD'     => 34,
    'NR.?34.*OBSERV'   => 35,
    'NR.?34.*QUENTE'   => 36,
    'NR.?34'           => 33,
    'NR.?35'           => 37,
    'LOTO'             => 38,
    'DIRECAO.?DEFEN'   => 39,
    'INTEGRACAO'       => 41,
    'AUTORIZACAO.*NR.?10' => 20, // Termo NR-10
];

// Mapeamento para anuências pelo nome do arquivo
$anuenciaMap = [
    'NR.?10' => 11,
    'NR.?33' => 12,
    'NR.?35' => 13,
];

// ── Buscar validade_meses por tipo ──────────────────────────
$tiposDoc = $db->query("SELECT id, nome, validade_meses FROM tipos_documento")->fetchAll();
$validadePorTipo = [];
foreach ($tiposDoc as $t) {
    $validadePorTipo[$t['id']] = $t['validade_meses'];
}

// ── Colaboradores ativos sem docs no banco ──────────────────
$colabsSemDocs = $db->query("
    SELECT c.id, c.nome_completo
    FROM colaboradores c
    WHERE c.status = 'ativo' AND c.excluido_em IS NULL
    AND NOT EXISTS (SELECT 1 FROM documentos d WHERE d.colaborador_id = c.id)
    ORDER BY c.nome_completo
")->fetchAll();

echo "=== REVALIDAÇÃO DO DOSSIÊ ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "Colaboradores sem documentos no banco: " . count($colabsSemDocs) . "\n";
if ($dossieBase) {
    echo "DOSSIÊ: {$dossieBase}\n";
} else {
    echo "ERRO: Pasta DOSSIÊ não encontrada!\n";
    echo "Tentativas:\n";
    foreach ($possiblePaths as $p) echo "  - {$p}\n";
    exit(1);
}
echo "Storage: {$storagePath}\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (simulação)" : "EXECUÇÃO REAL") . "\n\n";

$stats = ['processados' => 0, 'docs_criados' => 0, 'sem_pasta' => 0, 'erros' => 0, 'ignorados_obsoleto' => 0];
$detalhes = [];

foreach ($colabsSemDocs as $colab) {
    $nome = trim($colab['nome_completo']);
    $colabId = $colab['id'];
    $firstLetter = mb_strtoupper(mb_substr($nome, 0, 1));

    $pastaColab = "{$dossieBase}/{$firstLetter}/{$nome}";

    if (!is_dir($pastaColab)) {
        // Tentar busca case-insensitive
        $letterDir = "{$dossieBase}/{$firstLetter}";
        if (!is_dir($letterDir)) {
            $stats['sem_pasta']++;
            if ($verbose) echo "  [SEM PASTA] {$nome}\n";
            continue;
        }

        $found = false;
        foreach (scandir($letterDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (mb_strtoupper($entry) === mb_strtoupper($nome)) {
                $pastaColab = "{$letterDir}/{$entry}";
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Tentar match parcial (nome truncado no banco)
            foreach (scandir($letterDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (str_starts_with(mb_strtoupper($entry), mb_strtoupper(substr($nome, 0, 40)))) {
                    $pastaColab = "{$letterDir}/{$entry}";
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $stats['sem_pasta']++;
                if ($verbose) echo "  [SEM PASTA] {$nome}\n";
                continue;
            }
        }
    }

    echo "► {$nome} (ID {$colabId})\n";
    $stats['processados']++;
    $docsColab = 0;

    // Varrer subpastas do colaborador
    foreach (scandir($pastaColab) as $subdir) {
        if ($subdir === '.' || $subdir === '..' || $subdir === 'Thumbs.db') continue;

        $subdirPath = "{$pastaColab}/{$subdir}";

        // Ignorar arquivos na raiz que não são subpastas (exceto PDFs soltos)
        if (!is_dir($subdirPath)) {
            // PDF solto na raiz — categorizar como "outro" (14)
            if (preg_match('/\.(pdf|jpg|jpeg|png)$/i', $subdir)) {
                $resultado = processarArquivo($subdirPath, $subdir, $colabId, 14, $db, $storagePath, $validadePorTipo, $dryRun);
                if ($resultado === 'criado') { $stats['docs_criados']++; $docsColab++; }
                elseif ($resultado === 'erro') { $stats['erros']++; }
            }
            continue;
        }

        $subdirUpper = mb_strtoupper($subdir);

        // Determinar tipo base da pasta
        $tipoBase = null;
        $isTraining = false;
        $isAnuencia = false;

        if (str_contains($subdirUpper, 'ASO')) {
            $tipoBase = 1;
        } elseif (str_contains($subdirUpper, 'EPI')) {
            $tipoBase = 6;
        } elseif (str_contains($subdirUpper, 'O.S') || $subdirUpper === 'OS') {
            $tipoBase = 7;
        } elseif (str_contains($subdirUpper, 'TREINAMENTO')) {
            $tipoBase = 9; // Default, será refinado por arquivo
            $isTraining = true;
        } elseif (str_contains($subdirUpper, 'PROGRAMA')) {
            $tipoBase = 14;
        } elseif (str_contains($subdirUpper, 'ANUENCIA')) {
            $tipoBase = 11;
            $isAnuencia = true;
        } else {
            $tipoBase = 14; // Outro
        }

        // Varrer arquivos na subpasta (ignorar OBSOLETO/ABSOLETO)
        $arquivos = varrerPasta($subdirPath);

        foreach ($arquivos as $arqPath) {
            $arqNome = basename($arqPath);

            // Determinar tipo específico para treinamentos
            $tipoFinal = $tipoBase;
            if ($isTraining) {
                $tipoFinal = determinarTipoTreinamento($arqNome, $treinamentoMap);
            } elseif ($isAnuencia) {
                $tipoFinal = determinarTipoAnuencia($arqNome, $anuenciaMap);
            } elseif ($tipoBase === 1) {
                // Refinar tipo de ASO pelo nome do arquivo
                $arqUpper = mb_strtoupper($arqNome);
                if (str_contains($arqUpper, 'PERIODIC')) $tipoFinal = 2;
                elseif (str_contains($arqUpper, 'DEMISSION')) $tipoFinal = 3;
                elseif (str_contains($arqUpper, 'RETORNO')) $tipoFinal = 4;
                elseif (str_contains($arqUpper, 'MUDANCA') || str_contains($arqUpper, 'RISCO')) $tipoFinal = 5;
            }

            $resultado = processarArquivo($arqPath, $arqNome, $colabId, $tipoFinal, $db, $storagePath, $validadePorTipo, $dryRun);
            if ($resultado === 'criado') { $stats['docs_criados']++; $docsColab++; }
            elseif ($resultado === 'erro') { $stats['erros']++; }
            elseif ($resultado === 'obsoleto') { $stats['ignorados_obsoleto']++; }
        }
    }

    echo "  → {$docsColab} documentos importados\n";
    $detalhes[] = ['nome' => $nome, 'id' => $colabId, 'docs' => $docsColab];
}

echo "\n=== RESULTADO ===\n";
echo "Colaboradores processados: {$stats['processados']}\n";
echo "Documentos criados: {$stats['docs_criados']}\n";
echo "Sem pasta no DOSSIÊ: {$stats['sem_pasta']}\n";
echo "Obsoletos ignorados: {$stats['ignorados_obsoleto']}\n";
echo "Erros: {$stats['erros']}\n";

if ($dryRun) {
    echo "\n⚠ MODO DRY-RUN: nenhuma alteração foi feita. Execute sem --dry-run para aplicar.\n";
}

// ── Funções ─────────────────────────────────────────────────

function varrerPasta(string $dir): array
{
    $arquivos = [];

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'Thumbs.db') continue;

        $path = "{$dir}/{$entry}";
        $entryUpper = mb_strtoupper($entry);

        // Pular pastas OBSOLETO/ABSOLETO
        if (is_dir($path)) {
            if (str_contains($entryUpper, 'OBSOLET') || str_contains($entryUpper, 'ABSOLET')) {
                continue; // Ignorar obsoletos
            }
            // Subpasta normal (ex: ASO/ASO) — varrer recursivamente
            $arquivos = array_merge($arquivos, varrerPasta($path));
            continue;
        }

        // Apenas PDFs, JPGs, PNGs
        if (preg_match('/\.(pdf|jpg|jpeg|png)$/i', $entry)) {
            $arquivos[] = $path;
        }
    }

    return $arquivos;
}

function processarArquivo(string $arqPath, string $arqNome, int $colabId, int $tipoDocId, PDO $db, string $storagePath, array $validadePorTipo, bool $dryRun): string
{
    if (!file_exists($arqPath)) return 'erro';

    $fileSize = filesize($arqPath);
    if ($fileSize < 100) return 'ignorado'; // Arquivo vazio ou quase

    $hash = hash_file('sha256', $arqPath);
    $ext = strtolower(pathinfo($arqNome, PATHINFO_EXTENSION));

    // Verificar se já existe no banco (pelo hash)
    $existing = $db->prepare("SELECT id FROM documentos WHERE arquivo_hash = :hash AND colaborador_id = :cid LIMIT 1");
    $existing->execute(['hash' => $hash, 'cid' => $colabId]);
    if ($existing->fetch()) {
        return 'duplicado';
    }

    // Extrair data do nome do arquivo
    $dataEmissao = extrairData($arqNome);
    if (!$dataEmissao) {
        // Usar data de modificação do arquivo
        $dataEmissao = date('Y-m-d', filemtime($arqPath));
    }

    // Calcular validade
    $validadeMeses = $validadePorTipo[$tipoDocId] ?? null;
    $dataValidade = null;
    if ($validadeMeses) {
        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} +{$validadeMeses} months"));
    }

    // Determinar status
    $status = 'vigente';
    if ($dataValidade) {
        $hoje = date('Y-m-d');
        if ($dataValidade < $hoje) {
            $status = 'vencido';
        } elseif ($dataValidade <= date('Y-m-d', strtotime('+30 days'))) {
            $status = 'proximo_vencimento';
        }
    }

    $safeName = $hash . '.' . $ext;
    $relativePath = $colabId . '/' . $safeName;

    if (!$dryRun) {
        // Criar diretório e copiar arquivo
        $destDir = $storagePath . '/' . $colabId;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
        }
        $destPath = $destDir . '/' . $safeName;
        if (!file_exists($destPath)) {
            copy($arqPath, $destPath);
            chmod($destPath, 0640);
        }
        // Garantir que o diretório pertence ao www-data
        @chown($destDir, 'www-data');
        @chgrp($destDir, 'www-data');

        // Inserir no banco
        $stmt = $db->prepare("
            INSERT INTO documentos (colaborador_id, tipo_documento_id, arquivo_nome, arquivo_path, arquivo_hash, arquivo_tamanho, data_emissao, data_validade, status, observacoes, enviado_por, versao)
            VALUES (:cid, :tid, :nome, :path, :hash, :size, :emissao, :validade, :status, :obs, 2, 1)
        ");
        $stmt->execute([
            'cid'      => $colabId,
            'tid'      => $tipoDocId,
            'nome'     => $arqNome,
            'path'     => $relativePath,
            'hash'     => $hash,
            'size'     => $fileSize,
            'emissao'  => $dataEmissao,
            'validade' => $dataValidade,
            'status'   => $status,
            'obs'      => 'Importado automaticamente do DOSSIÊ em ' . date('d/m/Y H:i'),
        ]);
    }

    echo "    + [{$tipoDocId}] {$arqNome} ({$dataEmissao}" . ($dataValidade ? " → {$dataValidade} [{$status}]" : "") . ")\n";
    return 'criado';
}

function extrairData(string $nomeArquivo): ?string
{
    $nome = mb_strtoupper($nomeArquivo);

    // Padrão: DD.MM.YYYY ou DD-MM-YYYY ou DD/MM/YYYY
    if (preg_match('/(\d{2})[.\-\/](\d{2})[.\-\/](\d{4})/', $nome, $m)) {
        $d = (int)$m[1]; $mth = (int)$m[2]; $y = (int)$m[3];
        if ($d > 12 && $mth <= 12) {
            // DD.MM.YYYY
            return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        } elseif ($d <= 12 && $mth > 12) {
            // MM.DD.YYYY (improvável mas tratar)
            return sprintf('%04d-%02d-%02d', $y, $d, $mth);
        } else {
            // Assumir DD.MM.YYYY (padrão BR)
            return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        }
    }

    // Padrão: DD-MM-YY
    if (preg_match('/(\d{2})[.\-\/](\d{2})[.\-\/](\d{2})(?!\d)/', $nome, $m)) {
        $y = (int)$m[3];
        $y = $y > 50 ? 1900 + $y : 2000 + $y;
        return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
    }

    // Padrão: MM-YYYY (sem dia)
    if (preg_match('/(\d{2})[.\-\/](\d{4})/', $nome, $m)) {
        return sprintf('%04d-%02d-01', (int)$m[2], (int)$m[1]);
    }

    return null;
}

function determinarTipoTreinamento(string $nomeArquivo, array $map): int
{
    $nomeUpper = mb_strtoupper($nomeArquivo);

    foreach ($map as $pattern => $tipoId) {
        if (preg_match('/' . $pattern . '/i', $nomeUpper)) {
            return $tipoId;
        }
    }

    return 9; // Declaração de Treinamentos (genérico)
}

function determinarTipoAnuencia(string $nomeArquivo, array $map): int
{
    $nomeUpper = mb_strtoupper($nomeArquivo);

    foreach ($map as $pattern => $tipoId) {
        if (preg_match('/' . $pattern . '/i', $nomeUpper)) {
            return $tipoId;
        }
    }

    return 11; // Anuência NR-10 (default)
}
