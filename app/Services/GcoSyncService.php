<?php

namespace App\Services;

use App\Core\Database;

/**
 * Sincroniza colaboradores do sistema GCO com o SESMT.
 *
 * Lógica:
 *  - Busca todas as páginas da API (paginada de 100 em 100)
 *  - Para cada registro: cria ou atualiza colaborador pelo CPF
 *  - Ao final: desativa colaboradores que tinham codigo_gco mas não vieram na resposta
 */
class GcoSyncService
{
    private string $baseUrl;
    private string $token;
    private int $pageSize = 100;

    public function __construct()
    {
        $this->baseUrl = $_ENV['GCO_URL'] ?? 'https://gcong.tsea.com.br/gco_infra/Colaboradores/ObterViewDadosColaborador';
        $this->token   = $_ENV['GCO_TOKEN'] ?? '';
    }

    /**
     * Executa a sincronização completa.
     *
     * @param int|null $usuarioId ID do usuário que disparou a sync
     * @return array ['criados', 'atualizados', 'desativados', 'erros', 'total_api', 'detalhes_erros']
     */
    public function sincronizar(?int $usuarioId = null): array
    {
        $db = Database::getInstance();

        $logId = $this->iniciarLog($db, $usuarioId);

        $resultado = [
            'criados'         => 0,
            'atualizados'     => 0,
            'desativados'     => 0,
            'erros'           => 0,
            'total_api'       => 0,
            'detalhes_erros'  => [],
        ];

        try {
            $registros = $this->buscarTodosRegistros();
            $resultado['total_api'] = count($registros);

            if (empty($registros)) {
                throw new \RuntimeException('API retornou 0 registros. Sincronização cancelada por segurança.');
            }

            // Conjunto de codigos_gco que vieram da API (para desativar os ausentes)
            $codigosGcoAtivos = [];

            foreach ($registros as $reg) {
                try {
                    $acao = $this->processarRegistro($db, $reg);
                    $codigosGcoAtivos[] = $reg['CODIGO'];
                    $resultado[$acao]++;
                } catch (\Throwable $e) {
                    $resultado['erros']++;
                    $resultado['detalhes_erros'][] = "CPF {$reg['CPF']} ({$reg['NOME']}): " . $e->getMessage();
                }
            }

            // Desativar colaboradores que tinham codigo_gco mas não vieram na resposta
            $resultado['desativados'] += $this->desativarAusentes($db, $codigosGcoAtivos);

            $this->concluirLog($db, $logId, $resultado, 'concluido');

        } catch (\Throwable $e) {
            $resultado['detalhes_erros'][] = $e->getMessage();
            $this->concluirLog($db, $logId, $resultado, 'erro', $e->getMessage());
            throw $e;
        }

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    private function buscarTodosRegistros(): array
    {
        $todos    = [];
        $pagina   = 1;
        $total    = null;

        do {
            $url  = $this->buildUrl($pagina);
            $json = $this->fetch($url);
            $data = json_decode($json, true);

            if (!isset($data['Registros'])) {
                throw new \RuntimeException('Resposta da API inválida — campo "Registros" ausente.');
            }

            if ($total === null) {
                $total = (int)($data['TotalRegistros'] ?? 0);
            }

            $todos  = array_merge($todos, $data['Registros']);
            $pagina++;

        } while (count($todos) < $total && !empty($data['Registros']));

        return $todos;
    }

    private function processarRegistro(\PDO $db, array $reg): string
    {
        $cpfLimpo = preg_replace('/\D/', '', $reg['CPF'] ?? '');
        $cpfHash  = CryptoService::hash($cpfLimpo);

        // Busca existente por cpf_hash ou codigo_gco
        $stmt = $db->prepare(
            "SELECT id, status FROM colaboradores
             WHERE cpf_hash = :hash OR codigo_gco = :codigo
             LIMIT 1"
        );
        $stmt->execute(['hash' => $cpfHash, 'codigo' => $reg['CODIGO']]);
        $existente = $stmt->fetch(\PDO::FETCH_ASSOC);

        $dados = $this->mapearCampos($reg, $cpfLimpo, $cpfHash);

        if ($existente) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($dados)));
            $stmt = $db->prepare("UPDATE colaboradores SET {$sets} WHERE id = :id");
            $stmt->execute(array_merge($dados, ['id' => $existente['id']]));
            return 'atualizados';
        } else {
            $cols = implode(', ', array_keys($dados));
            $vals = implode(', ', array_map(fn($k) => ":{$k}", array_keys($dados)));
            $stmt = $db->prepare("INSERT INTO colaboradores ({$cols}) VALUES ({$vals})");
            $stmt->execute($dados);
            return 'criados';
        }
    }

    private function mapearCampos(array $reg, string $cpfLimpo, string $cpfHash): array
    {
        $status = ($reg['ATIVO'] === 'Sim') ? 'ativo' : 'inativo';

        $campos = [
            'codigo_gco'      => $reg['CODIGO'],
            'nome_completo'   => $reg['NOME'],
            'cpf_encrypted'   => CryptoService::encrypt($cpfLimpo),
            'cpf_hash'        => $cpfHash,
            'data_nascimento' => $this->parseDate($reg['DATADENASCIMENTO'] ?? null),
            'telefone'        => $reg['TELEFONE'] ?? null,
            'email'           => $reg['EMAIL'] ?? null,
            'data_admissao'   => $this->parseDate($reg['DATAADMISSAO'] ?? null),
            'cargo'           => $reg['DESCRICAOCARGO'] ?? null,
            'funcao'          => $reg['DESCRICAOFUNCAO'] ?? null,
            'setor'           => $reg['NOMESETOR'] ?? null,
            'unidade'         => $reg['UNIDADE'] ?? null,
            'status'          => $status,
            'atualizado_em'   => date('Y-m-d H:i:s'),

            // -------------------------------------------------------------------
            // FUTURO: quando a API GCO passar a retornar cliente e obra,
            // descomente e ajuste os campos abaixo:
            // 'cliente_id' => $this->resolverClienteId($reg['NOME_CLIENTE'] ?? null),
            // 'obra_id'    => $this->resolverObraId($reg['CODIGO_OBRA'] ?? null),
            // -------------------------------------------------------------------
        ];

        // Celular: só atualiza se a API enviar valor — nunca sobrescreve celular_manual
        if (!empty($reg['CELULAR'])) {
            $campos['celular'] = $reg['CELULAR'];
        }

        return $campos;
    }

    // -------------------------------------------------------------------
    // FUTURO: métodos de resolução de cliente/obra pelo nome/código do GCO
    // -------------------------------------------------------------------
    // private function resolverClienteId(?string $nomeCliente): ?int
    // {
    //     if (!$nomeCliente) return null;
    //     $db = Database::getInstance();
    //     $stmt = $db->prepare("SELECT id FROM clientes WHERE razao_social = :nome OR nome_fantasia = :nome LIMIT 1");
    //     $stmt->execute(['nome' => $nomeCliente]);
    //     return $stmt->fetchColumn() ?: null;
    // }
    //
    // private function resolverObraId(?string $codigoObra): ?int
    // {
    //     if (!$codigoObra) return null;
    //     $db = Database::getInstance();
    //     $stmt = $db->prepare("SELECT id FROM obras WHERE codigo = :cod LIMIT 1");
    //     $stmt->execute(['cod' => $codigoObra]);
    //     return $stmt->fetchColumn() ?: null;
    // }

    private function desativarAusentes(\PDO $db, array $codigosAtivos): int
    {
        if (empty($codigosAtivos)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($codigosAtivos), '?'));
        $stmt = $db->prepare(
            "UPDATE colaboradores
             SET status = 'inativo', atualizado_em = NOW()
             WHERE codigo_gco IS NOT NULL
               AND codigo_gco NOT IN ({$placeholders})
               AND status = 'ativo'"
        );
        $stmt->execute($codigosAtivos);
        return $stmt->rowCount();
    }

    private function parseDate(?string $isoDate): ?string
    {
        if (empty($isoDate)) return null;
        // Formato: "1983-08-14T00:00:00.000"
        try {
            $dt = new \DateTime($isoDate);
            return $dt->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildUrl(int $pagina): string
    {
        return $this->baseUrl . '?' . http_build_query([
            'token'        => $this->token,
            'filtros'      => 'ATIVO:eq:Sim',
            'pagina'       => $pagina,
            'tamanhoPagina'=> $this->pageSize,
        ]);
    }

    private function fetch(string $url): string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 30,
                'header'  => "Accept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            throw new \RuntimeException("Erro ao conectar na API GCO: {$url}");
        }
        return $resp;
    }

    private function iniciarLog(\PDO $db, ?int $usuarioId): int
    {
        $stmt = $db->prepare(
            "INSERT INTO gco_sync_logs (status, executado_por) VALUES ('em_andamento', :uid)"
        );
        $stmt->execute(['uid' => $usuarioId]);
        return (int)$db->lastInsertId();
    }

    private function concluirLog(\PDO $db, int $logId, array $res, string $status, string $msg = ''): void
    {
        $stmt = $db->prepare(
            "UPDATE gco_sync_logs SET
                concluido_em = NOW(),
                total_api    = :total,
                criados      = :criados,
                atualizados  = :atualizados,
                desativados  = :desativados,
                erros        = :erros,
                status       = :status,
                mensagem     = :msg
             WHERE id = :id"
        );
        $stmt->execute([
            'total'       => $res['total_api'],
            'criados'     => $res['criados'],
            'atualizados' => $res['atualizados'],
            'desativados' => $res['desativados'],
            'erros'       => $res['erros'],
            'status'      => $status,
            'msg'         => $msg ?: null,
            'id'          => $logId,
        ]);
    }
}
