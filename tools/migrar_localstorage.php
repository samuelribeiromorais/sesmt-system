<?php
/**
 * Script de Migracao: localStorage (sistema antigo) -> MariaDB (sistema novo)
 *
 * Uso:
 *   1. No sistema antigo, va em "Exportar dados" e baixe o JSON dos funcionarios
 *   2. Salve o arquivo como "funcionarios.json" na pasta tools/
 *   3. Execute: php tools/migrar_localstorage.php [caminho_do_json]
 *
 * O script ira:
 *   - Importar colaboradores (nome, CPF, funcao, admissao)
 *   - Importar certificados vinculados a cada colaborador
 *   - Mapear os tipos de certificado do sistema antigo para o novo (tipos_certificado)
 */

require dirname(__DIR__) . '/cron/bootstrap.php';

use App\Core\Database;
use App\Services\CryptoService;

echo "===========================================\n";
echo " SESMT TSE - Migracao localStorage -> MariaDB\n";
echo "===========================================\n\n";

// Caminho do JSON
$jsonPath = $argv[1] ?? __DIR__ . '/funcionarios.json';
if (!file_exists($jsonPath)) {
    echo "ERRO: Arquivo nao encontrado: {$jsonPath}\n";
    echo "Uso: php tools/migrar_localstorage.php [caminho_do_json]\n\n";
    echo "Para obter o JSON:\n";
    echo "  1. Abra o sistema antigo no navegador\n";
    echo "  2. Clique em 'Exportar dados'\n";
    echo "  3. Salve o arquivo .json baixado\n";
    exit(1);
}

$json = file_get_contents($jsonPath);
$funcionarios = json_decode($json, true);

if (!is_array($funcionarios)) {
    echo "ERRO: JSON invalido ou vazio.\n";
    exit(1);
}

echo "Funcionarios encontrados no JSON: " . count($funcionarios) . "\n\n";

$db = Database::getInstance();

// Carregar mapa de tipos de certificado do banco
$tiposCert = $db->query("SELECT id, codigo FROM tipos_certificado")->fetchAll();
$tiposMap = [];
foreach ($tiposCert as $tc) {
    $tiposMap[$tc['codigo']] = $tc['id'];
}

// Mapa de chaves legado -> codigo no banco
$migraChaves = [
    'DIRECAO DEFENSIVA' => 'DIRECAO DEFENSIVA',
    'DIREÇÃO DEFENSIVA' => 'DIRECAO DEFENSIVA',
    'LOTO - LOCKOUT & TAGOUT' => 'LOTO',
    'LOTO' => 'LOTO',
    'NR 06 - USO, GUARDA E CONSERVAÇÃO DE EPI' => 'NR 06',
    'NR 06 - USO, GUARDA E CONSERVACAO DE EPI' => 'NR 06',
    'NR 10 BÁSICO' => 'NR 10 BASICO',
    'NR 10 BASICO' => 'NR 10 BASICO',
    'NR 10 RECICLAGEM BÁSICO' => 'NR 10 RECICLAGEM',
    'NR 10 RECICLAGEM BASICO' => 'NR 10 RECICLAGEM',
    'NR 10 SEP' => 'NR 10 SEP',
    'NR 11 OPERADOR MAQ (GUINDAUTO MUNCK)' => 'NR 11 MUNK',
    'NR 11 SINALEIRO E AMARRADOR DE CARGAS' => 'NR 11 SINALEIRO',
    'NR 12 OPERAÇÃO DE MÁQUINAS E EQUIPAMENTOS' => 'NR 12',
    'NR 12 OPERACAO DE MAQUINAS E EQUIPAMENTOS' => 'NR 12',
    'NR 18 ANDAIMES' => 'NR 18 ANDAIME',
    'NR 18 INDÚSTRIA DA CONSTRUÇÃO' => 'NR 18 GERAL',
    'NR 18 INDUSTRIA DA CONSTRUCAO' => 'NR 18 GERAL',
    'NR 20 INFLAMÁVEIS E COMBUSTÍVEIS (Unilever) 4h' => 'NR 20 UNILEVER',
    'NR 20 INFLAMAVEIS E COMBUSTIVEIS (Unilever) 4h' => 'NR 20 UNILEVER',
    'NR 20 INFLAMÁVEIS E COMBUSTÍVEIS (Cargill) 16h' => 'NR 20 CARGILL',
    'NR 20 INFLAMAVEIS E COMBUSTIVEIS (Cargill) 16h' => 'NR 20 CARGILL',
    'NR 33 ESPAÇO CONFINADO (Supervisor) 40h' => 'NR 33 SUPERVISOR',
    'NR 33 ESPACO CONFINADO (Supervisor) 40h' => 'NR 33 SUPERVISOR',
    'NR 33 ESPAÇO CONFINADO (Trab autorizado) 16h' => 'NR 33 TRABALHADOR',
    'NR 33 ESPACO CONFINADO (Trab autorizado) 16h' => 'NR 33 TRABALHADOR',
    'NR 34 - OBSERVADOR' => 'NR 34 OBSERVADOR',
    'NR 34 - SOLDADOR' => 'NR 34 SOLDADOR',
    'NR 34 CONDIÇÕES E MEIO AMBIENTE DE TRABALHO' => 'NR 34 GERAL',
    'NR 34 CONDICOES E MEIO AMBIENTE DE TRABALHO' => 'NR 34 GERAL',
    'NR 35 TRABALHO EM ALTURA' => 'NR 35',
];

