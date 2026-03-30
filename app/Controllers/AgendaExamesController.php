<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;

class AgendaExamesController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $mes = (int)($this->input('mes') ?: date('n'));
        $ano = (int)($this->input('ano') ?: date('Y'));

        if ($mes < 1) { $mes = 12; $ano--; }
        if ($mes > 12) { $mes = 1; $ano++; }

        $db = Database::getInstance();

        // Buscar colaboradores com ASO vencendo no mês selecionado
        $stmt = $db->prepare(
            "SELECT c.id, c.nome_completo, c.cargo, c.funcao, c.setor,
                    d.data_validade, d.data_emissao, d.status,
                    td.nome as tipo_aso,
                    DATEDIFF(d.data_validade, CURDATE()) as dias_restantes
             FROM colaboradores c
             JOIN documentos d ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
               AND d.tipo_documento_id IN (1,2,3,4,5)
               AND d.status != 'obsoleto' AND d.excluido_em IS NULL
               AND MONTH(d.data_validade) = :mes AND YEAR(d.data_validade) = :ano
               AND d.id = (
                   SELECT d2.id FROM documentos d2
                   WHERE d2.colaborador_id = c.id
                     AND d2.tipo_documento_id IN (1,2,3,4,5)
                     AND d2.status != 'obsoleto' AND d2.excluido_em IS NULL
                   ORDER BY d2.data_emissao DESC LIMIT 1
               )
             ORDER BY d.data_validade ASC, c.nome_completo ASC"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $examesMes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contar por mês (próximos 12 meses) para o resumo
        $resumoMeses = [];
        for ($i = 0; $i < 12; $i++) {
            $m = ((date('n') - 1 + $i) % 12) + 1;
            $a = date('Y') + intdiv(date('n') - 1 + $i, 12);
            $stmt = $db->prepare(
                "SELECT COUNT(DISTINCT c.id) as total
                 FROM colaboradores c
                 JOIN documentos d ON d.colaborador_id = c.id
                 WHERE c.status = 'ativo' AND c.excluido_em IS NULL
                   AND d.tipo_documento_id IN (1,2,3,4,5)
                   AND d.status != 'obsoleto' AND d.excluido_em IS NULL
                   AND MONTH(d.data_validade) = :mes AND YEAR(d.data_validade) = :ano
                   AND d.id = (
                       SELECT d2.id FROM documentos d2
                       WHERE d2.colaborador_id = c.id
                         AND d2.tipo_documento_id IN (1,2,3,4,5)
                         AND d2.status != 'obsoleto' AND d2.excluido_em IS NULL
                       ORDER BY d2.data_emissao DESC LIMIT 1
                   )"
            );
            $stmt->execute(['mes' => $m, 'ano' => $a]);
            $resumoMeses[] = [
                'mes' => $m,
                'ano' => $a,
                'total' => (int)$stmt->fetchColumn(),
            ];
        }

        // Colaboradores com ASO vencido (sem data futura)
        $stmt = $db->query(
            "SELECT c.id, c.nome_completo, c.cargo, c.funcao, c.setor,
                    d.data_validade, d.status,
                    DATEDIFF(CURDATE(), d.data_validade) as dias_vencido
             FROM colaboradores c
             JOIN documentos d ON d.colaborador_id = c.id
             WHERE c.status = 'ativo' AND c.excluido_em IS NULL
               AND d.tipo_documento_id IN (1,2,3,4,5)
               AND d.status = 'vencido' AND d.excluido_em IS NULL
               AND d.id = (
                   SELECT d2.id FROM documentos d2
                   WHERE d2.colaborador_id = c.id
                     AND d2.tipo_documento_id IN (1,2,3,4,5)
                     AND d2.status != 'obsoleto' AND d2.excluido_em IS NULL
                   ORDER BY d2.data_emissao DESC LIMIT 1
               )
             ORDER BY d.data_validade ASC
             LIMIT 50"
        );
        $vencidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $meses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

        $this->view('agenda-exames/index', [
            'mes' => $mes,
            'ano' => $ano,
            'meses' => $meses,
            'examesMes' => $examesMes,
            'resumoMeses' => $resumoMeses,
            'vencidos' => $vencidos,
            'pageTitle' => 'Agenda Exames',
        ]);
    }
}
