<?php
/**
 * Cron Job: Enviar emails de alerta via SMTP
 * Executar diariamente as 07:30
 * crontab: 30 7 * * * php /var/www/cron/enviar_emails.php
 */

require __DIR__ . '/bootstrap.php';

use App\Services\AlertService;
use App\Services\EmailService;

$alertService = new AlertService();
$emailService = new EmailService();

$alertas = $alertService->getAlertasPendentesEmail(100);

if (empty($alertas)) {
    echo date('Y-m-d H:i:s') . " - Nenhum alerta pendente de envio.\n";
    exit(0);
}

// Envia resumo diario com todos os alertas
$ok = $emailService->enviarResumoDiario($alertas);

if ($ok) {
    foreach ($alertas as $alerta) {
        $alertService->marcarEmailEnviado($alerta['id']);
    }
    echo date('Y-m-d H:i:s') . " - Resumo diario enviado com " . count($alertas) . " alertas.\n";
} else {
    echo date('Y-m-d H:i:s') . " - ERRO ao enviar resumo diario.\n";
}
