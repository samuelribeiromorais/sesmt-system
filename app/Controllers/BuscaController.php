<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Database;

class BuscaController extends Controller
{
    public function index(): void
    {
        $q = trim($this->input('q', ''));
        $results = $this->search($q, 50);

        $this->view('busca/index', [
            'q'         => $q,
            'results'   => $results,
            'pageTitle' => 'Busca',
        ]);
    }

    public function jsonSearch(): void
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['error' => 'Nao autenticado']);
            exit;
        }

        $q = trim($this->input('q', ''));
        if (strlen($q) < 2) {
            header('Content-Type: application/json');
            echo json_encode(['results' => []]);
            exit;
        }

        $results = $this->search($q, 10);
        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }

    private function search(string $q, int $limitPerCategory = 10): array
    {
        if (strlen($q) < 2) {
            return [];
        }

        $limitPerCategory = (int)$limitPerCategory;
        $db = Database::getInstance();
        $like = "%{$q}%";
        $results = [];

        // Colaboradores
        try {
            $stmt = $db->prepare(
                "SELECT id, nome_completo, matricula, cargo, funcao
                 FROM colaboradores
                 WHERE nome_completo LIKE :q1 OR matricula LIKE :q2 OR cargo LIKE :q3
                 ORDER BY nome_completo ASC
                 LIMIT {$limitPerCategory}"
            );
            $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                $results['colaboradores'] = array_map(fn($r) => [
                    'id'    => $r['id'],
                    'titulo' => $r['nome_completo'],
                    'subtitulo' => $r['cargo'] ?: ($r['funcao'] ?: ($r['matricula'] ?: '')),
                    'link'  => '/colaboradores/' . $r['id'],
                ], $rows);
            }
        } catch (\Exception $e) {}

        // Clientes
        try {
            $stmt = $db->prepare(
                "SELECT id, razao_social, nome_fantasia
                 FROM clientes
                 WHERE razao_social LIKE :q1 OR nome_fantasia LIKE :q2
                 ORDER BY nome_fantasia ASC
                 LIMIT {$limitPerCategory}"
            );
            $stmt->execute(['q1' => $like, 'q2' => $like]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                $results['clientes'] = array_map(fn($r) => [
                    'id'    => $r['id'],
                    'titulo' => $r['nome_fantasia'] ?: $r['razao_social'],
                    'subtitulo' => $r['razao_social'],
                    'link'  => '/clientes/' . $r['id'],
                ], $rows);
            }
        } catch (\Exception $e) {}

        // Obras
        try {
            $stmt = $db->prepare(
                "SELECT o.id, o.nome, c.nome_fantasia as cliente_nome
                 FROM obras o
                 LEFT JOIN clientes c ON o.cliente_id = c.id
                 WHERE o.nome LIKE :q1
                 ORDER BY o.nome ASC
                 LIMIT {$limitPerCategory}"
            );
            $stmt->execute(['q1' => $like]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                $results['obras'] = array_map(fn($r) => [
                    'id'    => $r['id'],
                    'titulo' => $r['nome'],
                    'subtitulo' => $r['cliente_nome'] ?? '',
                    'link'  => '/clientes/' . ($r['id'] ?? ''),
                ], $rows);
            }
        } catch (\Exception $e) {}

        // Documentos
        try {
            $stmt = $db->prepare(
                "SELECT d.id, d.arquivo_nome, d.colaborador_id, col.nome_completo
                 FROM documentos d
                 JOIN colaboradores col ON d.colaborador_id = col.id
                 WHERE d.arquivo_nome LIKE :q1 OR col.nome_completo LIKE :q2
                 ORDER BY d.criado_em DESC
                 LIMIT {$limitPerCategory}"
            );
            $stmt->execute(['q1' => $like, 'q2' => $like]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                $results['documentos'] = array_map(fn($r) => [
                    'id'    => $r['id'],
                    'titulo' => $r['arquivo_nome'],
                    'subtitulo' => $r['nome_completo'],
                    'link'  => '/colaboradores/' . $r['colaborador_id'],
                ], $rows);
            }
        } catch (\Exception $e) {}

        // Certificados
        try {
            $stmt = $db->prepare(
                "SELECT cert.id, cert.colaborador_id, col.nome_completo, tc.nome as tipo_nome
                 FROM certificados cert
                 JOIN colaboradores col ON cert.colaborador_id = col.id
                 LEFT JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
                 WHERE col.nome_completo LIKE :q1 OR tc.nome LIKE :q2
                 ORDER BY cert.criado_em DESC
                 LIMIT {$limitPerCategory}"
            );
            $stmt->execute(['q1' => $like, 'q2' => $like]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                $results['certificados'] = array_map(fn($r) => [
                    'id'    => $r['id'],
                    'titulo' => $r['tipo_nome'] ?? 'Certificado',
                    'subtitulo' => $r['nome_completo'],
                    'link'  => '/colaboradores/' . $r['colaborador_id'],
                ], $rows);
            }
        } catch (\Exception $e) {}

        return $results;
    }
}
