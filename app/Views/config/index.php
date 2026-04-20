<?php
$categorias = [
    'aso' => 'ASO',
    'epi' => 'EPI',
    'os' => 'Ordem de Servico',
    'treinamento' => 'Treinamento',
    'anuencia' => 'Anuencia',
    'outro' => 'Outro',
];
?>

<style>
.config-tabs { display:flex; gap:0; border-bottom:2px solid var(--c-border); margin-bottom:24px; overflow-x:auto; }
.config-tab { padding:12px 20px; cursor:pointer; font-size:14px; font-weight:600; color:var(--c-gray); border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap; transition:all .2s; background:none; border-top:none; border-left:none; border-right:none; }
.config-tab:hover { color:var(--c-text); }
.config-tab.active { color:var(--c-primary); border-bottom-color:var(--c-primary); }
.config-panel { display:none; }
.config-panel.active { display:block; }
.config-card { background:var(--c-white); border:1px solid var(--c-border); border-radius:8px; padding:20px; margin-bottom:16px; }
.config-card h4 { margin:0 0 4px; font-size:14px; }
.config-card p { margin:0; font-size:13px; color:var(--c-gray); }
.form-row { display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
.form-row .form-group { flex:1; min-width:200px; }
.inline-form { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
.inline-form .form-group { margin-bottom:0; }
.ministrante-card { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; }
.ministrante-info { flex:1; }
.ministrante-info .name { font-weight:600; font-size:14px; color:var(--c-text); }
.ministrante-info .detail { font-size:12px; color:var(--c-gray); margin-top:2px; }
.badge-status { padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; }
.badge-ativo { background:#e6f9f0; color:#00875a; }
.badge-inativo { background:#fce8e8; color:#c0392b; }
.cert-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:12px; }
.cert-item { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:var(--c-white); border:1px solid var(--c-border); border-radius:6px; }
.cert-item .cert-code { font-weight:700; font-size:13px; }
.cert-item .cert-detail { font-size:11px; color:var(--c-gray); }
.smtp-field { margin-bottom:14px; }
.smtp-field label { font-weight:600; font-size:13px; display:block; margin-bottom:4px; }
.smtp-field input { width:100%; }
</style>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Configurações do Sistema</span>
    </div>

    <div style="padding:0 24px 24px;">
        <p style="color:var(--c-gray); font-size:13px; margin:16px 0 20px;">Area de configurações avancadas. Somente administradores.</p>

        <!-- Tabs -->
        <div class="config-tabs">
            <button class="config-tab active" data-tab="tipos-documento">Tipos de Documento</button>
            <button class="config-tab" data-tab="tipos-certificado">Tipos de Certificado</button>
            <button class="config-tab" data-tab="ministrantes">Ministrantes</button>
            <button class="config-tab" data-tab="smtp">Configuração SMTP</button>
            <a href="/configuracoes/funcoes" class="config-tab" style="text-decoration:none; display:flex; align-items:center;">NRs por Função</a>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: Tipos de Documento                                       -->
        <!-- ============================================================ -->
        <div class="config-panel active" id="panel-tipos-documento">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <p style="color:var(--c-gray); font-size:13px; margin:0;">Gerencie os tipos de documentos aceitos pelo sistema e suas validades padrao.</p>
                <button class="btn btn-primary btn-sm" onclick="openTipoDocModal()">+ Novo Tipo</button>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Validade</th>
                        <th>Obrigatório</th>
                        <th>Status</th>
                        <th width="120">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiposDocs as $td): ?>
                    <tr style="<?= !$td['ativo'] ? 'opacity:0.5;' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($td['nome']) ?></strong>
                            <?php if (!empty($td['descricao'])): ?>
                                <br><span style="font-size:11px; color:var(--c-gray);"><?= htmlspecialchars($td['descricao']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-info"><?= $categorias[$td['categoria']] ?? $td['categoria'] ?></span></td>
                        <td><?= $td['validade_meses'] ? $td['validade_meses'] . ' meses' : '<span style="color:var(--c-gray)">—</span>' ?></td>
                        <td><?= $td['obrigatorio'] ? '<span style="color:var(--c-success); font-weight:600;">Sim</span>' : '<span style="color:var(--c-gray);">Não</span>' ?></td>
                        <td><span class="badge-status <?= $td['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $td['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick='editTipoDoc(<?= json_encode($td) ?>)'>Editar</button>
                            <form method="POST" action="/configuracoes/tipo-doc/<?= $td['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Excluir tipo de documento?')">
                                <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::get('csrf_token') ?>">
                                <button type="submit" class="btn btn-danger btn-sm">X</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: Tipos de Certificado                                     -->
        <!-- ============================================================ -->
        <div class="config-panel" id="panel-tipos-certificado">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <p style="color:var(--c-gray); font-size:13px; margin:0;">Gerencie os tipos de certificados, conteudos programaticos e validades.</p>
                <button class="btn btn-primary btn-sm" onclick="openTipoCertModal()">+ Novo Tipo</button>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Título</th>
                        <th>Duracao</th>
                        <th>Validade</th>
                        <th>Ministrante</th>
                        <th>Status</th>
                        <th width="80">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiposCerts as $tc): ?>
                    <tr style="<?= !$tc['ativo'] ? 'opacity:0.5;' : '' ?>">
                        <td><strong><?= htmlspecialchars($tc['codigo']) ?></strong></td>
                        <td style="font-size:12px;"><?= htmlspecialchars(mb_strimwidth($tc['titulo'], 0, 60, '...')) ?></td>
                        <td><?= htmlspecialchars($tc['duracao']) ?></td>
                        <td><?= $tc['validade_meses'] ?> meses</td>
                        <td style="font-size:12px;"><?= htmlspecialchars($tc['ministrante_nome'] ?? '—') ?></td>
                        <td><span class="badge-status <?= $tc['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $tc['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                        <td style="white-space:nowrap;">
                            <button class="btn btn-outline btn-sm" onclick="previewCert(<?= $tc['id'] ?>)" title="Visualizar certificado modelo">Ver</button>
                            <button class="btn btn-outline btn-sm" onclick='editTipoCert(<?= json_encode($tc, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: Ministrantes                                             -->
        <!-- ============================================================ -->
        <div class="config-panel" id="panel-ministrantes">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <p style="color:var(--c-gray); font-size:13px; margin:0;">Cadastre os ministrantes (instrutores) que ministram os treinamentos e certificados.</p>
                <button class="btn btn-primary btn-sm" onclick="openMinistranteModal()">+ Novo Ministrante</button>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(400px, 1fr)); gap:12px;">
                <?php foreach ($ministrantes as $m): ?>
                <div class="config-card ministrante-card" style="<?= !$m['ativo'] ? 'opacity:0.5;' : '' ?>">
                    <div class="ministrante-info">
                        <div class="name"><?= htmlspecialchars($m['nome']) ?></div>
                        <div class="detail"><?= htmlspecialchars($m['cargo_titulo']) ?></div>
                        <?php if (!empty($m['registro'])): ?>
                            <div class="detail"><?= htmlspecialchars($m['registro']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span class="badge-status <?= $m['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $m['ativo'] ? 'Ativo' : 'Inativo' ?></span>
                        <button class="btn btn-outline btn-sm" onclick='editMinistrante(<?= json_encode($m) ?>)'>Editar</button>
                        <form method="POST" action="/configuracoes/ministrante/<?= $m['id'] ?>/excluir" style="display:inline;" onsubmit="return confirm('Excluir ministrante?')">
                            <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::get('csrf_token') ?>">
                            <button type="submit" class="btn btn-danger btn-sm">X</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($ministrantes)): ?>
            <div class="empty-state">
                <p>Nenhum ministrante cadastrado.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: SMTP                                                     -->
        <!-- ============================================================ -->
        <div class="config-panel" id="panel-smtp">
            <p style="color:var(--c-gray); font-size:13px; margin:0 0 20px;">Configuração do servidor de email para envio de alertas automáticos de vencimento.</p>

            <form method="POST" action="/configuracoes/smtp" class="config-card" style="max-width:600px;">
                <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::get('csrf_token') ?>">

                <div class="form-row">
                    <div class="form-group" style="flex:3;">
                        <label>Servidor SMTP</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($smtp['host']) ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group" style="flex:1; min-width:100px;">
                        <label>Porta</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($smtp['port']) ?>" placeholder="587">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Usuario SMTP</label>
                        <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($smtp['user']) ?>" placeholder="email@empresa.com">
                    </div>
                    <div class="form-group">
                        <label>Senha SMTP</label>
                        <input type="password" name="smtp_pass" class="form-control" placeholder="<?= !empty($smtp['host']) ? '••••••••' : '' ?>" autocomplete="new-password">
                        <small style="color:var(--c-gray); font-size:11px;">Deixe em branco para manter a senha atual.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do Remetente</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($smtp['from_name']) ?>" placeholder="SESMT TSE">
                    </div>
                    <div class="form-group">
                        <label>Email do Remetente</label>
                        <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($smtp['from_email']) ?>" placeholder="sesmt@tseautomacao.com.br">
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    <button type="button" class="btn btn-outline" onclick="testarSmtp()">Testar Conexão</button>
                </div>
            </form>

            <div id="smtpTestResult" style="margin-top:12px; display:none;" class="config-card"></div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Tipo de Documento                                      -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalTipoDoc" style="display:none;">
    <div class="modal" style="max-width:550px;">
        <div class="modal-header">
            <h3 id="modalTipoDocTitle">Novo Tipo de Documento</h3>
            <button class="modal-close" onclick="closeModal('modalTipoDoc')">&times;</button>
        </div>
        <form method="POST" action="/configuracoes/tipo-doc">
            <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::get('csrf_token') ?>">
            <input type="hidden" name="id" id="tdId" value="0">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="tdNome" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoria *</label>
                        <select name="categoria" id="tdCategoria" class="form-control">
                            <?php foreach ($categorias as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Validade (meses)</label>
                        <input type="number" name="validade_meses" id="tdValidade" class="form-control" min="1" placeholder="Sem validade">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Obrigatório</label>
                        <select name="obrigatorio" id="tdObrigatorio" class="form-control">
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="ativo" id="tdAtivo" class="form-control">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" id="tdDescricao" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalTipoDoc')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Tipo de Certificado                                    -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalTipoCert" style="display:none;">
    <div class="modal" style="max-width:650px;">
        <div class="modal-header">
            <h3 id="modalTipoCertTitle">Novo Tipo de Certificado</h3>
            <button class="modal-close" onclick="closeModal('modalTipoCert')">&times;</button>
        </div>
        <form method="POST" action="/configuracoes/tipo-cert">
            <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::get('csrf_token') ?>">
            <input type="hidden" name="id" id="tcId" value="0">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Código * <small>(ex: NR 10 BASICO)</small></label>
                        <input type="text" name="codigo" id="tcCodigo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="ativo" id="tcAtivo" class="form-control">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Título Completo *</label>
                    <input type="text" name="titulo" id="tcTitulo" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Duracao</label>
                        <input type="text" name="duracao" id="tcDuracao" class="form-control" value="8h" placeholder="8h, 40h, etc.">
                    </div>
                    <div class="form-group">
                        <label>Validade (meses)</label>
                        <input type="number" name="validade_meses" id="tcValidade" class="form-control" value="12" min="1">
                    </div>
                    <div class="form-group">
                        <label>Ministrante</label>
                        <select name="ministrante_id" id="tcMinistrante" class="form-control">
                            <option value="">— Nenhum —</option>
                            <?php foreach ($ministrantes as $m): ?>
                                <?php if ($m['ativo']): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="checkbox" name="tem_anuencia" id="tcAnuencia" value="1"> Tem anuencia</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="tem_diego" id="tcDiego" value="1"> Assinatura Diego</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="conteudo_no_verso" id="tcVerso" value="1"> Conteudo no verso</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Conteudo Programatico <small>(JSON ou texto)</small></label>
                    <textarea name="conteudo_programatico" id="tcConteudo" class="form-control" rows="4" placeholder='["Item 1", "Item 2", ...]'></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalTipoCert')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Ministrante                                            -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalMinistrante" style="display:none;">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3 id="modalMinistranteTitle">Novo Ministrante</h3>
            <button class="modal-close" onclick="closeModal('modalMinistrante')">&times;</button>
        </div>
        <form method="POST" action="/configuracoes/ministrante">
            <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::get('csrf_token') ?>">
            <input type="hidden" name="id" id="minId" value="0">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" id="minNome" class="form-control" required placeholder="Ex: Mariana Toscano Rios">
                </div>
                <div class="form-group">
                    <label>Cargo / Título *</label>
                    <input type="text" name="cargo_titulo" id="minCargo" class="form-control" required placeholder="Ex: Eng. de Seguranca do Trabalho">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Registro Profissional</label>
                        <input type="text" name="registro" id="minRegistro" class="form-control" placeholder="Ex: CREA - 5071365203/SP">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="ativo" id="minAtivo" class="form-control">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalMinistrante')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ========================================================================
// Tab Navigation
// ========================================================================
document.querySelectorAll('.config-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
        // Update URL hash
        history.replaceState(null, null, '#' + tab.dataset.tab);
    });
});

