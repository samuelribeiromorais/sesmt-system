<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Certificado;
use App\Models\TipoCertificado;
use App\Models\Colaborador;
use App\Models\Ministrante;

class CertificadoController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $tipoModel = new TipoCertificado();

        $colaboradores = $colabModel->all(['status' => 'ativo'], 'nome_completo ASC');
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');

        $this->view('certificados/index', [
            'colaboradores' => $colaboradores,
            'tipos'         => $tipos,
            'pageTitle'     => 'Certificados',
        ]);
    }

    public function emitir(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $colab = $colabModel->find((int)$colaboradorId);
        if (!$colab) {
            $this->redirect('/certificados');
        }

        $tipoModel = new TipoCertificado();
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');

        $certModel = new Certificado();
        $certs = $certModel->findByColaborador((int)$colaboradorId);

        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');

        $this->view('certificados/emitir', [
            'colab'        => $colab,
            'tipos'        => $tipos,
            'certs'        => $certs,
            'ministrantes' => $ministrantes,
            'pageTitle'    => 'Certificados',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colaboradorId = (int)$this->input('colaborador_id');
        $tipoCertificadoId = (int)$this->input('tipo_certificado_id');
        $dataRealizacao = $this->input('data_realizacao');
        $dataRealizacaoFim = $this->input('data_realizacao_fim') ?: null;
        $dataEmissao = $this->input('data_emissao');
        $ministranteId = (int)$this->input('ministrante_id') ?: null;

        if (!$colaboradorId || !$tipoCertificadoId || !$dataRealizacao || !$dataEmissao) {
            $this->flash('error', 'Preencha todos os campos.');
            $this->redirect("/certificados/emitir/{$colaboradorId}");
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find($tipoCertificadoId);

        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));

        $status = 'vigente';
        $daysLeft = (strtotime($dataValidade) - time()) / 86400;
        if ($daysLeft < 0) $status = 'vencido';
        elseif ($daysLeft <= 30) $status = 'proximo_vencimento';

        $certData = [
            'colaborador_id'      => $colaboradorId,
            'tipo_certificado_id' => $tipoCertificadoId,
            'data_realizacao'     => $dataRealizacao,
            'data_emissao'        => $dataEmissao,
            'data_validade'       => $dataValidade,
            'status'              => $status,
            'criado_por'          => Session::get('user_id'),
        ];

        if ($dataRealizacaoFim) {
            $certData['data_realizacao_fim'] = $dataRealizacaoFim;
        }

        if ($ministranteId) {
            $certData['ministrante_id'] = $ministranteId;
        }

        $certModel = new Certificado();
        $id = $certModel->create($certData);

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        LoggerMiddleware::log('criar', "Certificado emitido: {$tipo['codigo']} para {$colab['nome_completo']} (Cert ID: {$id})");

        $this->flash('success', "Certificado {$tipo['codigo']} emitido com sucesso.");
        $this->redirect("/colaboradores/{$colaboradorId}");
    }

    public function preview(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $cert = $certModel->query(
            "SELECT cert.*, tc.*, c.nome_completo, c.cpf_encrypted, c.funcao, c.cargo,
                    m.nome as ministrante_nome, m.cargo_titulo as ministrante_cargo, m.registro as ministrante_registro
             FROM certificados cert
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             JOIN colaboradores c ON cert.colaborador_id = c.id
             LEFT JOIN ministrantes m ON cert.ministrante_id = m.id
             WHERE cert.id = :id",
            ['id' => (int)$id]
        );

        if (empty($cert)) {
            $this->redirect('/certificados');
        }

        $this->view('certificados/preview', [
            'cert'      => $cert[0],
            'pageTitle' => 'Certificados',
        ], '');
    }

    public function tiposJson(): void
    {
        $tipoModel = new TipoCertificado();
        $tipos = $tipoModel->all(['ativo' => 1], 'id ASC');
        $this->json($tipos);
    }

    public function dadosJson(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $certs = $certModel->findByColaborador((int)$colaboradorId);

        $colabModel = new Colaborador();
        $colab = $colabModel->find((int)$colaboradorId);

        $this->json([
            'colaborador'  => $colab,
            'certificados' => $certs,
        ]);
    }

    public function ministrantesJson(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');
        $this->json($ministrantes);
    }
}
