<?php
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

echo "=== TIPOS DE DOCUMENTO ===" . PHP_EOL;
$stmt = $db->query("SELECT id, nome, categoria, validade_meses FROM tipos_documento WHERE ativo=1 ORDER BY categoria, nome");
$tipos = $stmt->fetchAll();
foreach ($tipos as $t) {
    echo $t['id'] . ' | ' . $t['categoria'] . ' | ' . $t['nome'] . ' | val:' . $t['validade_meses'] . 'm' . PHP_EOL;
}

echo PHP_EOL . "=== STATS ===" . PHP_EOL;
$r = $db->query("SELECT COUNT(*) as total FROM documentos WHERE status != 'obsoleto'")->fetch();
echo 'Total docs ativos: ' . $r['total'] . PHP_EOL;
$r = $db->query("SELECT COUNT(DISTINCT colaborador_id) as total FROM documentos WHERE status != 'obsoleto'")->fetch();
echo 'Colaboradores com docs: ' . $r['total'] . PHP_EOL;

echo PHP_EOL . "=== DOCS POR TIPO ===" . PHP_EOL;
$rows = $db->query("SELECT td.nome, td.categoria, COUNT(*) as total FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id=td.id WHERE d.status != 'obsoleto' GROUP BY td.id ORDER BY total DESC")->fetchAll();
foreach ($rows as $r) {
    echo $r['total'] . ' | ' . $r['categoria'] . ' | ' . $r['nome'] . PHP_EOL;
}

echo PHP_EOL . "=== AMOSTRA - ALLYFF CARNEIRO DE SOUSA ===" . PHP_EOL;
$rows = $db->query("SELECT d.id, d.arquivo_nome, d.arquivo_path, d.data_emissao, d.data_validade, d.status, td.nome as tipo_nome, td.categoria, c.nome_completo FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id=td.id JOIN colaboradores c ON d.colaborador_id=c.id WHERE d.status != 'obsoleto' AND c.nome_completo LIKE '%ALLYFF%' ORDER BY td.categoria, td.nome")->fetchAll();
foreach ($rows as $r) {
    echo $r['id'] . ' | ' . $r['tipo_nome'] . ' (' . $r['categoria'] . ') | emissao:' . $r['data_emissao'] . ' | val:' . ($r['data_validade'] ?? 'N/A') . ' | ' . $r['status'] . ' | ' . $r['arquivo_nome'] . PHP_EOL;
}

echo PHP_EOL . "=== TIPOS CERTIFICADO ===" . PHP_EOL;
$rows = $db->query("SELECT id, codigo, titulo, validade_meses FROM tipos_certificado WHERE ativo=1 ORDER BY codigo")->fetchAll();
foreach ($rows as $r) {
    echo $r['id'] . ' | ' . $r['codigo'] . ' | ' . $r['titulo'] . ' | val:' . $r['validade_meses'] . 'm' . PHP_EOL;
}