// Restore tab from URL hash
(function() {
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const tab = document.querySelector(`.config-tab[data-tab="${hash}"]`);
        if (tab) tab.click();
    }
})();

// ========================================================================
// Modal helpers
// ========================================================================
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.style.display = 'none';
    });
});

// ========================================================================
// Tipo de Documento
// ========================================================================
function openTipoDocModal() {
    document.getElementById('modalTipoDocTitle').textContent = 'Novo Tipo de Documento';
    document.getElementById('tdId').value = '0';
    document.getElementById('tdNome').value = '';
    document.getElementById('tdCategoria').value = 'outro';
    document.getElementById('tdValidade').value = '';
    document.getElementById('tdObrigatorio').value = '1';
    document.getElementById('tdAtivo').value = '1';
    document.getElementById('tdDescricao').value = '';
    openModal('modalTipoDoc');
}

function editTipoDoc(td) {
    document.getElementById('modalTipoDocTitle').textContent = 'Editar Tipo de Documento';
    document.getElementById('tdId').value = td.id;
    document.getElementById('tdNome').value = td.nome || '';
    document.getElementById('tdCategoria').value = td.categoria || 'outro';
    document.getElementById('tdValidade').value = td.validade_meses || '';
    document.getElementById('tdObrigatorio').value = td.obrigatorio ? '1' : '0';
    document.getElementById('tdAtivo').value = td.ativo ? '1' : '0';
    document.getElementById('tdDescricao').value = td.descricao || '';
    openModal('modalTipoDoc');
}

