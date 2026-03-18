(function () {
    'use strict';

    const PAGE_SIZE = 8;
    let currentPage = 1;
    let totalItems = 0;
    let searchTerm = '';
    let sortFilter = 'name';
    let editId = null;
    let deleteId = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();

        const params = new URLSearchParams(window.location.search);
        if (params.has('search')) {
            searchTerm = params.get('search') || '';
            const inp = document.getElementById('categories-search');
            if (inp) inp.value = searchTerm;
        }
        if (params.has('sort')) {
            sortFilter = params.get('sort') || sortFilter;
        }
        if (params.has('page')) {
            const p = parseInt(params.get('page'), 10);
            if (!isNaN(p) && p > 0) currentPage = p;
        }

        loadCategories();
    });

    function bindToolbar() {
        document.getElementById('create-category-btn')
            ?.addEventListener('click', openCreateForm);

        document.getElementById('categories-search')
            ?.addEventListener('input', function () {
                currentPage = 1;
                searchTerm = this.value.trim();
                applyFilter();
            });

        const sortSel = document.getElementById('categories-sort-filter');
        if (sortSel) {
            sortSel.value = sortFilter;
            sortSel.addEventListener('change', function () {
                sortFilter = this.value;
                currentPage = 1;
                applyFilter();
            });
        }

        document.getElementById('category-form-modal-close')
            ?.addEventListener('click', () => hideModal('category-form-modal'));
        document.getElementById('category-form-cancel')
            ?.addEventListener('click', () => hideModal('category-form-modal'));

        document.getElementById('category-confirm-close')
            ?.addEventListener('click', () => hideModal('category-confirm-modal'));
        document.getElementById('category-confirm-cancel')
            ?.addEventListener('click', () => hideModal('category-confirm-modal'));
        document.getElementById('category-confirm-delete')
            ?.addEventListener('click', executeDelete);

        document.querySelectorAll('.add-modal').forEach(m => {
            m.addEventListener('click', function (e) {
                if (e.target === this) hideModal(this.id);
            });
        });
    }

    function loadCategories() {
        const container = document.getElementById('categories-container');
        renderSkeletons(container, 6);

        const qs = new URLSearchParams();
        qs.set('page', currentPage);
        qs.set('limit', PAGE_SIZE);
        if (sortFilter) qs.set('sort', sortFilter);
        if (searchTerm) qs.set('search', searchTerm);

        fetch(`categories-list-api.php?${qs.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                const items = Array.isArray(data.items) ? data.items : [];
                totalItems = Number.isFinite(data.total) ? data.total : items.length;
                renderPage(items);
            })
            .catch(err => {
                console.error('Failed to load categories', err);
                container.innerHTML = '<p class="error-message">Unable to load categories.</p>';
                document.getElementById('categories-pagination').innerHTML = '';
            });
    }

    function applyFilter() {
        updateUrlParams();
        loadCategories();
    }

    function renderPage(items) {
        updateUrlParams();
        const container = document.getElementById('categories-container');
        const pagination = document.getElementById('categories-pagination');

        container.innerHTML = '';
        pagination.innerHTML = '';

        if (!items || items.length === 0) {
            container.innerHTML = '<p class="empty-list">No categories found.</p>';
            return;
        }

        items.forEach(c => container.appendChild(buildCard(c)));

        const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        renderPagination(pagination, totalPages);
    }

    function buildCard(c) {
        const card = document.createElement('div');
        card.className = 'service-item';
        card.dataset.id = c.id;

        card.innerHTML = `
            <div class="service-header">
                <i class="fa-solid fa-layer-group" style="color:#6b7280;font-size:1.1rem;flex-shrink:0;"></i>
                <h3 style="margin:0 0 0 8px;flex:1;">${escHtml(c.name || '')}</h3>
            </div>
            <div class="service-actions" style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn-secondary icon-only cat-edit-btn" data-id="${escHtml(c.id)}" title="Edit">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button class="btn-danger icon-only cat-delete-btn" data-id="${escHtml(c.id)}" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>`;

        card.querySelector('.cat-edit-btn').addEventListener('click', () => openEditForm(c));
        card.querySelector('.cat-delete-btn').addEventListener('click', () => confirmDelete(c));

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

        wrap.appendChild(makePageBtn('‹', currentPage === 1, () => { currentPage--; loadCategories(); }));

        for (let p = 1; p <= totalPages; p++) {
            const btn = makePageBtn(String(p), false, (function(pg) {
                return function() { currentPage = pg; loadCategories(); };
            }(p)));
            if (p === currentPage) {
                btn.style.cssText += ';background:#10b981;color:#fff;border-color:#10b981;font-weight:700;';
            }
            wrap.appendChild(btn);
        }

        wrap.appendChild(makePageBtn('›', currentPage === totalPages, () => { currentPage++; loadCategories(); }));
        container.appendChild(wrap);
    }

    function makePageBtn(label, disabled, onClick) {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.disabled = disabled;
        btn.className = 'btn-secondary';
        btn.style.cssText = 'min-width:36px;padding:4px 10px;font-size:.85rem;';
        if (!disabled) btn.addEventListener('click', onClick);
        return btn;
    }

    function openCreateForm() {
        editId = null;
        const form = document.getElementById('category-form');
        if (form) form.reset();
        document.getElementById('category-form-title').textContent = 'Add category';
        showModal('category-form-modal');
    }

    function openEditForm(c) {
        editId = c.id;
        const form = document.getElementById('category-form');
        if (form) {
            form.reset();
            form.querySelector('#category-name').value = c.name || '';
        }
        document.getElementById('category-form-title').textContent = 'Edit category';
        showModal('category-form-modal');
    }

    document.getElementById('category-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('category-form-error');
        if (errorBox) { errorBox.style.display = 'none'; errorBox.textContent = ''; }
        const name = this.querySelector('#category-name').value.trim();
        if (!name) {
            if (errorBox) { errorBox.textContent = 'Name is required'; errorBox.style.display = 'block'; }
            return;
        }
        const submitBtn = document.getElementById('category-form-submit');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

        const url = editId ? `category-update-api.php?id=${encodeURIComponent(editId)}` : 'category-create-api.php';
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
                    console.error('failed to parse category response', ex, text);
                    data = { error: 'Invalid response from server', body: trimmed };
                }
                if (data.error) throw new Error(data.error);
                hideModal('category-form-modal');
                loadCategories();
            })
            .catch(err => {
                if (errorBox) { errorBox.textContent = err.message || 'An error occurred.'; errorBox.style.display = 'block'; }
            })
            .finally(() => {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save'; }
            });
    });

    function confirmDelete(c) {
        deleteId = c.id;
        const body = document.getElementById('category-confirm-name');
        if (body) body.textContent = c.name;
        showModal('category-confirm-modal');
    }

    function executeDelete() {
        if (!deleteId) return;
        const btn = document.getElementById('category-confirm-delete');
        const errBox = document.getElementById('category-confirm-error');
        if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
        btn.disabled = true;
        btn.textContent = 'Deleting…';
        fetch(`category-delete-api.php?id=${encodeURIComponent(deleteId)}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
                hideModal('category-confirm-modal');
                loadCategories();
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
