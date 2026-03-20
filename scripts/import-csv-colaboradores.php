<?php
/**
 * Script de importacao em lote de colaboradores a partir de CSV externo.
 * Uso: php scripts/import-csv-colaboradores.php <caminho-csv>
 *
 * Formato esperado: SEQ,CODIGO,PE_NOME,PE_CPF,CTR_DATAADMISSAO,CTR_DATARESCISAO,PE_CIDADE,PE_UF,CTR_CENTROCUSTO4
 */

declare(strict_types=1);

// Bootstrap
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relativeClass = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Services\CryptoService;

// -------- CONFIG --------
$csvPath = $argv[1] ?? null;
if (!$csvPath || !file_exists($csvPath)) {
    echo "Uso: php scripts/import-csv-colaboradores.php <caminho-csv>\n";
    exit(1);
}

echo "=== Importacao de Colaboradores ===\n";
echo "Arquivo: {$csvPath}\n\n";

// -------- LER CSV --------
$handle = fopen($csvPath, 'r');

// Detect BOM
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// Read all lines, detect delimiter
$firstLine = fgets($handle);
rewind($handle);
// Skip BOM again
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
echo "Delimitador detectado: '{$delimiter}'\n";

$rows = [];
while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
    // Convert from Windows-1252/Latin1 to UTF-8 if needed
    $data = array_map(function($v) {
        if ($v === null) return '';
        $detected = mb_detect_encoding($v, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($detected && $detected !== 'UTF-8') {
            return mb_convert_encoding($v, 'UTF-8', $detected);
        }
        // Force conversion if there are garbled chars
        if (!mb_check_encoding($v, 'UTF-8')) {
            return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
        }
        return $v;
    }, $data);
    $rows[] = $data;
}
fclose($handle);

// Remove BOM from first cell
if (!empty($rows[0][0])) {
    $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
}

$headers = array_map('strtoupper', array_map('trim', $rows[0]));
echo "Colunas: " . implode(', ', $headers) . "\n";
echo "Total linhas dados: " . (count($rows) - 1) . "\n\n";

// Map column indices
$colMap = [];
foreach ($headers as $idx => $h) {
    $colMap[$h] = $idx;
}

$requiredCols = ['PE_NOME', 'PE_CPF'];
foreach ($requiredCols as $rc) {
    if (!isset($colMap[$rc])) {
        echo "ERRO: Coluna obrigatoria '{$rc}' nao encontrada.\n";
        exit(1);
    }
}

// -------- PROCESSAR --------
$db = Database::getInstance();

$stats = [
    'atualizados' => 0,
    'novos' => 0,
    'ignorados' => 0,
    'demitidos' => 0,
    'erros' => 0,
];

$dataRows = array_slice($rows, 1);

