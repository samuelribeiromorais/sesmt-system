<div class="table-container">
    <div class="table-header">
        <span class="table-title">Colaboradores (<?= $total ?>)</span>
        <?php if (!$isReadOnly): ?>
        <a href="/colaboradores/novo" class="btn btn-primary btn-sm">+ Novo</a>
        <?php endif; ?>
        <a href="/exportar/colaboradores?status=<?= urlencode($status ?? '') ?>" class="btn btn-outline btn-sm" title="Exportar Excel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Excel
        </a>
    </div>

    <!-- Search bar -->
    <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-bg);">
        <form method="GET" action="/colaboradores" id="search-form" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <div class="search-box" style="flex:1; min-width:250px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" class="form-control" placeholder="Buscar por nome, matricula ou cargo..." value="<?= htmlspecialchars($search) ?>" id="search-input" autocomplete="off">
            </div>
            <select name="status" class="form-control" style="width:120px;" onchange="this.form.submit()">
                <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                <option value="afastado" <?= $status === 'afastado' ? 'selected' : '' ?>>Afastados</option>
                <option value="todos" <?= $status === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
            <select name="cliente_id" class="form-control" style="width:140px;" onchange="this.form.submit()">
                <option value="">Todos Clientes</option>
                <?php foreach ($clientes ?? [] as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= ($clienteId ?? '') == $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['nome_fantasia']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="obra_id" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="">Todas Obras</option>
                <?php foreach ($obras ?? [] as $ob): ?>
                <option value="<?= $ob['id'] ?>" <?= ($obraId ?? '') == $ob['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ob['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
            <?php if ($search || $status !== 'ativo' || !empty($clienteId) || !empty($obraId)): ?>
            <a href="/colaboradores" class="btn btn-outline btn-sm">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Live search results (dropdown) -->
    <div id="live-results" style="display:none; position:relative;">
        <div id="live-list" style="position:absolute; top:0; left:0; right:0; background:var(--c-white); border:1px solid var(--c-border); border-top:none; box-shadow:0 4px 12px rgba(0,0,0,0.1); max-height:400px; overflow-y:auto; z-index:100;"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:30px;"><input type="checkbox" id="selectAllColab" title="Selecionar todos"></th>
                <th>Nome</th>
                <th>Cargo</th>
                <th>Setor</th>
                <th>Cliente / Obra</th>
                <th>Status</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($colaboradores)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--c-gray);padding:32px;">Nenhum colaborador encontrado.</td></tr>
            <?php else: ?>
            <?php foreach ($colaboradores as $c): ?>
            <tr>
                <td><input type="checkbox" class="colab-bulk-check" value="<?= $c['id'] ?>"></td>
                <td><a href="/colaboradores/<?= $c['id'] ?>" style="color:var(--c-primary);font-weight:600;"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
                <td style="font-size:13px;"><?= htmlspecialchars($c['cargo'] ?? $c['funcao'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($c['setor'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($c['cliente_nome'] ?? '-') ?><?= !empty($c['obra_nome']) ? ' / ' . htmlspecialchars($c['obra_nome']) : '' ?></td>
                <td><span class="badge badge-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
                <td>
                    <a href="/colaboradores/<?= $c['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Load More Button -->
    <?php if ($page < $totalPages): ?>
    <button type="button" class="btn-load-more" id="colabLoadMore"
            data-page="<?= $page ?>"
            data-total-pages="<?= $totalPages ?>"
            data-search="<?= htmlspecialchars($search) ?>"
            data-status="<?= htmlspecialchars($status) ?>">
        Carregar mais
    </button>
    <?php endif; ?>

    <!-- Traditional Pagination (fallback) -->
    <?php if ($totalPages > 1): ?>
    <div id="colabPagination" style="padding:12px 20px; display:flex; justify-content:center; align-items:center; gap:6px;">
        <?php if ($page > 1): ?>
        <a href="/colaboradores?page=1&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-outline btn-sm">&laquo;</a>
        <a href="/colaboradores?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-outline btn-sm">&lsaquo;</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        if ($start > 1) echo '<span style="color:#999;padding:0 4px;">...</span>';
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="/colaboradores?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>"
           class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages) echo '<span style="color:#999;padding:0 4px;">...</span>'; ?>

        <?php if ($page < $totalPages): ?>
        <a href="/colaboradores?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-outline btn-sm">&rsaquo;</a>
        <a href="/colaboradores?page=<?= $totalPages ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-outline btn-sm">&raquo;</a>
        <?php endif; ?>

        <span style="color:#999; font-size:12px; margin-left:12px;">Pagina <?= $page ?> de <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- Bulk Update Bar -->
<div id="bulkBar" style="display:none; position:fixed; bottom:0; left:0; right:0; background:#005e4e; color:white; padding:12px 24px; z-index:100; box-shadow:0 -2px 10px rgba(0,0,0,0.3);">
    <form method="POST" action="/colaboradores/bulk-update" id="bulkForm" style="display:flex; align-items:center; gap:12px; max-width:1200px; margin:0 auto;">
        <?= \App\Core\View::csrfField() ?>
        <div id="bulkIds"></div>
        <span id="bulkCount" style="font-weight:600; min-width:120px;">0 selecionados</span>
        <select name="campo" id="bulkCampo" style="padding:6px 10px; border-radius:4px; border:none; font-size:13px;" required>
            <option value="">-- Campo --</option>
            <option value="cargo">Cargo</option>
            <option value="função">Função</option>
            <option value="setor">Setor</option>
            <option value="status">Status</option>
            <option value="unidade">Unidade</option>
        </select>
        <input type="text" name="valor" id="bulkValor" placeholder="Novo valor..." style="padding:6px 10px; border-radius:4px; border:none; font-size:13px; flex:1; max-width:300px;" required>
        <button type="submit" class="btn btn-sm" style="background:#afd85a; color:#001e21; font-weight:600;">Aplicar</button>
        <button type="button" class="btn btn-sm" style="background:rgba(255,255,255,0.2); color:white;" onclick="limparBulk()">Cancelar</button>
    </form>
</div>

<script>
(function() {
    const input = document.getElementById('search-input');
    const liveResults = document.getElementById('live-results');
    const liveList = document.getElementById('live-list');
    let debounceTimer = null;

    input.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            liveResults.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('/colaboradores?q=' + encodeURIComponent(q) + '&format=json')
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) {
                        liveList.innerHTML = '<div style="padding:12px 16px;color:#999;font-size:13px;">Nenhum resultado para "' + q + '"</div>';
                    } else {
                        liveList.innerHTML = data.map(c =>
                            '<a href="/colaboradores/' + c.id + '" style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--c-border);color:var(--c-text);text-decoration:none;font-size:13px;" onmouseover="this.style.background=\'#f5f5f0\'" onmouseout="this.style.background=\'white\'">' +
                            '<span style="font-weight:600;">' + c.nome_completo + '</span>' +
                            '<span style="color:#999;">' + (c.cargo || '-') + '</span>' +
                            '</a>'
                        ).join('');
                    }
                    liveResults.style.display = 'block';
                })
                .catch(() => { liveResults.style.display = 'none'; });
        }, 500);
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            liveResults.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (!liveResults.contains(e.target) && e.target !== input) {
            liveResults.style.display = 'none';
        }
    });

    // Focus search on page load if no search term
    if (!input.value) input.focus();
})();

