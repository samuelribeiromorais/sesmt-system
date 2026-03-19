<?php

namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, Font};

class ReportService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gera relatorio Excel de um colaborador (certificados + documentos)
     */
    public function excelColaborador(int $colaboradorId): string
    {
        $colab = $this->db->prepare("SELECT * FROM colaboradores WHERE id = :id")->execute(['id' => $colaboradorId]);
        $stmt = $this->db->prepare("SELECT * FROM colaboradores WHERE id = :id");
        $stmt->execute(['id' => $colaboradorId]);
        $colab = $stmt->fetch();

        if (!$colab) throw new \RuntimeException('Colaborador nao encontrado.');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Colaborador');

        // Header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005E4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Dados do colaborador
        $sheet->setCellValue('A1', 'RELATÓRIO DO COLABORADOR');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '005E4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Nome:')->setCellValue('B3', $colab['nome_completo']);
        $sheet->setCellValue('A4', 'Cargo:')->setCellValue('B4', $colab['cargo'] ?? $colab['funcao'] ?? '-');
        $sheet->setCellValue('A5', 'Status:')->setCellValue('B5', ucfirst($colab['status']));
        $sheet->setCellValue('D3', 'Admissao:')->setCellValue('E3', $colab['data_admissao'] ? date('d/m/Y', strtotime($colab['data_admissao'])) : '-');
        $sheet->getStyle('A3:A5')->getFont()->setBold(true);
        $sheet->getStyle('D3')->getFont()->setBold(true);

        // Certificados
        $row = 7;
        $sheet->setCellValue("A{$row}", 'CERTIFICADOS');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
        $row++;

        $cols = ['A' => 'Tipo', 'B' => 'Duracao', 'C' => 'Realizacao', 'D' => 'Emissao', 'E' => 'Validade', 'F' => 'Status'];
        foreach ($cols as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F0EC');
        $row++;

        $certs = $this->db->prepare(
            "SELECT cert.*, tc.codigo, tc.duracao FROM certificados cert
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE cert.colaborador_id = :cid ORDER BY tc.codigo"
        );
        $certs->execute(['cid' => $colaboradorId]);

        foreach ($certs->fetchAll() as $c) {
            $sheet->setCellValue("A{$row}", $c['codigo']);
            $sheet->setCellValue("B{$row}", $c['duracao']);
            $sheet->setCellValue("C{$row}", date('d/m/Y', strtotime($c['data_realizacao'])));
            $sheet->setCellValue("D{$row}", date('d/m/Y', strtotime($c['data_emissao'])));
            $sheet->setCellValue("E{$row}", date('d/m/Y', strtotime($c['data_validade'])));
            $sheet->setCellValue("F{$row}", ucfirst(str_replace('_', ' ', $c['status'])));

            if ($c['status'] === 'vencido') {
                $sheet->getStyle("F{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFE74C3C'));
            } elseif ($c['status'] === 'proximo_vencimento') {
                $sheet->getStyle("F{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFF39C12'));
            }
            $row++;
        }

        // Documentos
        $row += 2;
        $sheet->setCellValue("A{$row}", 'DOCUMENTOS');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
        $row++;

        $cols2 = ['A' => 'Tipo', 'B' => 'Categoria', 'C' => 'Emissao', 'D' => 'Validade', 'E' => 'Status', 'F' => 'Arquivo'];
        foreach ($cols2 as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F0EC');
        $row++;

        $docs = $this->db->prepare(
            "SELECT d.*, td.nome as tipo_nome, td.categoria FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.colaborador_id = :cid AND d.status != 'obsoleto' ORDER BY td.categoria, td.nome"
        );
        $docs->execute(['cid' => $colaboradorId]);

        foreach ($docs->fetchAll() as $d) {
            $sheet->setCellValue("A{$row}", $d['tipo_nome']);
            $sheet->setCellValue("B{$row}", strtoupper($d['categoria']));
            $sheet->setCellValue("C{$row}", date('d/m/Y', strtotime($d['data_emissao'])));
            $sheet->setCellValue("D{$row}", $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : 'N/A');
            $sheet->setCellValue("E{$row}", ucfirst(str_replace('_', ' ', $d['status'])));
            $sheet->setCellValue("F{$row}", $d['arquivo_nome']);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Gera arquivo
        $config = require dirname(__DIR__) . '/config/app.php';
        $fileName = 'Relatorio_' . preg_replace('/[^a-zA-Z0-9]/', '_', $colab['nome_completo']) . '_' . date('Y-m-d') . '.xlsx';
        $filePath = $config['upload']['path'] . '/../reports/' . $fileName;

        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Gera relatorio Excel de conformidade por cliente
     */
    public function excelCliente(int $clienteId): string
    {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute(['id' => $clienteId]);
        $cliente = $stmt->fetch();
        if (!$cliente) throw new \RuntimeException('Cliente nao encontrado.');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conformidade');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005E4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $sheet->setCellValue('A1', 'RELATÓRIO DE CONFORMIDADE - ' . ($cliente['nome_fantasia'] ?? $cliente['razao_social']));
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '005E4E']],
        ]);

        $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i'));

        $row = 4;
        $headers = ['Colaborador', 'Cargo', 'Status', 'ASO', 'EPI', 'O.S.', 'Treinamentos', 'Conformidade'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($headerStyle);
        $row++;

        $colabs = $this->db->prepare(
            "SELECT * FROM colaboradores WHERE cliente_id = :cid AND status = 'ativo' ORDER BY nome_completo"
        );
        $colabs->execute(['cid' => $clienteId]);

        foreach ($colabs->fetchAll() as $c) {
            $docStatus = $this->getDocStatusResumo($c['id']);

            $sheet->setCellValue("A{$row}", $c['nome_completo']);
            $sheet->setCellValue("B{$row}", $c['cargo'] ?? $c['funcao'] ?? '-');
            $sheet->setCellValue("C{$row}", ucfirst($c['status']));
            $sheet->setCellValue("D{$row}", $docStatus['aso']);
            $sheet->setCellValue("E{$row}", $docStatus['epi']);
            $sheet->setCellValue("F{$row}", $docStatus['os']);
            $sheet->setCellValue("G{$row}", $docStatus['treinamentos']);
            $sheet->setCellValue("H{$row}", $docStatus['conformidade']);

            // Colorir conformidade
            $corConf = match($docStatus['conformidade']) {
                'OK' => 'AEF085',
                'PARCIAL' => 'FFE082',
                default => 'FFAAAA',
            };
            $sheet->getStyle("H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($corConf);
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $config = require dirname(__DIR__) . '/config/app.php';
        $fileName = 'Conformidade_' . preg_replace('/[^a-zA-Z0-9]/', '_', $cliente['nome_fantasia'] ?? $cliente['razao_social']) . '_' . date('Y-m-d') . '.xlsx';
        $filePath = $config['upload']['path'] . '/../reports/' . $fileName;
        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    private function getDocStatusResumo(int $colaboradorId): array
    {
        $categorias = ['aso', 'epi', 'os', 'treinamento'];
        $result = ['conformidade' => 'OK'];

        foreach ($categorias as $cat) {
            $stmt = $this->db->prepare(
                "SELECT d.status FROM documentos d
                 JOIN tipos_documento td ON d.tipo_documento_id = td.id
                 WHERE d.colaborador_id = :cid AND td.categoria = :cat AND d.status != 'obsoleto'
                 ORDER BY d.data_emissao DESC LIMIT 1"
            );
            $stmt->execute(['cid' => $colaboradorId, 'cat' => $cat]);
            $doc = $stmt->fetch();

            if (!$doc) {
                $key = $cat === 'treinamento' ? 'treinamentos' : $cat;
                $result[$key] = 'FALTANTE';
                $result['conformidade'] = 'PENDENTE';
            } elseif ($doc['status'] === 'vencido') {
                $key = $cat === 'treinamento' ? 'treinamentos' : $cat;
                $result[$key] = 'VENCIDO';
                $result['conformidade'] = 'PENDENTE';
            } elseif ($doc['status'] === 'proximo_vencimento') {
                $key = $cat === 'treinamento' ? 'treinamentos' : $cat;
                $result[$key] = 'VENCENDO';
                if ($result['conformidade'] === 'OK') $result['conformidade'] = 'PARCIAL';
            } else {
                $key = $cat === 'treinamento' ? 'treinamentos' : $cat;
                $result[$key] = 'OK';
            }
        }

        return $result;
    }
}
