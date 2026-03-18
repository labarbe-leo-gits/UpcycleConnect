(function () {
    'use strict';

    const PAGE_SIZE = 8;
    let allTypes       = [];
    let filtered       = [];
    let currentPage    = 1;
    let searchTerm     = '';
    let sortFilter     = 'name';
    let editId         = null;
    let deleteId       = null;

    const detailsState = {
        typeId: null,
        page: 1,
        limit: 5,
        search: '',
        total: 0
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();

        const params = new URLSearchParams(window.location.search);
        if (params.has('search')) {
            searchTerm = params.get('search') || '';
            const inp = document.getElementById('prestations-search');
            if (inp) inp.value = searchTerm;
        }
        if (params.has('sort')) {
            sortFilter = params.get('sort') || sortFilter;
        }
        if (params.has('page')) {
            const p = parseInt(params.get('page'), 10);
            if (!isNaN(p) && p > 0) currentPage = p;
        }

        loadTypes();
    });

    function bindToolbar() {
        document.getElementById('create-type-btn')
            ?.addEventListener('click', openCreateForm);

        document.getElementById('prestations-search')
            ?.addEventListener('input', function () {
                currentPage = 1;
                applyFilter(this.value.trim());
            });

        const sortSel = document.getElementById('prestations-sort-filter');
        if (sortSel) {
            sortSel.value = sortFilter;
            sortSel.addEventListener('change', function () {
                sortFilter = this.value;
                applyFilter(searchTerm);
            });
        }

        document.getElementById('type-form-modal-close')
            ?.addEventListener('click', () => hideModal('type-form-modal'));
        document.getElementById('type-form-cancel')
            ?.addEventListener('click', () => hideModal('type-form-modal'));

        document.getElementById('type-confirm-close')
            ?.addEventListener('click', () => hideModal('type-confirm-modal'));
        document.getElementById('type-confirm-cancel')
            ?.addEventListener('click', () => hideModal('type-confirm-modal'));
        document.getElementById('type-confirm-delete')
            ?.addEventListener('click', executeDelete);

        document.getElementById('type-details-close')
            ?.addEventListener('click', () => hideModal('type-details-modal'));

        let _detailSearchTimer = null;
        document.getElementById('type-details-search-input')
            ?.addEventListener('input', function () {
                const val = this.value.trim();
                clearTimeout(_detailSearchTimer);
                _detailSearchTimer = setTimeout(() => {
                    detailsState.search = val;
                    detailsState.page = 1;
                    loadDetailsPage(false);
                }, 300);
            });

        document.getElementById('type-details-load-more')
            ?.addEventListener('click', function () {
                loadDetailsPage(true);
            });

        document.querySelectorAll('.add-modal').forEach(m => {
            m.addEventListener('click', function (e) {
                if (e.target === this) hideModal(this.id);
            });
        });
    }

    function loadTypes() {
        const container = document.getElementById('prestations-container');
        renderSkeletons(container, 6);

        fetch('type-prestations-list-api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                const items = Array.isArray(data) ? data
                    : (data && Array.isArray(data.items)) ? data.items
                    : [];
                allTypes = items;
                filtered = items.slice();
                currentPage = 1;
                if (searchTerm || sortFilter !== 'name') {
                    applyFilter(searchTerm);
                } else {
                    renderPage();
                }
            })
            .catch(err => {
                console.error('Failed to load prestation types', err);
                container.innerHTML = '<p class="error-message">Unable to load types.</p>';
                document.getElementById('prestations-pagination').innerHTML = '';
            });
    }

    let _filterTimer = null;
    function applyFilter(search) {
        searchTerm = search;
        updateUrlParams();
        const container = document.getElementById('prestations-container');
        renderSkeletons(container, Math.min(allTypes.length || 6, 6));
        document.getElementById('prestations-pagination').innerHTML = '';

        clearTimeout(_filterTimer);
        _filterTimer = setTimeout(() => {
            const q = search.toLowerCase();
            filtered = allTypes.filter(t => (t.name || '').toLowerCase().includes(q));

            if (sortFilter === 'name') {
                filtered.sort((a,b)=> (a.name||'').localeCompare(b.name||'',undefined,{sensitivity:'base'}));
            } else if (sortFilter === 'created') {
                filtered.sort((a,b)=> (b.created_at||'').localeCompare(a.created_at||''));
            } else if (sortFilter === 'created_asc') {
                filtered.sort((a,b)=> (a.created_at||'').localeCompare(b.created_at||''));
            }
            renderPage();
        }, 200);
    }

    function renderPage() {
        updateUrlParams();
        const container  = document.getElementById('prestations-container');
        const pagination = document.getElementById('prestations-pagination');

        container.innerHTML  = '';
        pagination.innerHTML = '';

        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        if (filtered.length === 0) {
            container.innerHTML = '<p class="empty-list">No types found.</p>';
            return;
        }

        const start = (currentPage - 1) * PAGE_SIZE;
        filtered.slice(start, start + PAGE_SIZE).forEach(t => container.appendChild(buildCard(t)));
        renderPagination(pagination, totalPages);
    }

    function buildCard(t) {
        const card = document.createElement('div');
        card.className  = 'service-item';
        card.dataset.id = t.id;

        card.innerHTML = `
            <div class="service-header">
                <i class="fa-solid fa-tag" style="color:#6b7280;font-size:1.1rem;flex-shrink:0;"></i>
                <h3 style="margin:0 0 0 8px;flex:1;">${escHtml(t.name || '')}</h3>
            </div>
            <div class="service-actions" style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn-secondary icon-only pt-details-btn" data-id="${escHtml(t.id)}" title="Show details">
                    <i class="fa-solid fa-chart-bar"></i>
                </button>
                <button class="btn-secondary icon-only pt-edit-btn" data-id="${escHtml(t.id)}" title="Edit">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button class="btn-danger icon-only pt-delete-btn" data-id="${escHtml(t.id)}" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>`;

        card.querySelector('.pt-details-btn').addEventListener('click', () => openDetails(t));
        card.querySelector('.pt-edit-btn').addEventListener('click', () => openEditForm(t));
        card.querySelector('.pt-delete-btn').addEventListener('click', () => confirmDelete(t));

        return card;
    }

    function renderSkeletons(container, n) {
        const tpl =
            '<div class="skeleton-service-item">' +
                '<div class="skeleton-service-header">' +
                    '<div class="skeleton skeleton-title" style="flex:1;"></div>' +
                '</div>' +
                '<div class="skeleton-buttons">' +
                    '<div class="skeleton skeleton-button"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                '</div>' +
            '</div>';
        container.innerHTML = Array(n).fill(tpl).join('');
    }

    function renderPagination(container, totalPages) {
        if (totalPages <= 1) return;

        const wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap;';

        wrap.appendChild(makePageBtn('‹', currentPage === 1, () => { currentPage--; renderPage(); }));

        for (let p = 1; p <= totalPages; p++) {
            const btn = makePageBtn(String(p), false, (function(pg) {
                return function() { currentPage = pg; renderPage(); };
            }(p)));
            if (p === currentPage) {
                btn.style.cssText += ';background:#10b981;color:#fff;border-color:#10b981;font-weight:700;';
            }
            wrap.appendChild(btn);
        }

        wrap.appendChild(makePageBtn('›', currentPage === totalPages, () => { currentPage++; renderPage(); }));
        container.appendChild(wrap);
    }

    function makePageBtn(label, disabled, onClick) {
        const btn = document.createElement('button');
        btn.textContent   = label;
        btn.disabled      = disabled;
        btn.className     = 'btn-secondary';
        btn.style.cssText = 'min-width:36px;padding:4px 10px;font-size:.85rem;';
        if (!disabled) btn.addEventListener('click', onClick);
        return btn;
    }

    function openCreateForm() {
        editId = null;
        const form = document.getElementById('type-form');
        if (form) form.reset();
        document.getElementById('type-form-title').textContent = 'Add prestation type';
        showModal('type-form-modal');
    }

    function openEditForm(t) {
        editId = t.id;
        const form = document.getElementById('type-form');
        if (form) {
            form.reset();
            form.querySelector('#type-name').value = t.name || '';
        }
        document.getElementById('type-form-title').textContent = 'Edit prestation type';
        showModal('type-form-modal');
    }

    document.getElementById('type-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const errorBox = document.getElementById('type-form-error');
        if (errorBox) { errorBox.style.display = 'none'; errorBox.textContent = ''; }
        const name = this.querySelector('#type-name').value.trim();
        if (!name) {
            if (errorBox) { errorBox.textContent = 'Name is required'; errorBox.style.display = 'block'; }
            return;
        }
        const submitBtn = document.getElementById('type-form-submit');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

        const url = editId ? `type-prestation-update-api.php?id=${encodeURIComponent(editId)}` : 'type-prestation-create-api.php';
        const method = editId ? 'PATCH' : 'POST';
        fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ name })
        })
            .then(r => r.text())
            .then(text => {
                let data;
                const trimmed = text.trim();
                try {
                    data = trimmed ? JSON.parse(trimmed) : {};
                } catch (ex) {
                    console.error('failed to parse type response', ex, text);
                    data = { error: 'Invalid response from server', body: trimmed };
                }
                if (data.error) throw new Error(data.error);
                hideModal('type-form-modal');
                loadTypes();
            })
            .catch(err => {
                if (errorBox) { errorBox.textContent = err.message || 'An error occurred.'; errorBox.style.display = 'block'; }
            })
            .finally(() => {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save'; }
            });
    });

    function confirmDelete(t) {
        deleteId = t.id;
        const body = document.getElementById('type-confirm-name');
        if (body) body.textContent = t.name;
        showModal('type-confirm-modal');
    }

    function executeDelete() {
        if (!deleteId) return;
        const btn = document.getElementById('type-confirm-delete');
        const errBox = document.getElementById('type-confirm-error');
        if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
        btn.disabled = true;
        btn.textContent = 'Deleting…';
        fetch(`type-prestation-delete-api.php?id=${encodeURIComponent(deleteId)}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(text => {
                let data = {};
                const trimmed = text.trim();
                if (trimmed) {
                    try {
                        data = JSON.parse(trimmed);
                    } catch (ex) {
                        console.warn('delete response not json', ex, text);
                    }
                }
                if (data && data.error) throw new Error(data.error);
                hideModal('type-confirm-modal');
                loadTypes();
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
            })
            .catch(err => {
                const msg = 'Delete failed: ' + (err.message || 'Unknown error');
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.style.display = 'block';
                } else {
                    alert(msg);
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
            });
    }

    function openDetails(t) {
        detailsState.typeId = t.id;
        detailsState.page = 1;
        detailsState.search = '';
        detailsState.total = 0;
        const title = document.getElementById('type-details-title');
        if (title) title.textContent = `Statistics for "${t.name || ''}"`;
        document.getElementById('type-details-search-input').value = '';
        document.getElementById('type-details-stats').textContent = '';
        document.querySelector('#type-details-table tbody').innerHTML = '';
        document.getElementById('type-details-load-more').style.display = 'none';
        showModal('type-details-modal');
        loadDetailsStats();
        loadDetailsPage(false);
    }

    function loadDetailsStats() {
        fetch(`services-list-api.php?page=1&limit=1&type=${encodeURIComponent(detailsState.typeId)}`)
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : {};
                const total = Number.isFinite(data.total) ? data.total : (Array.isArray(data) ? data.length : 0);
                detailsState.total = total;
                const statsEl = document.getElementById('type-details-stats');
                if (statsEl) statsEl.textContent = `Total services: ${total}`;
            })
            .catch(err => {
                console.error('stats error', err);
                const statsEl = document.getElementById('type-details-stats');
                if (statsEl) statsEl.textContent = 'Error loading stats';
            });
    }

    function renderDetailSkeletons(n) {
        const tbody = document.querySelector('#type-details-table tbody');
        if (!tbody) return;
        const tpl = `
            <tr>
                <td colspan="3" style="padding:8px;">
                    <div class="skeleton" style="height:18px;width:100%;border-radius:6px;"></div>
                </td>
            </tr>
        `;
        tbody.innerHTML = Array(n).fill(tpl).join('');
    }

    function loadDetailsPage(append) {
        const tbody = document.querySelector('#type-details-table tbody');
        const moreBtn = document.getElementById('type-details-load-more');
        if (!append) {
            if (tbody) tbody.innerHTML = '';
            detailsState.page = 1;
            // show skeletons while fetching
            renderDetailSkeletons(3);
        }
        const urlParts = [];
        urlParts.push(`page=${detailsState.page}`);
        urlParts.push(`limit=${detailsState.limit}`);
        urlParts.push(`type=${encodeURIComponent(detailsState.typeId)}`);
        if (detailsState.search) urlParts.push(`search=${encodeURIComponent(detailsState.search)}`);
        const url = `services-list-api.php?${urlParts.join('&')}`;
        fetch(url)
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : {};
                let services = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total = Number.isFinite(data.total) ? data.total : services.length;
                detailsState.total = total;

                if (!append && tbody) tbody.innerHTML = '';
                services.forEach(s => {
                    const tr = document.createElement('tr');

                    let dateVal = s.event_date || s.service_date || '';
                    if (dateVal) {
                        const d = new Date(dateVal);
                        if (!isNaN(d)) dateVal = d.toLocaleDateString('en-GB');
                    }

                    let priceVal = '';
                    if (s.price != null && s.price !== '') {
                        const num = Number(s.price);
                        if (!isNaN(num)) {
                            priceVal = num.toLocaleString('en-GB', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2 });
                        }
                    }
                    tr.innerHTML = `
                        <td style="padding:8px;border-bottom:1px solid #e5e7eb;">${escHtml(s.name || '')}</td>
                        <td style="padding:8px;border-bottom:1px solid #e5e7eb;">${escHtml(dateVal)}</td>
                        <td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">${escHtml(priceVal)}</td>
                    `;
                    tbody.appendChild(tr);
                });
                detailsState.page++;
                if (moreBtn) {
                    moreBtn.style.display = (detailsState.page - 1) * detailsState.limit < detailsState.total ? 'inline-block' : 'none';
                    moreBtn.disabled = false;
                }
            })
            .catch(err => {
                console.error('detail list error', err);
                if (!append && tbody) tbody.innerHTML = '<tr><td colspan="3" style="padding:8px;color:#ef4444;">Unable to load services.</td></tr>';
                if (moreBtn) moreBtn.style.display = 'none';
            });
    }

    function showModal(id) {
        const m = document.getElementById(id);
        if (m) { m.classList.add('is-open'); m.setAttribute('aria-hidden', 'false'); }
    }

    function hideModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
    }

    function updateUrlParams() {
        const url = new URL(window.location.href);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        if (sortFilter) {
            url.searchParams.set('sort', sortFilter);
        } else {
            url.searchParams.delete('sort');
        }
        if (currentPage && currentPage > 1) {
            url.searchParams.set('page', currentPage);
        } else {
            url.searchParams.delete('page');
        }
        window.history.replaceState({}, '', url.toString());
    }

    function escHtml(str) {
        if (str === undefined || str === null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

})();
