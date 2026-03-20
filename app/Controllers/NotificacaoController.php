<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Notificacao;

class NotificacaoController extends Controller
{
    public function index(): void
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            $this->redirect('/login');
        }

        $model = new Notificacao();
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $notificacoes = $model->getAllForUser((int)$userId, $perPage, $offset);
        $total = $model->countForUser((int)$userId);
        $totalPages = max(1, (int)ceil($total / $perPage));

        $this->view('notificacoes/index', [
            'notificacoes' => $notificacoes,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
            'pageTitle'    => 'Notificacoes',
        ]);
    }

    public function json(): void
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            $this->json(['error' => 'Nao autenticado'], 401);
        }

        $model = new Notificacao();
        $unreadCount = $model->countUnread((int)$userId);
        $notificacoes = $model->getUnread((int)$userId, 10);

        $this->json([
            'unread_count'  => $unreadCount,
            'notificacoes'  => array_map(fn($n) => [
                'id'       => $n['id'],
                'tipo'     => $n['tipo'],
                'titulo'   => $n['titulo'],
                'mensagem' => $n['mensagem'],
                'link'     => $n['link'],
                'criado_em' => $n['criado_em'],
            ], $notificacoes),
        ]);
    }

    public function marcarLida(string $id): void
    {
        $this->requirePost();
        $userId = Session::get('user_id');
        if (!$userId) {
            $this->json(['error' => 'Nao autenticado'], 401);
        }

        $model = new Notificacao();
        $notif = $model->find((int)$id);

        if ($notif && (int)$notif['usuario_id'] === (int)$userId) {
            $model->markAsRead((int)$id);
        }

        // If AJAX request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json(['success' => true]);
        }

        $this->redirect('/notificacoes');
    }

    public function marcarTodasLidas(): void
    {
        $this->requirePost();
        $userId = Session::get('user_id');
        if (!$userId) {
            $this->json(['error' => 'Nao autenticado'], 401);
        }

        $model = new Notificacao();
        $model->markAllRead((int)$userId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json(['success' => true]);
        }

        $this->flash('success', 'Todas as notificacoes foram marcadas como lidas.');
        $this->redirect('/notificacoes');
    }
}
