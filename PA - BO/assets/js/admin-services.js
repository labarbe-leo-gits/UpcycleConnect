(function () {
    'use strict';

    const initialSize = 8;
    const moreSize    = 4;
    let offset        = 0;
    let limit         = initialSize;
    let totalCount    = 0;
    let searchTerm    = '';
    let typeFilter    = '';


    let typeLabels = {};
    let typeIcons  = {};
    let prestationTypes = [];

    let selectedEmployees = [];
    let originalEmployees = [];
    let currentServiceId = '';
    let _employeeTimer = null;

    let meetingType = 'none';
    let meetingUrl  = '';

    // filter-by-employee state
    let employeeFilterId = '';
    let employeeFilterName = '';
    let _filterTimer = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();
        loadTypes().then(() => requestChunk(false));
        bindAddressSearch();
        bindEmployeeLookup();
        bindEmployeeFilterLookup();
        bindMeetingSwitcher();
    });

    let _addrTimer = null;

    function bindAddressSearch() {

        const input = document.getElementById('svc-addr-search');
        const results = document.getElementById('svc-addr-results');
        if (!input || !results) return;

        input.addEventListener('input', function() {
            clearTimeout(_addrTimer);
            const q = this.value.trim();
            if (q.length < 3){
                results.style.display = 'none';
                return;
            }

            _addrTimer = setTimeout(() => {
                fetch('https://api-adresse.data.gouv.fr/search/?q=' + encodeURIComponent(q) + '&limit=6')
                    .then(r => r.json())
                    .then(data => renderAddrResults(data.features || []))
                    .catch(() => {results.style.display = 'none';});
            }, 300);
        
        });

        input.addEventListener('keydown', function(e){
            const items = results.querySelectorAll('.addr-result-item');
            const active = results.querySelector('.addr-result-item.addr-active');
            if (!items.length) return;
            if(e.key === 'ArrowDown'){
                e.preventDefault();
                const next = active ? active.nextElementSibling : items[0];
                if (active) active.classList.remove('addr-active');
                next.classList.add('addr-active');
                next.scrollIntoView({block:'nearest'});
            }else if(e.key === 'ArrowUp'){
                e.preventDefault();
                const prev = active ? active.previousElementSibling : items[items.length - 1];
                if (active) active.classList.remove('addr-active');
                prev.classList.add('addr-active');
                prev.scrollIntoView({block:'nearest'});
            }else if(e.key === 'Enter'){
                e.preventDefault();
                if (active) applyAddress(active);
            }else if(e.key === 'Escape'){
                results.style.display = 'none';
            }
        });

        document.addEventListener('click', function(e){
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    function renderAddrResults(features) {
        const results = document.getElementById('svc-addr-results');
        results.innerHTML = '';

        if (!features.length) { results.style.display = 'none'; return; }

        features.forEach(f => {
            const p = f.properties;
            const item = document.createElement('div');
            item.className = 'addr-result-item';
            item.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + escHtml(p.label) + '</span>';
            item.addEventListener('click', () => applyAddress(p));
            results.appendChild(item);
        });

        results.style.display = 'block';
    }

    function applyAddress(p) {
        const form = document.getElementById('service-form');
        if (!form) return;

        const road = [p.housenumber, p.street || p.name].filter(Boolean).join(' ');
        form.querySelector('#svc-road').value = road;
        form.querySelector('#svc-city').value = p.city     || '';
        form.querySelector('#svc-zip').value  = p.postcode || '';

        const input   = document.getElementById('svc-addr-search');
        const results = document.getElementById('svc-addr-results');
        if (input)   input.value           = p.label;
        if (results) results.style.display = 'none';
    }

    function bindEmployeeLookup() {
        const input = document.getElementById('employee-search');
        const results = document.getElementById('employee-results');
        if (!input || !results) return;

        input.addEventListener('input', function() {
            clearTimeout(_employeeTimer);
            const q = this.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            _employeeTimer = setTimeout(() => {
                fetch('users-list-api?search=' + encodeURIComponent(q) + '&limit=6&user_type=4')
                    .then(r => r.json())
                    .then(data => {
                        const users = Array.isArray(data.items) ? data.items : [];
                        results.innerHTML = '';
                        if (!users.length) { results.style.display = 'none'; return; }
                        users.forEach(u => {
                            if (selectedEmployees.some(e => e.userId === u.id)) return;
                            const item = document.createElement('div');
                            item.className = 'addr-result-item';
                            const name = ((u.first_name || '') + ' ' + (u.last_name || '')).trim();
                            item.innerHTML = '<i class="fa-solid fa-user"></i><span>' + escHtml(name ? name + ' \u2014 ' + u.username : u.username) + '</span>';
                            item.addEventListener('click', function() {
                                addEmployee({ userId: u.id, name: name ? name + ' — ' + u.username : u.username });
                                input.value = '';
                                results.style.display = 'none';
                            });
                            results.appendChild(item);
                        });
                        results.style.display = 'block';
                    })
                    .catch(() => { results.style.display = 'none'; });
            }, 300);
        });

        input.addEventListener('keydown', function(e) {
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

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    function bindEmployeeFilterLookup() {
        const input = document.getElementById('employee-filter-search');
        const results = document.getElementById('employee-filter-results');
        const chip = document.getElementById('employee-filter-chip');
        const chipName = document.getElementById('employee-filter-chip-name');
        const chipRemove = document.getElementById('employee-filter-chip-remove');
        if (!input || !results || !chip || !chipName || !chipRemove) return;

        chipRemove.addEventListener('click', function() {
            employeeFilterId = '';
            employeeFilterName = '';
            chip.style.display = 'none';
            input.closest('#employee-filter-wrapper').querySelector('.input-wrapper').style.display = '';
            resetList();
        });

        input.addEventListener('input', function() {
            clearTimeout(_filterTimer);
            const q = this.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            _filterTimer = setTimeout(() => {
                fetch('users-list-api?search=' + encodeURIComponent(q) + '&limit=6&user_type=4')
                    .then(r => r.json())
                    .then(data => {
                        const users = Array.isArray(data.items) ? data.items : [];
                        results.innerHTML = '';
                        if (!users.length) { results.style.display = 'none'; return; }
                        users.forEach(u => {
                            const item = document.createElement('div');
                            item.className = 'addr-result-item';
                            const name = ((u.first_name || '') + ' ' + (u.last_name || '')).trim();
                            item.innerHTML = '<i class="fa-solid fa-user"></i><span>' + escHtml(name ? name + ' \u2014 ' + u.username : u.username) + '</span>';
                            item.addEventListener('click', function() {
                                employeeFilterId = u.id;
                                employeeFilterName = name ? name + ' — ' + u.username : u.username;
                                chipName.textContent = employeeFilterName;
                                chip.style.display = 'flex';
                                input.closest('#employee-filter-wrapper').querySelector('.input-wrapper').style.display = 'none';
                                results.style.display = 'none';
                                resetList();
                            });
                            results.appendChild(item);
                        });
                        results.style.display = 'block';
                    })
                    .catch(() => { results.style.display = 'none'; });
            }, 300);
        });

        input.addEventListener('keydown', function(e) {
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

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    function bindMeetingSwitcher() {
        const wrapper = document.getElementById('svc-meet-switcher');
        if (!wrapper) return;
        wrapper.addEventListener('click', function(e) {
            const btn = e.target.closest('.svc-meet-opt');
            if (!btn) return;
            wrapper.querySelectorAll('.svc-meet-opt').forEach(b => b.classList.toggle('is-active', b === btn));
            meetingType = btn.dataset.type;
            const urlWrap = document.getElementById('svc-meeting-url-wrap');
            if (urlWrap) urlWrap.style.display = meetingType === 'other' ? 'block' : 'none';
        });
        const urlInput = document.getElementById('svc-meeting-url');
        if (urlInput) {
            urlInput.addEventListener('input', function() { meetingUrl = this.value; });
        }
    }

    function updateMeetingUI() {
        const wrapper = document.getElementById('svc-meet-switcher');
        if (!wrapper) return;
        wrapper.querySelectorAll('.svc-meet-opt').forEach(b => b.classList.toggle('is-active', b.dataset.type === meetingType));
        const urlWrap = document.getElementById('svc-meeting-url-wrap');
        if (urlWrap) urlWrap.style.display = meetingType === 'other' ? 'block' : 'none';
        const urlInput = document.getElementById('svc-meeting-url');
        if (urlInput) urlInput.value = meetingUrl;
    }


    function addEmployee(emp) {
        selectedEmployees.push(emp);
        renderEmployeeChips();
    }

    function renderEmployeeChips() {
        const container = document.getElementById('employee-chips');
        if (!container) return;
        container.innerHTML = '';
        selectedEmployees.forEach(emp => {
            const chip = document.createElement('div');
            chip.className = 'employee-chip';
            chip.style = 'display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f0fdf4;border:1px solid #a7f3d0;border-radius:20px;width:fit-content;margin-bottom:8px;';
            const text = document.createElement('span');
            text.textContent = emp.name;
            chip.appendChild(text);
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.style = 'background:none;border:none;cursor:pointer;padding:0 0 0 4px;color:#9ca3af;line-height:1;display:flex;align-items:center;';
            removeBtn.setAttribute('aria-label','Remove employee');
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark" style="font-size:.85em;"></i>';
            removeBtn.addEventListener('click', () => {
                selectedEmployees = selectedEmployees.filter(e => e.userId !== emp.userId);
                renderEmployeeChips();
            });
            chip.appendChild(removeBtn);
            container.appendChild(chip);
        });
    }

    function loadAffectedEmployees(serviceId) {
        selectedEmployees = [];
        originalEmployees = [];
        renderEmployeeChips();
        if (!serviceId) return;
        fetch(`service-affected-list-api.php?service_id=${encodeURIComponent(serviceId)}`)
            .then(r => r.json())
            .then(arr => {
                arr.forEach(ae => {
                    originalEmployees.push(ae);

                    selectedEmployees.push({ userId: ae.user_id, name: ae.user_id });
                    renderEmployeeChips();

                    fetch(`user-get-api.php?id=${encodeURIComponent(ae.user_id)}`)
                        .then(r => r.json())
                        .then(u => {
                            const name = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username;

                            const idx = selectedEmployees.findIndex(e => e.userId === ae.user_id);
                            if (idx !== -1) {
                                selectedEmployees[idx].name = name;
                                renderEmployeeChips();
                            }
                        })
                        .catch(() => {

                        });
                });
            });
    }

    function syncAffectedEmployees(serviceId) {
        if (!serviceId) return Promise.resolve();
        const toAdd = selectedEmployees.filter(e => !originalEmployees.some(o => o.user_id === e.userId));
        const toRemove = originalEmployees.filter(o => !selectedEmployees.some(e => e.userId === o.user_id));
        const promises = [];
        toAdd.forEach(e => {
            promises.push(
                fetch('service-affected-add-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ service_id: serviceId, user_id: e.userId })
                })
            );
        });
        toRemove.forEach(o => {
            promises.push(
                fetch('service-affected-remove-api.php?service_id=' + encodeURIComponent(serviceId) + '&ae_id=' + encodeURIComponent(o.id),
                    { method: 'DELETE' }
                )
            );
        });
        return Promise.all(promises);
    }


    function bindToolbar() {
        document.getElementById('create-service-btn')?.addEventListener('click', openCreateForm);
        document.getElementById('create-type-btn')?.addEventListener('click', openTypeForm);
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
        if (employeeFilterId) url += `&employee_id=${encodeURIComponent(employeeFilterId)}`;

        fetch(url)
            .then(r => r.text())
            .then(text => {
                const data     = text ? JSON.parse(text) : {};
                let services = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total    = Number.isFinite(data.total) ? data.total : services.length;
                totalCount     = total;

                if (!append) container.innerHTML = '';

                if (searchTerm) {
                    const term = searchTerm.toLowerCase();
                    services = services.filter(s => (s.name||'').toLowerCase().includes(term));
                }
                if (typeFilter) {
                    services = services.filter(s => s.type_id === typeFilter);
                }

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

            const typeLabel = typeLabels[svc.type_id] || svc.type_id || 'Unknown';
            const icon      = typeIcons[svc.type_id]  || 'fa-calendar';
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
                    <button class="btn-danger svc-delete-btn" data-id="${svc.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>`;

            card.querySelector('.svc-edit-btn').addEventListener('click', () => openEditForm(svc));
            card.querySelector('.svc-delete-btn').addEventListener('click', () => confirmDelete(svc));

            container.appendChild(card);
        });
    }

    function renderSkeletons(container, n) {
        const card =
            '<div class="skeleton-service-item">' +
                '<div class="skeleton-service-header">' +
                    '<div class="skeleton skeleton-title" style="flex:1;"></div>' +
                    '<div class="skeleton skeleton-badge"></div>' +
                '</div>' +
                '<div class="skeleton skeleton-description"></div>' +
                '<div class="skeleton skeleton-description" style="width:75%;"></div>' +
                '<div class="skeleton-meta">' +
                    '<div class="skeleton"></div>' +
                    '<div class="skeleton"></div>' +
                    '<div class="skeleton"></div>' +
                    '<div class="skeleton"></div>' +
                '</div>' +
                '<div class="skeleton-buttons">' +
                    '<div class="skeleton skeleton-button"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                '</div>' +
            '</div>';
        container.innerHTML = Array(n).fill(card).join('');
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

        currentServiceId = '';
        selectedEmployees = [];
        originalEmployees = [];
        renderEmployeeChips();
        meetingType = 'none';
        meetingUrl = '';
        updateMeetingUI();
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
        form.querySelector('#svc-type').value        = svc.type_id || '';
        form.querySelector('#svc-date').value        = (svc.service_date ?? '').substring(0, 10);
        form.querySelector('#svc-max-participants').value = svc.maximum_participants ?? '';

        const hasAddress = svc.service_road || svc.service_city;
        setLocationMode(hasAddress ? 'office' : 'online');
        if (hasAddress) {
            form.querySelector('#svc-road').value = svc.service_road ?? '';
            form.querySelector('#svc-city').value = svc.service_city ?? '';
            form.querySelector('#svc-zip').value  = svc.service_zip  ?? '';
        }

        currentServiceId = svc.id;
        loadAffectedEmployees(svc.id);

        meetingType = svc.meeting_type || 'none';
        meetingUrl  = svc.online_meeting_link || '';
        updateMeetingUI();

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
            type_id:              this.querySelector('#svc-type').value,
            service_date:         this.querySelector('#svc-date').value,
            service_road:         this.querySelector('#svc-road').value.trim(),
            service_city:         this.querySelector('#svc-city').value.trim(),
            service_zip:          this.querySelector('#svc-zip').value.trim(),
            meeting_type:         meetingType,
            online_meeting_link:  meetingUrl
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
                const trimmed = text.trim();
                const data = trimmed ? JSON.parse(trimmed) : {};
                if (data.error) {
                    let msg = data.error;
                    if (data.body && msg.startsWith('API returned HTTP')) {
                        msg = data.body;
                    }
                    throw new Error(msg);
                }

                let svcId = editId || (data && data.id);
                if (!svcId && data && data.id) svcId = data.id;
                if (!editId && !svcId) {
                    hideModal('service-form-modal');
                    resetList();
                } else {
                    syncAffectedEmployees(svcId)
                        .finally(() => {
                            hideModal('service-form-modal');
                            resetList();
                        });
                }
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
        if (m) { m.classList.add('is-open'); m.setAttribute('aria-hidden', 'false'); }
    }

    function loadTypes() {
        return fetch('type-prestations-list-api.php')
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : [];
                prestationTypes = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
                populateTypeOptions();
            })
            .catch(err => { console.error('failed to load types', err); });
    }

    function populateTypeOptions() {
        const filter = document.getElementById('service-type-filter');
        const svcSelect = document.getElementById('svc-type');
        if (filter) {
            filter.innerHTML = '<option value="">All types</option>';
            prestationTypes.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                filter.appendChild(opt);
                typeLabels[t.id] = t.name;
                const nm = t.name.toLowerCase();
                if (nm.includes('formation')) typeIcons[t.id] = 'fa-graduation-cap';
                else if (nm.includes('event')) typeIcons[t.id] = 'fa-calendar-days';
                else if (nm.includes('consult')) typeIcons[t.id] = 'fa-user-tie';
                else typeIcons[t.id] = 'fa-calendar';
            });
        }
        if (svcSelect) {
            svcSelect.innerHTML = '<option value="">-- select type --</option>';
            prestationTypes.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                svcSelect.appendChild(opt);
            });
        }
    }

    function openTypeForm() {
        const form = document.getElementById('type-form');
        if (form) form.reset();
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
        fetch('type-prestation-create-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        })
            .then(r => r.text())
            .then(text => {
                console.log('type creation raw response:', text);
                let data;
                const trimmed = text.trim();
                try {
                    data = trimmed ? JSON.parse(trimmed) : {};
                } catch (ex) {
                    console.error('failed to parse type creation response', ex, text);
                    data = { error: 'Invalid response from server', body: trimmed };
                }
                if (data.error) {
                    let msg = data.error;
                    if (data.body && (msg.startsWith('API returned HTTP') || msg === 'Invalid response from server')) {
                        msg = data.body;
                    }
                    throw new Error(msg);
                }
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

    document.getElementById('type-form-cancel')?.addEventListener('click', function () { hideModal('type-form-modal'); });
    document.getElementById('type-form-modal-close')?.addEventListener('click', function () { hideModal('type-form-modal'); });
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
