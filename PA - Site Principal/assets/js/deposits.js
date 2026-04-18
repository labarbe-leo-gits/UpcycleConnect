(function() {
    'use strict';

    const pageSize = 3;
    let currentPage = 1;
    let totalPages = 1;

    let editMode = false;
    let editingDepositId = null;
    let existingDepositFiles = [];
    let removedDepositFileIds = [];

    function showInlineStatus(text) {
        let st = document.getElementById('suggest-status');
        if (!st) {
            st = document.createElement('div');
            st.id = 'suggest-status';
            st.style.marginTop = '8px';
            st.style.fontSize = '0.95em';
            st.style.color = '#10b981';
            const parent = document.getElementById('suggest-conteneur');
            if (parent && parent.parentNode) parent.parentNode.appendChild(st);
        }
        if (st) st.textContent = text;
    }
    function clearInlineStatus() {
        const st = document.getElementById('suggest-status');
        if (st) st.remove();
    }

    function haversine(lat1, lon1, lat2, lon2) {
        function toRad(x) { return x * Math.PI / 180; }
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function showCustomModal(message, addressFields = false) {
        let modal = document.getElementById('custom-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'custom-modal';
            modal.className = 'deposit-modal add-modal';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="deposit-modal-content add-modal-content" style="max-width:400px;text-align:center;">
                    <span class="close-button" id="close-custom-modal">&times;</span>
                    <div id="custom-modal-message" style="margin:32px 0 24px;font-size:1.15em;"></div>
                    <button type="button" id="custom-modal-ok" class="add-offer-button" style="margin-bottom:8px;">OK</button>
                </div>
            `;
            document.body.appendChild(modal);
            modal.querySelector('#close-custom-modal').onclick = closeCustomModal;
            modal.querySelector('#custom-modal-ok').onclick = closeCustomModal;
        }
        if (addressFields) {
            modal.querySelector('#custom-modal-message').innerHTML = `
                <div style="font-weight:600;font-size:1.08em;margin-bottom:12px;">Enter an address:</div>
                <div class="field" style="margin-bottom:8px;position:relative;">
                    <div class="input-wrapper" style="width:100%;">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                        <input id="manual-searchbar-input" type="text" placeholder="Search address" autocomplete="off" style="padding-left:36px;width:100%;" />
                    </div>
                    <div id="manual-searchbar-results"></div>
                </div>
                <div class="manual-fields" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="input-wrapper"><i class="fa-solid fa-hashtag"></i><input id="manual-number-input" type="text" placeholder="#" style="padding-left:36px;width:100%;" /></div>
                    <div class="input-wrapper"><i class="fa-solid fa-road"></i><input id="manual-road-input" type="text" placeholder="Road" style="padding-left:36px;width:100%;" /></div>
                    <div class="input-wrapper"><i class="fa-solid fa-envelope"></i><input id="manual-postal-input" type="text" placeholder="Postal" style="padding-left:36px;width:100%;" /></div>
                    <div class="input-wrapper"><i class="fa-solid fa-city"></i><input id="manual-city-input" type="text" placeholder="City" style="padding-left:36px;width:100%;" /></div>
                </div>
            `;
            const okBtn = modal.querySelector('#custom-modal-ok');
            okBtn.textContent = 'OK';
            okBtn.style.background = '#1abc9c';
            okBtn.style.color = '#fff';
            okBtn.style.border = 'none';
            okBtn.style.borderRadius = '24px';
            okBtn.style.padding = '12px 32px';
            okBtn.style.fontSize = '1.08em';
            okBtn.style.fontWeight = '600';
            okBtn.style.boxShadow = '0 2px 8px rgba(26,188,156,0.12)';
            okBtn.style.transition = 'background 0.2s';
            okBtn.onclick = function() {
                const number = document.getElementById('manual-number-input').value;
                const road = document.getElementById('manual-road-input').value;
                const postal = document.getElementById('manual-postal-input').value;
                const city = document.getElementById('manual-city-input').value;
                const address = [number, road, postal, city].filter(Boolean).join(', ');
                closeCustomModal();
                if (address) suggestConteneurByAddress(address);
            };
            modal.querySelector('#close-custom-modal').onclick = closeCustomModal;
            modal.style.display = '';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            setTimeout(() => {
                document.getElementById('manual-number-input').focus();
            }, 100);

            const searchInput = document.getElementById('manual-searchbar-input');
            const resultsDiv = document.getElementById('manual-searchbar-results');
            let searchTimeout = null;
            searchInput.oninput = function() {
                const val = searchInput.value.trim();
                if (!val) { resultsDiv.innerHTML = ''; resultsDiv.style.display = 'none'; return; }
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch(`https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(val)}`)
                        .then(r => {
                            if (!r.ok) throw new Error('datagouv failed');
                            return r.json();
                        })
                        .catch(err => {
                            console.warn('datagouv lookup failed, using nominatim as fallback', err);
                            return fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(val)}`)
                                .then(r => r.json())
                                .then(items => ({
                                    features: Array.isArray(items) ? items.map(i => ({
                                        properties: {
                                            housenumber: (i.address && i.address.house_number) || '',
                                            street: (i.address && (i.address.road || i.address.pedestrian || i.address.residential || i.address.cycleway || i.address.footway)) || i.display_name || '',
                                            postcode: (i.address && i.address.postcode) || '',
                                            city: (i.address && (i.address.city || i.address.town || i.address.village)) || ''
                                        }
                                    })) : []
                                }));
                        })
                        .then(data => {
                            if (data && data.features && data.features.length) {
                                resultsDiv.style.display = 'block';
                                resultsDiv.innerHTML = data.features.slice(0,5).map(f => {
                                    const props = f.properties;
                                    return `<div class="addr-result-item" data-number="${props.housenumber||''}" data-road="${props.street||props.name||''}" data-postal="${props.postcode||''}" data-city="${props.city||''}"><i class='fa-solid fa-location-dot'></i>${props.housenumber||''} ${props.street||props.name||''}, ${props.postcode||''} ${props.city||''}</div>`;
                                }).join('');
                                resultsDiv.querySelectorAll('.addr-result-item').forEach(item => {
                                    item.onclick = function() {
                                        document.getElementById('manual-number-input').value = item.getAttribute('data-number');
                                        document.getElementById('manual-road-input').value = item.getAttribute('data-road');
                                        document.getElementById('manual-postal-input').value = item.getAttribute('data-postal');
                                        document.getElementById('manual-city-input').value = item.getAttribute('data-city');
                                        resultsDiv.innerHTML = '';
                                        resultsDiv.style.display = 'none';
                                    };
                                });
                            } else {
                                resultsDiv.style.display = 'block';
                                resultsDiv.innerHTML = '<div style="padding:6px 8px;color:#888;">No results</div>';
                            }
                        })
                        .catch(() => { resultsDiv.style.display = 'block'; resultsDiv.innerHTML = '<div style="padding:6px 8px;color:#888;">Error</div>'; });
                }, 350);
            };
        } else {
            modal.querySelector('#custom-modal-message').innerHTML = message;
            modal.querySelector('#custom-modal-ok').onclick = closeCustomModal;
        }
        modal.style.display = '';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        modal.style.zIndex = 4000;
        document.body.classList.add('modal-open');
        function closeCustomModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 220);
        }
    }

    function suggestConteneurByAddress(address) {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
            .then(r => r.json())
            .then(results => {
                if (Array.isArray(results) && results.length) {
                    const lat = parseFloat(results[0].lat);
                    const lon = parseFloat(results[0].lon);
                    suggestConteneurByCoords(lat, lon, true, address);
                } else {
                    showCustomModal('Address not found.');
                }
            })
            .catch(() => showCustomModal('Failed to geocode address.'));
    }

    function suggestConteneurByCoords(lat, lon, useModal = true, sourceAddr = '') {
        console.log('[DEBUG] Geolocation received:', { lat, lon, useModal, sourceAddr });
        const list = Array.isArray(window.AVAILABLE_CONTENEURS) ? window.AVAILABLE_CONTENEURS : [];
        if (!list.length) {
            if (useModal) return showCustomModal('No conteneurs available.');
            clearInlineStatus();
            showCustomModal('No conteneurs available.');
            return;
        }

        if (useModal) {
            showCustomModal(`<div style='display:flex;flex-direction:column;align-items:center;justify-content:center;'><i class='fa-solid fa-spinner fa-spin' style='font-size:2em;color:#10b981;margin-bottom:12px;'></i><span>Finding the nearest conteneur...</span></div>`);
        } else {
            showInlineStatus('Finding the nearest conteneur...');
        }

        let minDist = Infinity, nearest = null;
        let completed = 0;
        list.forEach((c, idx) => {
            const address = [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ');
            if (!address) {
                console.debug('[geocode] skipping container with no address', c);
                completed++;
                if (completed === list.length) finalize();
                return;
            }
            geocodeAddress(address).then(coords => {
                if (coords) {
                    const clat = coords.lat;
                    const clon = coords.lon;
                    const d = haversine(lat, lon, clat, clon);
                    console.debug('[geocode] ', address, '=>', coords, 'dist', d.toFixed(3));
                    if (d < minDist) { minDist = d; nearest = c; }
                } else {
                    console.warn('[geocode] no coords for', address);
                }
            }).catch(err => {
                console.warn('[geocode] exception for', address, err);
            }).finally(() => {
                completed++;
                if (completed === list.length) finalize();
            });
        });
        function finalize() {
            if (useModal) {
                let modal = document.getElementById('custom-modal');
                if (modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); modal.style.display = 'none'; document.body.classList.remove('modal-open'); }
            } else {
                clearInlineStatus();
            }
            if (!nearest && sourceAddr) {
                const low = sourceAddr.toLowerCase();
                const cand = list.find(c => {
                    const road = (c.road||'').toLowerCase();
                    const city = (c.city||'').toLowerCase();
                    return road && low.indexOf(road) !== -1 && (!city || low.indexOf(city) !== -1);
                });
                if (cand) nearest = cand;
            }
            if (nearest) {
                const select = document.getElementById('deposit-conteneur');
                if (select) {
                    select.value = nearest.id || nearest.ID || '';
                    select.dispatchEvent(new Event('change'));
                    select.focus();
                }
            } else {
                showCustomModal('No conteneur found near this location.');
            }
        }
    }


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            currentPage = getPageFromUrl();
            requestPage(currentPage, true);
            setupModalHandlers();
            setupAddDepositModal();
        });
    } else {
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
        setupModalHandlers();
        setupAddDepositModal();
    }

    function requestPage(page, replaceHistory) {
        const container = document.getElementById('deposits-container');
        const pagination = document.getElementById('deposits-pagination');
        if (!container) return;

        renderSkeletons(container, pageSize);

        let apiUrl = `deposits-api?page=${page}&limit=${pageSize}`;
        if (apiUrl.indexOf('http://') !== 0 && apiUrl.indexOf('https://') !== 0) {
            apiUrl = new URL(apiUrl, window.location.href).href;
        }

        fetch(apiUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(resp => resp.text())
        .then(text => {
            if (text && text.trim().charAt(0) === '<') {
                console.error('Deposits API returned non-JSON response:', text);
            }
            let data = {};
            try {
                data = text ? JSON.parse(text) : {};
            } catch (parseErr) {
                console.error('Failed to parse deposits API response:', text, parseErr);
                throw parseErr;
            }
            if (data && data.error) {
                throw new Error(data.error || 'Server error');
            }
            const items = Array.isArray(data.items) ? data.items : [];
            const total = Number.isFinite(data.total) ? data.total : items.length;
            totalPages = total > 0 ? Math.ceil(total / pageSize) : 1;

            if (!items || items.length === 0) {
                container.innerHTML = '<div class="deposit-empty"><i class="fa-solid fa-warehouse fa-2x"></i><p>No deposit requests found.</p></div>';
                if (pagination) pagination.innerHTML = '';
                updateUrlPage(1, true);
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

            container.innerHTML = '';
            if (pagination) pagination.innerHTML = '';
            renderDeposits(items, container);
            renderPagination(pagination);
        })
        .catch(err => {
            console.error('Failed to load deposits', err);
            container.innerHTML = '<p class="error-message">Unable to load deposits. Please try again later.</p>';
            if (pagination) pagination.innerHTML = '';
        });
    }

    function renderDeposits(items, container) {
        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'deposit-card';

            const title = document.createElement('h3');
            title.textContent = item.object_name || 'Deposit';
            card.appendChild(title);

            const meta = document.createElement('div');
            meta.className = 'deposit-meta';
            const created = document.createElement('span');
            created.innerHTML = `<i class="fa-regular fa-clock"></i>&nbsp;${formatDate(item.created_at)}`;
            meta.appendChild(created);

            const cont = document.createElement('span');
            cont.innerHTML = `<i class="fa-solid fa-warehouse"></i>&nbsp;${escapeHtml((item.conteneur && item.conteneur.name) || item.conteneur_name || '-')}`;
            meta.appendChild(cont);

            const status = document.createElement('span');
            const statusClass = mapStatusClass(item.status);
            status.className = `deposit-status ${statusClass}`;
            status.textContent = mapStatusLabel(item.status);
            meta.appendChild(status);

            card.appendChild(meta);

            if (item.object_description) {
                const desc = document.createElement('p');
                desc.className = 'deposit-description';
                desc.textContent = item.object_description;
                card.appendChild(desc);
            }

            const actions = document.createElement('div');
            actions.className = 'deposit-actions';

            const detailsBtn = document.createElement('button');
            detailsBtn.type = 'button';
            detailsBtn.className = 'btn-secondary';
            detailsBtn.textContent = 'Details';
            detailsBtn.addEventListener('click', function() {
                openDetailsModal(item.id);
            });
            actions.appendChild(detailsBtn);

            const currentStatus = parseInt(item.status || 0, 10);
            if (currentStatus === 1) {
                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn-secondary';
                editBtn.innerHTML = '<i class="fa-solid fa-pencil" style="margin-right:6px;"></i>Edit';
                editBtn.title = 'Edit this pending deposit';
                editBtn.addEventListener('click', function() {
                    openEditDepositModal(item);
                });
                actions.appendChild(editBtn);
            }
            if (currentStatus === 2) {
                const depositedBtn = document.createElement('button');
                depositedBtn.type = 'button';
                depositedBtn.className = 'btn-primary';
                depositedBtn.textContent = 'Mark as Deposited';
                depositedBtn.title = 'Finalize this approved deposit as deposited';

                depositedBtn.addEventListener('click', function() {
                    depositedBtn.disabled = true;
                    depositedBtn.textContent = 'Updating...';
                    const apiUrl = '../customers/deposit-status-api';

                    fetch(apiUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ id: item.id, status: 4 })
                    })
                    .then(r => r.text())
                    .then(txt => {
                        let res = {};
                        try { res = txt ? JSON.parse(txt) : {}; } catch (e) { res = {}; }
                        if (res && !res.error) {
                            showToast('Deposit marked as deposited', 'success');
                            requestPage(currentPage, true);
                        } else {
                            showToast('Failed to set deposited', 'error');
                            depositedBtn.disabled = false;
                            depositedBtn.textContent = 'Mark as Deposited';
                        }
                    })
                    .catch(() => {
                        showToast('Failed to set deposited', 'error');
                        depositedBtn.disabled = false;
                        depositedBtn.textContent = 'Mark as Deposited';
                    });
                });
                actions.appendChild(depositedBtn);
            }

            card.appendChild(actions);
            container.appendChild(card);
        });
    }

    function openEditDepositModal(item) {
        editMode = true;
        editingDepositId = item.id;
        existingDepositFiles = [];
        removedDepositFileIds = [];

        const modal = document.getElementById('add-deposit-modal');
        if (!modal) return;

        document.getElementById('deposit-id').value = item.id || '';
        document.getElementById('deposit-conteneur').value = item.conteneur_id || item.conteneur?.id || '';
        const objectStateSelect = document.getElementById('deposit-object-state');
        if (objectStateSelect) {
            objectStateSelect.value = typeof item.object_state !== 'undefined' ? String(item.object_state) : '0';
        }
        document.getElementById('deposit-object-name').value = item.object_name || '';
        document.getElementById('deposit-object-description').value = item.object_description || '';

        renderExistingFiles();
        fetch(`deposits-detail-api?deposit_id=${encodeURIComponent(item.id)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            existingDepositFiles = Array.isArray(data.files) ? data.files : [];
            if (data && typeof data.object_state !== 'undefined') {
                const objectStateSelect = document.getElementById('deposit-object-state');
                if (objectStateSelect) {
                    objectStateSelect.value = String(data.object_state);
                }
            }
            renderExistingFiles();
        })
        .catch(err => {
            console.error('Failed to fetch deposit details for edit', err);
        });

        const title = modal.querySelector('h2');
        if (title) title.textContent = 'Edit Deposit Request';

        const submitBtn = document.getElementById('add-deposit-submit');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save changes';
        }

        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');

        const focusTarget = document.getElementById('deposit-object-name');
        if (focusTarget) focusTarget.focus();
    }

    function renderExistingFiles() {
        const existing = document.getElementById('deposit-existing-files');
        if (!existing) return;
        if (!Array.isArray(existingDepositFiles) || existingDepositFiles.length === 0) {
            existing.innerHTML = '<p style="color:#6b7280;margin:0;">No existing files.</p>';
            return;
        }

        existing.innerHTML = '';
        existingDepositFiles.forEach(function(f) {
            const chip = document.createElement('div');
            chip.className = 'file-chip';

            const iconWrap = document.createElement('div');
            iconWrap.className = 'file-chip-icon';
            const ico = document.createElement('i');
            ico.className = 'fa-solid fa-file-image';
            iconWrap.appendChild(ico);
            chip.appendChild(iconWrap);

            const name = document.createElement('span');
            name.className = 'file-chip-name';
            name.textContent = f.original_name || f.filename;
            name.title = f.original_name || f.filename;
            chip.appendChild(name);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'file-chip-remove';
            remove.setAttribute('aria-label', 'Remove ' + (f.original_name || f.filename));
            remove.innerHTML = '&times;';
            remove.addEventListener('click', function() {
                removedDepositFileIds.push(f.id);
                existingDepositFiles = existingDepositFiles.filter(function(x) { return x.id !== f.id; });
                renderExistingFiles();
            });
            chip.appendChild(remove);

            existing.appendChild(chip);
        });
    }

    function openDetailsModal(depositId) {
        const modal = document.getElementById('deposit-modal');
        const modalContent = document.querySelector('.deposit-modal-content');
        if (!modal) return;

        showModal();
        const titleEl = document.getElementById('deposit-modal-title');
        const depositInfoEl = document.getElementById('deposit-info');
        const conteneurInfoEl = document.getElementById('conteneur-info');
        const mapEl = document.getElementById('deposit-map');

        titleEl.textContent = 'Loading…';
        depositInfoEl.innerHTML = `
            <div class="skeleton skeleton-title" style="width:40%"></div>
            <div class="skeleton skeleton-description" style="height:12px;margin-top:12px;width:60%"></div>
            <div class="skeleton skeleton-description" style="height:12px;margin-top:8px;width:50%"></div>
            <div class="skeleton skeleton-description" style="height:12px;margin-top:8px;width:30%"></div>
        `;
        conteneurInfoEl.innerHTML = `<div class="deposit-conteneur skeleton" style="height:72px;"></div>`;
        mapEl.innerHTML = `<div class="skeleton" style="height:320px;border-radius:10px"></div>`;

        const depositImageEl = document.querySelector('.deposit-image');
        if (depositImageEl) {
            depositImageEl.dataset._original = depositImageEl.innerHTML;
            depositImageEl.innerHTML = '<div class="skeleton" style="width:100%;height:320px;border-radius:10px"></div>';
        }

        fetch(`deposits-detail-api?deposit_id=${encodeURIComponent(depositId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(text => {
            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (parseErr) {
                console.error('Failed to parse deposits-detail response (raw):', text);
                depositInfoEl.innerHTML = `<p class="error-message">Unable to load details (invalid API response).</p>`;
                titleEl.textContent = 'Error';
                return;
            }

            if (!data || data.error) {
                if (data && data.api_raw) console.error('Upstream API returned invalid payload:', data.api_raw);
                depositInfoEl.innerHTML = `<p class="error-message">Unable to load details.</p>`;
                titleEl.textContent = 'Error';
                return;
            }

            const deposit = data.deposit || {};
            const conteneur = data.conteneur || {};
            titleEl.textContent = deposit.object_name || 'Deposit Details';

            depositInfoEl.innerHTML = `
                <p><strong>Object:</strong> ${escapeHtml(deposit.object_name || '-')}</p>
                <p><strong>Condition:</strong> ${escapeHtml(mapObjectStateLabel(deposit.object_state || 0))}</p>
                <p><strong>Description:</strong> ${escapeHtml(deposit.object_description || '-')}</p>
                <p><strong>Status:</strong> ${escapeHtml(mapStatusLabel(deposit.status || 0))}</p>
                <p><strong>Created:</strong> ${formatDate(deposit.created_at)}</p>
            `;
            depositInfoEl.classList.remove('fade-in');
            void depositInfoEl.offsetWidth; // trigger reflow
            depositInfoEl.classList.add('fade-in');
            setTimeout(() => depositInfoEl.classList.remove('fade-in'), 360);

            const filesSection = document.getElementById('deposit-files-section');
            const gallery = document.getElementById('deposit-modal-gallery');
            const downloads = document.getElementById('deposit-modal-downloads');
            const downloadZipBtn = document.getElementById('deposit-download-zip');
            const files = Array.isArray(data.files) ? data.files : [];
            if (filesSection && gallery && downloads && downloadZipBtn) {
                if (files.length > 0) {
                    gallery.innerHTML = '';
                    downloads.innerHTML = '';
                    downloadZipBtn.style.display = '';
                    downloadZipBtn.onclick = function() {
                        window.location.href = 'deposit-download-files?deposit_id=' + encodeURIComponent(deposit.id);
                    };
                    files.forEach(function(f) {
                        const imgUrl = '/files/uploads/deposit/' + encodeURIComponent(f.filename);
                        const thumb = document.createElement('img');
                        thumb.src = imgUrl;
                        thumb.alt = escapeHtml(f.original_name || f.filename);
                        thumb.title = escapeHtml(f.original_name || f.filename);
                        thumb.addEventListener('click', function() {
                            window.open(imgUrl, '_blank');
                        });
                        thumb.onerror = function() { this.style.display = 'none'; };
                        gallery.appendChild(thumb);

                        const link = document.createElement('a');
                        link.href = imgUrl;
                        link.download = f.original_name || f.filename;

                        const ico = document.createElement('i');
                        ico.className = 'fa-solid fa-download';
                        link.appendChild(ico);
                        const txt = document.createElement('span');
                        txt.textContent = f.original_name || f.filename;
                        link.appendChild(txt);
                        downloads.appendChild(link);

                        if (deposit.status === 1) {
                            const delBtn = document.createElement('button');
                            delBtn.type = 'button';
                            delBtn.className = 'btn-secondary';
                            delBtn.style.marginLeft = '8px';
                            delBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                            delBtn.title = 'Remove this file from deposit';
                            delBtn.addEventListener('click', function() {
                                fetch('delete-deposit-file', {
                                    method: 'DELETE',
                                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: JSON.stringify({ deposit_id: deposit.id, file_id: f.id })
                                })
                                .then(r => r.json())
                                .then(resp => {
                                    if (resp && resp.status === 'ok') {
                                        showToast('File removed', 'success');
                                        openDetailsModal(deposit.id);
                                    } else {
                                        showToast(resp.error || 'Failed to remove file', 'error');
                                    }
                                })
                                .catch(err => {
                                    console.error('Delete deposit file failed', err);
                                    showToast('Failed to remove file', 'error');
                                });
                            });
                            downloads.appendChild(delBtn);
                        }
                    });
                    filesSection.style.display = '';
                } else {
                    filesSection.style.display = 'none';
                    gallery.innerHTML = '';
                    downloads.innerHTML = '';
                    if (downloadZipBtn) downloadZipBtn.style.display = 'none';
                }
            }

            conteneurInfoEl.innerHTML = `
                <div class="deposit-conteneur">
                    <p><strong>${escapeHtml(conteneur.name || conteneur_nameOrEmpty(conteneur))}</strong></p>
                    <p>${escapeHtml(buildConteneurAddress(conteneur) || '-')}</p>
                </div>
            `;
            conteneurInfoEl.classList.remove('fade-in');
            void conteneurInfoEl.offsetWidth;
            conteneurInfoEl.classList.add('fade-in');
            setTimeout(() => conteneurInfoEl.classList.remove('fade-in'), 360);

            const depositImageEl = document.querySelector('.deposit-image');
            if (depositImageEl) {
                let imgHtml = '';

                if (deposit.barcode && deposit.barcode.trim() !== '') {
                    const barcodeText = deposit.barcode.trim();

                    if (window.JsBarcode && typeof JsBarcode === 'function') {
                        const barcodeId = `deposit-barcode-svg-${Date.now()}`;
                        imgHtml = `
                            <div class="barcode-preview">
                                <svg id="${barcodeId}" class="deposit-barcode-svg" aria-label="Deposit barcode"></svg>
                                <div class="barcode-label">${escapeHtml(barcodeText)}</div>
                            </div>
                        `;
                        depositImageEl.innerHTML = imgHtml;
                        const svgEl = document.getElementById(barcodeId);
                        if (svgEl) {
                            try {
                                JsBarcode(svgEl, barcodeText, {
                                    format: 'CODE128',
                                    lineColor: '#000',
                                    width: 2,
                                    height: 80,
                                    displayValue: false,
                                    margin: 10,
                                });
                            } catch (err) {
                                console.warn('JsBarcode failed, falling back to remote generator', err);
                                depositImageEl.innerHTML = `
                                    <div class="barcode-preview">
                                        <img src="https://api.qrserver.com/v1/barcode?data=${encodeURIComponent(barcodeText)}&code=Code128&dpi=150" alt="Deposit barcode" />
                                        <div class="barcode-label">${escapeHtml(barcodeText)}</div>
                                    </div>
                                `;
                            }
                        }
                    } else {
                        imgHtml = `
                            <div class="barcode-preview">
                                <img src="https://api.qrserver.com/v1/barcode?data=${encodeURIComponent(barcodeText)}&code=Code128&dpi=150" alt="Deposit barcode" />
                                <div class="barcode-label">${escapeHtml(barcodeText)}</div>
                            </div>
                        `;
                        depositImageEl.innerHTML = imgHtml;
                    }
                } else {
                    const imgSrc = (conteneur.image && conteneur.image.length) ? conteneur.image : '../../assets/img/defaults/container.png';
                    if (typeof imgSrc === 'string' && imgSrc.indexOf('data:') === 0) {
                        imgHtml = `<img src="${imgSrc}" alt="Conteneur" />`;
                    } else {
                        imgHtml = `<img data-blob-src="${imgSrc}" alt="Conteneur" />`;
                    }
                    depositImageEl.innerHTML = imgHtml;
                }

                const imgNode = depositImageEl.querySelector('img');
                if (imgNode) {
                    const markLoaded = () => { imgNode.classList.add('fade-in'); };
                    const onError = () => {
                        const fallbackSrc = (conteneur.image && conteneur.image.length) ? conteneur.image : '../../assets/img/defaults/container.png';
                        imgNode.src = fallbackSrc;
                        imgNode.classList.add('fade-in');
                    };

                    if (imgNode.complete && imgNode.naturalWidth) {
                        markLoaded();
                    } else {
                        imgNode.addEventListener('load', markLoaded);
                        imgNode.addEventListener('error', onError);
                    }
                }
            }

            const address = buildConteneurAddress(conteneur);
            if (address && address.trim() !== '') {
                geocodeAddress(address).then(coords => {
                    if (coords) {
                        initMap(mapEl, coords.lat, coords.lon, conteneur.name || 'Conteneur');
                    } else {
                        mapEl.innerHTML = mapPlaceholderHtml('Address not found on map.');
                        const retryBtn = document.createElement('button');
                        retryBtn.type = 'button';
                        retryBtn.className = 'btn-secondary';
                        retryBtn.textContent = 'Retry map';
                        retryBtn.style.marginTop = '8px';
                        retryBtn.addEventListener('click', function() {
                            retryBtn.disabled = true;
                            retryBtn.textContent = 'Retrying…';
                            geocodeAddress(address).then(r2 => {
                                retryBtn.disabled = false;
                                retryBtn.textContent = 'Retry map';
                                if (r2) initMap(mapEl, r2.lat, r2.lon, conteneur.name || 'Conteneur');
                                else alert('Still unable to locate the address.');
                            }).catch(() => {
                                retryBtn.disabled = false;
                                retryBtn.textContent = 'Retry map';
                                alert('Error while retrying geocode. See console for details.');
                            });
                        });
                        const placeholderNode = mapEl.querySelector('.map-placeholder');
                        if (placeholderNode) placeholderNode.appendChild(retryBtn);
                    }
                }).catch(err => {
                    mapEl.innerHTML = mapPlaceholderHtml('Map unavailable.');
                });
            } else {
                mapEl.innerHTML = mapPlaceholderHtml('No address provided for this conteneur.');
            }
        })
        .catch(err => {
            console.error(err);
            depositInfoEl.innerHTML = `<p class="error-message">Failed to load details.</p>`;
            titleEl.textContent = 'Error';
        });
    }

    function conteneur_nameOrEmpty(c) {
        return c && c.name ? c.name : '';
    }

    function buildConteneurAddress(c) {
        if (!c) return '';
        const parts = [];
        if (c.number || c.number === 0) {
            if (Number(c.number) > 0) parts.push(String(c.number));
        }
        if (c.road) parts.push(c.road);
        if (c.postal_code) parts.push(c.postal_code);
        if (c.city) parts.push(c.city);
        return parts.join(', ');
    }

    function mapPlaceholderHtml(message) {
        const safeMessage = escapeHtml(message || 'Map unavailable.');
        return `
            <div class="deposit-empty map-placeholder">
                <div class="map-placeholder-icon">♻</div>
                <div class="map-placeholder-text">${safeMessage}</div>
            </div>
        `;
    }

    function geocodeAddress(address) {

        const nomUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`;
        return fetch(nomUrl, { headers: { 'Accept-Language': 'en' }, timeout: 8000 })
            .then(r => {
                if (!r.ok) throw new Error('nominatim bad status '+r.status);
                return r.json();
            })
            .then(results => {
                if (Array.isArray(results) && results.length) {
                    return { lat: parseFloat(results[0].lat), lon: parseFloat(results[0].lon) };
                }
                throw new Error('nominatim no results');
            })
            .catch(err => {
                console.warn('geocodeAddress: nominatim failure', err);
                const datUrl = `https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(address)}&limit=1`;
                return fetch(datUrl)
                    .then(r => {
                        if (!r.ok) throw new Error('datagouv bad status '+r.status);
                        return r.json();
                    })
                    .then(data => {
                        if (data && data.features && data.features.length) {
                            const coords = data.features[0].geometry.coordinates;
                            return { lat: coords[1], lon: coords[0] };
                        }
                        return null;
                    })
                    .catch(e2 => {
                        console.warn('geocodeAddress: datagouv failure', e2);
                        return null;
                    });
            });
    }

    function reverseGeocode(lat, lon) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`;
        return fetch(url, { headers: { 'Accept-Language': 'en' } })
            .then(r => r.ok ? r.json() : Promise.reject('reverse failed'))
            .then(data => {
                if (data && data.display_name) return data.display_name;
                return '';
            })
            .catch(err => {
                console.warn('reverseGeocode failed', err);
                return '';
            });
    }

    function initMap(el, lat, lng, title) {
        try {
            if (typeof L === 'undefined') throw new Error('Leaflet (L) is not loaded');

            const waitForVisible = (cb, attempt = 0) => {
                const w = el.offsetWidth || el.clientWidth;
                const h = el.offsetHeight || el.clientHeight;
                if (w > 0 && h > 0) return cb();
                if (attempt >= 8) {
                    el.innerHTML = mapPlaceholderHtml('Map unavailable.');
                    return;
                }
                setTimeout(() => waitForVisible(cb, attempt + 1), 100);
            };

            waitForVisible(() => {
                if (el._deposit_map && el._deposit_map.map) {
                    const existing = el._deposit_map;
                    const container = existing.map && existing.map._container;

                    if (!container || !container.parentNode) {
                        try { existing.map.remove(); } catch (e) {  }
                        delete el._deposit_map;
                    } else {
                        try {
                            existing.map.invalidateSize(true);
                            existing.map.setView([lat, lng], 15);

                            if (existing.marker) {
                                const markerEl = existing.marker._icon || null;
                                if (!markerEl || !markerEl.parentNode) {
                                    try { existing.marker.remove(); } catch (e) {  }
                                    existing.marker = L.marker([lat, lng]).addTo(existing.map);
                                } else {
                                    existing.marker.setLatLng([lat, lng]);
                                }
                            } else {
                                existing.marker = L.marker([lat, lng]).addTo(existing.map);
                            }

                            if (title && existing.marker) existing.marker.bindPopup(`<strong>${escapeHtml(title)}</strong>`).openPopup();
                            if (existing.tileLayer && existing.tileLayer.redraw) existing.tileLayer.redraw();
                            setTimeout(() => { try { existing.map.invalidateSize(true); } catch (e) {} }, 150);
                            return;
                        } catch (reuseErr) {
                            try { existing.map.remove(); } catch (e) {}
                            delete el._deposit_map;
                        }
                    }
                }

                el.innerHTML = '';
                const map = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], 15);
                const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
                }).addTo(map);
                const marker = L.marker([lat, lng]).addTo(map);
                if (title) marker.bindPopup(`<strong>${escapeHtml(title)}</strong>`).openPopup();
                if (map.zoomControl) map.zoomControl.setPosition('topright');

                el._deposit_map = { map: map, marker: marker, tileLayer: tileLayer };
                setTimeout(() => { try { map.invalidateSize(true); if (tileLayer.redraw) tileLayer.redraw(); } catch (e) {} }, 200);
            });
        } catch (err) {
            console.error('initMap error:', err);
            try { if (el._deposit_map && el._deposit_map.map) el._deposit_map.map.remove(); } catch (e) {}
            delete el._deposit_map;
            el.innerHTML = mapPlaceholderHtml('Unable to render map.');
        }
    }

    function showModal() {
        const modal = document.getElementById('deposit-modal');
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const mapEl = document.getElementById('deposit-map');
        if (mapEl && mapEl._deposit_map && mapEl._deposit_map.map) {
            requestAnimationFrame(() => setTimeout(() => {
                try { mapEl._deposit_map.map.invalidateSize(); } catch (e) { /* ignore */ }
            }, 120));
        }
    }

    function hideModal() {
        const modal = document.getElementById('deposit-modal');
        const mapEl = document.getElementById('deposit-map');
        if (mapEl && mapEl._deposit_map && mapEl._deposit_map.map) {
            try { mapEl._deposit_map.map.remove(); } catch (e) { /* ignore */ }
            delete mapEl._deposit_map;
        }
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function setupModalHandlers() {
        document.addEventListener('click', function(e) {
            const close = e.target.closest('.deposit-modal .close-button');
            if (close) { hideModal(); }
            const backdrop = e.target.closest('.deposit-modal.is-open');
            if (backdrop && e.target === backdrop) { hideModal(); }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideModal();
        });
    }

    function setupAddDepositModal() {
        const openBtn = document.getElementById('add-deposit');
        const modal = document.getElementById('add-deposit-modal');
        const closeBtn = document.getElementById('close-add-deposit');
        const form = document.getElementById('add-deposit-form');
        const conteneurSelect = document.getElementById('deposit-conteneur');
        const errorDiv = document.getElementById('add-deposit-error');
        const fileInput = document.getElementById('deposit-files');
        const filePreview = document.getElementById('deposit-files-preview');
        if (!openBtn || !modal || !form || !conteneurSelect) return;

        populateConteneurSelect();

        let lastFocused = null;

        openBtn.addEventListener('click', function() {
            lastFocused = document.activeElement;
            modal.classList.add('is-open');
            document.body.classList.add('modal-open');
            modal.setAttribute('aria-hidden', 'false');
            const focusTarget = document.getElementById('deposit-object-name') || conteneurSelect;
            if (focusTarget) focusTarget.focus();
        });

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function(e) {
            if (!modal.classList.contains('is-open')) return;
            if (e.key === 'Escape') closeModal();
            if (e.key === 'Tab') trapFocus(e);
        });

        const dropzone = document.getElementById('deposit-dropzone');
        let selectedFiles = [];
        const MAX_FILES = 5;
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const TYPE_ICONS = {
            'image': 'fa-solid fa-image',
            'application/pdf': 'fa-solid fa-file-pdf',
            'application/zip': 'fa-solid fa-file-zipper',
            'application/x-zip-compressed': 'fa-solid fa-file-zipper',
            'text': 'fa-solid fa-file-lines',
            'video': 'fa-solid fa-file-video',
            'audio': 'fa-solid fa-file-audio',
        };

        function getIconClass(type) {
            if (!type) return 'fa-solid fa-file';
            if (TYPE_ICONS[type]) return TYPE_ICONS[type];
            const base = type.split('/')[0];
            return TYPE_ICONS[base] || 'fa-solid fa-file';
        }

        function renderChips() {
            if (!filePreview) return;
            filePreview.innerHTML = '';
            selectedFiles.forEach(function(file, idx) {
                const chip = document.createElement('div');
                chip.className = 'file-chip';

                const isImage = file.type.startsWith('image/');
                if (isImage) {
                    const img = document.createElement('img');
                    img.className = 'file-chip-thumb';
                    img.alt = file.name;
                    const reader = new FileReader();
                    reader.onload = function(e) { img.src = e.target.result; };
                    reader.readAsDataURL(file);
                    chip.appendChild(img);
                } else {
                    const iconWrap = document.createElement('div');
                    iconWrap.className = 'file-chip-icon';
                    const ico = document.createElement('i');
                    ico.className = getIconClass(file.type);
                    iconWrap.appendChild(ico);
                    chip.appendChild(iconWrap);
                }

                const name = document.createElement('span');
                name.className = 'file-chip-name';
                name.textContent = file.name;
                name.title = file.name;
                chip.appendChild(name);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'file-chip-remove';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.innerHTML = '&times;';
                remove.addEventListener('click', function() {
                    selectedFiles.splice(idx, 1);
                    renderChips();
                    updateDropzoneState();
                });
                chip.appendChild(remove);

                filePreview.appendChild(chip);
            });
        }


        function updateDropzoneState() {
            if (!dropzone) return;
            if (selectedFiles.length >= MAX_FILES) {
                dropzone.style.opacity = '0.5';
                dropzone.style.pointerEvents = 'none';
            } else {
                dropzone.style.opacity = '';
                dropzone.style.pointerEvents = '';
            }
        }

        function addFiles(newFiles) {
            Array.from(newFiles).forEach(function(file) {
                if (selectedFiles.length >= MAX_FILES) return;
                if (ALLOWED_TYPES.indexOf(file.type) === -1) return;
                // deduplicate by name+size
                const already = selectedFiles.some(function(f) { return f.name === file.name && f.size === file.size; });
                if (!already) selectedFiles.push(file);
            });
            renderChips();
            updateDropzoneState();
        }

        if (dropzone) {
            dropzone.addEventListener('click', function() { if (fileInput) fileInput.click(); });
            dropzone.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (fileInput) fileInput.click(); } });
            dropzone.addEventListener('dragover', function(e) { e.preventDefault(); dropzone.classList.add('drag-over'); });
            dropzone.addEventListener('dragleave', function() { dropzone.classList.remove('drag-over'); });
            dropzone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropzone.classList.remove('drag-over');
                addFiles(e.dataTransfer.files);
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                addFiles(fileInput.files);
                fileInput.value = '';
            });
        }

        function closeModal() {
            modal.classList.remove('is-open');
            document.body.classList.remove('modal-open');
            modal.setAttribute('aria-hidden', 'true');
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
            if (errorDiv) { errorDiv.style.display = 'none'; errorDiv.textContent = ''; }
            if (filePreview) filePreview.innerHTML = '';
            if (fileInput) fileInput.value = '';
            if (document.getElementById('deposit-id')) document.getElementById('deposit-id').value = '';
            selectedFiles = [];
            existingDepositFiles = [];
            removedDepositFileIds = [];
            editMode = false;
            editingDepositId = null;
            updateDropzoneState();
            document.getElementById('deposit-existing-files').innerHTML = '';
            const title = modal.querySelector('h2');
            if (title) title.textContent = 'New Deposit Request';
            const submitBtn = document.getElementById('add-deposit-submit');
            if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Request';
        }

        function trapFocus(event) {
            var focusable = Array.prototype.slice.call(modal.querySelectorAll('button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])'))
                .filter(function(el) { return !el.hasAttribute('disabled'); });
            if (focusable.length === 0) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); return; }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }

        function populateConteneurSelect() {
            conteneurSelect.innerHTML = '<option value="">-- Select One --</option>';
            const list = Array.isArray(window.AVAILABLE_CONTENEURS) ? window.AVAILABLE_CONTENEURS : [];
            if (!list.length) return;
            list.forEach(function(c) {
                const opt = document.createElement('option');
                opt.value = c.id || c.ID || '';
                const label = c.name || c.conteneur_name || c.conteneur_name || (c.city ? (c.city + (c.road ? ', ' + c.road : '')) : 'Conteneur');
                opt.textContent = label;
                conteneurSelect.appendChild(opt);
            });

            let infoDiv = document.getElementById('conteneur-info-dropdown');
            if (!infoDiv) {
                infoDiv = document.createElement('div');
                infoDiv.id = 'conteneur-info-dropdown';
                infoDiv.style.margin = '8px 0 0 0';
                infoDiv.style.fontSize = '0.98em';
                infoDiv.style.background = 'linear-gradient(135deg, #10b981, #34d399)';
                infoDiv.style.color = '#fff';
                infoDiv.style.borderRadius = '8px';
                infoDiv.style.padding = '8px';
                conteneurSelect.parentNode.appendChild(infoDiv);
            }

            function updateInfoDiv() {
                const val = conteneurSelect.value;
                if (!val) {
                    infoDiv.style.display = 'none';
                    infoDiv.innerHTML = '';
                    document.getElementById('add-deposit-submit').disabled = false;
                    return;
                }
                const c = list.find(x => (x.id || x.ID || '') == val);
                if (c) {
                    const capacity = c.capacity || 100;
                    const currentFill = c.current_fill || 0;
                    const isFull = currentFill >= capacity;
                    
                    if (isFull) {
                        infoDiv.style.background = 'linear-gradient(135deg, #ef4444, #f87171)';
                        infoDiv.innerHTML = `<div style='padding:8px 0 0 0;'><strong>${c.name || c.conteneur_name || ''}</strong><br>${[c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ')}<br><span style="color:#fff;font-weight:600;margin-top:6px;display:inline-block;"><i class="fa-solid fa-circle-exclamation"></i> This container is full and cannot accept new deposits</span></div>`;
                        document.getElementById('add-deposit-submit').disabled = true;
                    } else {
                        infoDiv.style.background = 'linear-gradient(135deg, #10b981, #34d399)';
                        const fillPercentage = Math.round((currentFill / capacity) * 100);
                        infoDiv.innerHTML = `<div style='padding:8px 0 0 0;'><strong>${c.name || c.conteneur_name || ''}</strong><br>${[c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ')}<br><span style="font-size:0.85em;margin-top:6px;display:inline-block;"><i class="fa-solid fa-gauge"></i> Capacity: ${currentFill}/${capacity} (${fillPercentage}%)</span>${c.description ? '<br><span style="color:#ddd">'+c.description+'</span>' : ''}</div>`;
                        document.getElementById('add-deposit-submit').disabled = false;
                    }
                    infoDiv.style.display = '';
                } else {
                    infoDiv.style.display = 'none';
                    infoDiv.innerHTML = '';
                    document.getElementById('add-deposit-submit').disabled = false;
                }
            }

            // initial state
            if (!list.length || !conteneurSelect.value) {
                infoDiv.style.display = 'none';
            } else {
                updateInfoDiv();
            }

            conteneurSelect.onchange = updateInfoDiv;
        }

        const suggestBtn = document.getElementById('suggest-conteneur');
        // showCustomModal was moved to top level

        if (suggestBtn) {
            let menu = null;
            suggestBtn.classList.add('styled-suggest-btn');
            suggestBtn.style.background = 'linear-gradient(135deg, #10b981, #34d399)';
            suggestBtn.style.border = '2px solid transparent';
            suggestBtn.style.color = '#fff';
            suggestBtn.style.fontWeight = '600';
            suggestBtn.style.borderRadius = '8px';
            suggestBtn.style.fontSize = '1.08em';
            suggestBtn.style.transition = 'background 0.2s, color 0.2s';
            suggestBtn.onmouseover = () => { suggestBtn.style.background = 'linear-gradient(135deg, #34d399, #10b981)'; };
            suggestBtn.onmouseout = () => { suggestBtn.style.background = 'linear-gradient(135deg, #10b981, #34d399)'; };

            suggestBtn.addEventListener('click', function(e) {
                console.log('[DEBUG] Suggest button clicked');
                e.preventDefault();
                if (menu) menu.remove();
                menu = document.createElement('div');
                menu.className = 'suggest-menu';
                menu.style.position = 'fixed';
                menu.style.zIndex = 99999;
                menu.style.background = '#fff';
                menu.style.border = '2px solid #1a7f37';
                menu.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
                menu.style.padding = '0';
                menu.style.minWidth = '260px';
                menu.style.borderRadius = '12px';
                menu.style.fontSize = '1.08em';
                menu.style.overflow = 'hidden';
                menu.innerHTML = `
                    <button type="button" class="suggest-menu-item" data-method="db" style="display:flex;align-items:center;width:100%;padding:10px 16px;text-align:left;background:none;border:none;cursor:pointer;font-weight:600;color:#1a7f37;font-size:0.98em;transition:background 0.2s;">
                        <i class="fa-solid fa-location-dot" style="font-size:1em;margin-right:10px;color:#1a7f37;"></i> Based on my address
                    </button>
                    <button type="button" class="suggest-menu-item" data-method="geo" style="display:flex;align-items:center;width:100%;padding:10px 16px;text-align:left;background:none;border:none;cursor:pointer;font-weight:600;color:#1a7f37;font-size:0.98em;transition:background 0.2s;">
                        <i class="fa-solid fa-location-crosshairs" style="font-size:1em;margin-right:10px;color:#1a7f37;"></i> Based on my position
                    </button>
                    <button type="button" class="suggest-menu-item" data-method="manual" style="display:flex;align-items:center;width:100%;padding:10px 16px;text-align:left;background:none;border:none;cursor:pointer;font-weight:600;color:#1a7f37;font-size:0.98em;transition:background 0.2s;">
                        <i class="fa-solid fa-pencil" style="font-size:1em;margin-right:10px;color:#1a7f37;"></i> Based on an address I fill out
                    </button>
                `;
                document.body.appendChild(menu);
                const rect = suggestBtn.getBoundingClientRect();
                let left = rect.left + window.scrollX;
                let top = rect.bottom + window.scrollY + 4;
                if (left + 280 > window.innerWidth) left = window.innerWidth/2 - 140;
                if (top + 160 > window.innerHeight) top = window.innerHeight/2 - 80;
                menu.style.left = left + 'px';
                menu.style.top = top + 'px';

                menu.querySelectorAll('.suggest-menu-item').forEach(btn => {
                    btn.onmouseover = () => { btn.style.background = '#e6f4ea'; };
                    btn.onmouseout = () => { btn.style.background = 'none'; };
                });

                function closeMenu(ev) {
                    if (!menu.contains(ev.target) && ev.target !== suggestBtn) {
                        menu.remove();
                        clearInlineStatus();
                        document.removeEventListener('mousedown', closeMenu);
                    }
                }
                setTimeout(() => document.addEventListener('mousedown', closeMenu), 0);

                menu.querySelectorAll('.suggest-menu-item').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const method = btn.getAttribute('data-method');
                        menu.remove();
                        if (method === 'db') {
                            if (window.CURRENT_USER_ID) {
                                fetch(`get-user-address?id=${encodeURIComponent(window.CURRENT_USER_ID)}`)
                                    .then(r => {
                                        console.debug('[address fetch] status', r.status);
                                        return r.text();
                                    })
                                    .then(text => {
                                        console.debug('[address fetch] raw body', text);
                                        let data;
                                        try {
                                            data = JSON.parse(text);
                                        } catch (e) {
                                            console.error('[address fetch] JSON parse error', e);
                                            throw e;
                                        }
                                        if (data && data.address) {
                                            suggestConteneurByAddress(data.address);
                                        } else {
                                            showCustomModal('No address found for your account.');
                                        }
                                    })
                                    .catch(err => {
                                        console.error('[address fetch] failed', err);
                                        showCustomModal('Failed to fetch your address.');
                                    });
                            } else {
                                showCustomModal('User not logged in.');
                            }
                        } else if (method === 'geo') {
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(function(pos) {
                                    const coords = pos.coords;
                                    
                                    reverseGeocode(coords.latitude, coords.longitude)
                                        .then(addr => {
                                            suggestConteneurByCoords(coords.latitude, coords.longitude, false, addr);
                                        });
                                }, function() {
                                    clearInlineStatus();
                                    showCustomModal('Unable to get your position.');
                                });
                            } else {
                                clearInlineStatus();
                                showCustomModal('Geolocation not supported.');
                            }
                        } else if (method === 'manual') {
                        showCustomModal('', true);
                        }
                    });
                });
            });

        }

        form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            if (errorDiv) { errorDiv.style.display = 'none'; errorDiv.textContent = ''; }

            const conteneurId = conteneurSelect.value.trim();
            const name = (document.getElementById('deposit-object-name').value || '').trim();
            const desc = (document.getElementById('deposit-object-description').value || '').trim();
            const submitBtn = document.getElementById('add-deposit-submit');

            const errors = [];
            if (!conteneurId) errors.push('Please select a conteneur');
            if (!name) errors.push('Object name is required'); else if (name.length > 60) errors.push('Object name must be 60 characters or less');
            if (!desc) errors.push('Description is required'); else if (desc.length > 1000) errors.push('Description must be 1000 characters or less');

            if (errors.length) {
                if (errorDiv) { errorDiv.textContent = errors.join('. '); errorDiv.style.display = 'block'; }
                return;
            }

            const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            const filesPayload_files = selectedFiles.filter(function(f) { return allowed.indexOf(f.type) !== -1; }).slice(0, 5);

            submitBtn.disabled = true;
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner"></i> Sending...';

            function readFilesAsBase64(files) {
                return Promise.all(files.map(function(file) {
                    return new Promise(function(resolve) {
                        const reader = new FileReader();
                        reader.onload = function(e) { resolve({ file_name: file.name, file_data: e.target.result }); };
                        reader.onerror = function() { resolve(null); };
                        reader.readAsDataURL(file);
                    });
                })).then(function(results) { return results.filter(Boolean); });
            }

            const isEdit = editMode && editingDepositId;
            const apiEndpoint = isEdit ? 'update-deposit' : 'create-deposit';
            readFilesAsBase64(filesPayload_files).then(function(filesPayload) {
                const objectStateInput = document.getElementById('deposit-object-state');
                const objectStateValue = objectStateInput ? parseInt(objectStateInput.value, 10) : 0;
                const body = {
                    conteneur_id: conteneurId,
                    object_name: name,
                    object_description: desc,
                    object_state: Number.isInteger(objectStateValue) ? objectStateValue : 0,
                    files: filesPayload
                };
                if (isEdit) {
                    body.id = editingDepositId;
                    body.removed_file_ids = removedDepositFileIds;
                }
                return fetch(apiEndpoint, {
                    method: isEdit ? 'PATCH' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body)
                });
            })
            .then(function(r) { return r.text().then(function(t) { try { return JSON.parse(t); } catch (e) { throw new Error('Invalid response from server'); } }); })
            .then(function(result) {
                if (result.error) throw new Error(result.error || 'Failed to create deposit');
                form.reset();
                closeModal();
                requestPage(1, true);
            })
            .catch(function(err) {
                if (errorDiv) { errorDiv.textContent = err.message || 'Failed to create deposit request'; errorDiv.style.display = 'block'; }
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });
    }


    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function renderPagination(pagination) {
        if (!pagination) return;
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        const prev = createPageButton('Prev', currentPage === 1, () => { if (currentPage > 1) requestPage(currentPage - 1); });
        pagination.appendChild(prev);

        for (let i = 1; i <= totalPages; i++) {
            const btn = createPageButton(String(i), false, () => requestPage(i));
            if (i === currentPage) { btn.classList.add('active'); btn.setAttribute('aria-current', 'page'); }
            pagination.appendChild(btn);
        }

        const next = createPageButton('Next', currentPage === totalPages, () => { if (currentPage < totalPages) requestPage(currentPage + 1); });
        pagination.appendChild(next);
    }

    function createPageButton(label, disabled, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-btn';
        button.textContent = label;
        if (disabled) { button.disabled = true; button.classList.add('disabled'); } else { button.addEventListener('click', onClick); }
        return button;
    }

    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const p = parseInt(params.get('page'), 10);
        return (Number.isNaN(p) || p < 1) ? 1 : p;
    }

    function updateUrlPage(page, replace) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(page));
        if (replace) window.history.replaceState({}, '', url.toString()); else window.history.pushState({}, '', url.toString());
    }

    function renderSkeletons(container, count) {
        const items = [];
        for (let i = 0; i < count; i++) {
            items.push('<div class="deposit-card">'
                + '<div class="skeleton skeleton-title" style="width:40%"></div>'
                + '<div class="skeleton skeleton-description" style="height:40px;margin-top:8px"></div>'
                + '<div style="height:12px"></div>'
                + '<div class="skeleton skeleton-button" style="width:120px;margin-left:auto"></div>'
                + '</div>');
        }
        container.innerHTML = items.join('');
    }

    function mapStatusLabel(status) {
        switch (parseInt(status || 0, 10)) {
            case 0:
            case 1: return 'Pending';
            case 2: return 'Accepted';
            case 3: return 'Rejected';
            case 4: return 'Deposited';
            case 5: return 'Completed';
            default: return 'Unknown';
        }
    }

    function mapObjectStateLabel(state) {
        switch (parseInt(state || 0, 10)) {
            case 0: return 'New';
            case 1: return 'Like new';
            case 2: return 'Good';
            case 3: return 'Fair';
            case 4: return 'Poor';
            default: return 'Unknown';
        }
    }

    function mapStatusClass(status) {
        switch (parseInt(status || 0, 10)) {
            case 1: return 'pending';
            case 2: return 'accepted';
            case 3: return 'rejected';
            case 4: return 'deposited';
            case 5: return 'completed';
            default: return 'unknown';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }


    window.showContainersMap = function(elId, searchInputId) {
        if (typeof L === 'undefined') {
            console.warn('Leaflet not loaded, cannot show containers map');
            return;
        }
        const mapEl = document.getElementById(elId);
        if (!mapEl) return;
        const map = L.map(mapEl, { scrollWheelZoom: false }).setView([46.5, 2], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(map);
        const list = Array.isArray(window.AVAILABLE_CONTENEURS) ? window.AVAILABLE_CONTENEURS : [];
        list.forEach(function(c) {
            const address = [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ');
            if (!address) return;
            geocodeAddress(address).then(function(coords) {
                if (coords) {
                    const marker = L.marker([coords.lat, coords.lon]).addTo(map);
                    const label = escapeHtml(c.name || c.conteneur_name || 'Conteneur');
                    marker.bindPopup(`<strong>${label}</strong><br>${escapeHtml(address)}`);
                }
            });
        });

        if (searchInputId) {
            const input = document.getElementById(searchInputId);
            let resultsDiv = document.getElementById(searchInputId + '-results');
            if (!resultsDiv) {
                resultsDiv = document.getElementById(searchInputId.replace(/-input$/, '') + '-results');
            }
            if (input && resultsDiv) {
                let timeout = null;
                input.addEventListener('input', function() {
                    const val = input.value.trim();
                    if (!val) { resultsDiv.innerHTML = ''; resultsDiv.style.display = 'none'; return; }
                    if (timeout) clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        fetch(`https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(val)}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.features && data.features.length) {
                                    resultsDiv.style.display = 'block';
                                    resultsDiv.innerHTML = data.features.map(f => {
                                        const props = f.properties;
                                        const coords = f.geometry && f.geometry.coordinates ? f.geometry.coordinates : [];
                                        return `<div class="addr-result-item" data-lat="${coords[1]||''}" data-lon="${coords[0]||''}"><i class='fa-solid fa-location-dot'></i>${props.housenumber||''} ${props.street||''}, ${props.postcode||''} ${props.city||''}</div>`;
                                    }).join('');
                                } else {
                                    resultsDiv.style.display = 'block';
                                    resultsDiv.innerHTML = '<div style="padding:6px 8px;color:#888;">No results</div>';
                                }
                            })
                            .catch(() => {
                                resultsDiv.style.display = 'block';
                                resultsDiv.innerHTML = '<div style="padding:6px 8px;color:#888;">Error</div>';
                            });
                    }, 350);
                });
                resultsDiv.addEventListener('click', function(e) {
                    const item = e.target.closest('.addr-result-item');
                    if (item) {
                        const lat = parseFloat(item.getAttribute('data-lat'));
                        const lon = parseFloat(item.getAttribute('data-lon'));                        
                        if (!isNaN(lat) && !isNaN(lon)) {
                            map.setView([lat, lon], 15);
                            const redIcon = L.icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41],
                                popupAnchor: [1, -34],
                                shadowSize: [41, 41]
                            });
                            L.marker([lat, lon], {icon: redIcon}).addTo(map);
                        }
                        resultsDiv.innerHTML = '';
                        resultsDiv.style.display = 'none';
                        input.value = '';
                    }
                });
            }
        }
    };

    window.openDepositDetails = openDetailsModal;
})();
