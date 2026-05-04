<?php
/**
 * Seed de dados DEMO para o módulo RH.
 *
 * Cria 3 clientes hipotéticos, 6 obras (2 por cliente), 8 colaboradores
 * distribuídos entre eles, documentos vigentes (ASO + NR-10 + NR-35),
 * configura as exigências por cliente, cria vínculos N:N para demonstrar
 * Fase 2, e dispara o motor de detecção para gerar pendências reais.
 *
 * Tudo é prefixado com "DEMO -" para fácil identificação e cleanup.
 *
 * Uso: docker exec sesmt-system-web-1 php /var/www/html/scripts/seed_demo_rh.php
 */

require __DIR__ . '/../cron/bootstrap.php';

use App\Core\Database;
use App\Services\RhPendenciaService;

$db = Database::getInstance();

echo "=== Seed DEMO do Módulo RH ===\n\n";

// ─── Tipos de documento exigidos por todos os clientes DEMO ────────────
const TIPO_ASO_PERIODICO   = 2;   // ASO Periódico
const TIPO_NR10_BASICO     = 16;  // Certificado NR-10 Básico
const TIPO_NR35            = 37;  // Certificado NR-35
const TIPO_FICHA_EPI       = 6;   // Ficha de EPI

$tiposExigidos = [TIPO_ASO_PERIODICO, TIPO_NR10_BASICO, TIPO_NR35, TIPO_FICHA_EPI];

// ─── 1. Clientes DEMO ────────────────────────────────────────────────
echo "1) Criando 3 clientes DEMO...\n";
$clientesDemo = [
    ['razao' => 'DEMO - Cargill Indústrias Ltda',  'fantasia' => 'DEMO - Cargill',  'cnpj' => '60.498.706/0001-57', 'contato' => 'João Silva',    'email' => 'joao@cargill-demo.com'],
    ['razao' => 'DEMO - Nestlé Brasil S/A',         'fantasia' => 'DEMO - Nestlé',   'cnpj' => '60.409.075/0001-52', 'contato' => 'Maria Santos',  'email' => 'maria@nestle-demo.com'],
    ['razao' => 'DEMO - Unilever Brasil Ltda',      'fantasia' => 'DEMO - Unilever', 'cnpj' => '61.082.426/0001-04', 'contato' => 'Pedro Oliveira','email' => 'pedro@unilever-demo.com'],
];

$clienteIds = [];
foreach ($clientesDemo as $c) {
    $existe = $db->prepare("SELECT id FROM clientes WHERE razao_social = :r");
    $existe->execute(['r' => $c['razao']]);
    if ($id = $existe->fetchColumn()) {
        $clienteIds[$c['fantasia']] = (int)$id;
        echo "   - {$c['fantasia']} já existe (id={$id}).\n";
        continue;
    }
    $stmt = $db->prepare(
        "INSERT INTO clientes (razao_social, nome_fantasia, cnpj, contato_nome, contato_email, ativo)
         VALUES (:r, :f, :cnpj, :cn, :em, 1)"
    );
    $stmt->execute(['r' => $c['razao'], 'f' => $c['fantasia'], 'cnpj' => $c['cnpj'], 'cn' => $c['contato'], 'em' => $c['email']]);
    $clienteIds[$c['fantasia']] = (int)$db->lastInsertId();
    echo "   ✓ {$c['fantasia']} criado (id={$clienteIds[$c['fantasia']]}).\n";
}

// ─── 2. Obras DEMO (2 por cliente) ───────────────────────────────────
echo "\n2) Criando 2 obras DEMO por cliente (6 total)...\n";
$obrasDemo = [
    ['cliente' => 'DEMO - Cargill',  'nome' => 'DEMO - Cargill Anápolis',         'local' => 'Anápolis/GO'],
    ['cliente' => 'DEMO - Cargill',  'nome' => 'DEMO - Cargill Goiânia',          'local' => 'Goiânia/GO'],
    ['cliente' => 'DEMO - Nestlé',   'nome' => 'DEMO - Nestlé Goiânia',           'local' => 'Goiânia/GO'],
    ['cliente' => 'DEMO - Nestlé',   'nome' => 'DEMO - Nestlé Brasília',          'local' => 'Brasília/DF'],
    ['cliente' => 'DEMO - Unilever', 'nome' => 'DEMO - Unilever Aparecida',       'local' => 'Aparecida de Goiânia/GO'],
    ['cliente' => 'DEMO - Unilever', 'nome' => 'DEMO - Unilever Goiânia',         'local' => 'Goiânia/GO'],
];

