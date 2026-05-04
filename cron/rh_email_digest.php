<?php
/**
 * Cron Job: E-mail digest diário do módulo RH (Fase 3)
 * Executar diariamente às 07:00
 * crontab: 0 7 * * * php /var/www/cron/rh_email_digest.php
 */

require __DIR__ . '/bootstrap.php';

use App\Services\RhDigestService;
use App\Services\EmailService;

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Iniciando envio do digest RH...\n";

$html = RhDigestService::montarDigest();
if ($html === null) {
    echo "[{$ts}] Sem pendências/alertas. Digest não enviado.\n";
    exit(0);
}

$destinatarios = RhDigestService::destinatarios();
if (empty($destinatarios)) {
    echo "[{$ts}] Sem destinatários configurados. Digest não enviado.\n";
    exit(0);
}

$assunto = '[TSESMT] Digest RH — ' . date('d/m/Y');
$enviados = 0; $falhas = 0;

try {
    $email = new EmailService();
    $ok    = $email->enviarPara($destinatarios, $assunto, $html);
    $n     = count($destinatarios);
    echo "[{$ts}] Digest enviado para {$n} destinatário(s). " . ($ok ? "OK" : "FALHA") . "\n";
    exit($ok ? 0 : 1);
} catch (\Throwable $e) {
    echo "[{$ts}] ERRO: " . $e->getMessage() . "\n";
    error_log('[cron rh_email_digest] ' . $e->getMessage());
    exit(1);
}