// Lazy loading / Load More for colaboradores
(function() {
    const btn = document.getElementById('colabLoadMore');
    if (!btn) return;

    const tbody = document.querySelector('.table-container table tbody');
    let currentPage = parseInt(btn.dataset.page);
    const totalPages = parseInt(btn.dataset.totalPages);
    const searchQ = btn.dataset.search;
    const statusFilter = btn.dataset.status;

    btn.addEventListener('click', function() {
        if (btn.disabled) return;
        currentPage++;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Carregando...';

        const url = '/colaboradores?page=' + currentPage +
                    '&q=' + encodeURIComponent(searchQ) +
                    '&status=' + encodeURIComponent(statusFilter) +
                    '&format=json';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(c => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><a href="/colaboradores/' + c.id + '" style="color:var(--c-primary);font-weight:600;">' + escapeHtml(c.nome_completo) + '</a></td>' +
                            '<td style="font-size:13px;">' + escapeHtml(c.cargo || '-') + '</td>' +
                            '<td style="font-size:13px;">' + escapeHtml(c.setor || '-') + '</td>' +
                            '<td style="font-size:13px;">' + escapeHtml(c.cliente_nome || '-') + '</td>' +
                            '<td><span class="badge badge-' + (c.status || 'ativo') + '">' + ucfirst(c.status || 'ativo') + '</span></td>' +
                            '<td><a href="/colaboradores/' + c.id + '" class="btn btn-outline btn-sm">Ver</a></td>';
                        tbody.appendChild(tr);
                    });
                }

                if (currentPage >= totalPages) {
                    btn.style.display = 'none';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = 'Carregar mais';
                }

                // Hide pagination when using load more
                var pag = document.getElementById('colabPagination');
                if (pag) pag.style.display = 'none';
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = 'Carregar mais';
            });
    });

    function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();

// Bulk update logic
document.getElementById('selectAllColab').addEventListener('change', function() {
    document.querySelectorAll('.colab-bulk-check').forEach(cb => cb.checked = this.checked);
    atualizarBulkBar();
});
document.querySelectorAll('.colab-bulk-check').forEach(cb => cb.addEventListener('change', atualizarBulkBar));

function atualizarBulkBar() {
    const checked = document.querySelectorAll('.colab-bulk-check:checked');
    const bar = document.getElementById('bulkBar');
    const idsDiv = document.getElementById('bulkIds');
    bar.style.display = checked.length > 0 ? 'block' : 'none';
    document.getElementById('bulkCount').textContent = checked.length + ' selecionado' + (checked.length !== 1 ? 's' : '');
    idsDiv.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'colaborador_ids[]'; input.value = cb.value;
        idsDiv.appendChild(input);
    });
}

function limparBulk() {
    document.querySelectorAll('.colab-bulk-check').forEach(cb => cb.checked = false);
    document.getElementById('selectAllColab').checked = false;
    atualizarBulkBar();
}

// When status is selected, show preset options
document.getElementById('bulkCampo').addEventListener('change', function() {
    const valorInput = document.getElementById('bulkValor');
    if (this.value === 'status') {
        valorInput.outerHTML = '<select name="valor" id="bulkValor" style="padding:6px 10px;border-radius:4px;border:none;font-size:13px;flex:1;max-width:300px;" required><option value="ativo">Ativo</option><option value="inativo">Inativo</option><option value="afastado">Afastado</option></select>';
    } else {
        const el = document.getElementById('bulkValor');
        if (el.tagName === 'SELECT') {
            el.outerHTML = '<input type="text" name="valor" id="bulkValor" placeholder="Novo valor..." style="padding:6px 10px;border-radius:4px;border:none;font-size:13px;flex:1;max-width:300px;" required>';
        }
    }
});

document.getElementById('bulkForm').addEventListener('submit', function(e) {
    const count = document.querySelectorAll('.colab-bulk-check:checked').length;
    if (!confirm('Atualizar ' + count + ' colaboradores?')) e.preventDefault();
});
</script>
