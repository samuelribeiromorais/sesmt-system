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

        $userId = (int)Session::get('user_id');
        $db     = Database::getInstance();

        // Documentos enviados pelo usuário RH logado (com status de aprovação)
        $meusEnvios = $db->prepare(
            "SELECT d.id, d.arquivo_nome, d.data_emissao, d.criado_em,
                    d.aprovacao_status, d.aprovacao_obs,
                    c.id as colaborador_id, c.nome_completo,
                    td.nome as tipo_nome,
                    ua.nome as aprovado_por_nome
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN usuarios ua ON d.aprovado_por = ua.id
             WHERE d.enviado_por = :uid
               AND d.excluido_em IS NULL
             ORDER BY d.criado_em DESC
             LIMIT 60"
        );
        $meusEnvios->execute(['uid' => $userId]);
        $meusEnvios = $meusEnvios->fetchAll(\PDO::FETCH_ASSOC);

        // Contadores
        $pendentes = 0; $aprovados = 0; $rejeitados = 0;
        foreach ($meusEnvios as $e) {
            if ($e['aprovacao_status'] === 'pendente' || $e['aprovacao_status'] === null) $pendentes++;
            elseif ($e['aprovacao_status'] === 'aprovado') $aprovados++;
            elseif ($e['aprovacao_status'] === 'rejeitado') $rejeitados++;
        }

        // Colaboradores sem nenhum documento (para sugerir uploads)
        $semDocs = $db->query(
            "SELECT c.id, c.nome_completo, c.funcao, c.cargo
             FROM colaboradores c
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM documentos d
                   WHERE d.colaborador_id = c.id AND d.excluido_em IS NULL
               )
             ORDER BY c.nome_completo
             LIMIT 30"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('rh/index', [
            'meusEnvios'  => $meusEnvios,
            'pendentes'   => $pendentes,
            'aprovados'   => $aprovados,
            'rejeitados'  => $rejeitados,
            'semDocs'     => $semDocs,
            'pageTitle'   => 'Painel RH',
        ]);
    }
}