$obraIds = [];
foreach ($obrasDemo as $o) {
    $cid = $clienteIds[$o['cliente']];
    $existe = $db->prepare("SELECT id FROM obras WHERE cliente_id = :c AND nome = :n");
    $existe->execute(['c' => $cid, 'n' => $o['nome']]);
    if ($id = $existe->fetchColumn()) {
        $obraIds[$o['nome']] = (int)$id;
        echo "   - {$o['nome']} já existe (id={$id}).\n";
        continue;
    }
    $stmt = $db->prepare(
        "INSERT INTO obras (cliente_id, nome, local_obra, data_inicio, status)
         VALUES (:c, :n, :l, :di, 'ativa')"
    );
    $stmt->execute(['c' => $cid, 'n' => $o['nome'], 'l' => $o['local'], 'di' => '2024-01-15']);
    $obraIds[$o['nome']] = (int)$db->lastInsertId();
    echo "   ✓ {$o['nome']} criada (id={$obraIds[$o['nome']]}).\n";
}

// ─── 3. Configurações de exigência por cliente ───────────────────────
echo "\n3) Configurando exigências por cliente (ASO, NR-10, NR-35, EPI)...\n";
foreach ($clienteIds as $nome => $cid) {
    foreach ($tiposExigidos as $tid) {
        $existe = $db->prepare("SELECT id FROM config_cliente_docs WHERE cliente_id = :c AND tipo_documento_id = :t AND obra_id IS NULL");
        $existe->execute(['c' => $cid, 't' => $tid]);
        if ($existe->fetchColumn()) continue;
        $db->prepare(
            "INSERT INTO config_cliente_docs (cliente_id, tipo_documento_id, obrigatorio) VALUES (:c, :t, 1)"
        )->execute(['c' => $cid, 't' => $tid]);
    }
    echo "   ✓ {$nome}: 4 exigências configuradas.\n";
}

// ─── 4. Colaboradores DEMO ───────────────────────────────────────────
echo "\n4) Criando 8 colaboradores DEMO distribuídos pelas obras...\n";
$colabsDemo = [
    ['nome' => 'DEMO - Antonio Pereira',    'matricula' => 'DEMO001', 'cargo' => 'Eletricista',      'cpf' => '11111111111', 'obra' => 'DEMO - Cargill Anápolis'],
    ['nome' => 'DEMO - Carlos Souza',       'matricula' => 'DEMO002', 'cargo' => 'Soldador',         'cpf' => '22222222222', 'obra' => 'DEMO - Cargill Anápolis'],
    ['nome' => 'DEMO - Marcos Lima',        'matricula' => 'DEMO003', 'cargo' => 'Encarregado',      'cpf' => '33333333333', 'obra' => 'DEMO - Cargill Goiânia'],
    ['nome' => 'DEMO - Roberto Almeida',    'matricula' => 'DEMO004', 'cargo' => 'Eletricista',      'cpf' => '44444444444', 'obra' => 'DEMO - Nestlé Goiânia'],
    ['nome' => 'DEMO - Felipe Rodrigues',   'matricula' => 'DEMO005', 'cargo' => 'Mecânico',         'cpf' => '55555555555', 'obra' => 'DEMO - Nestlé Brasília'],
    ['nome' => 'DEMO - Lucas Ferreira',     'matricula' => 'DEMO006', 'cargo' => 'Eletricista',      'cpf' => '66666666666', 'obra' => 'DEMO - Unilever Aparecida'],
    ['nome' => 'DEMO - Rafael Martins',     'matricula' => 'DEMO007', 'cargo' => 'Operador',         'cpf' => '77777777777', 'obra' => 'DEMO - Unilever Goiânia'],
    ['nome' => 'DEMO - Bruno Costa',        'matricula' => 'DEMO008', 'cargo' => 'Eletricista',      'cpf' => '88888888888', 'obra' => 'DEMO - Cargill Anápolis'],
];

$colabIds = [];
foreach ($colabsDemo as $c) {
    $oid = $obraIds[$c['obra']];
    $cid = $db->prepare("SELECT cliente_id FROM obras WHERE id = :o")->execute(['o' => $oid]) ?: null;
    $cid = $db->query("SELECT cliente_id FROM obras WHERE id = {$oid}")->fetchColumn();
    $hash = hash('sha256', $c['cpf']);

    $existe = $db->prepare("SELECT id FROM colaboradores WHERE cpf_hash = :h");
    $existe->execute(['h' => $hash]);
    if ($id = $existe->fetchColumn()) {
        $colabIds[$c['nome']] = (int)$id;
        echo "   - {$c['nome']} já existe (id={$id}).\n";
        continue;
    }

    $stmt = $db->prepare(
        "INSERT INTO colaboradores (nome_completo, cpf_hash, matricula, cargo, cliente_id, obra_id, data_admissao, status)
         VALUES (:n, :h, :m, :ca, :cli, :ob, '2024-03-01', 'ativo')"
    );
    $stmt->execute([
        'n' => $c['nome'], 'h' => $hash, 'm' => $c['matricula'], 'ca' => $c['cargo'],
        'cli' => $cid, 'ob' => $oid,
    ]);
    $colabIds[$c['nome']] = (int)$db->lastInsertId();
    echo "   ✓ {$c['nome']} (id={$colabIds[$c['nome']]}) → {$c['obra']}\n";
}

