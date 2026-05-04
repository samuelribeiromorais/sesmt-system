<?php
/**
 * Cron Job: Detectar pendências de reprotocolo (Módulo RH — Fase 2)
 * Executar diariamente às 02:00
 * crontab: 0 2 * * * php /var/www/cron/rh_detectar_pendencias.php
 */

require __DIR__ . '/bootstrap.php';

use App\Services\RhPendenciaService;

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Iniciando recálculo de pendências do RH...\n";

$t0 = microtime(true);
try {
    $stats = RhPendenciaService::recalcularTudo();
    $dt = round(microtime(true) - $t0, 2);
    echo "[{$ts}] Concluído em {$dt}s. ";
    echo "Criadas: {$stats['criadas']}, Atualizadas: {$stats['atualizadas']}, Mantidas: {$stats['mantidas']}.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "[{$ts}] ERRO: " . $e->getMessage() . "\n";
    error_log('[cron rh_detectar_pendencias] ' . $e->getMessage());
    exit(1);
}
