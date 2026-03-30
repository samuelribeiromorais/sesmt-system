<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Alignment};

class ExportController extends Controller
{
    public function colaboradores(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $db = Database::getInstance();

        $status = $this->input('status', '');
        $sql = "SELECT c.*, cl.nome_fantasia as cliente_nome, o.nome as obra_nome
                FROM colaboradores c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                LEFT JOIN obras o ON c.obra_id = o.id
                WHERE c.excluido_em IS NULL";
        $params = [];
        if ($status) {
            $sql .= " AND c.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY c.nome_completo";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Colaboradores');

        // Header style (TSE green)
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005E4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Title
        $sheet->setCellValue('A1', 'COLABORADORES - TSE ENGENHARIA');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '005E4E']]]);
        $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i'));

        // Headers
        $headers = ['Nome', 'Matricula', 'Cargo', 'Função', 'Setor', 'Cliente', 'Obra', 'Status'];
        $row = 4;
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($rows as $r) {
            $sheet->setCellValue("A{$row}", $r['nome_completo']);
            $sheet->setCellValue("B{$row}", $r['matricula'] ?? '-');
            $sheet->setCellValue("C{$row}", $r['cargo'] ?? '-');
            $sheet->setCellValue("D{$row}", $r['funcao'] ?? '-');
            $sheet->setCellValue("E{$row}", $r['setor'] ?? '-');
            $sheet->setCellValue("F{$row}", $r['cliente_nome'] ?? '-');
            $sheet->setCellValue("G{$row}", $r['obra_nome'] ?? '-');
            $sheet->setCellValue("H{$row}", ucfirst($r['status']));
            $row++;
        }

        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $this->downloadExcel($spreadsheet, 'Colaboradores_' . date('Y-m-d'));
    }

    public function documentos(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $db = Database::getInstance();

        $sql = "SELECT d.*, c.nome_completo, td.nome as tipo_nome, td.categoria
                FROM documentos d
                JOIN colaboradores c ON d.colaborador_id = c.id
                JOIN tipos_documento td ON d.tipo_documento_id = td.id
                WHERE d.status != 'obsoleto'
                ORDER BY c.nome_completo, td.categoria";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll();

        // Similar pattern: create spreadsheet, add headers [Colaborador, Tipo, Categoria, Emissão, Validade, Status, Arquivo], fill data
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Documentos');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005E4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $sheet->setCellValue('A1', 'DOCUMENTOS - TSE ENGENHARIA');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '005E4E']]]);
        $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i'));

        $headers = ['Colaborador', 'Tipo', 'Categoria', 'Emissão', 'Validade', 'Status', 'Arquivo'];
        $row = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65+$i) . $row, $h);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($rows as $r) {
            $sheet->setCellValue("A{$row}", $r['nome_completo']);
            $sheet->setCellValue("B{$row}", $r['tipo_nome']);
            $sheet->setCellValue("C{$row}", strtoupper($r['categoria']));
            $sheet->setCellValue("D{$row}", date('d/m/Y', strtotime($r['data_emissao'])));
            $sheet->setCellValue("E{$row}", $r['data_validade'] ? date('d/m/Y', strtotime($r['data_validade'])) : 'N/A');
            $sheet->setCellValue("F{$row}", ucfirst(str_replace('_', ' ', $r['status'])));
            $sheet->setCellValue("G{$row}", $r['arquivo_nome']);
            $row++;
        }

        foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $this->downloadExcel($spreadsheet, 'Documentos_' . date('Y-m-d'));
    }

    public function certificados(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $db = Database::getInstance();

        $sql = "SELECT cert.*, c.nome_completo, tc.codigo, tc.duracao
                FROM certificados cert
                JOIN colaboradores c ON cert.colaborador_id = c.id
                JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
                ORDER BY c.nome_completo, tc.codigo";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Certificados');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005E4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $sheet->setCellValue('A1', 'CERTIFICADOS - TSE ENGENHARIA');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '005E4E']]]);
        $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i'));

        $headers = ['Colaborador', 'Tipo', 'Duracao', 'Realizacao', 'Emissão', 'Validade', 'Status'];
        $row = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65+$i) . $row, $h);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($rows as $r) {
            $sheet->setCellValue("A{$row}", $r['nome_completo']);
            $sheet->setCellValue("B{$row}", $r['codigo']);
            $sheet->setCellValue("C{$row}", $r['duracao']);
            $sheet->setCellValue("D{$row}", date('d/m/Y', strtotime($r['data_realizacao'])));
            $sheet->setCellValue("E{$row}", date('d/m/Y', strtotime($r['data_emissao'])));
            $sheet->setCellValue("F{$row}", date('d/m/Y', strtotime($r['data_validade'])));
            $sheet->setCellValue("G{$row}", ucfirst(str_replace('_', ' ', $r['status'])));
            $row++;
        }

        foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $this->downloadExcel($spreadsheet, 'Certificados_' . date('Y-m-d'));
    }

    private function downloadExcel(Spreadsheet $spreadsheet, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
