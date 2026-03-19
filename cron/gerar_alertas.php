<?php
/**
 * Cron Job: Gerar alertas para documentos/certificados vencendo
 * Executar diariamente as 07:00
 * crontab: 0 7 * * * php /var/www/cron/gerar_alertas.php
 */

require __DIR__ . '/bootstrap.php';

use App\Services\AlertService;

$service = new AlertService();
$stats = $service->gerarAlertas();

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Alertas gerados.\n";
echo "  Novos alertas: {$stats['criados']}\n";
