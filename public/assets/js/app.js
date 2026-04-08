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

    // Toggle dropdown on bell click
    bell.addEventListener('click', function(e) {
        e.preventDefault();
        dropdownOpen = !dropdownOpen;
        dropdown.style.display = dropdownOpen ? 'block' : 'none';
        if (dropdownOpen) fetchNotifications();
    });

    // Close dropdown on outside mousedown (fires before click, avoids conflicts)
    document.addEventListener('mousedown', function(e) {
        if (dropdownOpen && !bell.contains(e.target) && !dropdown.contains(e.target)) {
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
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        fetch('/usuarios/tema', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: 'tema=' + encodeURIComponent(next) + '&_csrf_token=' + encodeURIComponent(csrfToken),
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
            fetch('/busca/json?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                    var results = data.results || {};
                    var categories = Object.keys(results);

                    if (categories.length === 0) {
                        resultsDiv.innerHTML = '<div style="padding:14px; text-align:center; color:var(--c-gray); font-size:13px;">Nenhum resultado para "' + escapeHtml(q) + '"</div>';
                        resultsDiv.style.display = 'block';
                        return;
                    }

                    var html = '';
                    categories.forEach(function(cat) {
                        html += '<div class="search-category">' + (categoryLabels[cat] || cat) + '</div>';
                        results[cat].forEach(function(item) {
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
                .catch(function(err) { console.error('Busca erro:', err); resultsDiv.style.display = 'none'; });
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

// =============================================
// Feature 4: Loading Overlay
// =============================================
(function() {
    // Create loading overlay element
    var overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(overlay);

    // Show loading on form submit (except search forms)
    document.querySelectorAll('form').forEach(function(form) {
        if (form.querySelector('[type="search"]') || form.closest('.search-box')) return;
        form.addEventListener('submit', function() {
            var btn = form.querySelector('[type="submit"]');
            if (btn) btn.classList.add('btn-loading');
            // Show overlay for file uploads
            if (form.querySelector('input[type="file"]')) {
                overlay.classList.add('active');
            }
        });
    });

    // Show loading on navigation links that trigger actions
    window.showLoading = function() {
        overlay.classList.add('active');
    };
    window.hideLoading = function() {
        overlay.classList.remove('active');
        document.querySelectorAll('.btn-loading').forEach(function(b) { b.classList.remove('btn-loading'); });
    };
})();

// =============================================
// Feature 5: Toast Notifications
// =============================================
(function() {
    // Create toast container
    var container = document.createElement('div');
    container.className = 'toast-container';
    container.id = 'toastContainer';
    document.body.appendChild(container);

    window.showToast = function(message, type, duration) {
        type = type || 'success';
        duration = duration || 4000;
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        var icon = type === 'success' ? '✓' : type === 'error' ? '✗' : '⚠';
        toast.innerHTML = '<span style="font-weight:700;font-size:16px;">' + icon + '</span>' +
            '<span>' + escapeHtml(message) + '</span>' +
            '<button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
        container.appendChild(toast);
        setTimeout(function() {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() { toast.remove(); }, 300);
        }, duration);
    };

    // Convert existing flash alerts to toasts
    document.querySelectorAll('.alert').forEach(function(alert) {
        var type = alert.classList.contains('alert-error') ? 'error' :
                   alert.classList.contains('alert-warning') ? 'warning' : 'success';
        showToast(alert.textContent.trim(), type, 5000);
        alert.remove();
    });
})();

// =============================================
// Feature 6: Bulk Actions
// =============================================
(function() {
    // Only activate on pages with tables that have checkboxes
    var tables = document.querySelectorAll('table');
    tables.forEach(function(table) {
        var checkboxes = table.querySelectorAll('.row-checkbox');
        if (checkboxes.length === 0) return;

        var bulkBar = table.closest('.table-container')?.querySelector('.bulk-bar');
        if (!bulkBar) return;

        var selectAll = table.querySelector('.select-all');
        var countSpan = bulkBar.querySelector('.bulk-count');

        function updateBulkBar() {
            var checked = table.querySelectorAll('.row-checkbox:checked');
            if (checked.length > 0) {
                bulkBar.classList.add('active');
                if (countSpan) countSpan.textContent = checked.length + ' selecionado(s)';
            } else {
                bulkBar.classList.remove('active');
            }
        }

        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', updateBulkBar);
        });

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                updateBulkBar();
            });
        }
    });

    // Export selected IDs
    window.bulkExport = function(tableId) {
        var ids = [];
        document.querySelectorAll('#' + tableId + ' .row-checkbox:checked').forEach(function(cb) {
            ids.push(cb.value);
        });
        if (ids.length === 0) { showToast('Selecione pelo menos um item.', 'warning'); return; }
        window.location.href = '/exportar/documentos?ids=' + ids.join(',');
    };

    // Delete selected
    window.bulkDelete = function(tableId) {
        var ids = [];
        document.querySelectorAll('#' + tableId + ' .row-checkbox:checked').forEach(function(cb) {
            ids.push(cb.value);
        });
        if (ids.length === 0) { showToast('Selecione pelo menos um item.', 'warning'); return; }
        if (!confirm('Excluir ' + ids.length + ' documento(s)? Esta acao nao pode ser desfeita.')) return;
        showLoading();
        fetch('/documentos/excluir-lote', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ ids: ids })
        }).then(function(r) { return r.json(); })
          .then(function(data) {
              hideLoading();
              if (data.success) {
                  showToast(data.message || ids.length + ' documento(s) excluido(s).', 'success');
                  setTimeout(function() { window.location.reload(); }, 1000);
              } else {
                  showToast(data.error || 'Erro ao excluir.', 'error');
              }
          }).catch(function() { hideLoading(); showToast('Erro de conexao.', 'error'); });
    };
})();

// =============================================
// Feature 7: Search Feedback
// =============================================
(function() {
    var globalInput = document.getElementById('globalSearch');
    var resultsDiv = document.getElementById('globalSearchResults');
    if (!globalInput || !resultsDiv) return;

    // Add searching indicator
    var originalPlaceholder = globalInput.placeholder;
    var searchObserver = new MutationObserver(function() {
        if (resultsDiv.style.display === 'block' && resultsDiv.innerHTML.includes('Carregando')) {
            globalInput.placeholder = 'Buscando...';
        } else {
            globalInput.placeholder = originalPlaceholder;
        }
    });
    searchObserver.observe(resultsDiv, { attributes: true, childList: true });
})();

// =============================================
// Feature 8: Keyboard Shortcuts Help
// =============================================
(function() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+K / Cmd+K = Focus search (already implemented)
        // Escape = Close modals/dropdowns
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
                m.classList.remove('active');
            });
        }
    });
})();
