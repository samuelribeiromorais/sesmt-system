<?php

namespace App\Services;

use App\Core\Database;

/**
 * E-mail digest diário do módulo RH (RF-06 do ETF).
 * Gera HTML resumindo pendências, atrasados e próximos vencimentos.
 */
class RhDigestService
{
    /**
     * Monta o HTML do digest. Retorna null se não há nada para reportar
     * (RN-06-01: zero pendências = não envia).
     */
    public static function montarDigest(): ?string
    {
        $db = Database::getInstance();

        $pendentes  = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status='pendente_envio'")->fetchColumn();
        $atrasados  = (int)$db->query(
            "SELECT COUNT(*) FROM rh_protocolos
             WHERE status='pendente_envio' AND prazo_sla IS NOT NULL AND prazo_sla < CURDATE()"
        )->fetchColumn();

        // Janelas configuradas
        $cfg = $db->query("SELECT * FROM rh_alertas_config WHERE id = 1")->fetch(\PDO::FETCH_ASSOC) ?: [];
        $janelas = [];
        if (($cfg['janela_60'] ?? 1) == 1) $janelas[] = 60;
        if (($cfg['janela_30'] ?? 1) == 1) $janelas[] = 30;
        if (($cfg['janela_15'] ?? 1) == 1) $janelas[] = 15;
        if (($cfg['janela_7']  ?? 1) == 1) $janelas[] = 7;

        // Próximos do vencimento por janela
        $vencendo = [];
        foreach ($janelas as $dias) {
            $stmt = $db->prepare(
                "SELECT COUNT(DISTINCT rp.id) AS n
                 FROM rh_protocolos rp
                 JOIN documentos d ON rp.documento_id = d.id
                 WHERE rp.status IN ('pendente_envio','enviado')
                   AND d.data_validade IS NOT NULL
                   AND d.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :d DAY)"
            );
            $stmt->execute(['d' => $dias]);
            $vencendo[$dias] = (int)$stmt->fetchColumn();
        }

        // Top 5 clientes com mais pendências
        $topClientes = $db->query(
            "SELECT cl.nome_fantasia, COUNT(*) AS n
             FROM rh_protocolos rp JOIN clientes cl ON rp.cliente_id = cl.id
             WHERE rp.status='pendente_envio'
             GROUP BY cl.id ORDER BY n DESC LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // RN-06-01: nada pra reportar → não envia
        if ($pendentes === 0 && $atrasados === 0 && array_sum($vencendo) === 0) {
            return null;
        }

        $hoje = date('d/m/Y');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
        $html .= '<body style="font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px; background:#f9fafb;">';
        $html .= '<h2 style="color:#005e4e; border-bottom:3px solid #afd85a; padding-bottom:8px;">Digest RH — ' . $hoje . '</h2>';

        // KPIs
        $html .= '<div style="display:flex; gap:8px; margin:20px 0;">';
        $html .= '<div style="flex:1; background:#fff; padding:12px; border-left:4px solid #f39c12; border-radius:4px;">';
        $html .= '<div style="font-size:24px; color:#f39c12; font-weight:700;">' . $pendentes . '</div>';
        $html .= '<div style="font-size:12px; color:#6b7280;">Pendentes de envio</div></div>';
        if ($atrasados > 0) {
            $html .= '<div style="flex:1; background:#fff; padding:12px; border-left:4px solid #e74c3c; border-radius:4px;">';
            $html .= '<div style="font-size:24px; color:#e74c3c; font-weight:700;">' . $atrasados . '</div>';
            $html .= '<div style="font-size:12px; color:#6b7280;">Atrasados (SLA vencido)</div></div>';
        }
        $html .= '</div>';

        // Próximos vencimentos
        if (array_sum($vencendo) > 0) {
            $html .= '<h3 style="color:#374151; margin-top:24px;">Documentos próximos do vencimento</h3>';
            $html .= '<ul style="background:#fff; padding:16px 16px 16px 36px; border-radius:6px;">';
            foreach ($vencendo as $dias => $n) {
                if ($n === 0) continue;
                $html .= '<li><strong>' . $n . '</strong> em até ' . $dias . ' dias</li>';
            }
            $html .= '</ul>';
        }

        // Top clientes
        if (!empty($topClientes)) {
            $html .= '<h3 style="color:#374151; margin-top:24px;">Top 5 clientes com mais pendências</h3>';
            $html .= '<table style="width:100%; background:#fff; border-radius:6px; border-collapse:collapse;">';
            foreach ($topClientes as $tc) {
                $html .= '<tr><td style="padding:10px 14px; border-bottom:1px solid #f3f4f6;">' . htmlspecialchars($tc['nome_fantasia']) . '</td>';
                $html .= '<td style="padding:10px 14px; text-align:right; font-weight:700; border-bottom:1px solid #f3f4f6;">' . $tc['n'] . '</td></tr>';
            }
            $html .= '</table>';
        }

        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8081';
        $html .= '<p style="margin-top:24px; padding:12px; background:#eef6fb; border-left:4px solid #3498db; border-radius:4px;">';
        $html .= '<a href="' . $appUrl . '/rh" style="color:#005e4e; font-weight:700;">▶ Acessar painel RH</a> ';
        $html .= '<span style="color:#6b7280;">para tratar as pendências.</span></p>';

        $html .= '<p style="font-size:11px; color:#9ca3af; margin-top:32px; border-top:1px solid #e5e7eb; padding-top:12px;">';
        $html .= 'Este e-mail é gerado automaticamente pelo TSESMT. Configure as janelas de alerta em /rh/configuracoes.</p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Lista os destinatários conforme rh_alertas_config ou perfis RH.
     */
    public static function destinatarios(): array
    {
        $db = Database::getInstance();
        $cfg = $db->query("SELECT email_digest_destinatarios FROM rh_alertas_config WHERE id = 1")
                  ->fetchColumn();
        if ($cfg) {
            $emails = array_filter(array_map('trim', explode(',', $cfg)));
            return $emails;
        }
        // Fallback: todos os usuários do perfil RH ativos
        $stmt = $db->query("SELECT email FROM usuarios WHERE perfil='rh' AND ativo=1");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