foreach ($dataRows as $lineNum => $row) {
    $nome = strtoupper(trim($row[$colMap['PE_NOME']] ?? ''));
    if (empty($nome)) continue;

    $cpfRaw = preg_replace('/\D/', '', $row[$colMap['PE_CPF']] ?? '');
    $matricula = trim($row[$colMap['CODIGO'] ?? -1] ?? '');
    $dataAdmissao = parseDate($row[$colMap['CTR_DATAADMISSAO'] ?? -1] ?? '');
    $dataRescisao = parseDate($row[$colMap['CTR_DATARESCISAO'] ?? -1] ?? '');
    $cidade = trim($row[$colMap['PE_CIDADE'] ?? -1] ?? '');
    $uf = trim($row[$colMap['PE_UF'] ?? -1] ?? '');
    $centroCusto = trim($row[$colMap['CTR_CENTROCUSTO4'] ?? -1] ?? '');

    $unidade = '';
    if ($cidade && $uf) {
        $unidade = "{$cidade}/{$uf}";
    } elseif ($cidade) {
        $unidade = $cidade;
    } elseif ($uf) {
        $unidade = $uf;
    }

    // Determinar status
    $status = 'ativo';
    if (!empty($dataRescisao)) {
        $status = 'inativo';
    }

    if (strlen($cpfRaw) !== 11) {
        echo "  [{$lineNum}] IGNORADO (CPF invalido): {$nome} - CPF: {$cpfRaw}\n";
        $stats['ignorados']++;
        continue;
    }

    try {
        $cpfHash = CryptoService::hash($cpfRaw);

        // Verificar se ja existe
        $stmt = $db->prepare("SELECT id, nome_completo, matricula, data_admissao, unidade, status, data_demissao FROM colaboradores WHERE cpf_hash = :hash AND excluido_em IS NULL LIMIT 1");
        $stmt->execute(['hash' => $cpfHash]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Atualizar campos vazios ou diferentes
            $updates = [];
            $camposAtualizados = [];

            // Matricula
            if (!empty($matricula) && (empty($existing['matricula']) || $existing['matricula'] !== $matricula)) {
                $updates['matricula'] = $matricula;
                $camposAtualizados[] = 'matricula';
            }

            // Data admissao
            if (!empty($dataAdmissao) && empty($existing['data_admissao'])) {
                $updates['data_admissao'] = $dataAdmissao;
                $camposAtualizados[] = 'data_admissao';
            }

            // Unidade
            if (!empty($unidade) && (empty($existing['unidade']) || $existing['unidade'] !== $unidade)) {
                $updates['unidade'] = $unidade;
                $camposAtualizados[] = 'unidade';
            }

            // Data demissao/rescisao
            if (!empty($dataRescisao) && empty($existing['data_demissao'])) {
                $updates['data_demissao'] = $dataRescisao;
                $updates['status'] = 'inativo';
                $camposAtualizados[] = 'data_demissao';
                $camposAtualizados[] = 'status';
                $stats['demitidos']++;
            }

            if (!empty($updates)) {
                $updates['atualizado_em'] = date('Y-m-d H:i:s');
                $setParts = [];
                $params = ['id' => $existing['id']];
                foreach ($updates as $field => $val) {
                    $setParts[] = "{$field} = :{$field}";
                    $params[$field] = $val;
                }
                $sql = "UPDATE colaboradores SET " . implode(', ', $setParts) . " WHERE id = :id";
                $db->prepare($sql)->execute($params);

                echo "  [OK] ATUALIZADO: {$nome} -> " . implode(', ', $camposAtualizados) . "\n";
                $stats['atualizados']++;
            } else {
                $stats['ignorados']++;
            }
        } else {
            // Criar novo colaborador
            $cpfEncrypted = CryptoService::encrypt($cpfRaw);

            $db->prepare(
                "INSERT INTO colaboradores (nome_completo, cpf_encrypted, cpf_hash, matricula, data_admissao, data_demissao, unidade, status, criado_em, atualizado_em)
                 VALUES (:nome, :cpfe, :cpfh, :mat, :admissao, :demissao, :unidade, :status, NOW(), NOW())"
            )->execute([
                'nome'     => $nome,
                'cpfe'     => $cpfEncrypted,
                'cpfh'     => $cpfHash,
                'mat'      => $matricula ?: null,
                'admissao' => $dataAdmissao ?: null,
                'demissao' => $dataRescisao ?: null,
                'unidade'  => $unidade ?: null,
                'status'   => $status,
            ]);

            $label = $status === 'inativo' ? ' (DEMITIDO)' : '';
            echo "  [+] NOVO: {$nome} - Mat: {$matricula}{$label}\n";
            $stats['novos']++;
            if ($status === 'inativo') $stats['demitidos']++;
        }
    } catch (\Exception $e) {
        echo "  [ERRO] {$nome}: " . $e->getMessage() . "\n";
        $stats['erros']++;
    }
}

echo "\n=== RESULTADO ===\n";
echo "Atualizados: {$stats['atualizados']}\n";
echo "Novos:       {$stats['novos']}\n";
echo "Ignorados:   {$stats['ignorados']}\n";
echo "Demitidos:   {$stats['demitidos']}\n";
echo "Erros:       {$stats['erros']}\n";
echo "Total:       " . array_sum($stats) . "\n";

// Limpar cache do dashboard
array_map('unlink', glob(dirname(__DIR__) . '/storage/cache/*.cache') ?: []);
echo "\nCache do dashboard limpo.\n";


// -------- FUNCOES --------
function parseDate(string $date): ?string
{
    $date = trim($date);
    if (empty($date)) return null;

    // M/D/YYYY or MM/DD/YYYY (US format from CSV)
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $m)) {
        $month = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $day = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        return "{$m[3]}-{$month}-{$day}";
    }
    // DD/MM/YYYY (BR format)
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    // YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    // Excel serial
    if (is_numeric($date) && (int)$date > 30000) {
        $unix = ((int)$date - 25569) * 86400;
        return date('Y-m-d', $unix);
    }
    return null;
}
