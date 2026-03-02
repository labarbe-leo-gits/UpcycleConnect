(function () {
    'use strict';

    const initialSize = 8;
    const moreSize    = 4;
    let offset        = 0;
    let limit         = initialSize;
    let totalCount    = 0;
    let searchTerm    = '';
    let typeFilter    = '';

    const typeLabels = { 1: 'Formation', 2: 'Event', 3: 'Consulting' };
    const typeIcons  = { 1: 'fa-graduation-cap', 2: 'fa-calendar-days', 3: 'fa-user-tie' };

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();
        requestChunk(false);
    });

    function bindToolbar() {
        document.getElementById('create-service-btn')?.addEventListener('click', openCreateForm);

        document.getElementById('service-search')?.addEventListener('input', function () {
            searchTerm = this.value.trim();
            resetList();
        });

        document.getElementById('service-type-filter')?.addEventListener('change', function () {
            typeFilter = this.value;
            resetList();
        });

        document.getElementById('svc-loc-switcher')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.svc-loc-opt');
            if (!btn) return;
            setLocationMode(btn.dataset.mode);
        });
    }

    function resetList() {
        offset = 0;
        limit  = initialSize;
        requestChunk(false);
    }

    function requestChunk(append) {
        const container = document.getElementById('services-container');
        const moreBtn   = document.getElementById('services-show-more');
        if (!container) return;

        if (!append) renderSkeletons(container, 4);

        let url = `services-list-api?page=${Math.floor(offset / initialSize) + 1}&limit=${limit}`;
        if (searchTerm)  url += `&search=${encodeURIComponent(searchTerm)}`;
        if (typeFilter !== '') url += `&type=${encodeURIComponent(typeFilter)}`;

        fetch(url)
            .then(r => r.text())
            .then(text => {
                const data     = text ? JSON.parse(text) : {};
                const services = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total    = Number.isFinite(data.total) ? data.total : services.length;
                totalCount     = total;

                if (!append) container.innerHTML = '';

                if (services.length === 0 && !append) {
                    container.innerHTML = '<p class="empty-list">No services found.</p>';
                    if (moreBtn) moreBtn.style.display = 'none';
                    hideInitialLoader();
                    return;
                }

                renderServices(services, container);
                if (!append) hideInitialLoader();

                offset += services.length;
                limit   = moreSize;
                if (moreBtn) {
                    moreBtn.style.display = offset < totalCount ? 'inline-block' : 'none';
                    moreBtn.disabled = false;
                }
            })
            .catch(err => {
                console.error('Failed to load services', err);
                if (!append) container.innerHTML = '<p class="error-message">Unable to load services.</p>';
                if (moreBtn) moreBtn.style.display = 'none';
                if (!append) hideInitialLoader();
            });
    }

    document.getElementById('services-show-more')?.addEventListener('click', function () {
        this.disabled = true;
        requestChunk(true);
    });

    function renderServices(services, container) {
        services.forEach(svc => {
            const card = document.createElement('div');
            card.className = 'service-item';
            card.dataset.id = svc.id;

            const typeLabel = typeLabels[svc.type] ?? `Type ${svc.type}`;
            const icon      = typeIcons[svc.type]  ?? 'fa-calendar';
            const price     = svc.price > 0 ? `€${parseFloat(svc.price).toFixed(2)}` : '<span style="color:#16a34a;">Free</span>';
            const dateStr   = svc.service_date ? new Date(svc.service_date).toLocaleDateString('fr-FR') : '—';
            const city      = [svc.service_city, svc.service_zip].filter(Boolean).join(' ');
            const locationHtml = city
                ? `<span><i class="fa-solid fa-location-dot"></i> ${escHtml(city)}</span>`
                : `<span style="color:#7c3aed;font-weight:500;"><i class="fa-solid fa-wifi"></i> Online</span>`;
            const maxP      = svc.maximum_participants != null ? svc.maximum_participants : '∞';
            const curP      = svc.current_participants ?? 0;

            card.innerHTML = `
                <div class="service-header">
                    <i class="fa-solid ${icon}" style="color:#7c3aed;font-size:1.2rem;"></i>
                    <h3 style="margin:0 0 0 8px;flex:1;">${escHtml(svc.name)}</h3>
                    <span class="badge" style="background:#ede9fe;color:#6d28d9;padding:2px 10px;border-radius:20px;font-size:.8rem;">${typeLabel}</span>
                </div>
                <p class="service-description" style="margin:6px 0;">${escHtml(svc.description ?? '')}</p>
                <div class="service-meta" style="display:flex;gap:18px;flex-wrap:wrap;font-size:.85rem;color:#6b7280;margin-bottom:10px;">
                    <span><i class="fa-solid fa-calendar"></i> ${dateStr}</span>
                    ${locationHtml}
                    <span><i class="fa-solid fa-users"></i> ${curP} / ${maxP}</span>
                    <span><i class="fa-solid fa-euro-sign"></i> ${price}</span>
                </div>
                <div class="service-actions" style="display:flex;gap:8px;justify-content:center;">
                    <button class="btn-secondary svc-edit-btn" data-id="${svc.id}"><i class="fa-solid fa-pen"></i> Edit</button>
                    <button class="btn-danger svc-delete-btn" data-id="${svc.id}" style="background:#ef4444;color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>`;

            card.querySelector('.svc-edit-btn').addEventListener('click', () => openEditForm(svc));
            card.querySelector('.svc-delete-btn').addEventListener('click', () => confirmDelete(svc));

            container.appendChild(card);
        });
    }

    function renderSkeletons(container, n) {
        container.innerHTML = '';
        for (let i = 0; i < n; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton-service-item';
            sk.innerHTML = `<div class="skeleton-service-header"><div class="skeleton skeleton-title" style="width:50%;"></div></div>
                            <div class="skeleton skeleton-description"></div>
                            <div class="skeleton skeleton-button" style="width:80px;height:32px;"></div>`;
            container.appendChild(sk);
        }
    }

    function hideInitialLoader() {
        const loader   = document.getElementById('initial-loader');
        const mainContent = document.getElementById('main-content');
        if (loader)      { loader.style.display = 'none'; loader.setAttribute('aria-hidden', 'true'); }
        if (mainContent) mainContent.style.visibility = 'visible';
    }

    function setLocationMode(mode) {
        const fields   = document.getElementById('svc-address-fields');
        const switcher = document.getElementById('svc-loc-switcher');
        if (!fields || !switcher) return;
        switcher.querySelectorAll('.svc-loc-opt').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.mode === mode);
        });
        const showAddress = mode === 'office';
        fields.style.display = showAddress ? 'flex' : 'none';
        if (!showAddress) {
            ['svc-road', 'svc-city', 'svc-zip'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        }
    }

    function openCreateForm() {
        document.getElementById('service-form-title').textContent = 'Add service / event';
        const form = document.getElementById('service-form');
        form.reset();
        form.dataset.editId = '';
        setLocationMode('online');
        showModal('service-form-modal');
    }

    function openEditForm(svc) {
        document.getElementById('service-form-title').textContent = 'Edit service / event';
        const form = document.getElementById('service-form');
        form.reset();
        form.dataset.editId = svc.id;

        form.querySelector('#svc-name').value        = svc.name        ?? '';
        form.querySelector('#svc-description').value = svc.description ?? '';
        form.querySelector('#svc-price').value       = svc.price       ?? 0;
        form.querySelector('#svc-type').value        = svc.type        ?? 1;
        form.querySelector('#svc-date').value        = (svc.service_date ?? '').substring(0, 10);
        form.querySelector('#svc-max-participants').value = svc.maximum_participants ?? '';

        const hasAddress = svc.service_road || svc.service_city;
        setLocationMode(hasAddress ? 'office' : 'online');
        if (hasAddress) {
            form.querySelector('#svc-road').value = svc.service_road ?? '';
            form.querySelector('#svc-city').value = svc.service_city ?? '';
            form.querySelector('#svc-zip').value  = svc.service_zip  ?? '';
        }

        showModal('service-form-modal');
    }

    document.getElementById('service-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('service-form-error');
        errorBox.style.display = 'none';

        const editId = this.dataset.editId;
        const payload = {
            name:                 this.querySelector('#svc-name').value.trim(),
            description:          this.querySelector('#svc-description').value.trim(),
            price:                parseFloat(this.querySelector('#svc-price').value) || 0,
            type:                 parseInt(this.querySelector('#svc-type').value, 10),
            service_date:         this.querySelector('#svc-date').value,
            service_road:         this.querySelector('#svc-road').value.trim(),
            service_city:         this.querySelector('#svc-city').value.trim(),
            service_zip:          this.querySelector('#svc-zip').value.trim(),
        };

        const maxP = this.querySelector('#svc-max-participants').value;
        if (maxP !== '') payload.maximum_participants = parseInt(maxP, 10);

        const submitBtn = document.getElementById('service-form-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';

        const isEdit  = !!editId;
        const url     = isEdit ? `service-update-api?id=${encodeURIComponent(editId)}` : 'service-create-api';
        const method  = isEdit ? 'PATCH' : 'POST';

        fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : {};
                if (data.error) throw new Error(data.error);
                hideModal('service-form-modal');
                resetList();
            })
            .catch(err => {
                errorBox.textContent = err.message || 'An error occurred.';
                errorBox.style.display = 'block';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save';
            });
    });

    function confirmDelete(svc) {
        const body    = document.getElementById('service-confirm-body');
        const actions = document.getElementById('service-confirm-actions');
        body.innerHTML = `<p>Are you sure you want to delete <strong>${escHtml(svc.name)}</strong>? This action cannot be undone.</p>`;
        actions.innerHTML = '';

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn-secondary';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.onclick = () => hideModal('service-confirm-modal');

        const deleteBtn = document.createElement('button');
        deleteBtn.textContent = 'Delete';
        deleteBtn.style.cssText = 'background:#ef4444;color:#fff;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:600;';
        deleteBtn.onclick = () => {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting…';
            fetch(`service-delete-api?id=${encodeURIComponent(svc.id)}`, { method: 'DELETE' })
                .then(r => r.text())
                .then(text => {
                    const data = text ? JSON.parse(text) : {};
                    if (data.error) throw new Error(data.error);
                    hideModal('service-confirm-modal');
                    resetList();
                })
                .catch(err => {
                    alert('Delete failed: ' + (err.message || 'Unknown error'));
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Delete';
                });
        };

        actions.appendChild(cancelBtn);
        actions.appendChild(deleteBtn);
        showModal('service-confirm-modal');
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

    ['service-modal-close', 'service-form-modal-close', 'service-form-cancel', 'service-confirm-close'].forEach(btnId => {
        document.getElementById(btnId)?.addEventListener('click', function () {
            this.closest('.add-modal')?.setAttribute('aria-hidden', 'true');
            this.closest('.add-modal').classList.remove('is-open');
        });
    });

    document.querySelectorAll('.add-modal').forEach(m => {
        m.addEventListener('click', function (e) {
            if (e.target === this) hideModal(this.id);
        });
    });

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }
})();
