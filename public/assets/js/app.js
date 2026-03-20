// =============================================
// SESMT TSE - JavaScript Principal
// =============================================

document.addEventListener('DOMContentLoaded', () => {
    // Auto-hide alerts after 5s
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.3s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, 5000);
    });

    // Confirm delete actions
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Modal handling
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modal);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                el.closest('.modal-overlay')?.classList.remove('active');
            }
        });
    });

    // CPF mask
    document.querySelectorAll('[data-mask="cpf"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').slice(0, 11);
            if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            e.target.value = v;
        });
    });

    // Search with debounce
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const form = searchInput.closest('form');
                if (form) form.submit();
            }, 400);
        });
    }
});

// Utility: fetch wrapper
async function apiFetch(url, options = {}) {
    const defaults = {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    };
    const response = await fetch(url, { ...defaults, ...options });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

// =============================================
// Feature 1: Notification Bell
// =============================================
(function() {
    const bell = document.getElementById('notifBell');
    const badge = document.getElementById('notifBadge');
    const dropdown = document.getElementById('notifDropdown');
    const notifList = document.getElementById('notifList');
    const markAllBtn = document.getElementById('notifMarkAllRead');

    if (!bell || !badge || !dropdown) return;

    let dropdownOpen = false;

    function formatTimeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return 'agora';
        if (diffMin < 60) return diffMin + ' min atras';
        const diffHour = Math.floor(diffMin / 60);
        if (diffHour < 24) return diffHour + 'h atras';
        const diffDay = Math.floor(diffHour / 24);
        if (diffDay < 7) return diffDay + 'd atras';
        return date.toLocaleDateString('pt-BR');
    }

    function fetchNotifications() {
        apiFetch('/notificacoes/json')
            .then(data => {
                // Update badge
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }

                // Update dropdown list
                if (data.notificacoes && data.notificacoes.length > 0) {
                    notifList.innerHTML = data.notificacoes.map(n => {
                        const iconColor = n.tipo === 'alerta' ? 'var(--c-warning)' :
                                          (n.tipo === 'erro' || n.tipo === 'vencimento') ? 'var(--c-danger)' :
                                          n.tipo === 'sucesso' ? 'var(--c-accent)' : 'var(--c-link)';
                        return '<a href="' + (n.link || '/notificacoes') + '" ' +
                            'style="display:block; padding:12px 16px; border-bottom:1px solid var(--c-border); text-decoration:none; color:var(--c-text); transition:background 0.1s;" ' +
                            'onmouseover="this.style.background=\'var(--c-bg)\'" onmouseout="this.style.background=\'transparent\'" ' +
                            'data-notif-id="' + n.id + '">' +
                            '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">' +
                            '<strong style="font-size:13px;">' + escapeHtml(n.titulo) + '</strong>' +
                            '<span style="font-size:11px; color:var(--c-gray);">' + formatTimeAgo(n.criado_em) + '</span>' +
                            '</div>' +
                            '<div style="font-size:12px; color:var(--c-gray); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + escapeHtml(n.mensagem) + '</div>' +
                            '</a>';
                    }).join('');
                } else {
                    notifList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--c-gray); font-size:13px;">Nenhuma notificacao nova.</div>';
                }
            })
            .catch(() => {});
    }

    // Toggle dropdown
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownOpen = !dropdownOpen;
        dropdown.style.display = dropdownOpen ? 'block' : 'none';
        if (dropdownOpen) fetchNotifications();
    });

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!bell.contains(e.target)) {
            dropdownOpen = false;
            dropdown.style.display = 'none';
        }
    });

    // Mark all as read
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            fetch('/notificacoes/marcar-todas', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(() => {
                badge.style.display = 'none';
                notifList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--c-gray); font-size:13px;">Nenhuma notificacao nova.</div>';
            }).catch(() => {});
        });
    }

    // Initial fetch and polling every 60s
    fetchNotifications();
    setInterval(fetchNotifications, 60000);
})();

// =============================================
// Feature 2: Dark Mode Toggle
// =============================================
(function() {
    const toggle = document.getElementById('themeToggle');
    const moonIcon = document.getElementById('themeIconMoon');
    const sunIcon = document.getElementById('themeIconSun');

    if (!toggle) return;

    toggle.addEventListener('click', function() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';

        html.setAttribute('data-theme', next);

        if (moonIcon && sunIcon) {
            moonIcon.style.display = next === 'dark' ? 'none' : 'block';
            sunIcon.style.display = next === 'dark' ? 'block' : 'none';
        }

        // Save preference via AJAX
        fetch('/usuarios/tema', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: 'tema=' + encodeURIComponent(next),
        }).catch(() => {});
    });
})();

// =============================================
// Feature 3: Global Search
// =============================================
(function() {
    const input = document.getElementById('globalSearch');
    const resultsDiv = document.getElementById('globalSearchResults');

    if (!input || !resultsDiv) return;

    let debounceTimer = null;

    const categoryLabels = {
        colaboradores: 'Colaboradores',
        clientes: 'Clientes',
        obras: 'Obras',
        documentos: 'Documentos',
        certificados: 'Certificados',
    };

    input.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(function() {
            apiFetch('/busca/json?q=' + encodeURIComponent(q))
                .then(data => {
                    const results = data.results || {};
                    const categories = Object.keys(results);

                    if (categories.length === 0) {
                        resultsDiv.innerHTML = '<div style="padding:14px; text-align:center; color:var(--c-gray); font-size:13px;">Nenhum resultado para "' + escapeHtml(q) + '"</div>';
                        resultsDiv.style.display = 'block';
                        return;
                    }

                    let html = '';
                    categories.forEach(cat => {
                        html += '<div class="search-category">' + (categoryLabels[cat] || cat) + '</div>';
                        results[cat].forEach(item => {
                            html += '<a href="' + escapeHtml(item.link) + '" class="search-item">' +
                                '<strong>' + escapeHtml(item.titulo) + '</strong>' +
                                '<span class="search-sub">' + escapeHtml(item.subtitulo || '') + '</span>' +
                                '</a>';
                        });
                    });

                    html += '<a href="/busca?q=' + encodeURIComponent(q) + '" class="search-item" style="justify-content:center; color:var(--c-link); font-weight:600;">' +
                        'Ver todos os resultados' +
                        '</a>';

                    resultsDiv.innerHTML = html;
                    resultsDiv.style.display = 'block';
                })
                .catch(() => { resultsDiv.style.display = 'none'; });
        }, 300);
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = this.value.trim();
            if (q.length >= 2) {
                window.location.href = '/busca?q=' + encodeURIComponent(q);
            }
        }
        if (e.key === 'Escape') {
            resultsDiv.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });

    // Keyboard shortcut: Ctrl+K or Cmd+K to focus search
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();

// =============================================
// Utility: escape HTML
// =============================================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
