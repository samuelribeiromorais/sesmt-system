<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

class AuditService
{
    /**
     * Registra alterações com diff (antes/depois).
     * Compara $before e $after e grava apenas os campos que mudaram.
     */
    public static function registrarAlteracao(string $tabela, int $registroId, array $before, array $after, string $acao = 'editar'): void
    {
        $db = Database::getInstance();
        $userId = Session::get('user_id');
        $userName = Session::get('user_nome', 'Sistema');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $db->prepare(
            "INSERT INTO audit_log (tabela, registro_id, acao, campo, valor_anterior, valor_novo, usuario_id, usuario_nome, ip_address)
             VALUES (:tabela, :registro_id, :acao, :campo, :anterior, :novo, :user_id, :user_nome, :ip)"
        );

        if ($acao === 'criar') {
            $stmt->execute([
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'acao' => 'criar',
                'campo' => null,
                'anterior' => null,
                'novo' => json_encode($after, JSON_UNESCAPED_UNICODE),
                'user_id' => $userId,
                'user_nome' => $userName,
                'ip' => $ip,
            ]);
            return;
        }

        if ($acao === 'excluir') {
            $stmt->execute([
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'acao' => 'excluir',
                'campo' => null,
                'anterior' => json_encode($before, JSON_UNESCAPED_UNICODE),
                'novo' => null,
                'user_id' => $userId,
                'user_nome' => $userName,
                'ip' => $ip,
            ]);
            return;
        }

        // Diff: registrar apenas campos que mudaram
        $camposIgnorados = ['atualizado_em', 'criado_em', 'senha_hash'];

        foreach ($after as $campo => $valorNovo) {
            if (in_array($campo, $camposIgnorados)) continue;
            $valorAnterior = $before[$campo] ?? null;

            if ((string)$valorAnterior !== (string)$valorNovo) {
                $stmt->execute([
                    'tabela' => $tabela,
                    'registro_id' => $registroId,
                    'acao' => 'editar',
                    'campo' => $campo,
                    'anterior' => $valorAnterior,
                    'novo' => $valorNovo,
                    'user_id' => $userId,
                    'user_nome' => $userName,
                    'ip' => $ip,
                ]);
            }
        }
    }

    /**
     * Busca histórico de auditoria para um registro.
     */
    public static function getHistorico(string $tabela, int $registroId, int $limit = 50): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM audit_log
             WHERE tabela = :tabela AND registro_id = :id
             ORDER BY criado_em DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':tabela', $tabela);
        $stmt->bindValue(':id', $registroId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca auditoria global (todas as tabelas).
     */
    public static function getGlobal(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $db = Database::getInstance();
        $where = '1=1';
        $params = [];

        if (!empty($filters['tabela'])) {
            $where .= ' AND tabela = :tabela';
            $params['tabela'] = $filters['tabela'];
        }
        if (!empty($filters['acao'])) {
            $where .= ' AND acao = :acao';
            $params['acao'] = $filters['acao'];
        }
        if (!empty($filters['usuario'])) {
            $where .= ' AND usuario_nome LIKE :usuario';
            $params['usuario'] = '%' . $filters['usuario'] . '%';
        }

        $sql = "SELECT * FROM audit_log WHERE {$where} ORDER BY criado_em DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
