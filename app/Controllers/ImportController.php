<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Services\CryptoService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    private array $templateHeaders = [
        'nome_completo',
        'cpf',
        'matricula',
        'cargo',
        'função',
        'setor',
        'cliente_id',
        'obra_id',
        'data_admissao',
        'status',
        'data_nascimento',
        'telefone',
        'email',
    ];

    public function form(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $this->view('importar/form', [
            'pageTitle' => 'Importar Colaboradores',
        ]);
    }

    public function preview(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Selecione um arquivo XLSX válido.');
            $this->redirect('/importar/colaboradores');
            return;
        }

        $file = $_FILES['arquivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls'])) {
            $this->flash('error', 'Formato inválido. Use arquivos .xlsx ou .xls.');
            $this->redirect('/importar/colaboradores');
            return;
        }

        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao ler arquivo: ' . $e->getMessage());
            $this->redirect('/importar/colaboradores');
            return;
        }

        if (count($rows) < 2) {
            $this->flash('error', 'Arquivo vazio ou sem dados além do cabecalho.');
            $this->redirect('/importar/colaboradores');
            return;
        }

        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $dataRows = array_slice($rows, 1);

        // Validate and map rows
        $previewRows = [];
        $errors = [];
        $colabModel = new Colaborador();

        foreach ($dataRows as $i => $row) {
            $rowNum = $i + 2; // Excel row number (1-based + header)
            $mapped = [];

            foreach ($this->templateHeaders as $idx => $field) {
                $colIdx = array_search($field, $headers);
                $mapped[$field] = $colIdx !== false ? trim($row[$colIdx] ?? '') : '';
            }

            $rowErrors = [];

            if (empty($mapped['nome_completo'])) {
                $rowErrors[] = 'Nome obrigatório';
            }

            if (!empty($mapped['cpf'])) {
                $cpfClean = preg_replace('/\D/', '', $mapped['cpf']);
                if (strlen($cpfClean) !== 11) {
                    $rowErrors[] = 'CPF inválido';
                } else {
                    // Check duplicate
                    $cpfHash = CryptoService::hash($cpfClean);
                    $existing = $colabModel->query(
                        "SELECT id FROM colaboradores WHERE cpf_hash = :hash LIMIT 1",
                        ['hash' => $cpfHash]
                    );
                    if (!empty($existing)) {
                        $rowErrors[] = 'CPF ja cadastrado';
                    }
                }
            }

            $mapped['_row_num'] = $rowNum;
            $mapped['_errors'] = $rowErrors;
            $previewRows[] = $mapped;
        }

        // Save temp file for executar step
        $tmpPath = sys_get_temp_dir() . '/sesmt_import_' . session_id() . '.xlsx';
        copy($file['tmp_name'], $tmpPath);
        Session::set('import_tmp_file', $tmpPath);
        Session::set('import_total_rows', count($dataRows));

        $this->view('importar/preview', [
            'pageTitle'   => 'Preview da Importação',
            'headers'     => $this->templateHeaders,
            'rows'        => array_slice($previewRows, 0, 20),
            'totalRows'   => count($dataRows),
            'errorCount'  => count(array_filter($previewRows, fn($r) => !empty($r['_errors']))),
        ]);
    }

    public function executar(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $tmpPath = Session::get('import_tmp_file');
        if (!$tmpPath || !file_exists($tmpPath)) {
            $this->flash('error', 'Sessão de importação expirada. Envie o arquivo novamente.');
            $this->redirect('/importar/colaboradores');
            return;
        }

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao ler arquivo temporario.');
            $this->redirect('/importar/colaboradores');
            return;
        }

        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $dataRows = array_slice($rows, 1);

        $colabModel = new Colaborador();
        $successes = 0;
        $errorsList = [];

        foreach ($dataRows as $i => $row) {
            $rowNum = $i + 2;
            $mapped = [];

            foreach ($this->templateHeaders as $field) {
                $colIdx = array_search($field, $headers);
                $mapped[$field] = $colIdx !== false ? trim($row[$colIdx] ?? '') : '';
            }

            // Validate required
            if (empty($mapped['nome_completo'])) {
                $errorsList[] = "Linha {$rowNum}: Nome obrigatório.";
                continue;
            }

            // Prepare data
            $cpfClean = preg_replace('/\D/', '', $mapped['cpf'] ?? '');
            $data = [
                'nome_completo'  => $mapped['nome_completo'],
                'cpf_encrypted'  => $cpfClean ? CryptoService::encrypt($cpfClean) : null,
                'cpf_hash'       => $cpfClean ? CryptoService::hash($cpfClean) : null,
                'matricula'      => $mapped['matricula'] ?: null,
                'cargo'          => $mapped['cargo'] ?: null,
                'funcao' => $mapped['funcao'] ?: null,
                'setor'          => $mapped['setor'] ?: null,
                'cliente_id'     => $mapped['cliente_id'] ?: null,
                'obra_id'        => $mapped['obra_id'] ?: null,
                'data_admissao'  => $mapped['data_admissao'] ?: null,
                'status'         => $mapped['status'] ?: 'ativo',
                'data_nascimento'=> $mapped['data_nascimento'] ?: null,
                'telefone'       => $mapped['telefone'] ?: null,
                'email'          => $mapped['email'] ?: null,
            ];

            // Check duplicate CPF
            if ($cpfClean) {
                $existing = $colabModel->query(
                    "SELECT id FROM colaboradores WHERE cpf_hash = :hash LIMIT 1",
                    ['hash' => $data['cpf_hash']]
                );
                if (!empty($existing)) {
                    $errorsList[] = "Linha {$rowNum}: CPF ja cadastrado ({$mapped['nome_completo']}).";
                    continue;
                }
            }

            try {
                $colabModel->create($data);
                $successes++;
            } catch (\Exception $e) {
                $errorsList[] = "Linha {$rowNum}: Erro ao salvar - {$e->getMessage()}";
            }
        }

        // Cleanup
        @unlink($tmpPath);
        Session::remove('import_tmp_file');
        Session::remove('import_total_rows');

        LoggerMiddleware::log('importar', "Importação de colaboradores: {$successes} criados, " . count($errorsList) . " erros.");

        $message = "{$successes} colaborador(es) importado(s) com sucesso.";
        if (!empty($errorsList)) {
            $message .= " " . count($errorsList) . " erro(s): " . implode(' | ', array_slice($errorsList, 0, 5));
            if (count($errorsList) > 5) {
                $message .= ' ...e mais ' . (count($errorsList) - 5) . ' erro(s).';
            }
        }

        $this->flash($successes > 0 ? 'success' : 'error', $message);
        $this->redirect('/colaboradores');
    }

    public function templateDownload(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Colaboradores');

        foreach ($this->templateHeaders as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
            $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
        ];
        $lastCol = count($this->templateHeaders);
        $sheet->getStyle([1, 1, $lastCol, 1])->applyFromArray($headerStyle);

        // Example row
        $example = [
            'Joao da Silva', '12345678901', 'MAT001', 'Eletricista',
            'Eletricista Industrial', 'Produção', '1', '1',
            '2024-01-15', 'ativo', '1990-05-20', '11999998888', 'joao@email.com',
        ];
        foreach ($example as $col => $value) {
            $sheet->setCellValue([$col + 1, 2], $value);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_colaboradores.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
