(function () {
    'use strict';

    const depositContainer = document.getElementById('deposits-container');
    const refreshBtn = document.getElementById('deposits-refresh-btn');
    const statusMsg = document.getElementById('deposits-status-msg');
    const searchInput = document.getElementById('deposits-search');
    const statusFilter = document.getElementById('deposits-status-filter');
    const sortSelect = document.getElementById('deposits-sort');
    const conteneurFilter = document.getElementById('deposits-conteneur-filter');
    const cityFilter = document.getElementById('deposits-city-filter');
    const clearFiltersBtn = document.getElementById('deposits-clear-filters');
    const pageInfo = document.getElementById('deposits-page-info');
    const pageDots = document.getElementById('deposits-page-dots');
    const pageSizeSelect = document.getElementById('deposits-page-size');

    let depositsData = [];
    let currentPage = 1;
    let pageSize = Number(pageSizeSelect?.value || 10);
    let totalPageCount = 1;
    let adminConteneurs = [];
    let adminConteneurMap = {};

    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        loadDeposits();
    });

    function bindEvents() {
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadDeposits(true);
            });
        }

        const closeModalers = ['deposit-detail-close', 'deposit-detail-close-btn'];
        closeModalers.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', () => hideModal('deposit-detail-modal'));
        });

        const modal = document.getElementById('deposit-detail-modal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === this) hideModal('deposit-detail-modal');
            });
        }

        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                showSkeleton();
                searchTimeout = setTimeout(() => applyDepositFilters(), 220);
            });
        }

        const quickApply = () => {
            showSkeleton();
            applyDepositFilters();
        };

        if (statusFilter) {
            statusFilter.addEventListener('change', quickApply);
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', () => { quickApply(); currentPage = 1; });
        }
        if (conteneurFilter) {
            conteneurFilter.addEventListener('change', () => { quickApply(); currentPage = 1; });
        }
        if (cityFilter) {
            cityFilter.addEventListener('change', () => { quickApply(); currentPage = 1; });
        }
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function () {
                searchInput.value = '';
                statusFilter.value = '';
                sortSelect.value = 'newest';
                conteneurFilter.value = '';
                cityFilter.value = '';
                currentPage = 1;
                applyDepositFilters();
            });
        }
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function () {
                pageSize = Number(this.value || 10);
                currentPage = 1;
                applyDepositFilters();
            });
        }
    }

    function loadDeposits(showReloading = false) {
        if (!depositContainer) return;

        if (showReloading) {
            showStatus('Refreshing deposits...', '#0b7285');
        }

        showSkeleton();

        fetch('deposits-api.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = text ? JSON.parse(text) : [];
                } catch (parseErr) {
                    depositContainer.innerHTML = '<p class="error-message">Invalid deposit list response.</p>';
                    return;
                }

                if (!Array.isArray(data)) {
                    depositContainer.innerHTML = '<p class="error-message">No deposit requests available.</p>';
                    return;
                }

                if (data.length === 0) {
                    depositContainer.innerHTML = '<p class="empty-list">No deposit requests found.</p>';
                    depositsData = [];
                    return;
                }

                depositsData = data;
                if (Array.isArray(data)) {
                    fetchContainers().then(() => {
                        applyDepositFilters();
                        clearStatus();
                    }).catch(() => {
                        applyDepositFilters();
                        clearStatus();
                    });
                } else {
                    applyDepositFilters();
                    clearStatus();
                }
            })
            .catch(err => {
                console.error('Failed to load deposits', err);
                depositContainer.innerHTML = '<p class="error-message">Unable to load deposit requests.</p>';
                showStatus('Failed to load deposit requests', '#b00020');
            });
    }

    function renderDepositItems(items) {
        depositContainer.innerHTML = '';
        items.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));

        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'service-item';

            const statusLabel = mapStatusLabel(item.status);
            const statusClass = mapStatusClass(item.status);

            let actionsHtml = '';
            if (item.status === 1) {
                actionsHtml = `
                    <button class="btn-primary" data-action="approve" data-id="${item.id}">Approve</button>
                    <button class="btn-danger" data-action="reject" data-id="${item.id}">Reject</button>
                `;
            }

            if (item.status === 2 && item.barcode) {
                actionsHtml += `<div style="margin-top:8px;">Barcode: <strong>${escapeHtml(item.barcode)}</strong></div>`;
            }

            card.innerHTML = `
                <div class="service-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <h3 style="margin:0;">${escapeHtml(item.object_name || 'Untitled')}</h3>
                    <span class="deposit-status ${statusClass}">${escapeHtml(statusLabel)}</span>
                </div>
                <p style="margin:0 0 8px;">${escapeHtml(item.object_description || '-')}</p>
                <div class="service-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn-secondary" data-action="details" data-id="${item.id}">Details</button>
                    ${actionsHtml}
                </div>
            `;

            depositContainer.appendChild(card);
        });

        document.querySelectorAll('#deposits-container button[data-action]').forEach(button => {
            button.addEventListener('click', function () {
                const action = this.getAttribute('data-action');
                const depositId = this.getAttribute('data-id');

                if (action === 'details') {
                    showDepositDetails(depositId);
                } else if (action === 'approve') {
                    updateDepositStatus(depositId, 2);
                } else if (action === 'reject') {
                    updateDepositStatus(depositId, 3);
                }
            });
        });
    }

    function getFilteredDeposits() {
        if (!Array.isArray(depositsData)) return [];

        const query = (searchInput?.value || '').trim().toLowerCase();
        const statusValue = statusFilter?.value;
        const conteneurValue = conteneurFilter?.value;
        const cityValue = cityFilter?.value;

        return depositsData
            .filter(item => {
                const matchesStatus = statusValue === '' || String(item.status || '').trim() === statusValue;
                const contId = item.conteneur_id || item.conteneurId || '';
                const contData = item.conteneur || adminConteneurMap[contId] || {};
                const contName = (contData.conteneur_name || contData.name || item.conteneur_name || '').toLowerCase();
                const matchesConteneur = conteneurValue === '' || contName === conteneurValue.toLowerCase();
                const cityName = (contData.conteneur_city || contData.city || item.city || item.conteneur_city || '').toLowerCase();
                const matchesCity = cityValue === '' || cityName === cityValue.toLowerCase();

                const searchable = [
                    item.object_name,
                    item.object_description,
                    item.barcode,
                    item.user_name || '',
                    item.conteneur?.conteneur_name || item.conteneur_name || ''
                ].join(' ').toLowerCase();
                const matchesQuery = query === '' || searchable.includes(query);

                return matchesStatus && matchesConteneur && matchesCity && matchesQuery;
            })
            .sort((a, b) => {
                const sortOption = sortSelect?.value || 'newest';
                const dateA = new Date(a.created_at || a.createdAt || 0).getTime() || 0;
                const dateB = new Date(b.created_at || b.createdAt || 0).getTime() || 0;
                return sortOption === 'oldest' ? dateA - dateB : dateB - dateA;
            });
    }

    function populateFilterOptions() {
        const uniqueConteneurs = new Set(adminConteneurs.map(c => c.conteneur_name || ''));
        const uniqueCities = new Set(adminConteneurs.map(c => c.conteneur_city || ''));

        if (Array.isArray(depositsData)) {
            depositsData.forEach(item => {
                const contId = item.conteneur_id || item.conteneurId || '';
                const contData = item.conteneur || adminConteneurMap[contId] || {};
                const contName = (contData.conteneur_name || contData.name || item.conteneur_name || '').trim();
                const cityName = (contData.conteneur_city || contData.city || item.city || item.conteneur_city || '').trim();
                if (contName) uniqueConteneurs.add(contName);
                if (cityName) uniqueCities.add(cityName);
            });
        }

        if (conteneurFilter) {
            const selected = conteneurFilter.value;
            conteneurFilter.innerHTML = '<option value="">All containers</option>' +
                Array.from(uniqueConteneurs).filter(v => v).sort().map(val => `<option value="${escapeHtml(val)}"${selected === val ? ' selected' : ''}>${escapeHtml(val)}</option>`).join('');
        }
        if (cityFilter) {
            const selected = cityFilter.value;
            cityFilter.innerHTML = '<option value="">All cities</option>' +
                Array.from(uniqueCities).filter(v => v).sort().map(val => `<option value="${escapeHtml(val)}"${selected === val ? ' selected' : ''}>${escapeHtml(val)}</option>`).join('');
        }
    }

    function applyDepositFilters() {
        populateFilterOptions();

        const filtered = getFilteredDeposits();
        const totalItems = filtered.length;
        totalPageCount = Math.max(1, Math.ceil(totalItems / pageSize));
        currentPage = Math.min(Math.max(1, currentPage), totalPageCount);

        if (pageInfo) {
            if (totalPageCount > 1) {
                pageInfo.textContent = `Page ${currentPage}/${totalPageCount} (${totalItems} item${totalItems > 1 ? 's' : ''})`;
            } else {
                pageInfo.textContent = `${totalItems} item${totalItems !== 1 ? 's' : ''}`;
            }
        }

        if (pageDots) {
            pageDots.innerHTML = '';
            if (totalPageCount > 1) {
                for (let i = 1; i <= totalPageCount; i++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'page-btn' + (i === currentPage ? ' active' : '');
                    btn.textContent = i;
                    btn.addEventListener('click', function () {
                        if (i === currentPage) return;
                        currentPage = i;
                        showSkeleton();
                        applyDepositFilters();
                    });
                    pageDots.appendChild(btn);
                }
            }
        }

        if (!filtered.length) {
            depositContainer.innerHTML = '<p class="empty-list">No deposit requests match the current filters.</p>';
            if (pageDots) pageDots.style.display = 'none';
            if (pageInfo) pageInfo.style.display = totalPageCount > 1 ? '' : '';
            return;
        }

        if (pageDots) pageDots.style.display = totalPageCount > 1 ? 'flex' : 'none';
        if (pageInfo) pageInfo.style.display = '';

        const start = (currentPage - 1) * pageSize;
        const slice = filtered.slice(start, start + pageSize);
        renderDepositItems(slice);
    }

    function showDepositDetails(depositId) {
        if (!depositId) return;

        const content = document.getElementById('deposit-detail-content');
        const title = document.getElementById('deposit-detail-title');
        const userInfo = document.getElementById('deposit-user-info');
        const conteneurInfo = document.getElementById('deposit-conteneur-info');
        const filesList = document.getElementById('deposit-files-list');

        if (content) {
            content.innerHTML = `
                <div class="skeleton-group">
                    <div class="skeleton-line" style="width: 60%; height: 20px;"></div>
                    <div class="skeleton-line" style="width: 100%; height: 14px;"></div>
                    <div class="skeleton-line" style="width: 90%; height: 14px;"></div>
                    <div class="skeleton-line" style="width: 75%; height: 14px;"></div>
                </div>
            `;
        }

        if (userInfo) {
            userInfo.innerHTML = '<div class="skeleton-avatar"></div><div class="skeleton-line" style="width:70%; height:14px; margin:12px auto 0;"></div>';
        }

        if (conteneurInfo) {
            conteneurInfo.innerHTML = '<div class="skeleton-avatar"></div><div class="skeleton-line" style="width:70%; height:14px; margin:12px auto 0;"></div>';
        }

        if (filesList) {
            filesList.innerHTML = Array.from({ length: 5 }).map(() => '<div class="photo-skel"></div>').join('');
        }

        if (title) title.textContent = 'Deposit request details';

        showModal('deposit-detail-modal');

        fetch(`deposits-api.php?id=${encodeURIComponent(depositId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(text => {
                let data = null;
                try {
                    data = text ? JSON.parse(text) : null;
                } catch (_) {
                    data = null;
                }

                if (!data || data.error) {
                    if (content) content.innerHTML = '<p class="error-message">Unable to load request details.</p>';
                    return;
                }

                let deposit = data;
                if (Array.isArray(data)) {
                    deposit = data.find(d => d.id === depositId) || null;
                }

                if (!deposit) {
                    if (content) content.innerHTML = '<p class="error-message">Deposit request not found.</p>';
                    return;
                }

                const createdAt = deposit.created_at ? formatDateTime(deposit.created_at) : '-';

                const html = [];
                html.push(`<p><strong>Object:</strong> ${escapeHtml(deposit.object_name || '-')}</p>`);
                html.push(`<p><strong>Description:</strong> ${escapeHtml(deposit.object_description || '-')}</p>`);
                html.push(`<p><strong>Status:</strong> ${escapeHtml(mapStatusLabel(deposit.status || 0))}</p>`);
                html.push(`<p><strong>Created at:</strong> ${escapeHtml(createdAt)}</p>`);

                if (deposit.barcode) {
                    html.push(`<div id="deposit-barcode-area" style="margin:10px 0;"></div>`);
                    html.push(`<div id="deposit-barcode-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;justify-content:center;"></div>`);
                } else {
                    html.push(`<div id="deposit-barcode-area"></div><div id="deposit-barcode-actions"></div>`);
                }

                content.innerHTML = html.join('');

                if (deposit.barcode) {
                    renderDepositBarcode(deposit.barcode);
                }

                const userInfo = document.getElementById('deposit-user-info');
                const conteneurInfo = document.getElementById('deposit-conteneur-info');
                const mapBox = document.getElementById('deposit-map-box');
                const filesList = document.getElementById('deposit-files-list');
                const downloadAllBtn = document.getElementById('deposit-download-all');

                if (userInfo) userInfo.innerHTML = '<p>Loading user...</p>';
                if (conteneurInfo) conteneurInfo.innerHTML = '<p>Loading container...</p>';
                if (mapBox) mapBox.innerHTML = '<p>Loading map...</p>';
                if (filesList) filesList.innerHTML = '<p>Loading files...</p>';

                if (downloadAllBtn) {
                    downloadAllBtn.onclick = function () {
                        window.location = 'deposit-download-files.php?deposit_id=' + encodeURIComponent(depositId);
                    };
                }

                Promise.allSettled([
                    fetch(`user-get-api.php?id=${encodeURIComponent(deposit.user_id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }}),
                    fetch(`container-get-api.php?id=${encodeURIComponent(deposit.conteneur_id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                ]).then(results => {
                    const userResp = results[0];
                    const conteneurResp = results[1];

                    if (userResp.status === 'fulfilled') {
                        userResp.value.text().then(userText => {
                            let userData;
                            try { userData = userText ? JSON.parse(userText) : null; } catch { userData = null; }
                            if (userData && !userData.error) {
                                if (userInfo) {
                                    const name = [userData.first_name, userData.last_name].filter(Boolean).join(' ') || userData.username || '-';
                                    const username = userData.username || '-';
                                    const email = userData.email || '-';
                                    userInfo.innerHTML = `
                                        <div class="compact-icon-block" style="justify-content:center;">
                                            <span class="icon-circle"><i class="fa-solid fa-user"></i></span>
                                            
                                        </div>
                                        <div style="text-align:center;margin-top:10px;color:#374151;font-size:.9rem;line-height:1.3;">
                                            <div><strong>${escapeHtml(name)}</strong></div>
                                            <div>${escapeHtml(username)}</div>
                                            <div>${escapeHtml(email)}</div>
                                        </div>
                                    `;
                                }
                            } else if (userInfo) {
                                userInfo.innerHTML = '<p class="error-message"><i class="fa-solid fa-triangle-exclamation"></i> Unable to load user info.</p>';
                            }
                        }).catch(() => {
                            if (userInfo) userInfo.innerHTML = '<p class="error-message">Unable to parse user info.</p>';
                        });
                    } else if (userInfo) {
                        userInfo.innerHTML = '<p class="error-message">Unable to load user info.</p>';
                    }

                    if (conteneurResp.status === 'fulfilled') {
                        conteneurResp.value.text().then(cText => {
                            let conteneurData;
                            try { conteneurData = cText ? JSON.parse(cText) : null; } catch { conteneurData = null; }
                            if (conteneurData && !conteneurData.error) {
                                if (conteneurInfo) {
                                    const conteneurName = conteneurData.conteneur_name || conteneurData.name || '-';
                                    const conteneurAddress = [conteneurData.conteneur_number || conteneurData.number || '', conteneurData.conteneur_road || conteneurData.road || '', conteneurData.conteneur_zip_code || conteneurData.postal_code || '', conteneurData.conteneur_city || conteneurData.city || ''].filter(Boolean).join(', ') || '-';
                                    const capacity = conteneurData.capacity ? String(conteneurData.capacity) : '-';
                                    conteneurInfo.innerHTML = `
                                        <div class="compact-icon-block" style="justify-content:center;">
                                            <span class="icon-circle"><i class="fa-solid fa-warehouse"></i></span>
                                        </div>
                                        <div style="text-align:center;margin-top:10px;color:#374151;font-size:.9rem;line-height:1.3;">
                                            <div><strong>${escapeHtml(conteneurName)}</strong></div>
                                            <div>${escapeHtml(conteneurAddress)}</div>
                                            <div>${escapeHtml(capacity)} capacity</div>
                                        </div>
                                    `;
                                }
                                const address = [conteneurData.conteneur_number || conteneurData.number || '', conteneurData.conteneur_road || conteneurData.road || '', conteneurData.conteneur_zip_code || conteneurData.postal_code || '', conteneurData.conteneur_city || conteneurData.city || ''].filter(Boolean).join(', ');
                                if (address) {
                                    geocodeAddress(address).then(coords => {
                                        if (coords && mapBox) {
                                            mapBox.innerHTML = '<div id="deposit-map" style="width:100%;height:280px"></div>';
                                            initMap('deposit-map', coords.lat, coords.lon, conteneurData.conteneur_name || conteneurData.name || 'Conteneur');
                                        } else if (mapBox) {
                                            mapBox.innerHTML = '<p class="error-message">Unable to locate container on map.</p>';
                                        }
                                    }).catch(err => {
                                        console.warn('Map geocode error', err);
                                        if (mapBox) mapBox.innerHTML = '<p class="error-message">Unable to locate container on map.</p>';
                                    });
                                } else if (mapBox) {
                                    mapBox.innerHTML = '<p class="error-message">No address available for map.</p>';
                                }
                            } else if (conteneurInfo) {
                                conteneurInfo.innerHTML = '<p class="error-message">Unable to load container info.</p>';
                                if (mapBox) mapBox.innerHTML = '<p class="error-message">Unable to show map.</p>';
                            }
                        }).catch(() => {
                            if (conteneurInfo) conteneurInfo.innerHTML = '<p class="error-message">Unable to parse container info.</p>';
                            if (mapBox) mapBox.innerHTML = '<p class="error-message">Unable to show map.</p>';
                        });
                    } else {
                        if (conteneurInfo) conteneurInfo.innerHTML = '<p class="error-message">Unable to load container info.</p>';
                        if (mapBox) mapBox.innerHTML = '<p class="error-message">Unable to show map.</p>';
                    }

                    // Request files list for deposit
                    fetch(`deposit-files-api.php?deposit_id=${encodeURIComponent(depositId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(resp => resp.text())
                        .then(t => {
                            let filesData;
                            try { filesData = t ? JSON.parse(t) : null; } catch { filesData = null; }
                            if (!Array.isArray(filesData)) {
                                if (filesList) filesList.innerHTML = '<p class="error-message">No files found.</p>';
                                return;
                            }

                            filesList.innerHTML = '';
                            if (filesData.length === 0) {
                                filesList.innerHTML = '<p>No photos uploaded.</p>';
                                return;
                            }

                            filesData.forEach(f => {
                                const item = document.createElement('div');
                                item.style.cssText = 'border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;text-align:center;position:relative;';
                                const img = document.createElement('img');
                                img.src = '/PA/files/uploads/deposit/' + encodeURIComponent(f.filename);
                                img.alt = escapeHtml(f.original_name || f.filename || 'photo');
                                img.style.cssText = 'width:100%;height:90px;object-fit:cover;cursor:pointer;';
                                img.addEventListener('click', () => {
                                    window.open('/PA/files/uploads/deposit/' + encodeURIComponent(f.filename), '_blank');
                                });
                                const caption = document.createElement('div');
                                caption.style.cssText = 'padding:4px;font-size:.8rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;';
                                caption.textContent = f.original_name || f.filename;

                                const download = document.createElement('a');
                                download.href = '/PA/files/uploads/deposit/' + encodeURIComponent(f.filename);
                                download.download = f.original_name || f.filename;
                                download.style.cssText = 'position:absolute;top:6px;right:6px;background:rgba(0,0,0,.60);color:#fff;padding:4px 6px;border-radius:4px;font-size:.75rem;';
                                download.innerHTML = '<i class="fa-solid fa-download"></i>';

                                item.appendChild(img);
                                item.appendChild(caption);
                                item.appendChild(download);
                                filesList.appendChild(item);
                            });
                        })
                        .catch(err => {
                            console.error('Failed to load deposit files', err);
                            if (filesList) filesList.innerHTML = '<p class="error-message">Unable to load deposit files.</p>';
                        });
                });
            })
            .catch(err => {
                console.error('Failed to load details', err);
                if (content) content.innerHTML = '<p class="error-message">Failed to get details.</p>';
            });
    }

    async function fetchContainers() {
        try {
            const resp = await fetch('containers-api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const text = await resp.text();
            const data = text ? JSON.parse(text) : [];
            if (Array.isArray(data)) {
                adminConteneurs = data;
                adminConteneurMap = data.reduce((map, c) => {
                    if (c && c.id) {
                        map[c.id] = c;
                    }
                    return map;
                }, {});
                return data;
            }
        } catch (err) {
            console.warn('Failed to load containers for filters', err);
        }
        adminConteneurMap = {};
        return [];
    }

    function showSkeleton() {
        if (!depositContainer) return;
        depositContainer.innerHTML = Array.from({ length: 4 }).map(() => `
            <div class="skeleton-deposit-item">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-line"></div>
                <div class="skeleton skeleton-line sm"></div>
                <div class="skeleton skeleton-line sm"></div>
                <div class="skeleton skeleton-line" style="width:40%;"></div>
            </div>
        `).join('');
    }

    function updateDepositStatus(depositId, status) {
        if (!depositId) return;
        showStatus(status === 2 ? 'Approving...' : 'Rejecting...', '#0b7285');

        fetch('deposit-update-status-api.php?id=' + encodeURIComponent(depositId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ status: status })
        })
            .then(r => r.text())
            .then(text => {
                let data;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (parseErr) {
                    console.error('Update status parse error, raw response', text);
                    throw parseErr;
                }

                if (data && data.error) {
                    console.error('Update status details', data);
                    throw new Error(data.error + (data.upstream_body ? ' | upstream: ' + JSON.stringify(data.upstream_body) : ''));
                }

                showStatus(status === 2 ? 'Deposit approved and barcode generated.' : 'Deposit rejected.', '#10b981');
                showToast(status === 2 ? 'Deposit approved and notification sent to customer.' : 'Deposit rejected and notification sent to customer.', 4500);
                loadDeposits();
            })
            .catch(err => {
                console.error('Update status failed', err);
                showStatus('Failed to update status.', '#b00020');
                showToast('Failed to update deposit status. Please retry.', 4500);
            });
    }

    function showModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.style.display = '';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function hideModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        setTimeout(() => { modal.style.display = 'none'; }, 220);
    }

    function geocodeAddress(address) {
        if (!address || !address.trim()) {
            return Promise.resolve(null);
        }
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`;
        return fetch(url, { headers: { 'Accept-Language': 'en' } })
            .then(r => r.ok ? r.json() : Promise.reject('geocode fail'))
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    return { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon) };
                }
                return null;
            })
            .catch(err => {
                console.warn('geocodeAddress error', err);
                return null;
            });
    }

    function initMap(containerId, lat, lon, title) {
        const el = document.getElementById(containerId);
        if (!el || typeof L === 'undefined') {
            return;
        }
        // reset existing map
        if (el._leaflet_map) {
            try { el._leaflet_map.remove(); } catch (e) { }
        }
        const map = L.map(el).setView([lat, lon], 15);
        el._leaflet_map = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(map);

        const marker = L.marker([lat, lon]).addTo(map);
        if (title) marker.bindPopup(title).openPopup();
    }

    function mapStatusLabel(status) {
        switch (Number(status || 0)) {
            case 0:
            case 1:
                return 'Pending';
            case 2:
                return 'Accepted';
            case 3:
                return 'Rejected';
            case 4:
                return 'Deposited';
            case 5:
                return 'Completed';
            default:
                return 'Unknown';
        }
    }

    function mapStatusClass(status) {
        switch (Number(status || 0)) {
            case 1:
                return 'pending';
            case 2:
                return 'accepted';
            case 3:
                return 'rejected';
            case 4:
                return 'deposited';
            case 5:
                return 'completed';
            default:
                return 'unknown';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function formatDateTime(value) {
        try {
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) throw new Error('invalid date');
            return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(d);
        } catch (_) {
            return value || '-';
        }
    }

    function renderDepositBarcode(barcodeText) {
        const area = document.getElementById('deposit-barcode-area');
        const actions = document.getElementById('deposit-barcode-actions');
        if (!area || !actions || !barcodeText) return;

        const svgId = `deposit-barcode-svg-${Date.now()}`;
        area.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                <svg id="${svgId}" width="260" height="80" aria-label="Deposit barcode" style="border:1px solid #e5e7eb;border-radius:8px;padding:4px;background:#fff;"></svg>
                <div style="font-size:.85rem;color:#374151;">${escapeHtml(barcodeText)}</div>
            </div>
        `;

        if (window.JsBarcode && typeof JsBarcode === 'function') {
            try {
                const svgElement = document.getElementById(svgId);
                if (svgElement) {
                    JsBarcode(svgElement, barcodeText, {
                        format: 'CODE128',
                        lineColor: '#000',
                        width: 2,
                        height: 60,
                        displayValue: false,
                        margin: 10
                    });
                }
            } catch (err) {
                console.warn('JsBarcode failed, fallback to remote generator', err);
                area.innerHTML = `<img src="https://api.qrserver.com/v1/barcode?data=${encodeURIComponent(barcodeText)}&code=Code128&dpi=150" alt="barcode" style="max-width:100%;border:1px solid #e5e7eb;border-radius:8px;" />`;
            }
        } else {
            area.innerHTML = `<img src="https://api.qrserver.com/v1/barcode?data=${encodeURIComponent(barcodeText)}&code=Code128&dpi=150" alt="barcode" style="max-width:100%;border:1px solid #e5e7eb;border-radius:8px;" />`;
        }

        actions.innerHTML = `
            <button type="button" class="btn-secondary" id="deposit-barcode-download-img"><i class="fa-solid fa-download"></i> Download image</button>
            <button type="button" class="btn-secondary" id="deposit-barcode-download-pdf"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
        `;

        const imgBtn = document.getElementById('deposit-barcode-download-img');
        const pdfBtn = document.getElementById('deposit-barcode-download-pdf');

        if (imgBtn) {
            imgBtn.addEventListener('click', () => {
                const svgElement = document.querySelector('#deposit-barcode-area svg');
                if (!svgElement) return;
                downloadSvgAsPng(svgElement, `barcode-${barcodeText}.png`);
            });
        }

        if (pdfBtn) {
            pdfBtn.addEventListener('click', () => {
                const safeBarcode = encodeURIComponent(barcodeText);
                window.open(`export-barcode.php?barcode=${safeBarcode}&download=pdf`, '_blank');
            });
        }
    }

    function downloadSvgAsPng(svgElement, filename) {
        const svgData = new XMLSerializer().serializeToString(svgElement);
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(svgBlob);
        const img = new Image();

        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width || 260;
            canvas.height = img.height || 80;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            canvas.toBlob((blob) => {
                if (!blob) return;
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            }, 'image/png');
            URL.revokeObjectURL(url);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
        };
        img.src = url;
    }

    function showToast(message, timeout = 4500) {
        if (typeof window.showToast === 'function' && window.showToast !== showToast) {
            return window.showToast(message, timeout);
        }

        const t = document.createElement('div');
        t.className = 'toast';
        t.style.position = 'fixed';
        t.style.bottom = '20px';
        t.style.right = '20px';
        t.style.padding = '10px 14px';
        t.style.color = '#fff';
        t.style.background = 'rgba(0, 0, 0, 0.8)';
        t.style.borderRadius = '8px';
        t.style.zIndex = '9999';
        t.style.maxWidth = '320px';
        t.style.boxShadow = '0 2px 10px rgba(0,0,0,.3)';
        t.style.opacity = '1';
        t.style.transition = 'opacity 0.3s ease';
        t.textContent = message;

        document.body.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            setTimeout(() => {
                if (t.parentNode) t.parentNode.removeChild(t);
            }, 300);
        }, timeout);
    }

    function showStatus(message, color) {
        if (!statusMsg) return;
        statusMsg.textContent = message;
        statusMsg.style.color = color || '#10b981';
        statusMsg.style.display = '';
    }

    function clearStatus() {
        if (!statusMsg) return;
        statusMsg.textContent = '';
        statusMsg.style.display = 'none';
    }

})();