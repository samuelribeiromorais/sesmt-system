<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Services\CryptoService;

class KitPjController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT k.*, c.nome_completo, c.cargo, c.funcao, c.setor
             FROM kits_pj k
             JOIN colaboradores c ON k.colaborador_id = c.id
             ORDER BY k.criado_em DESC
             LIMIT 50"
        );
        $kits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('kit-pj/index', [
            'kits' => $kits,
            'pageTitle' => 'Kit PJ',
        ]);
    }

    public function create(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colaboradorId = $this->input('colaborador_id');
        $colab = null;
        $cpfPlain = '';

        if ($colaboradorId) {
            $model = new Colaborador();
            $colab = $model->find((int)$colaboradorId);
            if ($colab && !empty($colab['cpf_encrypted'])) {
                try {
                    $cpfRaw = CryptoService::decrypt($colab['cpf_encrypted']);
                    $cpfPlain = substr($cpfRaw, 0, 3) . '.' . substr($cpfRaw, 3, 3) . '.' . substr($cpfRaw, 6, 3) . '-' . substr($cpfRaw, 9, 2);
                } catch (\Exception $e) {}
            }
        }

        // Buscar colaboradores para select
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, nome_completo, cargo, funcao, setor, data_nascimento FROM colaboradores WHERE status = 'ativo' AND excluido_em IS NULL ORDER BY nome_completo");
        $colaboradores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('kit-pj/criar', [
            'colab' => $colab,
            'cpfPlain' => $cpfPlain,
            'colaboradores' => $colaboradores,
            'pageTitle' => 'Kit PJ',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $db = Database::getInstance();

        $colaboradorId = (int)$this->input('colaborador_id');
        $model = new Colaborador();
        $colab = $model->find($colaboradorId);

        if (!$colab) {
            $this->flash('error', 'Colaborador não encontrado.');
            $this->redirect('/kit-pj/novo');
            return;
        }

        $exames = $this->input('exames') ?: [];
        $aptidoes = $this->input('aptidoes') ?: [];

        $riscosFisicos = $this->input('riscos_fisicos') ?: [];
        $riscosQuimicos = $this->input('riscos_quimicos') ?: [];
        $riscosBiologicos = $this->input('riscos_biologicos') ?: [];
        $riscosErgonomicos = $this->input('riscos_ergonomicos') ?: [];
        $riscosAcidentes = $this->input('riscos_acidentes') ?: [];

        $data = [
            'colaborador_id' => $colaboradorId,
            'razao_social' => trim($this->input('razao_social', '')),
            'cnpj' => trim($this->input('cnpj', '')),
            'endereco' => trim($this->input('endereco', '')),
            'tipo_aso' => $this->input('tipo_aso', 'periodico'),
            'riscos_fisicos' => is_array($riscosFisicos) ? implode(', ', $riscosFisicos) : $riscosFisicos,
            'riscos_quimicos' => is_array($riscosQuimicos) ? implode(', ', $riscosQuimicos) : $riscosQuimicos,
            'riscos_biologicos' => is_array($riscosBiologicos) ? implode(', ', $riscosBiologicos) : $riscosBiologicos,
            'riscos_ergonomicos' => is_array($riscosErgonomicos) ? implode(', ', $riscosErgonomicos) : $riscosErgonomicos,
            'riscos_acidentes' => is_array($riscosAcidentes) ? implode(', ', $riscosAcidentes) : $riscosAcidentes,
            'exames' => json_encode($exames),
            'aptidoes' => json_encode($aptidoes),
            'medico_nome' => trim($this->input('medico_nome', 'Dr. Haroldo Aquino Noleto')),
            'medico_crm' => trim($this->input('medico_crm', 'CRM: 2678')),
            'medico_uf' => trim($this->input('medico_uf', 'GO')),
            'gerado_por' => Session::get('user_id'),
        ];

        $cols = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $db->prepare("INSERT INTO kits_pj ({$cols}) VALUES ({$placeholders})");
        $stmt->execute($data);
        $kitId = $db->lastInsertId();

        LoggerMiddleware::log('criar', "Kit PJ gerado para {$colab['nome_completo']} (ID: {$kitId})");
        $this->flash('success', 'Kit PJ gerado com sucesso!');
        $this->redirect("/kit-pj/{$kitId}/imprimir");
    }

    public function imprimir(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT k.*, c.nome_completo, c.cargo, c.funcao, c.setor,
                    c.data_nascimento, c.data_admissao, c.cpf_encrypted
             FROM kits_pj k
             JOIN colaboradores c ON k.colaborador_id = c.id
             WHERE k.id = :id"
        );
        $stmt->execute(['id' => (int)$id]);
        $kit = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$kit) {
            $this->flash('error', 'Kit PJ não encontrado.');
            $this->redirect('/kit-pj');
            return;
        }

        // Decrypt CPF
        $cpfFormatado = '___.___.___-__';
        if (!empty($kit['cpf_encrypted'])) {
            try {
                $cpf = CryptoService::decrypt($kit['cpf_encrypted']);
                $cpfFormatado = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
            } catch (\Exception $e) {}
        }

        // Calcular idade
        $idade = '';
        if ($kit['data_nascimento']) {
            $nasc = new \DateTime($kit['data_nascimento']);
            $hoje = new \DateTime();
            $idade = $nasc->diff($hoje)->y . ' Anos';
        }

        $kit['cpf_formatado'] = $cpfFormatado;
        $kit['idade'] = $idade;
        $kit['exames_arr'] = json_decode($kit['exames'] ?? '[]', true) ?: [];
        $kit['aptidoes_arr'] = json_decode($kit['aptidoes'] ?? '[]', true) ?: [];

        $this->view('kit-pj/imprimir', ['kit' => $kit], '');
    }
}
