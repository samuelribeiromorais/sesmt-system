<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatorio por Colaborador</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Gera um relatorio completo com todos os documentos e certificados do colaborador.</p>
            <form method="GET" action="" id="form-rel-colab">
                <div class="form-group">
                    <label>Selecione o Colaborador</label>
                    <select class="form-control" onchange="if(this.value) window.location='/relatorios/colaborador/'+this.value">
                        <option value="">-- Selecionar --</option>
                        <?php foreach ($colaboradores as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatorio por Cliente/Obra</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Gera um relatorio de conformidade de todos os colaboradores alocados no cliente.</p>
            <form>
                <div class="form-group">
                    <label>Selecione o Cliente</label>
                    <select class="form-control" onchange="if(this.value) window.location='/relatorios/cliente/'+this.value">
                        <option value="">-- Selecionar --</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nome_fantasia'] ?? $cl['razao_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Obras Section -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:24px;">
    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatorio por Obra</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Gera um relatorio de conformidade de todos os colaboradores alocados na obra, verificando requisitos do cliente.</p>
            <form>
                <div class="form-group">
                    <label>Selecione a Obra</label>
                    <select class="form-control" onchange="if(this.value) window.location='/relatorios/obra/'+this.value">
                        <option value="">-- Selecionar --</option>
                        <?php if (!empty($obras)): ?>
                        <?php foreach ($obras as $ob): ?>
                        <option value="<?= $ob['id'] ?>"><?= htmlspecialchars($ob['nome']) ?> - <?= htmlspecialchars($ob['local_obra'] ?? '') ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatorios Mensais</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Visualize os relatorios mensais de vencimento gerados automaticamente pelo sistema.</p>
            <a href="/relatorios/mensal" class="btn btn-primary">Ver Relatorios Mensais</a>
        </div>
    </div>
</div>
