<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Services\CryptoService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UploadLinkController extends Controller
{
    /**
     * Listar links de upload (admin)
     */
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $db = Database::getInstance();

        $links = $db->query(
            "SELECT ul.*, u.nome as criado_por_nome
             FROM upload_links ul
             JOIN usuarios u ON ul.criado_por = u.id
             ORDER BY ul.criado_em DESC"
        )->fetchAll();

        $this->view('upload-links/index', [
            'links' => $links,
            'pageTitle' => 'Links de Upload Externo',
        ]);
    }

    /**
     * Gerar novo link de upload
     */
    public function gerar(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $descricao = trim($this->input('descricao', 'Upload de dados de colaboradores'));
        $diasValidade = max(1, min(30, (int)$this->input('dias_validade', 7)));

        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', strtotime("+{$diasValidade} days"));

        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO upload_links (token, descricao, criado_por, expira_em)
             VALUES (:token, :desc, :uid, :exp)"
        )->execute([
            'token' => $token,
            'desc'  => $descricao,
            'uid'   => Session::get('user_id'),
            'exp'   => $expiraEm,
        ]);

        $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
        $link = $baseUrl . '/upload-externo/' . $token;

        LoggerMiddleware::log('upload_link', "Link de upload externo gerado. Expira em: {$expiraEm}");

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "Link gerado com sucesso! Copie e envie ao colega: {$link}"
        ];
        $_SESSION['ultimo_link_gerado'] = $link;

        header('Location: /upload-links');
        exit;
    }

    /**
     * Revogar link
     */
    public function revogar(int $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $db = Database::getInstance();
        $db->prepare("UPDATE upload_links SET ativo = 0 WHERE id = :id")->execute(['id' => $id]);

        LoggerMiddleware::log('upload_link', "Link de upload #{$id} revogado.");

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Link revogado com sucesso.'];
        header('Location: /upload-links');
        exit;
    }

    /**
     * Importar dados a partir de link/URL/caminho de rede
     */
    public function importarLink(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $linkArquivo = trim($this->input('link_arquivo', ''));

        if (empty($linkArquivo)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Informe o link ou caminho do arquivo.'];
            header('Location: /upload-links');
            exit;
        }

        // Determinar tipo de caminho e buscar o arquivo
        $tempFile = null;
        $nomeArquivo = basename($linkArquivo);

        try {
            if (preg_match('#^https?://#i', $linkArquivo)) {
                // URL HTTP/HTTPS — download via cURL
                $tempFile = tempnam(sys_get_temp_dir(), 'sesmt_import_');
                $ch = curl_init($linkArquivo);
                $fp = fopen($tempFile, 'w');
                curl_setopt_array($ch, [
                    CURLOPT_FILE => $fp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'SESMT-System/1.0',
                ]);
                $success = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                fclose($fp);

                if (!$success || $httpCode >= 400) {
                    @unlink($tempFile);
                    throw new \Exception("Erro ao baixar arquivo (HTTP {$httpCode}): {$error}");
                }
            } elseif (str_starts_with($linkArquivo, '\\\\') || preg_match('#^[A-Za-z]:\\\\#', $linkArquivo)) {
                // Caminho de rede Windows (\\servidor\pasta\arquivo) ou caminho local (C:\...)
                // Normalizar barras
                $path = str_replace('/', '\\', $linkArquivo);
                if (!file_exists($path)) {
                    throw new \Exception("Arquivo nao encontrado: {$path}");
                }
                $tempFile = tempnam(sys_get_temp_dir(), 'sesmt_import_');
                if (!copy($path, $tempFile)) {
                    throw new \Exception("Nao foi possivel copiar o arquivo de: {$path}");
                }
            } elseif (str_starts_with($linkArquivo, '/')) {
                // Caminho Linux absoluto
                if (!file_exists($linkArquivo)) {
                    throw new \Exception("Arquivo nao encontrado: {$linkArquivo}");
                }
                $tempFile = tempnam(sys_get_temp_dir(), 'sesmt_import_');
                copy($linkArquivo, $tempFile);
            } else {
                throw new \Exception("Formato de caminho nao reconhecido. Use URL (http://...), caminho de rede (\\\\servidor\\...) ou caminho absoluto.");
            }

            // Detectar extensao
            $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                // Tentar detectar pelo conteudo
                $firstBytes = file_get_contents($tempFile, false, null, 0, 4);
                if (str_contains($firstBytes, ',') || str_contains($firstBytes, ';')) {
                    $ext = 'csv';
                } else {
                    $ext = 'xlsx'; // Tentar como Excel
                }
            }

            // Parse
            if ($ext === 'csv') {
                $rows = $this->parseCsv($tempFile);
            } else {
                $spreadsheet = IOFactory::load($tempFile);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            }

            @unlink($tempFile);

        } catch (\Exception $e) {
            if ($tempFile) @unlink($tempFile);
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Erro: ' . $e->getMessage()];
            header('Location: /upload-links');
            exit;
        }

        if (count($rows) < 2) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Arquivo vazio ou sem dados.'];
            header('Location: /upload-links');
            exit;
        }

        $resultado = $this->cruzarDados($rows);

        LoggerMiddleware::log('upload_link_remoto', sprintf(
            'Importacao via link: %s — %d atualizados, %d novos, %d ignorados',
            $nomeArquivo, $resultado['atualizados'], $resultado['novos'], $resultado['ignorados']
        ));

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => sprintf(
                'Arquivo "%s" importado! %d atualizados, %d novos, %d ignorados.',
                $nomeArquivo, $resultado['atualizados'], $resultado['novos'], $resultado['ignorados']
            )
        ];
        $_SESSION['ultimo_resultado_upload'] = $resultado;

        header('Location: /upload-links');
        exit;
    }

    /**
     * Upload direto pelo admin (com autenticacao)
     */
    public function uploadDireto(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Erro no upload do arquivo.'];
            header('Location: /upload-links');
            exit;
        }

        $file = $_FILES['arquivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Formato invalido. Use .xlsx, .xls ou .csv'];
            header('Location: /upload-links');
            exit;
        }

        try {
            if ($ext === 'csv') {
                $rows = $this->parseCsv($file['tmp_name']);
            } else {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            }
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Erro ao ler arquivo: ' . $e->getMessage()];
            header('Location: /upload-links');
            exit;
        }

        if (count($rows) < 2) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Arquivo vazio ou sem dados.'];
            header('Location: /upload-links');
            exit;
        }

        $resultado = $this->cruzarDados($rows);

        LoggerMiddleware::log('upload_direto', sprintf(
            'Upload direto por admin: %s — %d atualizados, %d novos, %d ignorados',
            $file['name'], $resultado['atualizados'], $resultado['novos'], $resultado['ignorados']
        ));

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => sprintf(
                'Arquivo "%s" processado! %d atualizados, %d novos, %d ignorados.',
                $file['name'], $resultado['atualizados'], $resultado['novos'], $resultado['ignorados']
            )
        ];
        $_SESSION['ultimo_resultado_upload'] = $resultado;

        header('Location: /upload-links');
        exit;
    }

    /**
     * Pagina publica de upload (sem autenticacao)
     */
    public function paginaUpload(string $token): void
    {
        $db = Database::getInstance();
        $link = $db->prepare(
            "SELECT * FROM upload_links WHERE token = :token"
        );
        $link->execute(['token' => $token]);
        $link = $link->fetch();

        if (!$link || !$link['ativo'] || strtotime($link['expira_em']) < time()) {
            http_response_code(404);
            echo $this->renderPublicPage('Link Invalido',
                '<div style="text-align:center; padding:60px 20px;">
                    <h1 style="color:#e74c3c; font-size:48px; margin-bottom:16px;">Link Invalido</h1>
                    <p style="color:#6b7280; font-size:16px;">Este link expirou ou foi revogado.</p>
                    <p style="color:#6b7280; font-size:14px; margin-top:12px;">Solicite um novo link ao administrador do sistema.</p>
                </div>');
            exit;
        }

        if ($link['usado_em']) {
            echo $this->renderPublicPage('Upload Ja Realizado',
                '<div style="text-align:center; padding:60px 20px;">
                    <h1 style="color:#f39c12; font-size:36px; margin-bottom:16px;">Upload Ja Realizado</h1>
                    <p style="color:#6b7280; font-size:16px;">Este link ja foi utilizado em ' . date('d/m/Y H:i', strtotime($link['usado_em'])) . '.</p>
                    <p style="color:#6b7280; font-size:14px; margin-top:12px;">Caso precise enviar novamente, solicite um novo link.</p>
                </div>');
            exit;
        }

        echo $this->renderPublicPage('Upload de Dados - TSE Engenharia',
            '<div style="max-width:600px; margin:0 auto; padding:40px 20px;">
                <div style="text-align:center; margin-bottom:32px;">
                    <h1 style="color:#005e4e; font-size:24px; margin-bottom:8px;">Upload de Dados de Colaboradores</h1>
                    <p style="color:#6b7280; font-size:14px;">' . htmlspecialchars($link['descricao']) . '</p>
                    <p style="color:#6b7280; font-size:12px; margin-top:4px;">Link valido ate: ' . date('d/m/Y H:i', strtotime($link['expira_em'])) . '</p>
                </div>

                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:24px; font-size:13px; color:#166534;">
                    <strong>Formato aceito:</strong> Planilha Excel (.xlsx, .xls) ou CSV (.csv)<br>
                    <strong>Colunas esperadas:</strong> nome_completo, cpf, matricula, cargo, funcao, setor, unidade, data_admissao, data_nascimento, telefone, email<br>
                    <strong>CPF:</strong> Usado como chave para cruzamento. Dados existentes serao atualizados automaticamente.
                </div>

                <form method="POST" action="/upload-externo/' . $token . '/enviar" enctype="multipart/form-data" id="uploadForm">
                    <div style="border:2px dashed #d1d5db; border-radius:12px; padding:40px; text-align:center; cursor:pointer; transition:border-color 0.2s;" id="dropZone" onclick="document.getElementById(\'arquivo\').click()">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="margin-bottom:12px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p style="color:#374151; font-size:16px; margin-bottom:4px;" id="dropText">Clique ou arraste o arquivo aqui</p>
                        <p style="color:#9ca3af; font-size:13px;">.xlsx, .xls ou .csv (max 10MB)</p>
                        <input type="file" name="arquivo" id="arquivo" accept=".xlsx,.xls,.csv" style="display:none" required>
                    </div>

                    <button type="submit" id="btnEnviar" disabled style="width:100%; margin-top:20px; padding:14px; background:#005e4e; color:white; border:none; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; opacity:0.5; transition:opacity 0.2s;">
                        Enviar Dados
                    </button>
                </form>

                <div id="resultado" style="display:none; margin-top:24px;"></div>
            </div>
            <script>
            const dropZone = document.getElementById("dropZone");
            const fileInput = document.getElementById("arquivo");
            const btnEnviar = document.getElementById("btnEnviar");
            const dropText = document.getElementById("dropText");

            fileInput.addEventListener("change", function() {
                if (this.files.length > 0) {
                    dropText.textContent = this.files[0].name;
                    dropZone.style.borderColor = "#005e4e";
                    btnEnviar.disabled = false;
                    btnEnviar.style.opacity = "1";
                }
            });

            dropZone.addEventListener("dragover", function(e) {
                e.preventDefault();
                this.style.borderColor = "#005e4e";
                this.style.background = "#f0fdf4";
            });
            dropZone.addEventListener("dragleave", function(e) {
                this.style.borderColor = "#d1d5db";
                this.style.background = "transparent";
            });
            dropZone.addEventListener("drop", function(e) {
                e.preventDefault();
                this.style.borderColor = "#005e4e";
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event("change"));
            });

            document.getElementById("uploadForm").addEventListener("submit", function() {
                btnEnviar.disabled = true;
                btnEnviar.textContent = "Processando...";
                btnEnviar.style.opacity = "0.6";
            });
            </script>');
        exit;
    }

    /**
     * Processar upload externo (sem autenticacao)
     */
    public function processarUpload(string $token): void
    {
        $db = Database::getInstance();
        $link = $db->prepare("SELECT * FROM upload_links WHERE token = :token");
        $link->execute(['token' => $token]);
        $link = $link->fetch();

        if (!$link || !$link['ativo'] || $link['usado_em'] || strtotime($link['expira_em']) < time()) {
            http_response_code(403);
            echo $this->renderPublicPage('Erro', '<div style="text-align:center;padding:60px;"><h1 style="color:#e74c3c;">Link invalido ou expirado.</h1></div>');
            exit;
        }

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            echo $this->renderPublicPage('Erro', '<div style="text-align:center;padding:60px;"><h1 style="color:#e74c3c;">Erro no upload. Tente novamente.</h1></div>');
            exit;
        }

        $file = $_FILES['arquivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            echo $this->renderPublicPage('Erro', '<div style="text-align:center;padding:60px;"><h1 style="color:#e74c3c;">Formato invalido. Use .xlsx, .xls ou .csv</h1></div>');
            exit;
        }

        // Parse file
        try {
            if ($ext === 'csv') {
                $rows = $this->parseCsv($file['tmp_name']);
            } else {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            }
        } catch (\Exception $e) {
            echo $this->renderPublicPage('Erro', '<div style="text-align:center;padding:60px;"><h1 style="color:#e74c3c;">Erro ao ler arquivo.</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></div>');
            exit;
        }

        if (count($rows) < 2) {
            echo $this->renderPublicPage('Erro', '<div style="text-align:center;padding:60px;"><h1 style="color:#e74c3c;">Arquivo vazio ou sem dados.</h1></div>');
            exit;
        }

        // Process data
        $resultado = $this->cruzarDados($rows);

        // Mark link as used
        $db->prepare(
            "UPDATE upload_links SET usado_em = NOW(), arquivo_nome = :nome, resultado = :res WHERE id = :id"
        )->execute([
            'nome' => $file['name'],
            'res'  => json_encode($resultado, JSON_UNESCAPED_UNICODE),
            'id'   => $link['id'],
        ]);

        // Show result
        $html = '<div style="max-width:700px; margin:0 auto; padding:40px 20px;">';
        $html .= '<div style="text-align:center; margin-bottom:32px;">';
        $html .= '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#00b279" stroke-width="2" style="margin-bottom:12px;"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        $html .= '<h1 style="color:#005e4e; font-size:24px;">Upload Processado com Sucesso!</h1>';
        $html .= '</div>';

        $html .= '<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:24px;">';
        $html .= '<div style="background:#f0fdf4; border-radius:8px; padding:16px; text-align:center;"><div style="font-size:28px; font-weight:bold; color:#00b279;">' . $resultado['atualizados'] . '</div><div style="font-size:12px; color:#166534;">Atualizados</div></div>';
        $html .= '<div style="background:#eff6ff; border-radius:8px; padding:16px; text-align:center;"><div style="font-size:28px; font-weight:bold; color:#2563eb;">' . $resultado['novos'] . '</div><div style="font-size:12px; color:#1e40af;">Novos</div></div>';
        $html .= '<div style="background:#fef3c7; border-radius:8px; padding:16px; text-align:center;"><div style="font-size:28px; font-weight:bold; color:#d97706;">' . $resultado['ignorados'] . '</div><div style="font-size:12px; color:#92400e;">Sem CPF/Ignorados</div></div>';
        $html .= '</div>';

        if (!empty($resultado['detalhes'])) {
            $html .= '<div style="background:#f9fafb; border-radius:8px; padding:16px; max-height:300px; overflow-y:auto; font-size:13px;">';
            $html .= '<h3 style="margin:0 0 12px; font-size:14px; color:#374151;">Detalhes do processamento:</h3>';
            foreach (array_slice($resultado['detalhes'], 0, 50) as $d) {
                $cor = $d['acao'] === 'atualizado' ? '#00b279' : ($d['acao'] === 'criado' ? '#2563eb' : '#d97706');
                $html .= '<div style="padding:4px 0; border-bottom:1px solid #e5e7eb;"><span style="color:' . $cor . '; font-weight:600;">[' . strtoupper($d['acao']) . ']</span> ' . htmlspecialchars($d['nome']) . (!empty($d['campos']) ? ' - Campos: ' . htmlspecialchars($d['campos']) : '') . '</div>';
            }
            if (count($resultado['detalhes']) > 50) {
                $html .= '<div style="padding:8px 0; color:#6b7280;">...e mais ' . (count($resultado['detalhes']) - 50) . ' registro(s)</div>';
            }
            $html .= '</div>';
        }

        $html .= '<p style="text-align:center; margin-top:24px; color:#6b7280; font-size:13px;">Os dados foram processados automaticamente. O administrador do sistema sera notificado.</p>';
        $html .= '</div>';

        echo $this->renderPublicPage('Upload Concluido - TSE Engenharia', $html);
        exit;
    }

    /**
     * Cruzar dados recebidos com o banco
     */
    private function cruzarDados(array $rows): array
    {
        $headers = array_map(function($h) {
            $h = strtolower(trim($h ?? ''));
            $h = str_replace(['ç', 'ã', 'á', 'é', 'ú'], ['c', 'a', 'a', 'e', 'u'], $h);
            // Normalize common variations
            $map = [
                'nome' => 'nome_completo', 'nome completo' => 'nome_completo',
                'pe_nome' => 'nome_completo',
                'pe_cpf' => 'cpf',
                'codigo' => 'matricula',
                'ctr_dataadmissao' => 'data_admissao',
                'ctr_datarescisao' => 'data_demissao',
                'pe_cidade' => 'cidade',
                'pe_uf' => 'uf',
                'ctr_centrocusto4' => 'centro_custo',
                'matricula' => 'matricula', 'matrícula' => 'matricula',
                'funcao' => 'funcao', 'função' => 'funcao',
                'data admissao' => 'data_admissao', 'data_admissao' => 'data_admissao',
                'admissao' => 'data_admissao', 'dt admissao' => 'data_admissao',
                'data nascimento' => 'data_nascimento', 'data_nascimento' => 'data_nascimento',
                'nascimento' => 'data_nascimento', 'dt nascimento' => 'data_nascimento',
                'e-mail' => 'email', 'email' => 'email',
                'tel' => 'telefone', 'telefone' => 'telefone', 'celular' => 'telefone',
            ];
            return $map[$h] ?? $h;
        }, $rows[0]);

        $dataRows = array_slice($rows, 1);
        $colabModel = new Colaborador();
        $db = Database::getInstance();

        $resultado = ['atualizados' => 0, 'novos' => 0, 'ignorados' => 0, 'detalhes' => []];

        foreach ($dataRows as $row) {
            $mapped = [];
            foreach ($headers as $idx => $field) {
                if (isset($row[$idx])) {
                    $mapped[$field] = trim($row[$idx] ?? '');
                }
            }

            $nome = $mapped['nome_completo'] ?? '';
            if (empty($nome)) continue;

            $cpfRaw = preg_replace('/\D/', '', $mapped['cpf'] ?? '');

            // Compor unidade a partir de cidade/uf se nao houver unidade direta
            if (empty($mapped['unidade']) && (!empty($mapped['cidade']) || !empty($mapped['uf']))) {
                $cidade = $mapped['cidade'] ?? '';
                $uf = $mapped['uf'] ?? '';
                $mapped['unidade'] = $cidade && $uf ? "{$cidade}/{$uf}" : ($cidade ?: $uf);
            }

            // Normalizar datas
            foreach (['data_admissao', 'data_nascimento', 'data_demissao'] as $dateField) {
                if (!empty($mapped[$dateField])) {
                    $mapped[$dateField] = $this->parseDate($mapped[$dateField]);
                }
            }

            if (strlen($cpfRaw) === 11) {
                // Tentar encontrar por CPF
                $cpfHash = CryptoService::hash($cpfRaw);
                $existing = $db->prepare(
                    "SELECT id, nome_completo FROM colaboradores WHERE cpf_hash = :hash AND excluido_em IS NULL LIMIT 1"
                );
                $existing->execute(['hash' => $cpfHash]);
                $existing = $existing->fetch();

                if ($existing) {
                    // ATUALIZAR campos vazios/diferentes
                    $updates = [];
                    $camposAtualizados = [];

                    $fieldMap = [
                        'matricula' => 'matricula',
                        'cargo' => 'cargo',
                        'funcao' => 'funcao',
                        'setor' => 'setor',
                        'unidade' => 'unidade',
                        'data_admissao' => 'data_admissao',
                        'data_nascimento' => 'data_nascimento',
                        'data_demissao' => 'data_demissao',
                        'telefone' => 'telefone',
                        'email' => 'email',
                    ];

                    // Buscar dados atuais
                    $current = $colabModel->find($existing['id']);

                    foreach ($fieldMap as $csvField => $dbField) {
                        $newVal = $mapped[$csvField] ?? '';
                        $curVal = $current[$dbField] ?? '';

                        if (!empty($newVal) && $newVal !== $curVal) {
                            $updates[$dbField] = $newVal;
                            $camposAtualizados[] = $dbField;
                        }
                    }

                    if (!empty($updates)) {
                        $updates['atualizado_em'] = date('Y-m-d H:i:s');
                        $colabModel->update($existing['id'], $updates);
                        $resultado['atualizados']++;
                        $resultado['detalhes'][] = [
                            'acao' => 'atualizado',
                            'nome' => $nome,
                            'campos' => implode(', ', $camposAtualizados),
                        ];
                    } else {
                        $resultado['ignorados']++;
                        $resultado['detalhes'][] = [
                            'acao' => 'sem_alteracao',
                            'nome' => $nome,
                            'campos' => '',
                        ];
                    }
                } else {
                    // CRIAR novo colaborador
                    $status = !empty($mapped['data_demissao']) ? 'inativo' : 'ativo';
                    $data = [
                        'nome_completo'   => strtoupper($nome),
                        'cpf_encrypted'   => CryptoService::encrypt($cpfRaw),
                        'cpf_hash'        => $cpfHash,
                        'matricula'       => $mapped['matricula'] ?? null,
                        'cargo'           => $mapped['cargo'] ?? null,
                        'funcao'          => $mapped['funcao'] ?? null,
                        'setor'           => $mapped['setor'] ?? null,
                        'unidade'         => $mapped['unidade'] ?? null,
                        'data_admissao'   => $mapped['data_admissao'] ?? null,
                        'data_demissao'   => $mapped['data_demissao'] ?? null,
                        'data_nascimento' => $mapped['data_nascimento'] ?? null,
                        'telefone'        => $mapped['telefone'] ?? null,
                        'email'           => $mapped['email'] ?? null,
                        'status'          => $status,
                    ];
                    $colabModel->create($data);
                    $resultado['novos']++;
                    $resultado['detalhes'][] = [
                        'acao' => 'criado',
                        'nome' => $nome,
                        'campos' => '',
                    ];
                }
            } else {
                // Sem CPF valido — tentar por nome exato
                $existing = $db->prepare(
                    "SELECT id FROM colaboradores WHERE UPPER(nome_completo) = UPPER(:nome) AND excluido_em IS NULL LIMIT 1"
                );
                $existing->execute(['nome' => $nome]);
                $existing = $existing->fetch();

                if ($existing) {
                    $updates = [];
                    $camposAtualizados = [];
                    $current = $colabModel->find($existing['id']);

                    // Se recebeu CPF parcial ou vazio, tentar atualizar outros campos
                    $fieldMap = ['matricula', 'cargo', 'funcao', 'setor', 'data_admissao', 'data_nascimento', 'telefone', 'email'];
                    foreach ($fieldMap as $field) {
                        $newVal = $mapped[$field] ?? '';
                        $curVal = $current[$field] ?? '';
                        if (!empty($newVal) && $newVal !== $curVal) {
                            $updates[$field] = $newVal;
                            $camposAtualizados[] = $field;
                        }
                    }

                    // Atualizar CPF se veio e nao tinha
                    if (strlen($cpfRaw) === 11 && empty($current['cpf_hash'])) {
                        $updates['cpf_encrypted'] = CryptoService::encrypt($cpfRaw);
                        $updates['cpf_hash'] = CryptoService::hash($cpfRaw);
                        $camposAtualizados[] = 'cpf';
                    }

                    if (!empty($updates)) {
                        $updates['atualizado_em'] = date('Y-m-d H:i:s');
                        $colabModel->update($existing['id'], $updates);
                        $resultado['atualizados']++;
                        $resultado['detalhes'][] = [
                            'acao' => 'atualizado',
                            'nome' => $nome,
                            'campos' => implode(', ', $camposAtualizados),
                        ];
                    } else {
                        $resultado['ignorados']++;
                    }
                } else {
                    $resultado['ignorados']++;
                    $resultado['detalhes'][] = [
                        'acao' => 'ignorado',
                        'nome' => $nome,
                        'campos' => 'CPF ausente/invalido e nome nao encontrado',
                    ];
                }
            }
        }

        // Log
        LoggerMiddleware::log('upload_externo', sprintf(
            'Upload externo processado: %d atualizados, %d novos, %d ignorados',
            $resultado['atualizados'], $resultado['novos'], $resultado['ignorados']
        ));

        // Limpar cache dashboard
        array_map('unlink', glob(dirname(__DIR__, 2) . '/storage/cache/*.cache') ?: []);

        return $resultado;
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Remove BOM from first cell
            if (empty($rows)) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
            }
            $rows[] = $data;
        }
        fclose($handle);
        return $rows;
    }

    private function parseDate(string $date): ?string
    {
        $date = trim($date);
        if (empty($date)) return null;

        // DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        // DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // Excel serial date
        if (is_numeric($date) && (int)$date > 30000) {
            $unix = ((int)$date - 25569) * 86400;
            return date('Y-m-d', $unix);
        }
        return $date;
    }

    private function renderPublicPage(string $title, string $content): string
    {
        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: "Segoe UI", system-ui, -apple-system, sans-serif; background:#f9fafb; color:#1f2937; min-height:100vh; }
        .header { background:#005e4e; color:white; padding:16px 24px; text-align:center; }
        .header h2 { font-size:18px; font-weight:600; }
        .footer { text-align:center; padding:24px; color:#9ca3af; font-size:12px; }
    </style>
</head>
<body>
    <div class="header"><h2>SESMT - TSE Engenharia e Automacao</h2></div>
    ' . $content . '
    <div class="footer">Sistema SESMT &copy; ' . date('Y') . ' TSE Engenharia e Automacao LTDA</div>
</body>
</html>';
    }
}
