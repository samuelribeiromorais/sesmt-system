<?php
/**
 * Cria tipos de documento específicos para cada treinamento
 * Baseado nos tipos_certificado existentes + padrões encontrados nos PDFs
 */
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

// Tipos de treinamento específicos a criar (baseado nos tipos_certificado + observações dos PDFs)
$novosTipos = [
    // NR-06
    ['nome' => 'Certificado NR-06 - EPI', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 1],
    // NR-10
    ['nome' => 'Certificado NR-10 Básico', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 1],
    ['nome' => 'Certificado NR-10 Reciclagem', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-10 SEP', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-10 Reciclagem SEP', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    ['nome' => 'Termo NR-10', 'categoria' => 'treinamento', 'validade_meses' => null, 'obrigatorio' => 0],
    // NR-11
    ['nome' => 'Certificado NR-11 Munck', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-11 Ponte Rolante', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-11 Rigger', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-11 Sinaleiro', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // NR-12
    ['nome' => 'Certificado NR-12', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-12 Reciclagem', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    // NR-18
    ['nome' => 'Certificado NR-18 Geral', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 1],
    ['nome' => 'Certificado NR-18 Andaime', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-18 Plataforma', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // NR-20
    ['nome' => 'Certificado NR-20', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // NR-33
    ['nome' => 'Certificado NR-33 Trabalhador', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-33 Supervisor', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // NR-34
    ['nome' => 'Certificado NR-34 Geral', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-34 Soldador', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-34 Observador', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
    ['nome' => 'Certificado NR-34 Trabalho Quente', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // NR-35
    ['nome' => 'Certificado NR-35', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 1],
    // LOTO
    ['nome' => 'Certificado LOTO', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // Direção Defensiva
    ['nome' => 'Certificado Direção Defensiva', 'categoria' => 'treinamento', 'validade_meses' => 60, 'obrigatorio' => 0],
    // Declaração (quando é uma declaração de NÃO realizar atividade)
    ['nome' => 'Declaração de Atividades', 'categoria' => 'treinamento', 'validade_meses' => null, 'obrigatorio' => 0],
    // Integração
    ['nome' => 'Integração de Segurança', 'categoria' => 'treinamento', 'validade_meses' => 12, 'obrigatorio' => 0],
    // SEP complementar
    ['nome' => 'Certificado NR-10 Básico Reciclagem 20h', 'categoria' => 'treinamento', 'validade_meses' => 24, 'obrigatorio' => 0],
];

echo "=== CRIANDO TIPOS DE DOCUMENTO ESPECÍFICOS ===" . PHP_EOL . PHP_EOL;

$created = 0;
$exists = 0;

foreach ($novosTipos as $tipo) {
    // Check if already exists
    $stmt = $db->prepare("SELECT id FROM tipos_documento WHERE nome = :nome LIMIT 1");
    $stmt->execute(['nome' => $tipo['nome']]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "  JA EXISTE: {$tipo['nome']} (ID: {$existing['id']})" . PHP_EOL;
        $exists++;
        continue;
    }

    $stmt = $db->prepare("INSERT INTO tipos_documento (nome, categoria, validade_meses, obrigatorio, ativo) VALUES (:nome, :categoria, :validade_meses, :obrigatorio, 1)");
    $stmt->execute([
        'nome' => $tipo['nome'],
        'categoria' => $tipo['categoria'],
        'validade_meses' => $tipo['validade_meses'],
        'obrigatorio' => $tipo['obrigatorio'],
    ]);
    $id = $db->lastInsertId();
    echo "  CRIADO: {$tipo['nome']} (ID: {$id}) | val: {$tipo['validade_meses']}m" . PHP_EOL;
    $created++;
}

echo PHP_EOL . "Criados: {$created} | Ja existiam: {$exists}" . PHP_EOL;

// Show all tipos now
echo PHP_EOL . "=== TODOS OS TIPOS DE DOCUMENTO ===" . PHP_EOL;
$all = $db->query("SELECT id, nome, categoria, validade_meses FROM tipos_documento WHERE ativo=1 ORDER BY categoria, nome")->fetchAll();
foreach ($all as $t) {
    echo "  {$t['id']} | {$t['categoria']} | {$t['nome']} | val:{$t['validade_meses']}m" . PHP_EOL;
}
