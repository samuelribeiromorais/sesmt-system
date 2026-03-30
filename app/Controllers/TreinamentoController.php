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
}
