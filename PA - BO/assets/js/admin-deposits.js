(function () {
    'use strict';

    const depositContainer = document.getElementById('deposits-container');
    const refreshBtn = document.getElementById('deposits-refresh-btn');
    const statusMsg = document.getElementById('deposits-status-msg');

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
    }

    function loadDeposits(showReloading = false) {
        if (!depositContainer) return;

        if (showReloading) {
            showStatus('Refreshing deposits...', '#0b7285');
        }

        depositContainer.innerHTML = '<div class="spinner"></div>';

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
                    return;
                }

                renderDepositItems(data);
                clearStatus();
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
            if (item.status === 0) {
                actionsHtml = `
                    <button class="btn-primary" data-action="approve" data-id="${item.id}">Approve</button>
                    <button class="btn-danger" data-action="reject" data-id="${item.id}">Reject</button>
                `;
            }

            if (item.status === 1 && item.barcode) {
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
                    updateDepositStatus(depositId, 1);
                } else if (action === 'reject') {
                    updateDepositStatus(depositId, 2);
                }
            });
        });
    }

    function showDepositDetails(depositId) {
        if (!depositId) return;

        const content = document.getElementById('deposit-detail-content');
        const title = document.getElementById('deposit-detail-title');

        if (content) content.innerHTML = '<p>Loading details...</p>';
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

                const html = [];
                html.push(`<p><strong>Object:</strong> ${escapeHtml(deposit.object_name || '-')}</p>`);
                html.push(`<p><strong>Description:</strong> ${escapeHtml(deposit.object_description || '-')}</p>`);
                html.push(`<p><strong>Status:</strong> ${escapeHtml(mapStatusLabel(deposit.status || 0))}</p>`);
                html.push(`<p><strong>Created at:</strong> ${escapeHtml(deposit.created_at || '-')}</p>`);
                
                if (deposit.barcode) {
                    html.push(`<p><strong>Barcode:</strong> <code>${escapeHtml(deposit.barcode)}</code></p>`);
                }
                content.innerHTML = html.join('');

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
                                    userInfo.innerHTML = `
                                        <h4><i class="fa-solid fa-user"></i> User</h4>
                                        <p><i class="fa-solid fa-user-tag"></i> <strong>Username:</strong> ${escapeHtml(userData.username || '-')}</p>
                                        <p><i class="fa-solid fa-id-card"></i> <strong>Name:</strong> ${escapeHtml(name)}</p>
                                        <p><i class="fa-solid fa-envelope"></i> <strong>Email:</strong> ${escapeHtml(userData.email || '-')}</p>
                                        <p><i class="fa-solid fa-user-shield"></i> <strong>User type:</strong> ${escapeHtml(String(userData.user_type || '-'))}</p>
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
                                    conteneurInfo.innerHTML = `
                                        <h4><i class="fa-solid fa-warehouse"></i> Conteneur</h4>
                                        <p><i class="fa-solid fa-tag"></i> <strong>Name:</strong> ${escapeHtml(conteneurData.conteneur_name || conteneurData.name || '-')}</p>
                                        <p><i class="fa-solid fa-location-dot"></i> <strong>Address:</strong> ${escapeHtml([conteneurData.conteneur_number || conteneurData.number || '', conteneurData.conteneur_road || conteneurData.road || '', conteneurData.conteneur_zip_code || conteneurData.postal_code || '', conteneurData.conteneur_city || conteneurData.city || ''].filter(Boolean).join(', ')) || '-'}</p>
                                        <p><i class="fa-solid fa-ruler-combined"></i> <strong>Capacity:</strong> ${escapeHtml(String(conteneurData.capacity || '-'))}</p>
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

    function updateDepositStatus(depositId, status) {
        if (!depositId) return;
        showStatus(status === 1 ? 'Approving...' : 'Rejecting...', '#0b7285');

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

                showStatus(status === 1 ? 'Deposit approved and barcode generated.' : 'Deposit rejected.', '#10b981');
                loadDeposits();
            })
            .catch(err => {
                console.error('Update status failed', err);
                showStatus('Failed to update status.', '#b00020');
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
            case 1:
                return 'Accepted';
            case 2:
                return 'Rejected';
            case 3:
                return 'Completed';
            default:
                return 'Pending';
        }
    }

    function mapStatusClass(status) {
        switch (Number(status || 0)) {
            case 1:
                return 'accepted';
            case 2:
                return 'rejected';
            case 3:
                return 'completed';
            default:
                return 'pending';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
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