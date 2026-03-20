<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Certificado;

class DashboardController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes

    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        // Check dashboard cache
        $cacheKey = 'dashboard_data';
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            $cached['pageTitle'] = 'Dashboard';
            $this->view('dashboard/index', $cached);
            return;
        }

        $colabModel = new Colaborador();
        $docModel   = new Documento();
        $certModel  = new Certificado();

        // --- Basic stats (existing) ---
        $data = [
            'colaboradores_status' => $colabModel->countByStatus(),
            'documentos_status'    => $docModel->countByStatus(),
            'certificados_status'  => $certModel->countByStatus(),
            'docs_expiring'        => $docModel->getExpiring(30, 10),
            'docs_expired'         => $docModel->getExpired(10),
            'certs_expiring'       => $certModel->getExpiring(30, 10),
            'pageTitle'            => 'Dashboard',
        ];

        // Subquery base: apenas o doc mais recente de cada tipo por colaborador
        // Desempate por id ASC: menor ID = documento original assinado (não réplica do sistema)
        $latestDocs = "(
            SELECT d2.* FROM documentos d2
            INNER JOIN (
                SELECT MIN(id) as min_id FROM (
                    SELECT id, colaborador_id, tipo_documento_id,
                           ROW_NUMBER() OVER (PARTITION BY colaborador_id, tipo_documento_id ORDER BY data_emissao DESC, id ASC) as rn
                    FROM documentos WHERE status != 'obsoleto' AND excluido_em IS NULL
                ) ranked WHERE rn = 1 GROUP BY colaborador_id, tipo_documento_id
            ) latest ON d2.id = latest.min_id
        )";

        // --- Document status counts by category (SOMENTE COLABORADORES ATIVOS) ---
        $data['docs_by_category'] = $docModel->query(
            "SELECT td.categoria, d.status, COUNT(*) as total
             FROM {$latestDocs} d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             JOIN colaboradores c ON d.colaborador_id = c.id
             WHERE c.status = 'ativo'
             GROUP BY td.categoria, d.status"
        );

        // --- Top 10 collaborators ATIVOS with most expired/expiring items ---
        $data['top_pendentes'] = $docModel->query(
            "SELECT c.id as colaborador_id, c.nome_completo,
                    COALESCE(doc_cnt.total, 0) as docs_pendentes,
                    COALESCE(cert_cnt.total, 0) as certs_pendentes,
                    (COALESCE(doc_cnt.total, 0) + COALESCE(cert_cnt.total, 0)) as total_pendentes
             FROM colaboradores c
             LEFT JOIN (
                 SELECT colaborador_id, COUNT(*) as total
                 FROM {$latestDocs} ld
                 WHERE ld.status IN ('vencido', 'proximo_vencimento')
                 GROUP BY colaborador_id
             ) doc_cnt ON doc_cnt.colaborador_id = c.id
             LEFT JOIN (
                 SELECT colaborador_id, COUNT(*) as total
                 FROM certificados
                 WHERE status IN ('vencido', 'proximo_vencimento')
                 GROUP BY colaborador_id
             ) cert_cnt ON cert_cnt.colaborador_id = c.id
             WHERE c.status = 'ativo'
             HAVING total_pendentes > 0
             ORDER BY total_pendentes DESC
             LIMIT 10"
        );

        // --- Documents by client (top 10, SOMENTE ATIVOS) ---
        $data['docs_by_client'] = $docModel->query(
            "SELECT cl.nome_fantasia, COUNT(*) as total,
                    SUM(CASE WHEN d.status='vencido' THEN 1 ELSE 0 END) as vencidos
             FROM {$latestDocs} d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN clientes cl ON c.cliente_id = cl.id
             WHERE c.status = 'ativo'
             GROUP BY cl.id
             ORDER BY total DESC
             LIMIT 10"
        );

        // --- KPI: Taxa de conformidade (% colaboradores ativos com TODOS docs em dia) ---
        $totalAtivosKpi = (int)($docModel->query(
            "SELECT COUNT(*) as total FROM colaboradores WHERE status = 'ativo'"
        )[0]['total'] ?? 0);

        $comProblemas = (int)($docModel->query(
            "SELECT COUNT(DISTINCT c.id) as total
             FROM colaboradores c
             JOIN {$latestDocs} d ON d.colaborador_id = c.id
             WHERE c.status = 'ativo'
               AND d.status IN ('vencido', 'proximo_vencimento')"
        )[0]['total'] ?? 0);

        $data['kpi_conformidade_atual'] = $totalAtivosKpi > 0
            ? round(($totalAtivosKpi - $comProblemas) / $totalAtivosKpi * 100, 1)
            : 0;
        $data['kpi_conformidade_tendencia'] = 0; // Will calculate after we have historical data

        // --- KPI: Tempo medio de renovacao (dias entre vencimento e nova emissao) ---
        // Encontra pares de docs do mesmo tipo/colaborador onde um é mais novo que outro
        $tempoRenovacao = $docModel->query(
            "SELECT ROUND(AVG(dias), 1) as media FROM (
                SELECT DATEDIFF(d_new.data_emissao, d_old.data_validade) as dias
                FROM documentos d_old
                JOIN documentos d_new ON d_new.colaborador_id = d_old.colaborador_id
                    AND d_new.tipo_documento_id = d_old.tipo_documento_id
                    AND d_new.id != d_old.id
                    AND d_new.data_emissao > d_old.data_emissao
                    AND d_new.excluido_em IS NULL
                JOIN colaboradores c ON c.id = d_old.colaborador_id AND c.status = 'ativo'
                WHERE d_old.data_validade IS NOT NULL
                    AND d_old.data_validade >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    AND d_old.excluido_em IS NULL
                    AND DATEDIFF(d_new.data_emissao, d_old.data_validade) BETWEEN -90 AND 180
                GROUP BY d_old.id
            ) sub"
        );
        $data['kpi_tempo_renovacao'] = $tempoRenovacao[0]['media'] ?? 'N/A';

        // --- KPI: Tendencia vencimentos (proximos 6 meses) ---
        $data['kpi_tendencia_vencimentos'] = $docModel->query(
            "SELECT
                DATE_FORMAT(m.mes, '%b/%Y') as mes_label,
                (SELECT COUNT(*) FROM documentos d
                 JOIN colaboradores c ON c.id = d.colaborador_id AND c.status = 'ativo'
                 WHERE d.data_validade >= DATE_FORMAT(m.mes, '%Y-%m-01')
                   AND d.data_validade <= LAST_DAY(m.mes)
                   AND d.status != 'obsoleto' AND d.excluido_em IS NULL
                ) +
                (SELECT COUNT(*) FROM certificados cert
                 JOIN colaboradores c ON c.id = cert.colaborador_id AND c.status = 'ativo'
                 WHERE cert.data_validade >= DATE_FORMAT(m.mes, '%Y-%m-01')
                   AND cert.data_validade <= LAST_DAY(m.mes)
                ) as total
             FROM (
                SELECT DATE_ADD(CURDATE(), INTERVAL n MONTH) as mes
                FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) nums
             ) m
             ORDER BY m.mes ASC"
        );

        // --- KPI: Docs vencidos de colaboradores ativos (apenas o mais recente por tipo) ---
        $docsVencidosAtivos = (int)($docModel->query(
            "SELECT COUNT(*) as total FROM (
                 SELECT id, colaborador_id, status,
                        ROW_NUMBER() OVER (
                            PARTITION BY colaborador_id, tipo_documento_id
                            ORDER BY data_emissao DESC, id ASC
                        ) as rn
                 FROM documentos
                 WHERE status != 'obsoleto' AND excluido_em IS NULL
             ) d
             JOIN colaboradores c ON c.id = d.colaborador_id AND c.status = 'ativo'
             WHERE d.status = 'vencido' AND d.rn = 1"
        )[0]['total'] ?? 0);
        $data['kpi_docs_vencidos_ativos'] = $docsVencidosAtivos;

        // --- Colaboradores ativos SEM NENHUM documento ---
        $semDocsList = $docModel->query(
            "SELECT c.id, c.nome_completo, c.cargo, cl.nome_fantasia as cliente_nome
             FROM colaboradores c
             LEFT JOIN clientes cl ON c.cliente_id = cl.id
             LEFT JOIN documentos d ON d.colaborador_id = c.id AND d.status != 'obsoleto' AND d.excluido_em IS NULL
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
             GROUP BY c.id
             HAVING COUNT(d.id) = 0
             ORDER BY c.nome_completo ASC"
        );
        $data['colab_sem_docs'] = $semDocsList;
        $data['colab_sem_docs_count'] = count($semDocsList);

        // --- Active collaborators missing required doc categories ---
        $data['missing_docs_count'] = $docModel->query(
            "SELECT COUNT(DISTINCT c.id) as total
             FROM colaboradores c
             WHERE c.status = 'ativo'
               AND (
                   NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'aso' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'epi' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'os' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'treinamento' AND d.status != 'obsoleto')
               )"
        )[0]['total'] ?? 0;

        // Cache dashboard data (without pageTitle)
        $cacheData = $data;
        unset($cacheData['pageTitle']);
        $this->setCache($cacheKey, $cacheData);

        $this->view('dashboard/index', $data);
    }

    // ========================================================================
    // Cache helpers (file-based, no external deps)
    // ========================================================================

    private function getCachePath(string $key): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . md5($key) . '.cache';
    }

    private function getCache(string $key): ?array
    {
        $file = $this->getCachePath($key);
        if (!file_exists($file)) return null;

        $content = file_get_contents($file);
        $data = unserialize($content);

        if (!is_array($data) || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['payload'];
    }

    private function setCache(string $key, array $payload): void
    {
        $file = $this->getCachePath($key);
        $data = [
            'expires' => time() + self::CACHE_TTL,
            'payload' => $payload,
        ];
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    /**
     * Clear the dashboard cache. Call this after data changes.
     */
    public static function clearCache(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        $file = $dir . '/' . md5('dashboard_data') . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * AJAX endpoint: returns dashboard chart data as JSON.
     */
    public function dashboardData(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel  = new Documento();
        $certModel = new Certificado();

        $response = [
            'documentos_status'  => $docModel->countByStatus(),
            'certificados_status' => $certModel->countByStatus(),
            'docs_by_category'   => $docModel->query(
                "SELECT td.categoria, d.status, COUNT(*) as total
                 FROM documentos d
                 JOIN tipos_documento td ON d.tipo_documento_id = td.id
                 JOIN colaboradores c ON d.colaborador_id = c.id
                 WHERE d.status != 'obsoleto' AND d.excluido_em IS NULL AND c.status = 'ativo'
                 GROUP BY td.categoria, d.status"
            ),
            'docs_by_client'     => $docModel->query(
                "SELECT cl.nome_fantasia, COUNT(*) as total,
                        SUM(CASE WHEN d.status='vencido' THEN 1 ELSE 0 END) as vencidos
                 FROM documentos d
                 JOIN colaboradores c ON d.colaborador_id = c.id
                 JOIN clientes cl ON c.cliente_id = cl.id
                 WHERE d.status != 'obsoleto' AND d.excluido_em IS NULL AND c.status = 'ativo'
                 GROUP BY cl.id
                 ORDER BY total DESC
                 LIMIT 10"
            ),
        ];

        $this->json($response);
    }
}
