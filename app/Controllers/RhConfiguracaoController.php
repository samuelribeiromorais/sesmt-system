<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\RoleMiddleware;

/**
 * Configurações do módulo RH (módulo 4.8 do ETF).
 * Janelas de alerta, SLA, destinatários do digest.
 */
class RhConfiguracaoController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db   = Database::getInstance();
        $cfg  = $db->query("SELECT * FROM rh_alertas_config WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);

        // Garante singleton
        if (!$cfg) {
            $db->exec("INSERT INTO rh_alertas_config (id) VALUES (1)");
            $cfg = $db->query("SELECT * FROM rh_alertas_config WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);
        }

        $this->view('rh/configuracoes', [
            'cfg'       => $cfg,
            'pageTitle' => 'Painel RH — Configurações',
        ]);
    }

    public function salvar(): void
    {
        RoleMiddleware::requireRhOrSesmt();
        $this->requirePost();

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE rh_alertas_config SET
                janela_60 = :j60,
                janela_30 = :j30,
                janela_15 = :j15,
                janela_7  = :j7,
                sla_reprotocolo_dias_uteis = :sla,
                email_digest_destinatarios = :dest,
                email_digest_horario       = :hora,
                atualizado_por             = :user
             WHERE id = 1"
        );
        $stmt->execute([
            'j60'  => $this->input('janela_60') ? 1 : 0,
            'j30'  => $this->input('janela_30') ? 1 : 0,
            'j15'  => $this->input('janela_15') ? 1 : 0,
            'j7'   => $this->input('janela_7')  ? 1 : 0,
            'sla'  => max(1, min(30, (int)$this->input('sla', 5))),
            'dest' => trim($this->input('email_digest_destinatarios', '')) ?: null,
            'hora' => $this->input('email_digest_horario', '07:00:00'),
            'user' => Session::get('user_id'),
        ]);

        $this->flash('success', 'Configurações salvas.');
        $this->redirect('/rh/configuracoes');
    }
}
