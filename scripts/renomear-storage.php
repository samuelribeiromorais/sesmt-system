<?php
/**
 * Script para renomear pastas e arquivos do storage para nomes legíveis.
 *
 * Antes:  storage/uploads/1/a3f5c8e2d1...pdf
 * Depois: storage/uploads/1 - ABADIO FLAVIO SOUZA MADUREIRA/ABADIO FLAVIO SOUZA MADUREIRA - ASO Admissional - 15.03.2026.pdf
 *
 * Uso: docker exec sesmt-system-web-1 php scripts/renomear-storage.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv ?? []);
$storagePath = dirname(__DIR__) . '/storage/uploads';

echo "=== RENOMEAR STORAGE ===\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (simulacao)" : "REAL (vai renomear)") . "\n";
echo "Storage: $storagePath\n\n";

// --- Conectar ao banco ---
$envFile = dirname(__DIR__) . '/.env';
$dbHost = 'db'; $dbName = 'sesmt_tse'; $dbUser = 'sesmt'; $dbPass = 'sesmt2026';

if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key); $val = trim($val, "\" '");
        match($key) {
            'DB_HOST' => $dbHost = $val,
            'DB_NAME' => $dbName = $val,
            'DB_USER' => $dbUser = $val,
            'DB_PASS' => $dbPass = $val,
            default => null,
        };
    }
}

try {
    $pdo = new PDO("mysql:host=$dbHost;port=3306;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die("Erro DB: " . $e->getMessage() . "\n");
}

// --- Carregar dados ---
echo "[1/4] Carregando colaboradores...\n";
$stmt = $pdo->query("SELECT id, nome_completo FROM colaboradores");
$colaboradores = [];
while ($row = $stmt->fetch()) {
    $colaboradores[$row['id']] = $row['nome_completo'];
}
echo "  " . count($colaboradores) . " colaboradores carregados\n";

echo "[2/4] Carregando documentos...\n";
$stmt = $pdo->query("
    SELECT d.id, d.colaborador_id, d.arquivo_path, d.data_emissao, td.nome as tipo_nome
    FROM documentos d
    JOIN tipos_documento td ON d.tipo_documento_id = td.id
    ORDER BY d.colaborador_id, td.nome, d.data_emissao
");
$documentos = $stmt->fetchAll();
echo "  " . count($documentos) . " documentos carregados\n\n";

// --- Função para limpar nome de arquivo (remover caracteres inválidos) ---
function sanitizarNome(string $nome): string {
    // Remover caracteres proibidos em Windows: \ / : * ? " < > |
    $nome = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $nome);
    // Remover espaços duplos
    $nome = preg_replace('/\s+/', ' ', trim($nome));
    // Limitar tamanho (Windows max path é 260, mas queremos nomes razoáveis)
    if (mb_strlen($nome) > 120) {
        $nome = mb_substr($nome, 0, 120);
    }
    return $nome;
}

// --- Etapa 1: Renomear pastas ---
echo "[3/4] Renomeando pastas...\n";
$pastasRenomeadas = 0;
$pastasErro = 0;
$mapaPasstas = []; // id => novo nome da pasta

foreach ($colaboradores as $id => $nome) {
    $pastaAtual = $storagePath . '/' . $id;
    $novaPastaNome = $id . ' - ' . sanitizarNome($nome);
    $novaPasta = $storagePath . '/' . $novaPastaNome;
    $mapaPasstas[$id] = $novaPastaNome;

    if (!is_dir($pastaAtual)) {
        // Verificar se já foi renomeada anteriormente
        if (is_dir($novaPasta)) continue;
        continue;
    }

    // Já tem o nome correto?
    if ($pastaAtual === $novaPasta) continue;

    if ($dryRun) {
        echo "  [DRY] $id/ -> $novaPastaNome/\n";
    } else {
        if (rename($pastaAtual, $novaPasta)) {
            $pastasRenomeadas++;
        } else {
            echo "  [ERRO] Falha ao renomear $id/ -> $novaPastaNome/\n";
            $pastasErro++;
            $mapaPasstas[$id] = (string)$id; // fallback
        }
    }
}
echo "  Pastas renomeadas: $pastasRenomeadas | Erros: $pastasErro\n\n";

// --- Etapa 2: Renomear arquivos e atualizar banco ---
echo "[4/4] Renomeando arquivos e atualizando banco...\n";
$arquivosRenomeados = 0;
$arquivosErro = 0;
$dbAtualizados = 0;
$nomesUsados = []; // Para controle de duplicatas por pasta

$updateStmt = $pdo->prepare("UPDATE documentos SET arquivo_path = :path WHERE id = :id");

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    foreach ($documentos as $doc) {
        $colabId = $doc['colaborador_id'];
        $nome = $colaboradores[$colabId] ?? 'DESCONHECIDO';
        $pastaNome = $mapaPasstas[$colabId] ?? (string)$colabId;
        $tipoNome = sanitizarNome($doc['tipo_nome'] ?? 'Documento');

        // Formatar data
        $dataStr = '00.00.0000';
        if ($doc['data_emissao'] && $doc['data_emissao'] !== '0000-00-00') {
            $dt = DateTime::createFromFormat('Y-m-d', $doc['data_emissao']);
            if ($dt) $dataStr = $dt->format('d.m.Y');
        }

        // Construir novo nome do arquivo
        $nomeBase = sanitizarNome($nome) . ' - ' . $tipoNome . ' - ' . $dataStr;
        $ext = pathinfo($doc['arquivo_path'], PATHINFO_EXTENSION) ?: 'pdf';

        // Controle de duplicatas
        $chave = $pastaNome . '/' . $nomeBase . '.' . $ext;
        if (isset($nomesUsados[$chave])) {
            $nomesUsados[$chave]++;
            $nomeBase .= ' (' . $nomesUsados[$chave] . ')';
        } else {
            $nomesUsados[$chave] = 1;
        }

        $novoNomeArquivo = $nomeBase . '.' . $ext;
        $novoPathRelativo = $pastaNome . '/' . $novoNomeArquivo;

        // Caminho físico atual
        $caminhoAtual = $storagePath . '/' . $doc['arquivo_path'];
        // Se a pasta já foi renomeada, o arquivo pode estar no novo caminho
        if (!file_exists($caminhoAtual)) {
            $caminhoAtual = $storagePath . '/' . $pastaNome . '/' . basename($doc['arquivo_path']);
        }
        $caminhoNovo = $storagePath . '/' . $novoPathRelativo;

        if ($dryRun) {
            if ($arquivosRenomeados < 20) { // Mostrar apenas primeiros 20 no dry-run
                echo "  [DRY] " . basename($doc['arquivo_path']) . " -> $novoNomeArquivo\n";
            }
            $arquivosRenomeados++;
        } else {
            // Renomear arquivo físico
            if (file_exists($caminhoAtual) && $caminhoAtual !== $caminhoNovo) {
                if (rename($caminhoAtual, $caminhoNovo)) {
                    $arquivosRenomeados++;
                } else {
                    echo "  [ERRO] Falha: " . basename($doc['arquivo_path']) . "\n";
                    $arquivosErro++;
                    continue; // Não atualizar DB se o rename falhou
                }
            } elseif (!file_exists($caminhoAtual)) {
                // Arquivo não encontrado — atualizar DB mesmo assim
            }

            // Atualizar banco
            $updateStmt->execute(['path' => $novoPathRelativo, 'id' => $doc['id']]);
            $dbAtualizados++;
        }

        // Progresso a cada 1000
        if (($arquivosRenomeados + $arquivosErro) % 1000 === 0) {
            echo "  Progresso: " . ($arquivosRenomeados + $arquivosErro) . " / " . count($documentos) . "\n";
            if (!$dryRun) gc_collect_cycles();
        }
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Exception $e) {
    if (!$dryRun) $pdo->rollBack();
    die("ERRO CRITICO: " . $e->getMessage() . "\n");
}

echo "\n=== RESULTADO ===\n";
echo "Pastas renomeadas: $pastasRenomeadas\n";
echo "Arquivos renomeados: $arquivosRenomeados\n";
echo "DB atualizado: $dbAtualizados\n";
echo "Erros: " . ($pastasErro + $arquivosErro) . "\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Nada foi alterado. Execute sem --dry-run para aplicar.\n";
}
