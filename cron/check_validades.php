<?php
/**
 * Cron Job: Verificar validades e atualizar status
 * Executar diariamente as 06:00
 * crontab: 0 6 * * * php /var/www/cron/check_validades.php
 */

require __DIR__ . '/bootstrap.php';

use App\Services\AlertService;

$service = new AlertService();
$stats = $service->atualizarValidades();

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Validades atualizadas.\n";
echo "  Docs vencidos: {$stats['docs_vencidos']}\n";
echo "  Docs proximo vencimento: {$stats['docs_proximos']}\n";
echo "  Certs vencidos: {$stats['certs_vencidos']}\n";
echo "  Certs proximo vencimento: {$stats['certs_proximos']}\n";
