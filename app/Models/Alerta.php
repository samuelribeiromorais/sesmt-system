<?php

namespace App\Models;

use App\Core\Model;

class Alerta extends Model
{
    protected string $table = 'alertas';

    public function getActive(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, c.nome_completo,
                    td.nome as doc_tipo_nome, tc.codigo as cert_codigo
             FROM alertas a
             JOIN colaboradores c ON a.colaborador_id = c.id
             LEFT JOIN documentos d ON a.documento_id = d.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN certificados cert ON a.certificado_id = cert.id
             LEFT JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE a.notificado = 0
             ORDER BY a.dias_restantes ASC, a.criado_em DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
