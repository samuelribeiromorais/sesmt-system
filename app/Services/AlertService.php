<?php

namespace App\Services;

use App\Core\Database;

class AlertService
{
    private \PDO $db;
    private array $diasAlerta;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $config = require dirname(__DIR__) . '/config/app.php';
        $this->diasAlerta = $config['alerts']['days_before'];
    }

    /**
     * Atualiza status de todos os documentos e certificados baseado na data atual
     */
    public function atualizarValidades(): array
    {
        $stats = ['docs_vencidos' => 0, 'docs_proximos' => 0, 'certs_vencidos' => 0, 'certs_proximos' => 0];

        // Documentos vencidos
        $stmt = $this->db->prepare(
            "UPDATE documentos SET status = 'vencido', atualizado_em = NOW()
             WHERE data_validade IS NOT NULL AND data_validade < CURDATE()
               AND status IN ('vigente', 'proximo_vencimento')"
        );
        $stmt->execute();
        $stats['docs_vencidos'] = $stmt->rowCount();

        // Documentos próximo do vencimento (30 dias)
        $stmt = $this->db->prepare(
            "UPDATE documentos SET status = 'proximo_vencimento', atualizado_em = NOW()
             WHERE data_validade IS NOT NULL
               AND data_validade >= CURDATE()
               AND data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               AND status = 'vigente'"
        );
        $stmt->execute();
        $stats['docs_proximos'] = $stmt->rowCount();

        // Certificados vencidos
        $stmt = $this->db->prepare(
            "UPDATE certificados SET status = 'vencido', atualizado_em = NOW()
             WHERE data_validade < CURDATE()
               AND status IN ('vigente', 'proximo_vencimento')"
        );
        $stmt->execute();
        $stats['certs_vencidos'] = $stmt->rowCount();

        // Certificados próximo do vencimento
        $stmt = $this->db->prepare(
            "UPDATE certificados SET status = 'proximo_vencimento', atualizado_em = NOW()
             WHERE data_validade >= CURDATE()
               AND data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               AND status = 'vigente'"
        );
        $stmt->execute();
        $stats['certs_proximos'] = $stmt->rowCount();

        return $stats;
    }

    /**
     * Gera alertas para documentos e certificados que estao vencendo ou vencidos
     */
    public function gerarAlertas(): array
    {
        $stats = ['criados' => 0, 'duplicados_ignorados' => 0];

        // Alertas para documentos vencendo (30, 15, 7 dias)
        foreach ($this->diasAlerta as $dias) {
            $docs = $this->db->prepare(
                "SELECT d.id as documento_id, d.colaborador_id, d.data_validade,
                        DATEDIFF(d.data_validade, CURDATE()) as dias_restantes
                 FROM documentos d
                 JOIN colaboradores c ON d.colaborador_id = c.id
                 WHERE d.data_validade IS NOT NULL
                   AND d.status IN ('vigente', 'proximo_vencimento')
                   AND DATEDIFF(d.data_validade, CURDATE()) = :dias
                   AND c.status = 'ativo'
                   AND d.id NOT IN (
                       SELECT a.documento_id FROM alertas a
                       WHERE a.documento_id IS NOT NULL
                         AND a.tipo = 'vencimento_proximo'
                         AND a.dias_restantes = :dias2
                   )"
            );
            $docs->execute(['dias' => $dias, 'dias2' => $dias]);

            foreach ($docs->fetchAll() as $doc) {
                $this->criarAlerta(
                    $doc['colaborador_id'],
                    'vencimento_proximo',
                    $doc['dias_restantes'],
                    $doc['documento_id'],
                    null
                );
                $stats['criados']++;
            }
        }

        // Alertas para documentos ja vencidos (1 alerta por documento vencido)
        $docsVencidos = $this->db->query(
            "SELECT d.id as documento_id, d.colaborador_id,
                    DATEDIFF(CURDATE(), d.data_validade) as dias_vencido
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             WHERE d.data_validade IS NOT NULL
               AND d.data_validade < CURDATE()
               AND d.status != 'obsoleto'
               AND c.status = 'ativo'
               AND d.id NOT IN (
                   SELECT a.documento_id FROM alertas a
                   WHERE a.documento_id IS NOT NULL AND a.tipo = 'vencido'
               )"
        );

        foreach ($docsVencidos->fetchAll() as $doc) {
            $this->criarAlerta(
                $doc['colaborador_id'],
                'vencido',
                -$doc['dias_vencido'],
                $doc['documento_id'],
                null
            );
            $stats['criados']++;
        }

        // Alertas para certificados vencendo
        foreach ($this->diasAlerta as $dias) {
            $certs = $this->db->prepare(
                "SELECT cert.id as certificado_id, cert.colaborador_id,
                        DATEDIFF(cert.data_validade, CURDATE()) as dias_restantes
                 FROM certificados cert
                 JOIN colaboradores c ON cert.colaborador_id = c.id
                 WHERE cert.data_validade IS NOT NULL
                   AND cert.status IN ('vigente', 'proximo_vencimento')
                   AND DATEDIFF(cert.data_validade, CURDATE()) = :dias
                   AND c.status = 'ativo'
                   AND cert.id NOT IN (
                       SELECT a.certificado_id FROM alertas a
                       WHERE a.certificado_id IS NOT NULL
                         AND a.tipo = 'vencimento_proximo'
                         AND a.dias_restantes = :dias2
                   )"
            );
            $certs->execute(['dias' => $dias, 'dias2' => $dias]);

            foreach ($certs->fetchAll() as $cert) {
                $this->criarAlerta(
                    $cert['colaborador_id'],
                    'vencimento_proximo',
                    $cert['dias_restantes'],
                    null,
                    $cert['certificado_id']
                );
                $stats['criados']++;
            }
        }

        // Alertas para certificados vencidos
        $certsVencidos = $this->db->query(
            "SELECT cert.id as certificado_id, cert.colaborador_id,
                    DATEDIFF(CURDATE(), cert.data_validade) as dias_vencido
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             WHERE cert.data_validade < CURDATE()
               AND cert.status = 'vencido'
               AND c.status = 'ativo'
               AND cert.id NOT IN (
                   SELECT a.certificado_id FROM alertas a
                   WHERE a.certificado_id IS NOT NULL AND a.tipo = 'vencido'
               )"
        );

        foreach ($certsVencidos->fetchAll() as $cert) {
            $this->criarAlerta(
                $cert['colaborador_id'],
                'vencido',
                -$cert['dias_vencido'],
                null,
                $cert['certificado_id']
            );
            $stats['criados']++;
        }

        return $stats;
    }

    /**
     * Retorna alertas pendentes de envio por email
     */
    public function getAlertasPendentesEmail(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, c.nome_completo, c.id as colab_id,
                    td.nome as doc_tipo_nome, tc.codigo as cert_codigo,
                    d.data_validade as doc_validade, cert.data_validade as cert_validade
             FROM alertas a
             JOIN colaboradores c ON a.colaborador_id = c.id
             LEFT JOIN documentos d ON a.documento_id = d.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN certificados cert ON a.certificado_id = cert.id
             LEFT JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE a.email_enviado = 0
             ORDER BY a.criado_em ASC
             LIMIT :lim"
        );
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Marca alerta como email enviado
     */
    public function marcarEmailEnviado(int $alertaId): void
    {
        $this->db->prepare(
            "UPDATE alertas SET email_enviado = 1, email_enviado_em = NOW() WHERE id = :id"
        )->execute(['id' => $alertaId]);
    }

    private function criarAlerta(int $colaboradorId, string $tipo, int $diasRestantes, ?int $documentoId, ?int $certificadoId): void
    {
        $this->db->prepare(
            "INSERT INTO alertas (colaborador_id, tipo, dias_restantes, documento_id, certificado_id, criado_em)
             VALUES (:cid, :tipo, :dias, :did, :certid, NOW())"
        )->execute([
            'cid'    => $colaboradorId,
            'tipo'   => $tipo,
            'dias'   => $diasRestantes,
            'did'    => $documentoId,
            'certid' => $certificadoId,
        ]);
    }
}
