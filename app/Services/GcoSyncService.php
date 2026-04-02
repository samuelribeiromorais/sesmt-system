<?php

namespace App\Services;

use App\Core\Database;

/**
 * Sincroniza colaboradores do sistema GCO com o SESMT.
 *
 * Lógica:
 *  - Busca todas as páginas da API (paginada de 100 em 100)
 *    OU array direto (novo formato da API)
 *  - Para cada registro: cria ou atualiza colaborador pelo CPF
 *  - SITE_OBRA é usado para vincular obra_id / cliente_id automaticamente
 *  - Ao final: desativa colaboradores que tinham codigo_gco mas não vieram na resposta
 */
class GcoSyncService
{
    private string $baseUrl;
    private string $token;
    private int $pageSize = 100;

    /** Cache obra_id por SITE_OBRA para não repetir queries */
    private array $cacheObra = [];

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
        $todos     = [];
        $pagina    = 1;
        $total     = null;
        $registros = [];

        do {
            $url  = $this->buildUrl($pagina);
            $json = $this->fetch($url);
            $data = json_decode($json, true);

            if (!is_array($data)) {
                throw new \RuntimeException('Resposta da API inválida — JSON inválido ou não é array/objeto.');
            }

            // ----------------------------------------------------------------
            // Suporta dois formatos de resposta:
            //
            // 1. OBJETO PAGINADO (formato antigo):
            //    { "Registros": [...], "TotalRegistros": 712 }
            //
            // 2. ARRAY DIRETO paginado (novo formato):
            //    [ {"CODIGO": "...", "NOME": "...", ...}, ... ]
            //    Continua buscando páginas até retornar página vazia ou incompleta.
            // ----------------------------------------------------------------

            if (array_key_exists('Registros', $data)) {
                // Formato paginado com envelope (legado)
                if ($total === null) {
                    $total = (int)($data['TotalRegistros'] ?? 0);
                }
                $registros = $data['Registros'] ?? [];
                $todos     = array_merge($todos, $registros);
                $pagina++;

            } else {
                // Formato array direto — pode ainda ser paginado
                $registros = $data;
                $todos     = array_merge($todos, $registros);
                $pagina++;
            }

        } while ($this->deveContunuarPaginando($todos, $total, $registros));

        return $todos;
    }

    /**
     * Decide se deve buscar mais uma página.
     *
     * - Formato com envelope: para quando total foi atingido ou página veio vazia
     * - Formato array direto: para quando a página veio com menos itens que o pageSize
     *   (indica que é a última página)
     */
    private function deveContunuarPaginando(array $todos, ?int $total, array $ultimaPagina): bool
    {
        if (empty($ultimaPagina)) {
            return false; // Página vazia — fim da paginação
        }

        if ($total !== null) {
            // Formato com envelope: para quando buscou tudo
            return count($todos) < $total;
        }

        // Formato array direto: para quando a página retornou menos que o tamanho máximo
        return count($ultimaPagina) >= $this->pageSize;
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

        $dados = $this->mapearCampos($reg, $cpfLimpo, $cpfHash, $db);

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

    private function mapearCampos(array $reg, string $cpfLimpo, string $cpfHash, \PDO $db): array
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
        ];

        // Celular: só atualiza se a API enviar valor — nunca sobrescreve celular_manual
        if (!empty($reg['CELULAR'])) {
            $campos['celular'] = $reg['CELULAR'];
        }

        // SITE_OBRA: vincula obra_id e cliente_id quando disponível
        if (!empty($reg['SITE_OBRA'])) {
            $ids = $this->resolverObraIdDeSiteObra($reg['SITE_OBRA'], $db);
            if ($ids !== null) {
                $campos['obra_id']   = $ids['obra_id'];
                $campos['cliente_id'] = $ids['cliente_id'];
            }
        }

        return $campos;
    }

    /**
     * Tenta encontrar a obra correspondente ao campo SITE_OBRA do GCO.
     *
     * Formato esperado: "{CODIGO} - {CLIENTE} - {LOCAL}"
     * Exemplos:
     *   "240 - CARGILL - PORTO NACIONAL - TO"  → Cargill Porto Nacional (id=7)
     *   "101 - NESTLE - CAÇAPAVA"              → Nestle Cacapava (id=33)
     *
     * @return array|null ['obra_id' => int, 'cliente_id' => int] ou null se não encontrar
     */
    private function resolverObraIdDeSiteObra(string $siteObra, \PDO $db): ?array
    {
        // Cache para não repetir queries para o mesmo SITE_OBRA
        if (array_key_exists($siteObra, $this->cacheObra)) {
            return $this->cacheObra[$siteObra];
        }

        // Normaliza e divide o campo
        $partes = explode(' - ', $siteObra);
        if (count($partes) < 3) {
            $this->cacheObra[$siteObra] = null;
            return null;
        }

        // partes[0] = código (ex: "240")
        // partes[1] = nome cliente (ex: "CARGILL")
        // partes[2..n] = local/nome da obra (ex: "PORTO NACIONAL", "TO")
        $clienteHint = $this->normalizarTexto($partes[1]);
        // Usa no mínimo a primeira palavra do local para busca
        $localPartes = array_slice($partes, 2);
        $localHint   = $this->normalizarTexto(implode(' ', $localPartes));
        // Primeira palavra significativa do local (ex: "PORTO" de "PORTO NACIONAL TO")
        $localPrimeiro = explode(' ', trim($localHint))[0] ?? '';

        try {
            $stmt = $db->prepare(
                "SELECT o.id, o.cliente_id
                 FROM obras o
                 JOIN clientes c ON c.id = o.cliente_id
                 WHERE UPPER(c.nome_fantasia) LIKE :cliente
                   AND (
                       UPPER(o.nome) LIKE :local
                       OR UPPER(o.local_obra) LIKE :local
                   )
                 LIMIT 1"
            );
            $stmt->execute([
                'cliente' => '%' . $clienteHint . '%',
                'local'   => '%' . $localPrimeiro . '%',
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $resultado = $row
                ? ['obra_id' => (int)$row['id'], 'cliente_id' => (int)$row['cliente_id']]
                : null;

        } catch (\Throwable) {
            $resultado = null;
        }

        $this->cacheObra[$siteObra] = $resultado;
        return $resultado;
    }

    /**
     * Normaliza texto para comparação: remove acentos e converte para maiúsculas.
     */
    private function normalizarTexto(string $texto): string
    {
        // Remove acentos via transliteração
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        return strtoupper(trim($texto));
    }

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
