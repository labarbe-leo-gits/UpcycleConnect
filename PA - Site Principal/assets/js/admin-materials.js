(function () {
    'use strict';

    const PAGE_SIZE = 8;

    let allMaterials  = [];
    let filtered      = [];
    let currentPage   = 1;

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();
        loadMaterials();
    });


    function bindToolbar() {
        document.getElementById('create-material-btn')
            ?.addEventListener('click', openCreateForm);

        document.getElementById('material-search')
            ?.addEventListener('input', function () {
                currentPage = 1;
                applyFilter(this.value.trim());
            });

        document.getElementById('material-form-modal-close')
            ?.addEventListener('click', () => hideModal('material-form-modal'));

        document.getElementById('material-form-cancel')
            ?.addEventListener('click', () => hideModal('material-form-modal'));

        document.getElementById('material-confirm-close')
            ?.addEventListener('click', () => hideModal('material-confirm-modal'));

        document.querySelectorAll('.add-modal').forEach(m => {
            m.addEventListener('click', function (e) {
                if (e.target === this) hideModal(this.id);
            });
        });
    }


    function loadMaterials() {
        const container = document.getElementById('materials-container');
        renderSkeletons(container, 6);

        fetch('materials-list-api')
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : [];
                if (data.error) throw new Error(data.error);
                allMaterials = Array.isArray(data) ? data : [];
                filtered     = allMaterials.slice();
                currentPage  = 1;
                renderPage();
            })
            .catch(err => {
                console.error('Failed to load materials', err);
                container.innerHTML = '<p class="error-message">Unable to load materials.</p>';
                document.getElementById('materials-pagination').innerHTML = '';
            });
    }

    let _filterTimer = null;

    function applyFilter(search) {
        const container = document.getElementById('materials-container');
        renderSkeletons(container, Math.min(allMaterials.length || 6, 6));
        document.getElementById('materials-pagination').innerHTML = '';

        clearTimeout(_filterTimer);
        _filterTimer = setTimeout(() => {
            const q = search.toLowerCase();
            filtered = allMaterials.filter(m => m.nom.toLowerCase().includes(q));
            renderPage();
        }, 200);
    }

    function renderPage() {
        const container  = document.getElementById('materials-container');
        const pagination = document.getElementById('materials-pagination');

        container.innerHTML  = '';
        pagination.innerHTML = '';

        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        if (filtered.length === 0) {
            container.innerHTML = '<p class="empty-list">No material factors found.</p>';
            return;
        }

        const start  = (currentPage - 1) * PAGE_SIZE;
        const slice  = filtered.slice(start, start + PAGE_SIZE);

        slice.forEach(m => container.appendChild(buildCard(m)));
        renderPagination(pagination, totalPages);
    }

    function buildCard(m) {
        const card = document.createElement('div');
        card.className  = 'service-item';
        card.dataset.id = m.id;

        card.innerHTML = `
            <div class="service-header">
                <i class="fa-solid fa-recycle" style="color:#6b7280;font-size:1.1rem;flex-shrink:0;"></i>
                <h3 style="margin:0 0 0 8px;flex:1;">${escHtml(m.nom)}</h3>
            </div>
            <div class="service-meta" style="display:flex;gap:20px;flex-wrap:wrap;font-size:.875rem;color:#6b7280;margin:8px 0 10px;">
                <span style="display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-smog" style="color:#6b7280;"></i>
                    CO₂&nbsp;:&nbsp;<strong style="color:#111827;">${parseFloat(m.facteur_co2).toLocaleString('fr-FR', {maximumFractionDigits: 4})}</strong>&nbsp;kg&nbsp;CO₂&nbsp;eq/kg
                    <span class="mat-tip">
                        <i class="fa-solid fa-circle-question" style="color:#9ca3af;font-size:.8rem;"></i>
                        <span class="mat-tip-box">kg of CO₂ equivalent emitted per kg of this material.<br><br><strong>Upcycling score formula:</strong><br>Score = weight (kg) × CO₂ factor<br><br>A higher value = more CO₂ saved by upcycling this material.</span>
                    </span>
                </span>
            </div>
            <div class="service-actions" style="display:flex;gap:8px;justify-content:center;">
                <button class="btn-secondary mat-edit-btn" data-id="${escHtml(m.id)}">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
                <button class="btn-danger mat-delete-btn" data-id="${escHtml(m.id)}">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>`;

        card.querySelector('.mat-edit-btn').addEventListener('click', () => openEditForm(m));
        card.querySelector('.mat-delete-btn').addEventListener('click', () => confirmDelete(m));

        return card;
    }

    function renderSkeletons(container, n) {
        const card =
            '<div class="skeleton-service-item">' +
                '<div class="skeleton-service-header">' +
                    '<div class="skeleton skeleton-title" style="flex:1;"></div>' +
                    '<div class="skeleton skeleton-badge"></div>' +
                '</div>' +
                '<div class="skeleton-meta">' +
                    '<div class="skeleton" style="height:18px;width:140px;border-radius:6px;"></div>' +
                    '<div class="skeleton" style="height:18px;width:140px;border-radius:6px;"></div>' +
                '</div>' +
                '<div class="skeleton-buttons">' +
                    '<div class="skeleton skeleton-button"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                '</div>' +
            '</div>';
        container.innerHTML = Array(n).fill(card).join('');
    }

    function renderPagination(container, totalPages) {
        if (totalPages <= 1) return;

        const wrap = document.createElement('div');
        wrap.className = 'pagination-controls';
        wrap.style.cssText = 'display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap;';

        const prevBtn = makePageBtn('‹', currentPage === 1, () => {
            currentPage--;
            renderPage();
            scrollToTop();
        });

        wrap.appendChild(prevBtn);

        for (let p = 1; p <= totalPages; p++) {
            const btn = makePageBtn(String(p), false, () => {
                currentPage = p;
                renderPage();
                scrollToTop();
            });
            if (p === currentPage) {
                btn.style.cssText += ';background:#7c3aed;color:#fff;border-color:#7c3aed;font-weight:700;';
            }
            wrap.appendChild(btn);
        }

        const nextBtn = makePageBtn('›', currentPage === totalPages, () => {
            currentPage++;
            renderPage();
            scrollToTop();
        });

        wrap.appendChild(nextBtn);
        container.appendChild(wrap);
    }

    function makePageBtn(label, disabled, onClick) {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.disabled    = disabled;
        btn.className   = 'btn-secondary';
        btn.style.cssText = 'min-width:36px;padding:4px 10px;font-size:.85rem;';
        if (!disabled) btn.addEventListener('click', onClick);
        return btn;
    }

    function scrollToTop() {
        document.getElementById('main-content')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openCreateForm() {
        document.getElementById('material-form-title').textContent = 'Add material factor';
        const form = document.getElementById('material-form');
        form.reset();
        form.dataset.editId = '';
        document.getElementById('material-form-error').style.display = 'none';
        showModal('material-form-modal');
    }

    function openEditForm(m) {
        document.getElementById('material-form-title').textContent = 'Edit material factor';
        const form = document.getElementById('material-form');
        form.reset();
        form.dataset.editId = m.id;

        form.querySelector('#mat-nom').value = m.nom         ?? '';
        form.querySelector('#mat-co2').value  = m.facteur_co2 ?? '';

        document.getElementById('material-form-error').style.display = 'none';
        showModal('material-form-modal');
    }

    document.getElementById('material-form')?.addEventListener('submit', function (e) {
        e.preventDefault();

        const errorBox = document.getElementById('material-form-error');
        errorBox.style.display = 'none';

        const editId  = this.dataset.editId;
        const payload = {
            nom:         this.querySelector('#mat-nom').value.trim(),
            facteur_co2: parseFloat(this.querySelector('#mat-co2').value),
        };

        const submitBtn = document.getElementById('material-form-submit');
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Saving…';

        const isEdit = !!editId;
        const url    = isEdit ? `material-update-api?id=${encodeURIComponent(editId)}` : 'material-create-api';
        const method = isEdit ? 'PATCH' : 'POST';

        fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        })
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : {};
                if (data.error) throw new Error(data.error);
                hideModal('material-form-modal');
                loadMaterials();
            })
            .catch(err => {
                errorBox.textContent    = err.message || 'An error occurred.';
                errorBox.style.display  = 'block';
            })
            .finally(() => {
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Save';
            });
    });

    function confirmDelete(m) {
        const body    = document.getElementById('material-confirm-body');
        const actions = document.getElementById('material-confirm-actions');

        body.innerHTML = `<p>Are you sure you want to delete <strong>${escHtml(m.nom)}</strong>? This action cannot be undone.</p>`;
        actions.innerHTML = '';

        const cancelBtn = document.createElement('button');
        cancelBtn.className   = 'btn-secondary';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.onclick     = () => hideModal('material-confirm-modal');

        const deleteBtn = document.createElement('button');
        deleteBtn.textContent = 'Delete';
        deleteBtn.style.cssText = 'background:#ef4444;color:#fff;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:600;';
        deleteBtn.onclick = () => {
            deleteBtn.disabled    = true;
            deleteBtn.textContent = 'Deleting…';

            fetch(`material-delete-api?id=${encodeURIComponent(m.id)}`, { method: 'DELETE' })
                .then(r => r.text())
                .then(text => {
                    const data = text ? JSON.parse(text) : {};
                    if (data.error) throw new Error(data.error);
                    hideModal('material-confirm-modal');
                    loadMaterials();
                })
                .catch(err => {
                    alert('Delete failed: ' + (err.message || 'Unknown error'));
                    deleteBtn.disabled    = false;
                    deleteBtn.textContent = 'Delete';
                });
        };

        actions.appendChild(cancelBtn);
        actions.appendChild(deleteBtn);
        showModal('material-confirm-modal');
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

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

})();
