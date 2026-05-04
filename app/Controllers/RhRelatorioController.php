<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Relatórios do módulo RH (RF — módulo 4.7 do ETF).
 */
class RhRelatorioController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db = Database::getInstance();
        $clientes = $db->query("SELECT id, nome_fantasia FROM clientes WHERE ativo=1 ORDER BY nome_fantasia")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('rh/relatorios', [
            'clientes'  => $clientes,
            'pageTitle' => 'Painel RH — Relatórios',
        ]);
    }

    // GET /rh/relatorios/pendencias-cliente.xlsx?cliente_id=X
    public function pendenciasPorCliente(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db        = Database::getInstance();
        $clienteId = (int)$this->input('cliente_id', 0);

        $where  = "rp.status = 'pendente_envio' AND c.excluido_em IS NULL";
        $params = [];
        if ($clienteId > 0) {
            $where .= " AND rp.cliente_id = :cl";
            $params['cl'] = $clienteId;
        }

        $stmt = $db->prepare(
            "SELECT cl.nome_fantasia AS cliente, c.matricula, c.nome_completo AS colaborador,
                    td.nome AS tipo_documento, d.data_emissao, d.data_validade,
                    rp.prazo_sla, DATEDIFF(rp.prazo_sla, CURDATE()) AS dias_para_sla
             FROM rh_protocolos rp
             JOIN colaboradores c ON rp.colaborador_id = c.id
             JOIN clientes cl ON rp.cliente_id = cl.id
             JOIN tipos_documento td ON rp.tipo_documento_id = td.id
             JOIN documentos d ON rp.documento_id = d.id
             WHERE {$where}
             ORDER BY cl.nome_fantasia, rp.prazo_sla ASC, c.nome_completo"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->exportarXlsx('Pendencias_Reprotocolo', $rows, [
            'cliente'        => 'Cliente',
            'matricula'      => 'Matrícula',
            'colaborador'    => 'Colaborador',
            'tipo_documento' => 'Tipo de Documento',
            'data_emissao'   => 'Emissão',
            'data_validade'  => 'Validade',
            'prazo_sla'      => 'Prazo SLA',
            'dias_para_sla'  => 'Dias até SLA',
        ]);
    }

    // GET /rh/relatorios/historico-colab.xlsx?colab_id=X
    public function historicoPorColaborador(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db = Database::getInstance();
        $colabId = (int)$this->input('colab_id', 0);
        if ($colabId <= 0) {
            $this->flash('error', 'Informe colab_id.');
            $this->redirect('/rh/relatorios');
            return;
        }

        $stmt = $db->prepare(
            "SELECT cl.nome_fantasia AS cliente, td.nome AS tipo_documento,
                    rp.numero_protocolo, rp.protocolado_em, rp.status,
                    rp.enviado_em, rp.confirmado_em, rp.motivo_rejeicao,
                    u.nome AS enviado_por
             FROM rh_protocolos rp
             JOIN clientes cl ON rp.cliente_id = cl.id
             JOIN tipos_documento td ON rp.tipo_documento_id = td.id
             LEFT JOIN usuarios u ON rp.enviado_por = u.id
             WHERE rp.colaborador_id = :c
             ORDER BY rp.criado_em DESC"
        );
        $stmt->execute(['c' => $colabId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $colabNome = $db->prepare("SELECT nome_completo FROM colaboradores WHERE id=:c");
        $colabNome->execute(['c' => $colabId]);
        $nome = preg_replace('/[^A-Za-z0-9_]/', '_', $colabNome->fetchColumn() ?: 'colab');

        $this->exportarXlsx('Historico_Protocolo_' . $nome, $rows, [
            'cliente'          => 'Cliente',
            'tipo_documento'   => 'Tipo',
            'numero_protocolo' => 'Nº Protocolo',
            'protocolado_em'   => 'Data Protocolo',
            'status'           => 'Status',
            'enviado_em'       => 'Enviado em',
            'confirmado_em'    => 'Confirmado em',
            'motivo_rejeicao'  => 'Motivo rejeição',
            'enviado_por'      => 'Por',
        ]);
    }

    // GET /rh/relatorios/conformidade-obra.xlsx
    public function conformidadePorObra(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT cl.nome_fantasia AS cliente, o.nome AS obra,
                    SUM(rp.status='pendente_envio') AS pendentes,
                    SUM(rp.status='enviado')        AS enviados,
                    SUM(rp.status='confirmado')     AS confirmados,
                    SUM(rp.status='rejeitado')      AS rejeitados,
                    COUNT(*) AS total,
                    ROUND(100 * SUM(rp.status='confirmado') / COUNT(*), 1) AS pct_conformidade
             FROM rh_protocolos rp
             JOIN clientes cl ON rp.cliente_id = cl.id
             LEFT JOIN obras o ON rp.obra_id = o.id
             GROUP BY cl.id, o.id
             ORDER BY pct_conformidade ASC, cl.nome_fantasia"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->exportarXlsx('Conformidade_por_Obra', $rows, [
            'cliente'          => 'Cliente',
            'obra'             => 'Obra',
            'pendentes'        => 'Pendentes',
            'enviados'         => 'Enviados',
            'confirmados'      => 'Confirmados',
            'rejeitados'       => 'Rejeitados',
            'total'            => 'Total',
            'pct_conformidade' => '% Conformidade',
        ]);
    }

    private function exportarXlsx(string $nomeArquivo, array $rows, array $colunas): void
    {
        $sheet = new Spreadsheet();
        $ws    = $sheet->getActiveSheet();
        $ws->setTitle(substr($nomeArquivo, 0, 30));

        // Cabeçalho
        $col = 'A';
        foreach ($colunas as $key => $label) {
            $ws->setCellValue($col . '1', $label);
            $ws->getStyle($col . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005E4E']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $col++;
        }

        // Dados
        $linha = 2;
        foreach ($rows as $row) {
            $col = 'A';
            foreach (array_keys($colunas) as $key) {
                $ws->setCellValue($col . $linha, $row[$key] ?? '');
                $col++;
            }
            $linha++;
        }

        // Auto-size
        foreach (range('A', $col) as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }

        $arquivo = $nomeArquivo . '_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($sheet);
        $writer->save('php://output');
        exit;
    }
}
