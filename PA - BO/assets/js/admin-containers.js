(function () {
    'use strict';

    const PAGE_SIZE = 8;

    let allContainers = [];
    let filtered      = [];
    let currentPage   = 1;
    let pendingDeleteId = null;
    let searchTerm = '';
    let cityFilter = '';
    let sortFilter = 'name';

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();

        const params = new URLSearchParams(window.location.search);
        if (params.has('search')) {
            searchTerm = params.get('search') || '';
            const inp = document.getElementById('container-search');
            if (inp) inp.value = searchTerm;
        }
        if (params.has('city')) {
            cityFilter = params.get('city') || '';
        }
        if (params.has('sort')) {
            sortFilter = params.get('sort') || sortFilter;
        }

        if (params.has('page')) {
            const p = parseInt(params.get('page'), 10);
            if (!isNaN(p) && p > 0) currentPage = p;
        }
        loadContainers();
        bindAddressSearch();
    });

    function bindToolbar() {
        document.getElementById('create-container-btn')
            ?.addEventListener('click', openCreateForm);

        document.getElementById('container-search')
            ?.addEventListener('input', function () {
                currentPage = 1;
                applyFilter(this.value.trim());
            });
        const citySel = document.getElementById('container-city-filter');
        if (citySel) {
            citySel.addEventListener('change', function() {
                cityFilter = this.value;
                currentPage = 1;
                applyFilter(searchTerm);
            });
        }
        const sortSel = document.getElementById('container-sort-filter');
        if (sortSel) {
            sortSel.value = sortFilter;
            sortSel.addEventListener('change', function() {
                sortFilter = this.value;
                applyFilter(searchTerm);
            });
        }

        document.getElementById('container-form-modal-close')
            ?.addEventListener('click', () => hideModal('container-form-modal'));

        document.getElementById('container-form-cancel')
            ?.addEventListener('click', () => hideModal('container-form-modal'));

        document.getElementById('container-confirm-close')
            ?.addEventListener('click', () => hideModal('container-confirm-modal'));

        document.getElementById('container-confirm-cancel')
            ?.addEventListener('click', () => hideModal('container-confirm-modal'));

        document.getElementById('container-confirm-delete')
            ?.addEventListener('click', executeDelete);

        document.getElementById('container-view-close')
            ?.addEventListener('click', () => hideModal('container-view-modal'));

        document.getElementById('container-view-close-btn')
            ?.addEventListener('click', () => hideModal('container-view-modal'));

        document.querySelectorAll('.add-modal').forEach(m => {
            m.addEventListener('click', function (e) {
                if (e.target === this) hideModal(this.id);
            });
        });
    }

    function loadContainers() {
        const container = document.getElementById('containers-container');
        renderSkeletons(container, 6);

        fetch('containers-api', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                const items = Array.isArray(data) ? data
                    : (data && Array.isArray(data.items)) ? data.items
                    : [];
                allContainers = items;
                populateCityDropdown(items);
                filtered      = items.slice();
                currentPage   = 1;
                
                if (searchTerm || cityFilter || sortFilter !== 'name') {
                    applyFilter(searchTerm);
                } else {
                    renderPage();
                }
            })
            .catch(err => {
                console.error('Failed to load containers', err);
                container.innerHTML = '<p class="error-message">Unable to load containers.</p>';
                document.getElementById('containers-pagination').innerHTML = '';
            });
    }

    let _filterTimer = null;

    function populateCityDropdown(items) {
        const sel = document.getElementById('container-city-filter');
        if (!sel) return;
        const cities = Array.from(new Set(items.map(c => (c.city || '').trim()).filter(Boolean))).sort((a,b)=>a.localeCompare(b,undefined,{sensitivity:'base'}));
        sel.innerHTML = '<option value="">All cities</option>' + cities.map(c => `<option value="${escHtml(c)}">${escHtml(c)}</option>`).join('');
        if (cityFilter) sel.value = cityFilter;
    }

    function applyFilter(search) {
        searchTerm = search;
        updateUrlParams();
        const container = document.getElementById('containers-container');
        renderSkeletons(container, Math.min(allContainers.length || 6, 6));
        document.getElementById('containers-pagination').innerHTML = '';

        clearTimeout(_filterTimer);
        _filterTimer = setTimeout(() => {
            const q = search.toLowerCase();
            filtered = allContainers.filter(c =>
                ((c.name || '').toLowerCase().includes(q) ||
                (c.city || '').toLowerCase().includes(q) ||
                (c.road || '').toLowerCase().includes(q)) &&
                (cityFilter === '' || (c.city || '').toLowerCase() === cityFilter.toLowerCase())
            );

            if (sortFilter === 'name') {
                filtered.sort((a,b)=> (a.name||'').localeCompare(b.name||'',undefined,{sensitivity:'base'}));
            } else if (sortFilter === 'city') {
                filtered.sort((a,b)=> (a.city||'').localeCompare(b.city||'',undefined,{sensitivity:'base'}));
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
        const container  = document.getElementById('containers-container');
        const pagination = document.getElementById('containers-pagination');

        container.innerHTML  = '';
        pagination.innerHTML = '';

        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        if (filtered.length === 0) {
            container.innerHTML = '<p class="empty-list">No containers found.</p>';
            return;
        }

        const start = (currentPage - 1) * PAGE_SIZE;
        filtered.slice(start, start + PAGE_SIZE).forEach(c => container.appendChild(buildCard(c)));
        renderPagination(pagination, totalPages);
    }

    function buildCard(c) {
        const card = document.createElement('div');
        card.className  = 'service-item';
        card.dataset.id = c.id;

        const capacity = c.capacity || 100;
        const currentFill = c.current_fill || 0;
        const percentage = Math.round((currentFill / capacity) * 100);

        card.innerHTML = `
            <div class="service-header">
                <i class="fa-solid fa-warehouse" style="color:#6b7280;font-size:1.1rem;flex-shrink:0;"></i>
                <h3 style="margin:0 0 0 8px;flex:1;">${escHtml(c.name || '')}</h3>
            </div>
            <div class="service-meta" style="display:flex;gap:20px;flex-wrap:wrap;font-size:.875rem;color:#6b7280;margin:8px 0 10px;">
                <span style="display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-location-dot" style="color:#10b981;"></i>
                    ${escHtml([c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', '))}
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:.8rem;color:#6b7280;">
                <div style="flex:1;">
                    <div style="height:12px;background:#e5e7eb;border-radius:4px;overflow:hidden;border:1px solid #d1d5db;">
                        <div style="height:100%;background:linear-gradient(to right,#10b981,#059669);width:${percentage}%;transition:width 0.3s ease;"></div>
                    </div>
                </div>
                <span style="min-width:50px;text-align:right;font-weight:600;">${currentFill}/${capacity}</span>
            </div>
            <div class="service-actions" style="display:flex;gap:8px;justify-content:center;">
                <button class="btn-secondary cnt-view-btn" data-id="${escHtml(c.id)}">
                    <i class="fa-solid fa-eye"></i> View
                </button>
                <button class="btn-secondary cnt-edit-btn" data-id="${escHtml(c.id)}">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
                <button class="btn-danger cnt-delete-btn" data-id="${escHtml(c.id)}">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>`;

        card.querySelector('.cnt-view-btn').addEventListener('click', () => openViewModal(c));
        card.querySelector('.cnt-edit-btn').addEventListener('click', () => openEditForm(c));
        card.querySelector('.cnt-delete-btn').addEventListener('click', () => confirmDelete(c));

        return card;
    }

    function renderSkeletons(container, n) {
        const tpl =
            '<div class="skeleton-service-item">' +
                '<div class="skeleton-service-header">' +
                    '<div class="skeleton skeleton-title" style="flex:1;"></div>' +
                '</div>' +
                '<div class="skeleton-meta">' +
                    '<div class="skeleton" style="height:18px;width:200px;border-radius:6px;"></div>' +
                '</div>' +
                '<div class="skeleton-buttons">' +
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

    let _addrTimer = null;

    function bindAddressSearch() {
        const input   = document.getElementById('cnt-addr-search');
        const results = document.getElementById('cnt-addr-results');
        if (!input || !results) return;

        input.addEventListener('input', function () {
            clearTimeout(_addrTimer);
            const q = this.value.trim();
            if (q.length < 3) { results.style.display = 'none'; return; }

            _addrTimer = setTimeout(() => {
                fetch('https://api-adresse.data.gouv.fr/search/?q=' + encodeURIComponent(q) + '&limit=6&autocomplete=1')
                    .then(r => r.json())
                    .then(data => renderAddrResults(data.features || []))
                    .catch(() => { results.style.display = 'none'; });
            }, 300);
        });

        input.addEventListener('keydown', function (e) {
            const items = results.querySelectorAll('.addr-result-item');
            const active = results.querySelector('.addr-result-item.addr-active');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const next = active ? (active.nextElementSibling || items[0]) : items[0];
                if (active) active.classList.remove('addr-active');
                next.classList.add('addr-active');
                next.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = active ? (active.previousElementSibling || items[items.length - 1]) : items[items.length - 1];
                if (active) active.classList.remove('addr-active');
                prev.classList.add('addr-active');
                prev.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                if (active) { e.preventDefault(); active.click(); }
            } else if (e.key === 'Escape') {
                results.style.display = 'none';
            }
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    function renderAddrResults(features) {
        const results = document.getElementById('cnt-addr-results');
        results.innerHTML = '';

        if (!features.length) { results.style.display = 'none'; return; }

        features.forEach(f => {
            const p    = f.properties;
            const item = document.createElement('div');
            item.className = 'addr-result-item';
            item.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + escHtml(p.label) + '</span>';
            item.addEventListener('click', () => applyAddress(p));
            results.appendChild(item);
        });

        results.style.display = 'block';
    }

    function updateUrlParams() {
        const url = new URL(window.location.href);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        if (cityFilter) {
            url.searchParams.set('city', cityFilter);
        } else {
            url.searchParams.delete('city');
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

    function applyAddress(p) {
        const form = document.getElementById('container-form');
        if (!form) return;

        form.querySelector('#cnt-number').value = p.housenumber || '';
        form.querySelector('#cnt-road').value   = p.street || p.name || '';
        form.querySelector('#cnt-city').value   = p.city   || '';
        form.querySelector('#cnt-zip').value    = p.postcode || '';

        const input   = document.getElementById('cnt-addr-search');
        const results = document.getElementById('cnt-addr-results');
        if (input)   input.value        = p.label;
        if (results) results.style.display = 'none';

        const nameEl = form.querySelector('#cnt-name');
        const numEl  = form.querySelector('#cnt-number');
        if (nameEl && !nameEl.value) nameEl.focus();
        else if (numEl && !numEl.value) numEl.focus();
    }


    function openViewModal(c) {
        const modal = document.getElementById('container-view-modal');
        if (!modal) return;

        modal.querySelector('#container-view-name').textContent    = c.name || '-';
        modal.querySelector('#container-view-address').textContent = [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ') || '-';
        modal.querySelector('#container-view-city').textContent    = c.city || '-';
        modal.querySelector('#container-view-postal').textContent  = c.postal_code || '-';
        modal.querySelector('#container-view-created').textContent = c.created_at ? new Date(c.created_at).toLocaleDateString('en-GB') : '-';

        const capacity = c.capacity || 100;
        const currentFill = c.current_fill || 0;
        const percentage = Math.round((currentFill / capacity) * 100);
        
        const gaugeFill = modal.querySelector('#container-view-gauge-fill');
        const gaugeText = modal.querySelector('#container-view-gauge-text');
        if (gaugeFill) gaugeFill.style.width = percentage + '%';
        if (gaugeText) gaugeText.textContent = currentFill + '/' + capacity;

        showModal('container-view-modal');

        // Reset map div
        const oldMap = document.getElementById('container-view-map');
        const freshMap = document.createElement('div');
        freshMap.id = 'container-view-map';
        freshMap.style.cssText = 'height:260px;border-radius:10px;overflow:hidden;margin-top:18px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;';
        freshMap.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Loading map\u2026</span>';
        oldMap.replaceWith(freshMap);

        const query = [c.number, c.road, c.postal_code, c.city, 'France'].filter(Boolean).join(', ');
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query), {
            headers: { 'Accept-Language': 'en' }
        })
        .then(r => r.json())
        .then(results => {
            const target = document.getElementById('container-view-map');
            if (!target) return;
            if (!results || !results.length) {
                target.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Location not found</span>';
                return;
            }
            const lat = parseFloat(results[0].lat);
            const lng = parseFloat(results[0].lon);
            target.innerHTML = '';
            target.style.cssText = 'height:260px;border-radius:10px;overflow:hidden;margin-top:18px;';
            const map = L.map(target, { zoomControl: true, scrollWheelZoom: false }).setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '\u00a9 <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            L.marker([lat, lng]).addTo(map)
                .bindPopup('<strong>' + escHtml(c.name || '') + '</strong><br>' + escHtml(query))
                .openPopup();
            setTimeout(() => map.invalidateSize(), 250);
        })
        .catch(() => {
            const target = document.getElementById('container-view-map');
            if (target) target.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Map unavailable</span>';
        });
    }

    function openCreateForm() {
        document.getElementById('container-form-title').textContent = 'Add container';
        const form = document.getElementById('container-form');
        form.reset();
        form.dataset.editId = '';
        const addrInput = document.getElementById('cnt-addr-search');
        if (addrInput) addrInput.value = '';
        document.getElementById('container-form-error').style.display = 'none';
        showModal('container-form-modal');
    }

    function openEditForm(c) {
        document.getElementById('container-form-title').textContent = 'Edit container';
        const form = document.getElementById('container-form');
        form.reset();
        form.dataset.editId = c.id;

        const addrInput = document.getElementById('cnt-addr-search');
        if (addrInput) addrInput.value = [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ');

        form.querySelector('#cnt-name').value   = c.name        ?? '';
        form.querySelector('#cnt-road').value   = c.road        ?? '';
        form.querySelector('#cnt-number').value = c.number      ?? '';
        form.querySelector('#cnt-city').value   = c.city        ?? '';
        form.querySelector('#cnt-zip').value    = c.postal_code ?? '';

        document.getElementById('container-form-error').style.display = 'none';
        showModal('container-form-modal');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('container-form')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const errorBox = document.getElementById('container-form-error');
            errorBox.style.display = 'none';

            const editId  = this.dataset.editId;
            const payload = {
                name:        this.querySelector('#cnt-name').value.trim(),
                road:        this.querySelector('#cnt-road').value.trim(),
                number:      this.querySelector('#cnt-number').value.trim(),
                city:        this.querySelector('#cnt-city').value.trim(),
                postal_code: this.querySelector('#cnt-zip').value.trim(),
            };

            const submitBtn = document.getElementById('container-form-submit');
            submitBtn.disabled   = true;
            const origText       = submitBtn.innerHTML;
            submitBtn.innerHTML  = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            const isEdit = !!editId;
            const url    = isEdit ? `container-update-api?id=${encodeURIComponent(editId)}` : 'container-create-api';
            const method = isEdit ? 'PATCH' : 'POST';

            fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled   = false;
                submitBtn.innerHTML  = origText;
                if (data.error || data.errors) {
                    const msg = data.error || (Array.isArray(data.errors) ? data.errors.join(', ') : JSON.stringify(data.errors));
                    errorBox.textContent    = msg;
                    errorBox.style.display  = 'block';
                    return;
                }
                hideModal('container-form-modal');
                loadContainers();
            })
            .catch(() => {
                submitBtn.disabled  = false;
                submitBtn.innerHTML = origText;
                errorBox.textContent   = 'An unexpected error occurred.';
                errorBox.style.display = 'block';
            });
        });
    });

    function confirmDelete(c) {
        pendingDeleteId = c.id;
        document.getElementById('container-confirm-name').textContent = c.name || c.id;
        showModal('container-confirm-modal');
    }

    function executeDelete() {
        if (!pendingDeleteId) return;

        const btn     = document.getElementById('container-confirm-delete');
        btn.disabled  = true;
        const origTxt = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting…';

        fetch(`container-delete-api?id=${encodeURIComponent(pendingDeleteId)}`, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled  = false;
            btn.innerHTML = origTxt;
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            hideModal('container-confirm-modal');
            pendingDeleteId = null;
            loadContainers();
        })
        .catch(() => {
            btn.disabled  = false;
            btn.innerHTML = origTxt;
            alert('An unexpected error occurred.');
        });
    }

    function showModal(id) {
        const m = document.getElementById(id);
        if (m) { m.classList.add('is-open'); m.setAttribute('aria-hidden', 'false'); }
    }

    function hideModal(id) {
        const m = document.getElementById(id);
        if (m) { m.classList.remove('is-open'); m.setAttribute('aria-hidden', 'true'); }
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}());