// ─── 5. Documentos vigentes por colaborador ──────────────────────────
echo "\n5) Criando documentos vigentes (ASO, NR-10, NR-35) para cada colab DEMO...\n";
$adminId = $db->query("SELECT id FROM usuarios WHERE perfil='admin' ORDER BY id LIMIT 1")->fetchColumn();

$docsCount = 0;
foreach ($colabIds as $nome => $cid) {
    foreach ([TIPO_ASO_PERIODICO => 'ASO', TIPO_NR10_BASICO => 'NR10', TIPO_NR35 => 'NR35'] as $tipoId => $sigla) {
        $existe = $db->prepare(
            "SELECT id FROM documentos
             WHERE colaborador_id = :c AND tipo_documento_id = :t AND status != 'obsoleto' AND excluido_em IS NULL"
        );
        $existe->execute(['c' => $cid, 't' => $tipoId]);
        if ($existe->fetchColumn()) continue;

        // Validades variadas: alguns em dia, outros próximos do vencimento
        $validades = ['2026-08-15', '2026-06-30', '2026-12-20', '2027-02-10'];
        $emissoes  = ['2025-08-15', '2025-06-30', '2025-12-20', '2026-02-10'];
        $idx = $tipoId % 4;

        $db->prepare(
            "INSERT INTO documentos (colaborador_id, tipo_documento_id, arquivo_nome, arquivo_path, arquivo_hash, arquivo_tamanho,
                                     data_emissao, data_validade, status, enviado_por, aprovacao_status, aprovado_por, aprovado_em, versao, enviado_cliente)
             VALUES (:c, :t, :n, :p, :h, 1024, :de, :dv, 'vigente', :u, 'aprovado', :u2, NOW(), 1, 0)"
        )->execute([
            'c'  => $cid, 't' => $tipoId,
            'n'  => "DEMO_{$sigla}.pdf",
            'p'  => "demo/colab_{$cid}/DEMO_{$sigla}.pdf",
            'h'  => hash('sha256', "demo_{$cid}_{$tipoId}"),
            'de' => $emissoes[$idx], 'dv' => $validades[$idx],
            'u'  => $adminId, 'u2' => $adminId,
        ]);
        $docsCount++;
    }
}
echo "   ✓ {$docsCount} documentos criados.\n";

// ─── 6. Vínculos N:N (Fase 2) ─────────────────────────────────────────
echo "\n6) Criando vínculos N:N (alguns colabs prestam serviço em outros clientes)...\n";
// Antonio (Cargill) também atende Nestlé Goiânia
// Carlos (Cargill) também atende Unilever Aparecida
// Lucas (Unilever) também atende Cargill Goiânia
$vinculosExtras = [
    ['DEMO - Antonio Pereira',  'DEMO - Nestlé Goiânia',     'Eletricista terceiro turno'],
    ['DEMO - Carlos Souza',     'DEMO - Unilever Aparecida', 'Soldador volante'],
    ['DEMO - Lucas Ferreira',   'DEMO - Cargill Goiânia',    'Apoio operacional'],
];

foreach ($vinculosExtras as [$nomeColab, $nomeObra, $funcao]) {
    $cid = $colabIds[$nomeColab];
    $oid = $obraIds[$nomeObra];
    $existe = $db->prepare("SELECT id FROM rh_vinculos_obra WHERE colaborador_id=:c AND obra_id=:o AND ate_quando IS NULL AND excluido_em IS NULL");
    $existe->execute(['c' => $cid, 'o' => $oid]);
    if ($existe->fetchColumn()) {
        echo "   - {$nomeColab} → {$nomeObra}: já existe.\n";
        continue;
    }
    $db->prepare(
        "INSERT INTO rh_vinculos_obra (colaborador_id, obra_id, desde, funcao_no_site, criado_por)
         VALUES (:c, :o, '2025-09-01', :f, :u)"
    )->execute(['c' => $cid, 'o' => $oid, 'f' => $funcao, 'u' => $adminId]);
    echo "   ✓ {$nomeColab} → {$nomeObra} ({$funcao})\n";
}

// ─── 7. Dispara motor de detecção ─────────────────────────────────────
echo "\n7) Disparando motor de detecção de pendências...\n";
$stats = RhPendenciaService::recalcularTudo();
echo "   ✓ Criadas: {$stats['criadas']}\n";
echo "   ✓ Atualizadas: {$stats['atualizadas']}\n";
echo "   ✓ Mantidas: {$stats['mantidas']}\n";

// ─── Resumo final ────────────────────────────────────────────────────
$totalProtocolos = $db->query("SELECT COUNT(*) FROM rh_protocolos")->fetchColumn();
$totalPendentes  = $db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status='pendente_envio'")->fetchColumn();
echo "\n=== Resumo ===\n";
echo "Total de registros em rh_protocolos: {$totalProtocolos}\n";
echo "Pendentes de envio: {$totalPendentes}\n";
echo "\n✓ Seed concluído. Acesse /rh para ver as pendências geradas.\n";