$stats = [
    'colaboradores_criados' => 0,
    'colaboradores_existentes' => 0,
    'certificados_importados' => 0,
    'certificados_sem_tipo' => 0,
    'erros' => 0,
];

$db->beginTransaction();

try {
    foreach ($funcionarios as $f) {
        $nome = trim($f['nome'] ?? '');
        if (empty($nome)) {
            $stats['erros']++;
            continue;
        }

        $cpf = preg_replace('/\D/', '', $f['cpf'] ?? '');
        $funcao = trim($f['funcao'] ?? '');
        $admissao = $f['admissao'] ?? null;

        // Verificar se colaborador ja existe (por CPF hash ou nome)
        $existente = null;
        if ($cpf) {
            $cpfHash = CryptoService::hash($cpf);
            $stmt = $db->prepare("SELECT id FROM colaboradores WHERE cpf_hash = :h LIMIT 1");
            $stmt->execute(['h' => $cpfHash]);
            $existente = $stmt->fetch();
        }

        if (!$existente) {
            // Busca por nome exato
            $stmt = $db->prepare("SELECT id FROM colaboradores WHERE nome_completo = :nome LIMIT 1");
            $stmt->execute(['nome' => $nome]);
            $existente = $stmt->fetch();
        }

        if ($existente) {
            $colaboradorId = $existente['id'];
            $stats['colaboradores_existentes']++;
            echo "  [EXISTE] {$nome} (ID: {$colaboradorId})\n";
        } else {
            // Criar colaborador
            $stmt = $db->prepare(
                "INSERT INTO colaboradores (nome_completo, cpf_encrypted, cpf_hash, funcao, data_admissao, status, criado_em)
                 VALUES (:nome, :cpf_enc, :cpf_hash, :funcao, :adm, 'ativo', NOW())"
            );
            $stmt->execute([
                'nome'     => $nome,
                'cpf_enc'  => $cpf ? CryptoService::encrypt($cpf) : null,
                'cpf_hash' => $cpf ? CryptoService::hash($cpf) : null,
                'funcao'   => $funcao ?: null,
                'adm'      => $admissao ?: null,
            ]);
            $colaboradorId = (int) $db->lastInsertId();
            $stats['colaboradores_criados']++;
            echo "  [NOVO] {$nome} (ID: {$colaboradorId})\n";
        }

        // Importar certificados
        $certificados = $f['certificados'] ?? [];
        foreach ($certificados as $certKey => $certData) {
            $dataRealizacao = $certData['dataRealizacao'] ?? null;
            $dataEmissao = $certData['dataEmissao'] ?? $dataRealizacao;

            if (!$dataRealizacao) continue;

            // Mapear chave do certificado para codigo do banco
            $codigoBanco = $migraChaves[$certKey] ?? $certKey;
            $tipoCertId = $tiposMap[$codigoBanco] ?? null;

            if (!$tipoCertId) {
                // Tentar busca parcial
                foreach ($tiposMap as $codigo => $id) {
                    if (stripos($certKey, $codigo) !== false || stripos($codigo, $certKey) !== false) {
                        $tipoCertId = $id;
                        break;
                    }
                }
            }

            if (!$tipoCertId) {
                echo "    [AVISO] Tipo nao encontrado: '{$certKey}'\n";
                $stats['certificados_sem_tipo']++;
                continue;
            }

            // Verificar se ja existe este certificado
            $stmt = $db->prepare(
                "SELECT id FROM certificados
                 WHERE colaborador_id = :cid AND tipo_certificado_id = :tid AND data_realizacao = :dr LIMIT 1"
            );
            $stmt->execute(['cid' => $colaboradorId, 'tid' => $tipoCertId, 'dr' => $dataRealizacao]);
            if ($stmt->fetch()) continue;

            // Calcular validade
            $stmtTipo = $db->prepare("SELECT validade_meses FROM tipos_certificado WHERE id = :id");
            $stmtTipo->execute(['id' => $tipoCertId]);
            $validadeMeses = $stmtTipo->fetchColumn();
            $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$validadeMeses} months"));

            // Determinar status
            $status = 'vigente';
            $daysLeft = (strtotime($dataValidade) - time()) / 86400;
            if ($daysLeft < 0) $status = 'vencido';
            elseif ($daysLeft <= 30) $status = 'proximo_vencimento';

            $db->prepare(
                "INSERT INTO certificados (colaborador_id, tipo_certificado_id, data_realizacao, data_emissao, data_validade, status, criado_em)
                 VALUES (:cid, :tid, :dr, :de, :dv, :st, NOW())"
            )->execute([
                'cid' => $colaboradorId,
                'tid' => $tipoCertId,
                'dr'  => $dataRealizacao,
                'de'  => $dataEmissao,
                'dv'  => $dataValidade,
                'st'  => $status,
            ]);

            $stats['certificados_importados']++;
        }
    }

    $db->commit();
    echo "\n===========================================\n";
    echo " MIGRACAO CONCLUIDA\n";
    echo "===========================================\n";
    echo "  Colaboradores criados:      {$stats['colaboradores_criados']}\n";
    echo "  Colaboradores ja existiam:  {$stats['colaboradores_existentes']}\n";
    echo "  Certificados importados:    {$stats['certificados_importados']}\n";
    echo "  Certificados sem tipo:      {$stats['certificados_sem_tipo']}\n";
    echo "  Erros:                      {$stats['erros']}\n";
    echo "===========================================\n";

} catch (\Exception $e) {
    $db->rollBack();
    echo "\nERRO CRITICO: " . $e->getMessage() . "\n";
    echo "Migracao revertida (rollback).\n";
    exit(1);
}