// ========================================================================
// Tipo de Certificado
// ========================================================================
function openTipoCertModal() {
    document.getElementById('modalTipoCertTitle').textContent = 'Novo Tipo de Certificado';
    document.getElementById('tcId').value = '0';
    document.getElementById('tcCodigo').value = '';
    document.getElementById('tcTitulo').value = '';
    document.getElementById('tcDuracao').value = '8h';
    document.getElementById('tcValidade').value = '12';
    document.getElementById('tcMinistrante').value = '';
    document.getElementById('tcAnuencia').checked = false;
    document.getElementById('tcDiego').checked = false;
    document.getElementById('tcVerso').checked = false;
    document.getElementById('tcConteudo').value = '';
    document.getElementById('tcAtivo').value = '1';
    openModal('modalTipoCert');
}

function editTipoCert(tc) {
    document.getElementById('modalTipoCertTitle').textContent = 'Editar Tipo de Certificado';
    document.getElementById('tcId').value = tc.id;
    document.getElementById('tcCodigo').value = tc.codigo || '';
    document.getElementById('tcTitulo').value = tc.titulo || '';
    document.getElementById('tcDuracao').value = tc.duracao || '8h';
    document.getElementById('tcValidade').value = tc.validade_meses || '12';
    document.getElementById('tcMinistrante').value = tc.ministrante_id || '';
    document.getElementById('tcAnuencia').checked = !!parseInt(tc.tem_anuencia);
    document.getElementById('tcDiego').checked = !!parseInt(tc.tem_diego);
    document.getElementById('tcVerso').checked = !!parseInt(tc.conteudo_no_verso);
    document.getElementById('tcConteudo').value = tc.conteudo_programatico || '';
    document.getElementById('tcAtivo').value = tc.ativo ? '1' : '0';
    openModal('modalTipoCert');
}

