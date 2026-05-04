<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Models\RhVinculoObra;
use App\Services\RhPendenciaService;

/**
 * Vínculos N:N entre colaborador e obras (RF-01).
 * Acessível a admin e RH. SESMT pode visualizar via tela do colaborador.
 */
class RhVinculoController extends Controller
{
    // GET /colaboradores/{id}/vinculos — JSON da lista (consumido por modal)
    public function listar(int $colabId): void
    {
        // Visualização permitida para qualquer perfil autenticado
        $linhas = RhVinculoObra::listarDoColaborador($colabId);
        $this->json(['success' => true, 'vinculos' => $linhas]);
    }

    // POST /colaboradores/{id}/vinculos — cria vínculo
    public function criar(int $colabId): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $obraId = (int)$this->input('obra_id', 0);
        $desde  = trim($this->input('desde', date('Y-m-d')));
        $ate    = trim($this->input('ate_quando', '')) ?: null;
        $funcao = trim($this->input('funcao_no_site', '')) ?: null;
        $userId = (int)Session::get('user_id');

        if ($obraId <= 0) {
            $this->json(['success' => false, 'error' => 'Obra é obrigatória.'], 400);
            return;
        }

        try {
            $vinculoId = RhVinculoObra::criar($colabId, $obraId, $desde, $ate, $funcao, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
            return;
        }

        // Dispara recálculo de pendências para esse colaborador
        $stats = RhPendenciaService::recalcularColaborador($colabId);

        $this->json([
            'success'    => true,
            'vinculo_id' => $vinculoId,
            'pendencias' => $stats,
            'msg'        => "Vínculo criado. {$stats['criadas']} pendência(s) gerada(s).",
        ]);
    }

    // POST /vinculos/{id}/encerrar — fecha vínculo (preenche ate_quando)
    public function encerrar(int $vinculoId): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $ate = trim($this->input('ate_quando', date('Y-m-d')));
        $ok  = RhVinculoObra::encerrar($vinculoId, $ate);

        $this->json(['success' => $ok]);
    }

    // POST /vinculos/{id}/excluir — soft-delete
    public function excluir(int $vinculoId): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $ok = RhVinculoObra::excluir($vinculoId);
        $this->json(['success' => $ok]);
    }

    // POST /rh/recalcular — botão "Recalcular agora"
    public function recalcularTudo(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $stats = RhPendenciaService::recalcularTudo();
        $this->json(['success' => true, 'stats' => $stats,
                     'msg' => "Recálculo concluído: {$stats['criadas']} criadas, {$stats['atualizadas']} atualizadas, {$stats['mantidas']} mantidas."]);
    }
}
