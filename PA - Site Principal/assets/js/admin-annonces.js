(function () {
    'use strict';

    const pageSize = 8;
    let currentPage = 1;
    let totalPages  = 1;
    let searchTerm  = '';
    let statusFilter = '';

    const statusLabels = { 0: 'Available', 1: 'Sold', 2: 'Closed' };
    const statusColors = {
        0: { bg: '#d1fae5', text: '#065f46' },
        1: { bg: '#dbeafe', text: '#1e40af' },
        2: { bg: '#f3f4f6', text: '#374151' },
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
    });

    function bindToolbar() {
        document.getElementById('annonce-search')?.addEventListener('input', function () {
            searchTerm = this.value.trim();
            currentPage = 1;
            requestPage(currentPage, true);
        });
        document.getElementById('annonce-status-filter')?.addEventListener('change', function () {
            statusFilter = this.value;
            currentPage = 1;
            requestPage(currentPage, true);
        });
    }

    function getPageFromUrl() {
        const p = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
        return p > 0 ? p : 1;
    }
    function updateUrlPage(page, replace) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        if (replace) {
            history.replaceState(null, '', url);
        } else {
            history.pushState(null, '', url);
        }
    }

    window.addEventListener('popstate', function () {
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
    });

    function requestPage(page, replaceHistory) {
        const container  = document.getElementById('annonces-container');
        const pagination = document.getElementById('annonces-pagination');
        if (!container) return;

        renderSkeletons(container, pageSize);
        if (pagination) pagination.innerHTML = '';

        let url = `annonces-list-api?page=${page}&limit=${pageSize}`;
        if (statusFilter !== '') url += `&status=${encodeURIComponent(statusFilter)}`;

        fetch(url)
            .then(r => r.text())
            .then(text => {
                const data   = text ? JSON.parse(text) : {};
                let annonces = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total  = Number.isFinite(data.total) ? data.total : annonces.length;
                totalPages   = total > 0 ? Math.ceil(total / pageSize) : 1;

                // Client-side title search
                if (searchTerm) {
                    const s = searchTerm.toLowerCase();
                    annonces = annonces.filter(a => (a.title ?? '').toLowerCase().includes(s));
                }

                container.innerHTML = '';

                if (annonces.length === 0) {
                    container.innerHTML = '<p class="empty-list">No annonces found.</p>';
                    hideInitialLoader();
                    updateStats(total);
                    if (pagination) pagination.innerHTML = '';
                    return;
                }

                if (page > totalPages) {
                    currentPage = totalPages;
                    updateUrlPage(currentPage, true);
                    requestPage(currentPage, true);
                    return;
                }

                currentPage = page;
                updateUrlPage(currentPage, replaceHistory);

                renderAnnonces(annonces, container);
                resolveUsernames(annonces);
                renderPagination(pagination, total);
                hideInitialLoader();
                updateStats(total);
            })
            .catch(err => {
                console.error('Failed to load annonces', err);
                container.innerHTML = '<p class="error-message">Unable to load annonces.</p>';
                hideInitialLoader();
            });
    }

    function renderAnnonces(annonces, container) {
        annonces.forEach(ann => {
            const card = document.createElement('div');
            card.className = 'service-item';
            card.dataset.id = ann.id;

            const st      = ann.status ?? 0;
            const sc      = statusColors[st] ?? statusColors[0];
            const label   = statusLabels[st] ?? `Status ${st}`;
            const price   = ann.price > 0
                ? `€${parseFloat(ann.price).toFixed(2)}`
                : '<span style="color:#16a34a;">Free</span>';
            const score   = ann.upcycling_score
                ? `${parseFloat(ann.upcycling_score).toFixed(2)} pts`
                : '—';
            const dateStr = ann.created_at
                ? new Date(ann.created_at).toLocaleDateString('fr-FR')
                : '—';

            card.innerHTML = `
                <div class="service-header" style="align-items:flex-start;gap:10px;">
                    <div style="flex:1;">
                        <h3 style="margin:0 0 4px;">${escHtml(ann.title)}</h3>
                        <p style="margin:0;font-size:.85rem;color:#6b7280;">
                            <i class="fa-solid fa-user"></i> <span data-user-id="${escHtml(ann.user_id ?? '')}">${escHtml(ann.user_id ?? '—')}</span>
                            &nbsp;·&nbsp;
                            <i class="fa-solid fa-calendar"></i> ${dateStr}
                        </p>
                    </div>
                    <span class="badge" style="background:${sc.bg};color:${sc.text};padding:3px 12px;border-radius:20px;font-size:.8rem;white-space:nowrap;">${label}</span>
                </div>
                <p class="service-description" style="margin:6px 0;font-size:.9rem;">
                    ${escHtml((ann.description ?? '').substring(0, 200))}${(ann.description ?? '').length > 200 ? '…' : ''}
                </p>
                <div class="service-meta" style="display:flex;gap:14px;flex-wrap:wrap;font-size:.82rem;color:#6b7280;margin-bottom:10px;">
                    <span><i class="fa-solid fa-euro-sign"></i> ${price}</span>
                    <span><i class="fa-solid fa-recycle"></i> Score: ${score}</span>
                    <span><i class="fa-solid fa-eye"></i> ${ann.view_count ?? 0} views</span>
                </div>
                <div class="service-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="../common/offer?uuid=${encodeURIComponent(ann.id)}"
                       class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                        <i class="fa-solid fa-eye"></i> View
                    </a>
                    <button class="ann-delete-btn"
                        style="background:#ef4444;color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>`;

            card.querySelector('.ann-delete-btn').addEventListener('click', () => confirmDelete(ann));
            container.appendChild(card);
        });
    }

    function renderSkeletons(container, n) {
        container.innerHTML = '';
        for (let i = 0; i < n; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton-service-item';
            sk.innerHTML = `
                <div class="skeleton-service-header">
                    <div class="skeleton skeleton-title" style="width:55%;"></div>
                    <div class="skeleton" style="width:70px;height:22px;border-radius:20px;"></div>
                </div>
                <div class="skeleton skeleton-description"></div>
                <div class="skeleton skeleton-description" style="width:60%;"></div>
                <div style="display:flex;gap:8px;margin-top:10px;">
                    <div class="skeleton skeleton-button" style="width:70px;height:32px;"></div>
                    <div class="skeleton skeleton-button" style="width:80px;height:32px;"></div>
                </div>`;
            container.appendChild(sk);
        }
    }

    function renderPagination(pagination, total) {
        if (!pagination) return;
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        const mkBtn = (label, page, active, disabled) => {
            const btn = document.createElement('button');
            btn.innerHTML = label;
            btn.style.cssText = `padding:6px 12px;border-radius:8px;border:1px solid #d1d5db;cursor:${disabled ? 'default' : 'pointer'};
                background:${active ? '#7c3aed' : '#fff'};color:${active ? '#fff' : (disabled ? '#9ca3af' : '#374151')};font-weight:${active ? '600' : '400'};`;
            if (!disabled && !active) {
                btn.addEventListener('click', () => {
                    currentPage = page;
                    requestPage(currentPage, false);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            btn.disabled = disabled;
            return btn;
        };

        pagination.appendChild(mkBtn('<i class="fa-solid fa-chevron-left"></i>', currentPage - 1, false, currentPage === 1));

        const pages = buildPageRange(currentPage, totalPages);
        pages.forEach(p => {
            if (p === '…') {
                const span = document.createElement('span');
                span.textContent = '…';
                span.style.cssText = 'padding:6px 4px;color:#6b7280;';
                pagination.appendChild(span);
            } else {
                pagination.appendChild(mkBtn(p, p, p === currentPage, false));
            }
        });

        pagination.appendChild(mkBtn('<i class="fa-solid fa-chevron-right"></i>', currentPage + 1, false, currentPage === totalPages));
    }

    function buildPageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = [];
        pages.push(1);
        if (current > 3)       pages.push('…');
        for (let p = Math.max(2, current - 1); p <= Math.min(total - 1, current + 1); p++) pages.push(p);
        if (current < total - 2) pages.push('…');
        pages.push(total);
        return pages;
    }

    function hideInitialLoader() {
        const loader      = document.getElementById('initial-loader');
        const mainContent = document.getElementById('main-content');
        if (loader)      { loader.style.display = 'none'; loader.setAttribute('aria-hidden', 'true'); }
        if (mainContent) mainContent.style.visibility = 'visible';
    }

    function updateStats(total) {
        const box = document.getElementById('annonce-stats');
        if (!box) return;
        const filter = statusFilter !== ''
            ? statusLabels[parseInt(statusFilter, 10)] ?? 'Filtered'
            : 'Total';
        box.innerHTML = `<span style="background:#ede9fe;color:#6d28d9;padding:4px 14px;border-radius:20px;font-size:.85rem;">
            <i class="fa-solid fa-layer-group"></i> ${total} ${filter.toLowerCase()} annonce${total !== 1 ? 's' : ''}
        </span>`;
    }

    function confirmDelete(ann) {
        const body    = document.getElementById('annonce-confirm-body');
        const actions = document.getElementById('annonce-confirm-actions');

        body.innerHTML = `
            <p>Are you sure you want to permanently delete:</p>
            <p style="font-weight:600;color:#111827;">"${escHtml(ann.title)}"</p>
            <p style="font-size:.875rem;color:#6b7280;">This action cannot be undone.</p>`;

        actions.innerHTML = '';

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn-secondary';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.onclick = () => hideModal('annonce-confirm-modal');

        const deleteBtn = document.createElement('button');
        deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete permanently';
        deleteBtn.style.cssText = 'background:#ef4444;color:#fff;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:600;margin-left:8px;';
        deleteBtn.onclick = () => {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting…';
            fetch(`annonce-delete-api?id=${encodeURIComponent(ann.id)}`, { method: 'DELETE' })
                .then(r => r.text())
                .then(text => {
                    const data = text ? JSON.parse(text) : {};
                    if (data.error) throw new Error(data.error);
                    hideModal('annonce-confirm-modal');
                    const card = document.querySelector(`.service-item[data-id="${ann.id}"]`);
                    if (card) {
                        card.style.transition = 'opacity .25s, transform .25s';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(.97)';
                        setTimeout(() => {
                            card.remove();
                            const remaining = document.querySelectorAll('#annonces-container .service-item').length;
                            if (remaining === 0) {
                                requestPage(currentPage > 1 ? currentPage - 1 : 1, true);
                            } else {
                                const statsEl = document.getElementById('annonce-stats');
                                if (statsEl) {
                                    const cur = parseInt(statsEl.querySelector('span')?.textContent ?? '0', 10);
                                    if (!isNaN(cur) && cur > 0) updateStats(cur - 1);
                                }
                            }
                        }, 250);
                    } else {
                        requestPage(currentPage, true);
                    }
                })
                .catch(err => {
                    console.error('Delete failed:', err);
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete permanently';
                });
        };

        actions.appendChild(cancelBtn);
        actions.appendChild(deleteBtn);
        showModal('annonce-confirm-modal');
    }

    function resolveUsernames(annonces) {
        const unique = [...new Set(annonces.map(a => a.user_id).filter(Boolean))];
        unique.forEach(uid => {
            fetch(`user-get-api?id=${encodeURIComponent(uid)}`)
                .then(r => r.text())
                .then(text => {
                    const data = text ? JSON.parse(text) : {};
                    const name = data.username || data.Username || null;
                    if (!name) return;
                    document.querySelectorAll(`[data-user-id="${CSS.escape(uid)}"]`).forEach(el => {
                        el.textContent = name;
                    });
                })
                .catch(() => {});
        });
    }

    function showModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add('is-open');
        m.setAttribute('aria-hidden', 'false');
    }
    function hideModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('annonce-confirm-close')?.addEventListener('click', function () {
        hideModal('annonce-confirm-modal');
    });
    document.getElementById('annonce-confirm-modal')?.addEventListener('click', function (e) {
        if (e.target === this) hideModal('annonce-confirm-modal');
    });

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }
})();

