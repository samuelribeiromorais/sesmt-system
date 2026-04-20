<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Treinamento;
use App\Models\TipoCertificado;
use App\Models\Certificado;
use App\Models\Colaborador;
use App\Models\Ministrante;
use App\Services\CryptoService;

class TreinamentoController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $tipoModel = new TipoCertificado();

        $filters = [
            'tipo_certificado_id' => $this->input('tipo', ''),
            'data_de' => $this->input('data_de', ''),
            'data_ate' => $this->input('data_ate', ''),
            'q' => trim($this->input('q', '')),
        ];
        $filters = array_filter($filters);

        $page = max(1, (int)$this->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $total = $treinModel->countFiltered($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $treinamentos = $treinModel->allWithDetails($perPage, $offset, $filters);

        $contadoresMes = $treinModel->getContadoresMes();
        $totalGeral = $treinModel->count();
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');

        $this->view('treinamentos/index', [
            'treinamentos'   => $treinamentos,
            'tipos'          => $tipos,
            'contadoresMes'  => $contadoresMes,
            'totalGeral'     => $totalGeral,
            'filters'        => $filters,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'pageTitle'      => 'Treinamentos',
        ]);
    }

    public function create(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $tipoModel = new TipoCertificado();
        $ministranteModel = new Ministrante();
        $colabModel = new Colaborador();

        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');
        $colaboradores = $colabModel->all(['status' => 'ativo'], 'nome_completo ASC');

        $this->view('treinamentos/criar', [
            'tipos'          => $tipos,
            'ministrantes'   => $ministrantes,
            'colaboradores'  => $colaboradores,
            'pageTitle'      => 'Treinamentos',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $tipoCertificadoId = (int)$this->input('tipo_certificado_id');
        $ministranteId = (int)$this->input('ministrante_id') ?: null;
        $dataRealizacao = $this->input('data_realizacao');
        $dataRealizacaoFim = $this->input('data_realizacao_fim') ?: null;
        $dataEmissao = $this->input('data_emissao');
        $observacoes = trim($this->input('observacoes', ''));
        $colaboradorIds = $this->input('colaborador_ids');

        if (!$tipoCertificadoId || !$dataRealizacao || !$dataEmissao || empty($colaboradorIds)) {
            $this->flash('error', 'Preencha todos os campos e selecione ao menos um colaborador.');
            $this->redirect('/treinamentos/novo');
            return;
        }

        if (!is_array($colaboradorIds)) {
            $colaboradorIds = [$colaboradorIds];
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find($tipoCertificadoId);
        if (!$tipo) {
            $this->flash('error', 'Tipo de certificado inválido.');
            $this->redirect('/treinamentos/novo');
            return;
        }

        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));
        $status = 'vigente';
        $daysLeft = (strtotime($dataValidade) - time()) / 86400;
        if ($daysLeft < 0) $status = 'vencido';
        elseif ($daysLeft <= 30) $status = 'proximo_vencimento';

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $treinModel = new Treinamento();
            $treinamentoId = $treinModel->create([
                'tipo_certificado_id' => $tipoCertificadoId,
                'ministrante_id'      => $ministranteId,
                'data_realizacao'     => $dataRealizacao,
                'data_realizacao_fim' => $dataRealizacaoFim,
                'data_emissao'        => $dataEmissao,
                'observacoes'         => $observacoes ?: null,
                'total_participantes' => count($colaboradorIds),
                'criado_por'          => Session::get('user_id'),
            ]);

            $certModel = new Certificado();
            foreach ($colaboradorIds as $colabId) {
                $certData = [
                    'colaborador_id'      => (int)$colabId,
                    'tipo_certificado_id' => $tipoCertificadoId,
                    'data_realizacao'     => $dataRealizacao,
                    'data_emissao'        => $dataEmissao,
                    'data_validade'       => $dataValidade,
                    'status'              => $status,
                    'treinamento_id'      => $treinamentoId,
                    'criado_por'          => Session::get('user_id'),
                ];
                if ($dataRealizacaoFim) {
                    $certData['data_realizacao_fim'] = $dataRealizacaoFim;
                }
                if ($ministranteId) {
                    $certData['ministrante_id'] = $ministranteId;
                }
                $certModel->create($certData);
            }

            $db->commit();

            LoggerMiddleware::log('criar', "Treinamento em massa: {$tipo['codigo']} para " . count($colaboradorIds) . " colaboradores (ID: {$treinamentoId})");

            $this->flash('success', "Treinamento registrado com sucesso! " . count($colaboradorIds) . " certificados gerados.");
            $this->redirect("/treinamentos/{$treinamentoId}");
        } catch (\Exception $e) {
            $db->rollBack();
            $this->flash('error', 'Erro ao registrar treinamento: ' . $e->getMessage());
            $this->redirect('/treinamentos/novo');
        }
    }

    public function show(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->findWithDetails((int)$id);

        if (!$treinamento) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('/treinamentos');
            return;
        }

        $participantes = $treinModel->getParticipantes((int)$id);

        // Decrypt CPFs for certificate generation
        $participantesJson = [];
        foreach ($participantes as $p) {
            $cpfFormatado = '***.***.***-**';
            if (!empty($p['cpf_encrypted'])) {
                try {
                    $cpfRaw = CryptoService::decrypt($p['cpf_encrypted']);
                    if (strlen($cpfRaw) === 11) {
                        $cpfFormatado = substr($cpfRaw, 0, 3) . '.' . substr($cpfRaw, 3, 3) . '.' . substr($cpfRaw, 6, 3) . '-' . substr($cpfRaw, 9, 2);
                    }
                } catch (\Exception $e) {}
            }
            $participantesJson[] = [
                'id' => $p['colaborador_id'],
                'nome' => $p['nome_completo'],
                'cpf' => $cpfFormatado,
                'funcao' => $p['funcao'] ?? $p['cargo'] ?? '',
                'cargo' => $p['cargo'] ?? '',
                'data_admissao' => $p['data_admissao'] ?? '',
                'certificado_id' => $p['certificado_id'],
                'status' => $p['status'],
                'data_validade' => $p['data_validade'],
            ];
        }

        // Load tipos and ministrantes for certificate preview
        $tipoModel = new TipoCertificado();
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');
        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');

        $this->view('treinamentos/visualizar', [
            'treinamento'      => $treinamento,
            'participantes'    => $participantes,
            'participantesJson' => $participantesJson,
            'tipos'            => $tipos,
            'ministrantes'     => $ministrantes,
            'pageTitle'        => 'Treinamentos',
        ]);
    }

    public function certificados(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->findWithDetails((int)$id);

        if (!$treinamento) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('/treinamentos');
            return;
        }

        $participantes = $treinModel->getParticipantes((int)$id);

        $participantesJson = [];
        foreach ($participantes as $p) {
            $cpfFormatado = '***.***.***-**';
            if (!empty($p['cpf_encrypted'])) {
                try {
                    $cpfRaw = CryptoService::decrypt($p['cpf_encrypted']);
                    if (strlen($cpfRaw) === 11) {
                        $cpfFormatado = substr($cpfRaw, 0, 3) . '.' . substr($cpfRaw, 3, 3) . '.' . substr($cpfRaw, 6, 3) . '-' . substr($cpfRaw, 9, 2);
                    }
                } catch (\Exception $e) {}
            }
            $participantesJson[] = [
                'id' => $p['colaborador_id'],
                'nome' => $p['nome_completo'],
                'cpf' => $cpfFormatado,
                'funcao' => $p['funcao'] ?? $p['cargo'] ?? '',
                'cargo' => $p['cargo'] ?? '',
                'data_admissao' => $p['data_admissao'] ?? '',
            ];
        }

        $tipoModel = new TipoCertificado();
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');
        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');

        $this->view('treinamentos/certificados', [
            'treinamento'       => $treinamento,
            'participantesJson' => $participantesJson,
            'tipos'             => $tipos,
            'ministrantes'      => $ministrantes,
        ], '');
    }

    public function calendario(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $mes = (int)$this->input('mes', date('n'));
        $ano = (int)$this->input('ano', date('Y'));

        if ($mes < 1) { $mes = 12; $ano--; }
        if ($mes > 12) { $mes = 1; $ano++; }

        $db = \App\Core\Database::getInstance();
        $stmt = $db->prepare(
            "SELECT t.*, tc.codigo as tipo_codigo, tc.titulo as tipo_titulo, tc.duracao,
                    m.nome as ministrante_nome
             FROM treinamentos t
             JOIN tipos_certificado tc ON t.tipo_certificado_id = tc.id
             LEFT JOIN ministrantes m ON t.ministrante_id = m.id
             WHERE MONTH(t.data_realizacao) = :mes AND YEAR(t.data_realizacao) = :ano
             ORDER BY t.data_realizacao ASC"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $treinamentosMes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Organizar por dia
        $eventosCalendario = [];
        foreach ($treinamentosMes as $t) {
            $dia = (int)date('j', strtotime($t['data_realizacao']));
            $eventosCalendario[$dia][] = $t;
        }

        $this->view('treinamentos/calendario', [
            'mes' => $mes,
            'ano' => $ano,
            'eventosCalendario' => $eventosCalendario,
            'pageTitle' => 'Treinamentos',
        ]);
    }

    public function listaPresenca(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->findWithDetails((int)$id);

        if (!$treinamento) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('/treinamentos');
            return;
        }

        $participantes = $treinModel->getParticipantes((int)$id);

        $this->view('treinamentos/lista-presenca', [
            'treinamento'  => $treinamento,
            'participantes' => $participantes,
        ], '');
    }

    public function colaboradoresJson(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $q = trim($this->input('q', ''));
        $colabModel = new Colaborador();

        $db = Database::getInstance();
        $sql = "SELECT id, nome_completo, cargo, funcao, setor FROM colaboradores WHERE status = 'ativo' AND excluido_em IS NULL";
        $params = [];

        if ($q !== '') {
            $sql .= " AND nome_completo LIKE :q";
            $params['q'] = "%{$q}%";
        }

        $sql .= " ORDER BY nome_completo ASC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    public function editForm(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->findWithDetails((int)$id);

        if (!$treinamento || $treinamento['excluido_em']) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('/treinamentos');
            return;
        }

        $tipoModel = new TipoCertificado();
        $ministranteModel = new Ministrante();
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');

        $this->view('treinamentos/editar', [
            'treinamento' => $treinamento,
            'tipos'       => $tipos,
            'ministrantes'=> $ministrantes,
            'pageTitle'   => 'Treinamentos',
        ]);
    }

    public function update(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->find((int)$id);

        if (!$treinamento || $treinamento['excluido_em']) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('/treinamentos');
            return;
        }

        $ministranteId     = (int)$this->input('ministrante_id') ?: null;
        $dataRealizacao    = $this->input('data_realizacao');
        $dataRealizacaoFim = $this->input('data_realizacao_fim') ?: null;
        $dataEmissao       = $this->input('data_emissao');
        $observacoes       = trim($this->input('observacoes', ''));

        if (!$dataRealizacao || !$dataEmissao) {
            $this->flash('error', 'Data de realização e emissão são obrigatórias.');
            $this->redirect("/treinamentos/{$id}/editar");
            return;
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find($treinamento['tipo_certificado_id']);
        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));
        $daysLeft = (strtotime($dataValidade) - time()) / 86400;
        $statusCert = 'vigente';
        if ($daysLeft < 0) $statusCert = 'vencido';
        elseif ($daysLeft <= 30) $statusCert = 'proximo_vencimento';

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $treinModel->update((int)$id, [
                'ministrante_id'      => $ministranteId,
                'data_realizacao'     => $dataRealizacao,
                'data_realizacao_fim' => $dataRealizacaoFim,
                'data_emissao'        => $dataEmissao,
                'observacoes'         => $observacoes ?: null,
            ]);

            // Recalculate all non-deleted certs in this training
            $stmt = $db->prepare(
                "UPDATE certificados SET
                    data_realizacao = :dr,
                    data_realizacao_fim = :drf,
                    data_emissao = :de,
                    data_validade = :dv,
                    status = :st,
                    ministrante_id = :mid
                 WHERE treinamento_id = :tid AND excluido_em IS NULL"
            );
            $stmt->execute([
                'dr'  => $dataRealizacao,
                'drf' => $dataRealizacaoFim,
                'de'  => $dataEmissao,
                'dv'  => $dataValidade,
                'st'  => $statusCert,
                'mid' => $ministranteId,
                'tid' => $id,
            ]);

            $db->commit();
            LoggerMiddleware::log('editar', "Treinamento ID {$id} atualizado");
            $this->flash('success', 'Treinamento atualizado com sucesso.');
            $this->redirect("/treinamentos/{$id}");
        } catch (\Exception $e) {
            $db->rollBack();
            $this->flash('error', 'Erro ao atualizar: ' . $e->getMessage());
            $this->redirect("/treinamentos/{$id}/editar");
        }
    }

    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->find((int)$id);

        if (!$treinamento) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('/treinamentos');
            return;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $treinModel->update((int)$id, ['excluido_em' => $now]);

            $db->prepare(
                "UPDATE certificados SET excluido_em = :now WHERE treinamento_id = :tid AND excluido_em IS NULL"
            )->execute(['now' => $now, 'tid' => $id]);

            $db->commit();
            LoggerMiddleware::log('excluir', "Treinamento ID {$id} excluído (soft)");
            $this->flash('success', 'Treinamento excluído.');
        } catch (\Exception $e) {
            $db->rollBack();
            $this->flash('error', 'Erro ao excluir: ' . $e->getMessage());
        }
        $this->redirect('/treinamentos');
    }

    public function adicionarColaboradores(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $treinModel = new Treinamento();
        $treinamento = $treinModel->find((int)$id);

        if (!$treinamento || $treinamento['excluido_em']) {
            http_response_code(404);
            echo json_encode(['error' => 'Treinamento não encontrado.']);
            exit;
        }

        $colaboradorIds = $this->input('colaborador_ids');
        if (!$colaboradorIds) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum colaborador selecionado.']);
            exit;
        }
        if (!is_array($colaboradorIds)) {
            $colaboradorIds = [$colaboradorIds];
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find($treinamento['tipo_certificado_id']);
        $dataEmissao  = $treinamento['data_emissao'];
        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));
        $daysLeft = (strtotime($dataValidade) - time()) / 86400;
        $statusCert = 'vigente';
        if ($daysLeft < 0) $statusCert = 'vencido';
        elseif ($daysLeft <= 30) $statusCert = 'proximo_vencimento';

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $certModel = new Certificado();
            $adicionados = 0;
            foreach ($colaboradorIds as $colabId) {
                $colabId = (int)$colabId;
                // Skip if already in this training
                $chk = $db->prepare(
                    "SELECT id FROM certificados WHERE treinamento_id = :tid AND colaborador_id = :cid AND excluido_em IS NULL"
                );
                $chk->execute(['tid' => $id, 'cid' => $colabId]);
                if ($chk->fetch()) continue;

                $certData = [
                    'colaborador_id'      => $colabId,
                    'tipo_certificado_id' => $treinamento['tipo_certificado_id'],
                    'data_realizacao'     => $treinamento['data_realizacao'],
                    'data_realizacao_fim' => $treinamento['data_realizacao_fim'] ?? null,
                    'data_emissao'        => $dataEmissao,
                    'data_validade'       => $dataValidade,
                    'status'              => $statusCert,
                    'treinamento_id'      => (int)$id,
                    'ministrante_id'      => $treinamento['ministrante_id'] ?? null,
                    'criado_por'          => Session::get('user_id'),
                ];
                $certModel->create($certData);
                $adicionados++;
            }

            // Update total_participantes
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM certificados WHERE treinamento_id = :tid AND excluido_em IS NULL"
            );
            $stmt->execute(['tid' => $id]);
            $total = (int)$stmt->fetchColumn();
            $treinModel->update((int)$id, ['total_participantes' => $total]);
            $treinModel->sincronizarStatus((int)$id);

            $db->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'adicionados' => $adicionados]);
        } catch (\Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public function removerColaborador(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certId = (int)$this->input('certificado_id');
        if (!$certId) {
            http_response_code(400);
            echo json_encode(['error' => 'certificado_id ausente.']);
            exit;
        }

        $certModel = new Certificado();
        $cert = $certModel->find($certId);

        if (!$cert || (int)$cert['treinamento_id'] !== (int)$id) {
            http_response_code(404);
            echo json_encode(['error' => 'Certificado não encontrado neste treinamento.']);
            exit;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $certModel->update($certId, ['excluido_em' => $now]);

            // Update total_participantes and status
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM certificados WHERE treinamento_id = :tid AND excluido_em IS NULL"
            );
            $stmt->execute(['tid' => $id]);
            $total = (int)$stmt->fetchColumn();
            $treinModel = new Treinamento();
            $treinModel->update((int)$id, ['total_participantes' => $total]);
            $treinModel->sincronizarStatus((int)$id);

            $db->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public function uploadAssinado(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certId = (int)$this->input('certificado_id');
        if (!$certId) {
            $this->flash('error', 'Certificado não informado.');
            $this->redirect("/treinamentos/{$id}");
            return;
        }

        $certModel = new Certificado();
        $cert = $certModel->find($certId);

        if (!$cert || (int)$cert['treinamento_id'] !== (int)$id) {
            $this->flash('error', 'Certificado não encontrado neste treinamento.');
            $this->redirect("/treinamentos/{$id}");
            return;
        }

        if (empty($_FILES['arquivo_assinado']) || $_FILES['arquivo_assinado']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Arquivo inválido ou não enviado.');
            $this->redirect("/treinamentos/{$id}");
            return;
        }

        $file = $_FILES['arquivo_assinado'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $this->flash('error', 'Apenas PDFs são aceitos.');
            $this->redirect("/treinamentos/{$id}");
            return;
        }

        $colabId = $cert['colaborador_id'];
        $uploadDir = BASE_PATH . "/uploads/colaboradores/{$colabId}/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'cert_assinado_' . $certId . '_' . time() . '.pdf';
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->flash('error', 'Falha ao salvar o arquivo.');
            $this->redirect("/treinamentos/{$id}");
            return;
        }

        $relativePath = "uploads/colaboradores/{$colabId}/{$filename}";
        $certModel->update($certId, [
            'arquivo_assinado' => $relativePath,
            'assinado_em'      => date('Y-m-d H:i:s'),
        ]);

        $treinModel = new Treinamento();
        $treinModel->sincronizarStatus((int)$id);

        LoggerMiddleware::log('upload', "Cert assinado vinculado: cert_id={$certId}, treinamento={$id}");
        $this->flash('success', 'Certificado assinado vinculado com sucesso.');
        $this->redirect("/treinamentos/{$id}");
    }
}
