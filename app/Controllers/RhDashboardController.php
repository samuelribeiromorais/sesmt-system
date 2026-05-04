<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;

/**
 * Dashboard gerencial do RH (RF do ETF — módulo 4.6).
 *
 * KPIs:
 *   - % conformidade global (protocolos confirmados / pendências totais)
 *   - Pendências de envio
 *   - Atrasados (prazo SLA vencido)
 *   - Próximos do vencimento (validade do doc nas janelas configuradas)
 *
 * Mapa de calor cliente × tipo de documento.
 * Top 10 colaboradores com mais pendências.
 * Gráfico de protocolos confirmados por mês (últimos 6).
 */
class RhDashboardController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db = Database::getInstance();

        // ─── KPIs principais ─────────────────────────────────────────
        $totalPend = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status='pendente_envio'")->fetchColumn();
        $totalEnv  = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status='enviado'")->fetchColumn();
        $totalConf = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status='confirmado'")->fetchColumn();
        $totalRej  = (int)$db->query("SELECT COUNT(*) FROM rh_protocolos WHERE status='rejeitado'")->fetchColumn();
        $totalGeral = $totalPend + $totalEnv + $totalConf + $totalRej;
        $conformidade = $totalGeral > 0 ? round(100 * $totalConf / $totalGeral, 1) : 0;

        // Atrasados: pendente_envio com prazo_sla vencido
        $atrasados = (int)$db->query(
            "SELECT COUNT(*) FROM rh_protocolos
             WHERE status='pendente_envio' AND prazo_sla IS NOT NULL AND prazo_sla < CURDATE()"
        )->fetchColumn();

        // Próximos do vencimento: docs com validade nos próximos 30 dias e que ainda têm pendência aberta
        $proxVenc = (int)$db->query(
            "SELECT COUNT(DISTINCT rp.id)
             FROM rh_protocolos rp
             JOIN documentos d ON rp.documento_id = d.id
             WHERE rp.status IN ('pendente_envio','enviado')
               AND d.data_validade IS NOT NULL
               AND d.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        // ─── Mapa de calor: cliente × tipo de documento (pendências em aberto) ─
        $mapaQuery = $db->query(
            "SELECT cl.nome_fantasia AS cliente, td.nome AS tipo,
                    SUM(rp.status='pendente_envio') AS pendentes,
                    SUM(rp.status='enviado')        AS enviados,
                    SUM(rp.status='confirmado')     AS confirmados,
                    COUNT(*) AS total
             FROM rh_protocolos rp
             JOIN clientes cl       ON rp.cliente_id = cl.id
             JOIN tipos_documento td ON rp.tipo_documento_id = td.id
             GROUP BY cl.id, td.id
             ORDER BY cl.nome_fantasia, td.nome"
        );
        $mapaRows = $mapaQuery->fetchAll(\PDO::FETCH_ASSOC);

        // Pivota pra estrutura cliente -> [tipo -> stats]
        $mapa = [];
        $tiposSet = [];
        foreach ($mapaRows as $row) {
            $mapa[$row['cliente']][$row['tipo']] = [
                'pendentes'   => (int)$row['pendentes'],
                'enviados'    => (int)$row['enviados'],
                'confirmados' => (int)$row['confirmados'],
                'total'       => (int)$row['total'],
            ];
            $tiposSet[$row['tipo']] = true;
        }
        $tiposLista = array_keys($tiposSet);
        sort($tiposLista);

        // ─── Top 10 colaboradores com mais pendências ────────────────
        $topColab = $db->query(
            "SELECT c.id, c.nome_completo, COUNT(rp.id) AS pendencias
             FROM rh_protocolos rp
             JOIN colaboradores c ON rp.colaborador_id = c.id
             WHERE rp.status='pendente_envio'
             GROUP BY c.id
             ORDER BY pendencias DESC
             LIMIT 10"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // ─── Gráfico: protocolos confirmados nos últimos 6 meses ─────
        $confMes = $db->query(
            "SELECT DATE_FORMAT(confirmado_em, '%Y-%m') AS mes, COUNT(*) AS total
             FROM rh_protocolos
             WHERE status='confirmado'
               AND confirmado_em >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY mes
             ORDER BY mes ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('rh/dashboard', [
            'pageTitle'    => 'Painel RH — Dashboard',
            'totalPend'    => $totalPend,
            'totalEnv'     => $totalEnv,
            'totalConf'    => $totalConf,
            'totalRej'     => $totalRej,
            'totalGeral'   => $totalGeral,
            'conformidade' => $conformidade,
            'atrasados'    => $atrasados,
            'proxVenc'     => $proxVenc,
            'mapa'         => $mapa,
            'tiposLista'   => $tiposLista,
            'topColab'     => $topColab,
            'confMes'      => $confMes,
        ]);
    }
}
