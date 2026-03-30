<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Certificado;

class DashboardService
{
    private \PDO $db;
    private string $latestDocsSubquery;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->latestDocsSubquery = "(
            SELECT d2.* FROM documentos d2
            INNER JOIN (
                SELECT MIN(id) as min_id FROM (
                    SELECT id, colaborador_id, tipo_documento_id,
                           ROW_NUMBER() OVER (PARTITION BY colaborador_id, tipo_documento_id ORDER BY data_emissao DESC, id ASC) as rn
                    FROM documentos WHERE status != 'obsoleto' AND excluido_em IS NULL
                ) ranked WHERE rn = 1 GROUP BY colaborador_id, tipo_documento_id
            ) latest ON d2.id = latest.min_id
        )";
    }

    public function getBasicStats(): array
    {
        $colabModel = new Colaborador();
        $docModel = new Documento();
        $certModel = new Certificado();

        return [
            'colaboradores_status' => $colabModel->countByStatus(),
            'documentos_status'    => $docModel->countByStatus(),
            'certificados_status'  => $certModel->countByStatus(),
            'docs_expiring'        => $docModel->getExpiring(30, 10),
            'docs_expired'         => $docModel->getExpired(10),
            'certs_expiring'       => $certModel->getExpiring(30, 10),
        ];
    }

    public function getDocsByCategory(): array
    {
        $stmt = $this->db->query(
            "SELECT td.categoria, d.status, COUNT(*) as total
             FROM {$this->latestDocsSubquery} d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             JOIN colaboradores c ON d.colaborador_id = c.id
             WHERE c.status = 'ativo'
             GROUP BY td.categoria, d.status"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTopPendentes(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id as colaborador_id, c.nome_completo,
                    COALESCE(doc_cnt.total, 0) as docs_pendentes,
                    COALESCE(cert_cnt.total, 0) as certs_pendentes,
                    (COALESCE(doc_cnt.total, 0) + COALESCE(cert_cnt.total, 0)) as total_pendentes
             FROM colaboradores c
             LEFT JOIN (
                 SELECT colaborador_id, COUNT(*) as total
                 FROM {$this->latestDocsSubquery} ld
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
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDocsByClient(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT cl.nome_fantasia, COUNT(*) as total,
                    SUM(CASE WHEN d.status='vencido' THEN 1 ELSE 0 END) as vencidos
             FROM {$this->latestDocsSubquery} d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN clientes cl ON c.cliente_id = cl.id
             WHERE c.status = 'ativo'
             GROUP BY cl.id
             ORDER BY total DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getKpiConformidade(): array
    {
        $totalAtivos = (int)$this->db->query(
            "SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo' AND excluido_em IS NULL"
        )->fetchColumn();

        $comProblemas = (int)$this->db->query(
            "SELECT COUNT(DISTINCT c.id)
             FROM colaboradores c
             JOIN {$this->latestDocsSubquery} d ON d.colaborador_id = c.id
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
               AND d.status IN ('vencido', 'proximo_vencimento')"
        )->fetchColumn();

        return [
            'percentual' => $totalAtivos > 0 ? round(($totalAtivos - $comProblemas) / $totalAtivos * 100, 1) : 0,
            'tendencia' => 0,
        ];
    }

    public function getKpiTempoRenovacao(): string
    {
        $result = $this->db->query(
            "SELECT ROUND(AVG(dias), 1) as media FROM (
                SELECT DATEDIFF(d_new.data_emissao, d_old.data_validade) as dias
                FROM documentos d_old
                JOIN documentos d_new ON d_new.colaborador_id = d_old.colaborador_id
                    AND d_new.tipo_documento_id = d_old.tipo_documento_id
                    AND d_new.id != d_old.id
                    AND d_new.data_emissao > d_old.data_emissao
                    AND d_new.excluido_em IS NULL
                JOIN colaboradores c ON c.id = d_old.colaborador_id AND c.status = 'ativo' AND c.excluido_em IS NULL
                WHERE d_old.data_validade IS NOT NULL
                    AND d_old.data_validade >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    AND d_old.excluido_em IS NULL
                    AND DATEDIFF(d_new.data_emissao, d_old.data_validade) BETWEEN -90 AND 180
                GROUP BY d_old.id
            ) sub"
        )->fetch(\PDO::FETCH_ASSOC);

        return $result['media'] ?? 'N/A';
    }

    public function getKpiTendenciaVencimentos(): array
    {
        $stmt = $this->db->query(
            "SELECT
                DATE_FORMAT(m.mes, '%b/%Y') as mes_label,
                (SELECT COUNT(*) FROM documentos d
                 JOIN colaboradores c ON c.id = d.colaborador_id AND c.status = 'ativo' AND c.excluido_em IS NULL
                 WHERE d.data_validade >= DATE_FORMAT(m.mes, '%Y-%m-01')
                   AND d.data_validade <= LAST_DAY(m.mes)
                   AND d.status != 'obsoleto' AND d.excluido_em IS NULL
                ) +
                (SELECT COUNT(*) FROM certificados cert
                 JOIN colaboradores c ON c.id = cert.colaborador_id AND c.status = 'ativo' AND c.excluido_em IS NULL
                 WHERE cert.data_validade >= DATE_FORMAT(m.mes, '%Y-%m-01')
                   AND cert.data_validade <= LAST_DAY(m.mes)
                ) as total
             FROM (
                SELECT DATE_ADD(CURDATE(), INTERVAL n MONTH) as mes
                FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) nums
             ) m
             ORDER BY m.mes ASC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getKpiDocsVencidosAtivos(): int
    {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM (
                 SELECT id, colaborador_id, status,
                        ROW_NUMBER() OVER (
                            PARTITION BY colaborador_id, tipo_documento_id
                            ORDER BY data_emissao DESC, id ASC
                        ) as rn
                 FROM documentos
                 WHERE status != 'obsoleto' AND excluido_em IS NULL
             ) d
             JOIN colaboradores c ON c.id = d.colaborador_id AND c.status = 'ativo' AND c.excluido_em IS NULL
             WHERE d.status = 'vencido' AND d.rn = 1"
        )->fetchColumn();
    }

    public function getColabSemDocs(): array
    {
        $stmt = $this->db->query(
            "SELECT c.id, c.nome_completo, c.cargo, cl.nome_fantasia as cliente_nome
             FROM colaboradores c
             LEFT JOIN clientes cl ON c.cliente_id = cl.id
             LEFT JOIN documentos d ON d.colaborador_id = c.id AND d.status != 'obsoleto' AND d.excluido_em IS NULL
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
             GROUP BY c.id
             HAVING COUNT(d.id) = 0
             ORDER BY c.nome_completo ASC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMissingDocsCount(): int
    {
        return (int)$this->db->query(
            "SELECT COUNT(DISTINCT c.id)
             FROM colaboradores c
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
               AND (
                   NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'aso' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'epi' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'os' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'treinamento' AND d.status != 'obsoleto')
               )"
        )->fetchColumn();
    }

    public function getChartData(): array
    {
        $docModel = new Documento();
        $certModel = new Certificado();

        return [
            'documentos_status'   => $docModel->countByStatus(),
            'certificados_status' => $certModel->countByStatus(),
            'docs_by_category'    => $this->getDocsByCategory(),
            'docs_by_client'      => $this->getDocsByClient(),
        ];
    }

    public function getAprovacoesPendentes(): array
    {
        $stmt = $this->db->query(
            "SELECT d.id, d.arquivo_nome, d.data_emissao, d.criado_em,
                    c.id as colaborador_id, c.nome_completo,
                    td.nome as tipo_nome,
                    u.nome as enviado_por_nome
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN usuarios u ON d.enviado_por = u.id
             WHERE (d.aprovacao_status IS NULL OR d.aprovacao_status = 'pendente')
               AND d.status != 'obsoleto'
               AND d.excluido_em IS NULL
               AND c.excluido_em IS NULL
               AND c.status = 'ativo'
             ORDER BY d.criado_em DESC
             LIMIT 20"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getVencendoEstaSemana(): array
    {
        $stmt = $this->db->query(
            "SELECT 'documento' as tipo, d.id, c.id as colaborador_id, c.nome_completo,
                    td.nome as item_nome, d.data_validade,
                    DATEDIFF(d.data_validade, CURDATE()) as dias_restantes
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND d.status != 'obsoleto' AND d.excluido_em IS NULL
               AND c.status = 'ativo' AND c.excluido_em IS NULL
             UNION ALL
             SELECT 'certificado', cert.id, c.id, c.nome_completo,
                    tc.codigo, cert.data_validade,
                    DATEDIFF(cert.data_validade, CURDATE())
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE cert.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND cert.excluido_em IS NULL
               AND c.status = 'ativo' AND c.excluido_em IS NULL
             ORDER BY dias_restantes ASC, nome_completo ASC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os dados do dashboard de uma vez.
     */
    public function getAllData(): array
    {
        $data = $this->getBasicStats();
        $data['docs_by_category'] = $this->getDocsByCategory();
        $data['top_pendentes'] = $this->getTopPendentes();
        $data['docs_by_client'] = $this->getDocsByClient();

        $kpiConf = $this->getKpiConformidade();
        $data['kpi_conformidade_atual'] = $kpiConf['percentual'];
        $data['kpi_conformidade_tendencia'] = $kpiConf['tendencia'];
        $data['kpi_tempo_renovacao'] = $this->getKpiTempoRenovacao();
        $data['kpi_tendencia_vencimentos'] = $this->getKpiTendenciaVencimentos();
        $data['kpi_docs_vencidos_ativos'] = $this->getKpiDocsVencidosAtivos();

        $semDocs = $this->getColabSemDocs();
        $data['colab_sem_docs'] = $semDocs;
        $data['colab_sem_docs_count'] = count($semDocs);
        $data['missing_docs_count'] = $this->getMissingDocsCount();
        $data['vencendo_esta_semana'] = $this->getVencendoEstaSemana();
        $data['aprovacoes_pendentes'] = $this->getAprovacoesPendentes();
        $stmt = $this->db->query("SELECT COUNT(*) FROM documentos WHERE (aprovacao_status = 'pendente' OR aprovacao_status IS NULL) AND status != 'obsoleto' AND excluido_em IS NULL");
        $data['total_aprovacoes_pendentes'] = (int)$stmt->fetchColumn();

        // Contagem total docs mês (rápido)
        $stmtCount = $this->db->query(
            "SELECT COUNT(*) FROM documentos
             WHERE MONTH(criado_em) = MONTH(CURDATE())
               AND YEAR(criado_em) = YEAR(CURDATE())
               AND excluido_em IS NULL"
        );
        $data['docs_mes_corrente_count'] = (int)$stmtCount->fetchColumn();

        // Docs produzidos mês passado
        $stmtPassado = $this->db->query(
            "SELECT COUNT(*) FROM documentos
             WHERE MONTH(criado_em) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
               AND YEAR(criado_em) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
               AND excluido_em IS NULL"
        );
        $data['docs_mes_passado_count'] = (int)$stmtPassado->fetchColumn();

        // Docs que vencem no próximo mês (precisam ser renovados)
        $stmtProximo = $this->db->query(
            "SELECT COUNT(*) FROM documentos
             WHERE data_validade >= DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
               AND data_validade < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
               AND excluido_em IS NULL"
        );
        $data['docs_vencendo_proximo_mes_count'] = (int)$stmtProximo->fetchColumn();

        // Preview: apenas 10 mais recentes para o dashboard
        $data['docs_mes_corrente'] = $this->getDocsMesCorrente();

        return $data;
    }

    private function getDocsMesCorrente(): array
    {
        $stmt = $this->db->query(
            "SELECT d.id, d.status, d.data_emissao, d.criado_em,
                    c.nome_completo, td.nome as tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE MONTH(d.criado_em) = MONTH(CURDATE())
               AND YEAR(d.criado_em) = YEAR(CURDATE())
               AND d.excluido_em IS NULL
             ORDER BY d.criado_em DESC
             LIMIT 10"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
