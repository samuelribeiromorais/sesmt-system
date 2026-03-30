<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\EsocialEvento;
use App\Models\Colaborador;

class EsocialController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model   = new EsocialEvento();
        $page    = max(1, (int) $this->input('page', 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        $filters = [];
        $tipoEvento = $this->input('tipo_evento', '');
        $status     = $this->input('status', '');

        if ($tipoEvento) $filters['tipo_evento'] = $tipoEvento;
        if ($status)     $filters['status'] = $status;

        $total   = $model->countWithFilters($filters);
        $eventos = $model->allWithColaborador($filters, $perPage, $offset);

        $this->view('esocial/index', [
            'eventos'    => $eventos,
            'tipoEvento' => $tipoEvento,
            'status'     => $status,
            'page'       => $page,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'total'      => $total,
            'pageTitle'  => 'eSocial SST',
        ]);
    }

    public function gerar(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $colab = $colabModel->findWithRelations((int) $colaboradorId);

        if (!$colab) {
            $this->flash('error', 'Colaborador não encontrado.');
            $this->redirect('/esocial');
            return;
        }

        $this->view('esocial/gerar', [
            'colab'     => $colab,
            'pageTitle' => 'Gerar Evento eSocial',
        ]);
    }

    public function criarEvento(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $colaboradorId = (int) $this->input('colaborador_id', 0);
        $tipoEvento    = $this->input('tipo_evento', '');

        if (!$colaboradorId || !in_array($tipoEvento, ['S-2210', 'S-2220', 'S-2240'])) {
            $this->flash('error', 'Dados invalidos.');
            $this->redirect('/esocial');
            return;
        }

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        if (!$colab) {
            $this->flash('error', 'Colaborador não encontrado.');
            $this->redirect('/esocial');
            return;
        }

        $payload = $this->buildPayload($tipoEvento, $colab);

        $model = new EsocialEvento();
        $id = $model->create([
            'colaborador_id' => $colaboradorId,
            'tipo_evento'    => $tipoEvento,
            'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status'         => 'pendente',
            'criado_em'      => date('Y-m-d H:i:s'),
        ]);

        LoggerMiddleware::log('esocial', "Evento {$tipoEvento} criado para {$colab['nome_completo']} (ID: {$id})");
        $this->flash('success', "Evento {$tipoEvento} criado com sucesso.");
        $this->redirect("/esocial/{$id}");
    }

    public function visualizar(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model  = new EsocialEvento();
        $evento = $model->findWithColaborador((int) $id);

        if (!$evento) {
            $this->flash('error', 'Evento não encontrado.');
            $this->redirect('/esocial');
            return;
        }

        $evento['payload_decoded'] = json_decode($evento['payload'], true) ?? [];

        $this->view('esocial/visualizar', [
            'evento'   => $evento,
            'pageTitle' => "Evento {$evento['tipo_evento']}",
        ]);
    }

    public function exportarXml(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model  = new EsocialEvento();
        $evento = $model->findWithColaborador((int) $id);

        if (!$evento) {
            $this->flash('error', 'Evento não encontrado.');
            $this->redirect('/esocial');
            return;
        }

        $payload = json_decode($evento['payload'], true) ?? [];
        $xml = $this->generateXml($evento, $payload);

        $filename = "esocial_{$evento['tipo_evento']}_{$evento['id']}.xml";

        header('Content-Type: application/xml; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache');
        echo $xml;
        exit;
    }

    private function buildPayload(string $tipo, array $colab): array
    {
        $base = [
            'colaborador' => [
                'nome'      => $colab['nome_completo'],
                'matricula' => $colab['matricula'] ?? '',
                'cargo'     => $colab['cargo'] ?? '',
                'funcao' => $colab['funcao'] ?? '',
            ],
        ];

        switch ($tipo) {
            case 'S-2210': // CAT
                $base['cat'] = [
                    'data_acidente'  => $this->input('data_acidente', ''),
                    'tipo_acidente'  => $this->input('tipo_acidente', ''),
                    'hora_acidente'  => $this->input('hora_acidente', ''),
                    'local_acidente' => $this->input('local_acidente', ''),
                    'descricao' => $this->input('descricao', ''),
                    'parte_atingida' => $this->input('parte_atingida', ''),
                    'agente_causador'=> $this->input('agente_causador', ''),
                    'houve_obito'    => $this->input('houve_obito', 'não'),
                ];
                break;

            case 'S-2220': // ASO
                $base['aso'] = [
                    'data_aso'       => $this->input('data_aso', ''),
                    'tipo_aso'       => $this->input('tipo_aso', ''),
                    'resultado'      => $this->input('resultado', ''),
                    'medico_nome'    => $this->input('medico_nome', ''),
                    'medico_crm'     => $this->input('medico_crm', ''),
                    'exames'         => $this->input('exames', ''),
                    'observacoes'    => $this->input('observacoes', ''),
                ];
                break;

            case 'S-2240': // Exposicao a agentes nocivos
                $base['exposicao'] = [
                    'fator_risco'       => $this->input('fator_risco', ''),
                    'intensidade'       => $this->input('intensidade', ''),
                    'tecnica_utilizada' => $this->input('tecnica_utilizada', ''),
                    'epc_eficaz'        => $this->input('epc_eficaz', 'sim'),
                    'epi_eficaz'        => $this->input('epi_eficaz', 'sim'),
                    'data_inicio'       => $this->input('data_inicio', ''),
                    'descricao' => $this->input('descricao', ''),
                ];
                break;
        }

        return $base;
    }

    private function generateXml(array $evento, array $payload): string
    {
        $tipo = $evento['tipo_evento'];
        $tagName = str_replace('-', '', $tipo);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= "<eSocial xmlns=\"http://www.esocial.gov.br/schema/evt/{$tagName}/v1\">\n";
        $xml .= "  <evtSST>\n";
        $xml .= "    <ideEvento>\n";
        $xml .= "      <tpEvento>{$tipo}</tpEvento>\n";
        $xml .= "      <dtEvento>" . ($evento['criado_em'] ? date('Y-m-d', strtotime($evento['criado_em'])) : date('Y-m-d')) . "</dtEvento>\n";
        $xml .= "      <status>{$evento['status']}</status>\n";

        if (!empty($evento['protocolo'])) {
            $xml .= "      <protocolo>{$evento['protocolo']}</protocolo>\n";
        }

        $xml .= "    </ideEvento>\n";
        $xml .= "    <ideVinculo>\n";

        if (!empty($payload['colaborador'])) {
            foreach ($payload['colaborador'] as $key => $value) {
                if ($value !== '') {
                    $xml .= "      <{$key}>" . htmlspecialchars($value) . "</{$key}>\n";
                }
            }
        }

        $xml .= "    </ideVinculo>\n";

        // Event-specific data
        $sections = ['cat', 'aso', 'exposicao'];
        foreach ($sections as $section) {
            if (!empty($payload[$section])) {
                $xml .= "    <{$section}>\n";
                foreach ($payload[$section] as $key => $value) {
                    if ($value !== '') {
                        $xml .= "      <{$key}>" . htmlspecialchars($value) . "</{$key}>\n";
                    }
                }
                $xml .= "    </{$section}>\n";
            }
        }

        $xml .= "  </evtSST>\n";
        $xml .= "</eSocial>\n";

        return $xml;
    }
}
