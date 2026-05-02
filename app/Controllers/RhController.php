<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\RoleMiddleware;

class RhController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db     = Database::getInstance();
        $search = trim($this->input('q', ''));
        $somentePendentes = $this->input('filtro', 'pendentes') === 'pendentes';

        // Filtro: documentos vigentes (não obsoletos, não excluídos),
        // pendentes de envio ao cliente OU já enviados (toggle).
        $where = "d.excluido_em IS NULL
                  AND d.status != 'obsoleto'
                  AND d.aprovacao_status = 'aprovado'
                  AND td.ativo = 1";
        $params = [];
        if ($somentePendentes) {
            $where .= " AND d.enviado_cliente = 0";
        }
        if ($search !== '') {
            $where .= " AND c.nome_completo LIKE :q";
            $params['q'] = "%{$search}%";
        }

        $stmt = $db->prepare(
            "SELECT d.id, d.arquivo_nome, d.data_emissao, d.data_validade,
                    d.criado_em, d.enviado_cliente, d.enviado_cliente_em,
                    c.id as colaborador_id, c.nome_completo, c.matricula,
                    td.nome as tipo_nome, td.categoria,
                    ue.nome as enviado_por_nome,
                    uec.nome as enviado_cliente_por_nome,
                    cl.nome_fantasia as cliente_nome
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN usuarios ue ON d.enviado_por = ue.id
             LEFT JOIN usuarios uec ON d.enviado_cliente_por = uec.id
             LEFT JOIN clientes cl ON c.cliente_id = cl.id
             WHERE {$where}
             ORDER BY d.criado_em DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contadores
        $totalPendentes = (int)$db->query(
            "SELECT COUNT(*) FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.excluido_em IS NULL
               AND d.status != 'obsoleto'
               AND d.aprovacao_status = 'aprovado'
               AND td.ativo = 1
               AND d.enviado_cliente = 0"
        )->fetchColumn();

        $totalEnviados = (int)$db->query(
            "SELECT COUNT(*) FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.excluido_em IS NULL
               AND d.status != 'obsoleto'
               AND d.aprovacao_status = 'aprovado'
               AND td.ativo = 1
               AND d.enviado_cliente = 1"
        )->fetchColumn();

        $this->view('rh/index', [
            'documentos'        => $documentos,
            'totalPendentes'    => $totalPendentes,
            'totalEnviados'     => $totalEnviados,
            'search'            => $search,
            'somentePendentes'  => $somentePendentes,
            'pageTitle'         => 'Painel RH — Envio ao Cliente',
        ]);
    }
}
