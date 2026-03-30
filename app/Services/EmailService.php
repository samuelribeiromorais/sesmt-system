<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/mail.php';
    }

    /**
     * Envia email de alerta de vencimento
     */
    public function enviarAlertaVencimento(array $alerta): bool
    {
        $tipo = $alerta['doc_tipo_nome'] ?? $alerta['cert_codigo'] ?? 'Documento';
        $colaborador = $alerta['nome_completo'];
        $diasRestantes = $alerta['dias_restantes'];

        if ($diasRestantes >= 0) {
            $assunto = "[SESMT] {$tipo} de {$colaborador} vence em {$diasRestantes} dias";
            $urgencia = $diasRestantes <= 7 ? 'URGENTE' : 'ATENÇÃO';
            $corBadge = $diasRestantes <= 7 ? '#e74c3c' : '#f39c12';
            $mensagemStatus = "vence em <strong>{$diasRestantes} dia(s)</strong>";
        } else {
            $diasVencido = abs($diasRestantes);
            $assunto = "[SESMT] VENCIDO: {$tipo} de {$colaborador} ({$diasVencido} dias)";
            $urgencia = 'VENCIDO';
            $corBadge = '#e74c3c';
            $mensagemStatus = "está <strong>vencido há {$diasVencido} dia(s)</strong>";
        }

        $validade = '';
        if (!empty($alerta['doc_validade'])) {
            $validade = date('d/m/Y', strtotime($alerta['doc_validade']));
        } elseif (!empty($alerta['cert_validade'])) {
            $validade = date('d/m/Y', strtotime($alerta['cert_validade']));
        }

        $corpo = $this->gerarHtmlAlerta(
            $urgencia, $corBadge, $colaborador, $tipo, $mensagemStatus, $validade
        );

        return $this->enviar($assunto, $corpo);
    }

    /**
     * Envia email de resumo diario de alertas
     */
    public function enviarResumoDiario(array $alertas): bool
    {
        if (empty($alertas)) return true;

        $vencidos = array_filter($alertas, fn($a) => $a['tipo'] === 'vencido');
        $proximos = array_filter($alertas, fn($a) => $a['tipo'] === 'vencimento_proximo');

        $assunto = sprintf(
            '[SESMT] Resumo diário: %d vencido(s), %d vencendo',
            count($vencidos),
            count($proximos)
        );

        $linhasVencidos = '';
        foreach ($vencidos as $a) {
            $tipo = $a['doc_tipo_nome'] ?? $a['cert_codigo'] ?? '-';
            $linhasVencidos .= "<tr><td style='padding:8px;border-bottom:1px solid #eee;'>{$a['nome_completo']}</td>"
                . "<td style='padding:8px;border-bottom:1px solid #eee;'>{$tipo}</td>"
                . "<td style='padding:8px;border-bottom:1px solid #eee;color:#e74c3c;font-weight:bold;'>"
                . abs($a['dias_restantes']) . " dias</td></tr>";
        }

        $linhasProximos = '';
        foreach ($proximos as $a) {
            $tipo = $a['doc_tipo_nome'] ?? $a['cert_codigo'] ?? '-';
            $linhasProximos .= "<tr><td style='padding:8px;border-bottom:1px solid #eee;'>{$a['nome_completo']}</td>"
                . "<td style='padding:8px;border-bottom:1px solid #eee;'>{$tipo}</td>"
                . "<td style='padding:8px;border-bottom:1px solid #eee;color:#f39c12;font-weight:bold;'>"
                . $a['dias_restantes'] . " dias</td></tr>";
        }

        $corpo = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:#005e4e;color:white;padding:20px 24px;border-radius:8px 8px 0 0;'>
                <h2 style='margin:0;font-size:18px;'>SESMT - Resumo Diário de Alertas</h2>
                <p style='margin:4px 0 0;font-size:13px;opacity:0.8;'>" . date('d/m/Y') . " - TSE Engenharia</p>
            </div>
            <div style='background:white;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;'>";

        if ($linhasVencidos) {
            $corpo .= "
                <h3 style='color:#e74c3c;font-size:15px;margin:0 0 12px;'>Documentos/Certificados VENCIDOS (" . count($vencidos) . ")</h3>
                <table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>
                    <thead><tr style='background:#fef2f2;'>
                        <th style='padding:8px;text-align:left;font-size:12px;'>Colaborador</th>
                        <th style='padding:8px;text-align:left;font-size:12px;'>Tipo</th>
                        <th style='padding:8px;text-align:left;font-size:12px;'>Vencido há</th>
                    </tr></thead>
                    <tbody>{$linhasVencidos}</tbody>
                </table>";
        }

        if ($linhasProximos) {
            $corpo .= "
                <h3 style='color:#f39c12;font-size:15px;margin:0 0 12px;'>Vencendo em breve (" . count($proximos) . ")</h3>
                <table style='width:100%;border-collapse:collapse;'>
                    <thead><tr style='background:#fffbeb;'>
                        <th style='padding:8px;text-align:left;font-size:12px;'>Colaborador</th>
                        <th style='padding:8px;text-align:left;font-size:12px;'>Tipo</th>
                        <th style='padding:8px;text-align:left;font-size:12px;'>Vence em</th>
                    </tr></thead>
                    <tbody>{$linhasProximos}</tbody>
                </table>";
        }

        $corpo .= "
                <p style='margin-top:20px;font-size:12px;color:#6b7280;'>
                    Este é um email automático do Sistema SESMT. Acesse o sistema para mais detalhes.
                </p>
            </div>
        </div>";

        return $this->enviar($assunto, $corpo);
    }

    /**
     * Envia relatório mensal de vencimentos
     */
    public function enviarRelatorioMensal(string $assunto, string $corpo): bool
    {
        return $this->enviar($assunto, $corpo);
    }

    private function gerarHtmlAlerta(string $urgencia, string $cor, string $colaborador, string $tipo, string $status, string $validade): string
    {
        return "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:500px;margin:0 auto;'>
            <div style='background:#005e4e;color:white;padding:16px 20px;border-radius:8px 8px 0 0;text-align:center;'>
                <h2 style='margin:0;font-size:16px;'>SESMT - TSE Engenharia</h2>
            </div>
            <div style='background:white;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;'>
                <div style='background:{$cor};color:white;padding:8px 16px;border-radius:6px;text-align:center;font-weight:bold;font-size:14px;margin-bottom:16px;'>
                    {$urgencia}
                </div>
                <p style='font-size:14px;color:#001e21;'>
                    O documento <strong>{$tipo}</strong> do colaborador <strong>{$colaborador}</strong> {$status}.
                </p>
                " . ($validade ? "<p style='font-size:13px;color:#6b7280;'>Data de validade: <strong>{$validade}</strong></p>" : "") . "
                <p style='font-size:12px;color:#6b7280;margin-top:16px;'>
                    Acesse o Sistema SESMT para tomar as providências necessárias.
                </p>
            </div>
        </div>";
    }

    /**
     * Envia email via SMTP
     */
    private function enviar(string $assunto, string $corpo): bool
    {
        if (empty($this->config['host']) || empty($this->config['username'])) {
            error_log("[EmailService] SMTP não configurado. Email não enviado: {$assunto}");
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $this->config['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);

            // Envia para todos os usuarios admin e sesmt
            $db = \App\Core\Database::getInstance();
            $stmt = $db->query("SELECT email FROM usuarios WHERE perfil IN ('admin','sesmt') AND ativo = 1");
            $destinatarios = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($destinatarios)) {
                error_log("[EmailService] Nenhum destinatario encontrado.");
                return false;
            }

            foreach ($destinatarios as $email) {
                $mail->addAddress($email);
            }

            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $corpo;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $corpo));

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("[EmailService] Erro ao enviar email: " . $e->getMessage());
            return false;
        }
    }
}