// ========================================================================
// Ministrante
// ========================================================================
function openMinistranteModal() {
    document.getElementById('modalMinistranteTitle').textContent = 'Novo Ministrante';
    document.getElementById('minId').value = '0';
    document.getElementById('minNome').value = '';
    document.getElementById('minCargo').value = '';
    document.getElementById('minRegistro').value = '';
    document.getElementById('minAtivo').value = '1';
    openModal('modalMinistrante');
}

function editMinistrante(m) {
    document.getElementById('modalMinistranteTitle').textContent = 'Editar Ministrante';
    document.getElementById('minId').value = m.id;
    document.getElementById('minNome').value = m.nome || '';
    document.getElementById('minCargo').value = m.cargo_titulo || '';
    document.getElementById('minRegistro').value = m.registro || '';
    document.getElementById('minAtivo').value = m.ativo ? '1' : '0';
    openModal('modalMinistrante');
}

// ========================================================================
// SMTP Test
// ========================================================================
function testarSmtp() {
    const email = prompt('Digite um email para teste de conexão:', '');
    if (!email) return;

    const resultDiv = document.getElementById('smtpTestResult');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<span style="color:var(--c-gray);">Testando conexão...</span>';

    fetch('/configuracoes/smtp/testar', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + encodeURIComponent('<?= \App\Core\Session::get('csrf_token') ?>') +
              '&email_teste=' + encodeURIComponent(email)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<span style="color:var(--c-success); font-weight:600;">&#10004; ' + data.message + '</span>';
        } else {
            resultDiv.innerHTML = '<span style="color:var(--c-danger); font-weight:600;">&#10008; ' + data.error + '</span>';
        }
    })
    .catch(() => {
        resultDiv.innerHTML = '<span style="color:var(--c-danger);">Erro ao testar conexão.</span>';
    });
}

function previewCert(tipoCertId) {
    window.open('/configuracoes/preview-certificado/' + tipoCertId, '_blank');
}
</script>
