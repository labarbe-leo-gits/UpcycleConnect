(function() {
    'use strict';

    const DEFAULT_PAGE_SIZE = 4;
    let currentPage = 1;
    let totalPages = 1;
    let pageSize = DEFAULT_PAGE_SIZE;
    let allContainers = [];
    let filteredContainers = [];
    let searchTerm = '';
    let currentItemsContainerId = null;
    let sortType = 'created_desc';
    let userPosition = null;
    const locationCache = {};

    document.addEventListener('DOMContentLoaded', function() {
        currentPage = getPageFromUrl();
        bindToolbar();
        loadContainers();
    });

    function bindToolbar() {
        document.getElementById('container-search')?.addEventListener('input', function() {
            searchTerm = this.value.trim();
            currentPage = 1;
            applyFilters();
        });

        document.getElementById('container-sort')?.addEventListener('change', function() {
            sortType = this.value;
            currentPage = 1;
            applyFilters();
        });

        document.getElementById('container-page-size')?.addEventListener('change', function() {
            pageSize = parseInt(this.value, 10) || DEFAULT_PAGE_SIZE;
            currentPage = 1;
            applyFilters();
        });

        document.getElementById('container-reset-filters')?.addEventListener('click', function() {
            searchTerm = '';
            sortType = 'created_desc';
            pageSize = DEFAULT_PAGE_SIZE;
            userPosition = null;
            currentPage = 1;

            document.getElementById('container-search').value = '';
            document.getElementById('container-sort').value = 'created_desc';
            document.getElementById('container-page-size').value = String(DEFAULT_PAGE_SIZE);

            applyFilters();
        });

        document.getElementById('container-nearest-btn')?.addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            this.disabled = true;
            this.textContent = 'Locating…';

            navigator.geolocation.getCurrentPosition(function(position) {
                userPosition = { lat: position.coords.latitude, lng: position.coords.longitude };
                this.textContent = 'Nearest to me';
                this.disabled = false;
                sortType = 'nearest';
                document.getElementById('container-sort').value = 'nearest';
                currentPage = 1;
                applyFilters();
            }.bind(this), function() {
                alert('Unable to retrieve your location.');
                this.textContent = 'Nearest to me';
                this.disabled = false;
            }.bind(this), { timeout: 10000 });
        });
    }

    function loadContainers() {
        const container = document.getElementById('containers-container');
        renderSkeletons(container, pageSize);

        fetch('containers-api?page=1&limit=50', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => { if (!r.ok) throw new Error('Failed loading'); return r.text(); })
        .then(text => {
            let data = [];
            try { data = JSON.parse(text); } catch (err) { throw err; }

            const items = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
            allContainers = items.map(z => ({ ...z }));
            applyFilters();
        })
        .catch(err => {
            console.error('Error loading containers:', err);
            container.innerHTML = '<p class="error-message">Unable to load containers. Please try again later.</p>';
            document.getElementById('containers-pagination').innerHTML = '';
        });
    }

    function applyFilters() {
        filteredContainers = allContainers.filter(c => {
            const q = searchTerm.toLowerCase();
            if (!q) return true;
            return (c.name || '').toLowerCase().includes(q)
                || (c.city || '').toLowerCase().includes(q)
                || (c.road || '').toLowerCase().includes(q);
        });

        if (sortType === 'created_desc') {
            filteredContainers.sort((a,b) => (b.created_at || '').localeCompare(a.created_at || ''));
        } else if (sortType === 'created_asc') {
            filteredContainers.sort((a,b) => (a.created_at || '').localeCompare(b.created_at || ''));
        } else if (sortType === 'name_asc') {
            filteredContainers.sort((a,b) => (a.name||'').localeCompare(b.name||'', undefined, { sensitivity:'base' }));
        } else if (sortType === 'name_desc') {
            filteredContainers.sort((a,b) => (b.name||'').localeCompare(a.name||'', undefined, { sensitivity:'base' }));
        }

        if (sortType === 'nearest' && userPosition) {
            sortByNearest().then(() => renderPage()).catch(() => renderPage());
            return;
        }

        totalPages = Math.max(1, Math.ceil(filteredContainers.length / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        renderPage();
    }

    function sortByNearest() {
        const promises = filteredContainers.map(c => {
            if (typeof c._distance === 'number') return Promise.resolve();

            const address = [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ');
            if (!address) {
                c._distance = Infinity;
                return Promise.resolve();
            }

            if (locationCache[address]) {
                const coords = locationCache[address];
                c._distance = distanceHaversine(userPosition.lat, userPosition.lng, coords.lat, coords.lng);
                return Promise.resolve();
            }

            return fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`, { headers: { 'Accept-Language': 'en' }})
                .then(r => r.json())
                .then(results => {
                    if (Array.isArray(results) && results.length) {
                        const lat = parseFloat(results[0].lat);
                        const lng = parseFloat(results[0].lon);
                        locationCache[address] = { lat, lng };
                        c._distance = distanceHaversine(userPosition.lat, userPosition.lng, lat, lng);
                    } else {
                        c._distance = Infinity;
                    }
                })
                .catch(() => { c._distance = Infinity; });
        });

        return Promise.all(promises).then(() => {
            filteredContainers.sort((a, b) => (a._distance || Infinity) - (b._distance || Infinity));
            totalPages = Math.max(1, Math.ceil(filteredContainers.length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
        });
    }

    function renderPage() {
        const container = document.getElementById('containers-container');
        const pagination = document.getElementById('containers-pagination');

        if (!container) return;

        container.innerHTML = '';
        pagination.innerHTML = '';

        if (!filteredContainers.length) {
            container.innerHTML = '<p class="empty-containers">No containers found.</p>';
            return;
        }

        totalPages = Math.max(1, Math.ceil(filteredContainers.length / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * pageSize;
        const sliced = filteredContainers.slice(start, start + pageSize);
        sliced.forEach(c => container.appendChild(createContainerElement(c)));

        renderPagination(pagination);
        updateUrlPage(currentPage, true);
    }

    function renderPagination(pagination) {
        if (!pagination) return;
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        const prev = createPageButton('Prev', currentPage === 1, function() { if (currentPage > 1) { currentPage--; renderPage(); }});
        pagination.appendChild(prev);

        for (let i = 1; i <= totalPages; i++) {
            const btn = createPageButton(String(i), false, function() { currentPage = i; renderPage(); });
            if (i === currentPage) {
                btn.classList.add('active');
                btn.setAttribute('aria-current', 'page');
            }
            pagination.appendChild(btn);
        }

        const next = createPageButton('Next', currentPage === totalPages, function() { if (currentPage < totalPages) { currentPage++; renderPage(); }});
        pagination.appendChild(next);
    }

    function createPageButton(label, disabled, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-btn';
        button.textContent = label;
        if (disabled) {
            button.disabled = true;
            button.classList.add('disabled');
        } else {
            button.addEventListener('click', onClick);
        }
        return button;
    }

    function createContainerElement(c) {
        const div = document.createElement('div');
        div.className = 'service-item';

        const header = document.createElement('div');
        header.className = 'service-header';

        const title = document.createElement('h3');
        title.innerHTML = `<i class="fa-solid fa-warehouse"></i>${escapeHtml(c.name || '')}`;
        header.appendChild(title);
        div.appendChild(header);

        const address = document.createElement('p');
        address.className = 'service-description';
        const addrParts = [];
        if (c.number) addrParts.push(c.number);
        if (c.road) addrParts.push(c.road);
        if (c.postal_code) addrParts.push(c.postal_code);
        if (c.city) addrParts.push(c.city);
        address.textContent = addrParts.join(', ');
        div.appendChild(address);

        const actions = document.createElement('div');
        actions.className = 'service-actions';
        actions.style.display = 'flex'; actions.style.gap = '8px';

        const openBtn = document.createElement('button');
        openBtn.type = 'button';
        openBtn.className = 'btn-primary';
        openBtn.textContent = 'Open';
        openBtn.addEventListener('click', function() { openContainerModal(c); });
        actions.appendChild(openBtn);

        const itemsBtn = document.createElement('button');
        itemsBtn.type = 'button';
        itemsBtn.className = 'btn-secondary';
        itemsBtn.textContent = 'View Items';
        itemsBtn.addEventListener('click', function() { openItemsModal(c); });
        actions.appendChild(itemsBtn);

        div.appendChild(actions);
        return div;
    }

    function openItemsModal(container) {
        currentItemsContainerId = container.id;
        const modal = document.getElementById('container-items-modal');
        if (!modal) return;
        modal.querySelector('#container-items-container-name').textContent = `${container.name || 'Container'} - Approved items`;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        loadContainerItems(container.id);
    }

    function loadContainerItems(containerId) {
        const itemsList = document.getElementById('container-items-list');
        const itemsStatus = document.getElementById('container-items-status');
        const emptyBox = document.getElementById('container-items-empty');
        itemsList.innerHTML = '';
        emptyBox.style.display = 'none';
        itemsStatus.textContent = 'Loading approved items...';

        fetch(`container-items-api?container_id=${encodeURIComponent(containerId)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); } catch (e) { throw e; }
            if (!data || data.error) throw new Error(data && data.error ? data.error : 'Invalid response');

            const items = Array.isArray(data.items) ? data.items : [];
            if (!items.length) {
                itemsStatus.textContent = '';
                emptyBox.style.display = 'block';
                return;
            }

            itemsStatus.textContent = `${items.length} approved item(s).`;

            items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'service-item';
                card.style.padding = '8px';
                card.style.marginBottom = '6px';
                card.style.background = '#fff';
                card.style.border = '1px solid #e5e7eb';
                card.style.borderRadius = '10px';

                const heading = document.createElement('div');
                heading.style.display = 'flex';
                heading.style.justifyContent = 'space-between';
                heading.style.alignItems = 'center';

                const left = document.createElement('div');
                const title = document.createElement('strong');
                title.textContent = item.object_name || '(Unnamed item)';
                left.appendChild(title);

                const status = document.createElement('span');
                status.textContent = item.status !== undefined ? mapStatusLabel(item.status) : 'Unknown';
                status.style.fontSize = '.9rem';
                status.style.color = '#374151';
                status.style.marginLeft = '10px';
                left.appendChild(status);

                heading.appendChild(left);

                const moreBtn = document.createElement('button');
                moreBtn.type = 'button';
                moreBtn.className = 'btn-secondary';
                moreBtn.textContent = 'See More';
                moreBtn.addEventListener('click', function() { openItemDetail(item.id); });
                heading.appendChild(moreBtn);

                card.appendChild(heading);

                if (item.object_description) {
                    const desc = document.createElement('p');
                    desc.style.margin = '8px 0 0 0';
                    desc.style.color = '#6b7280';
                    desc.textContent = item.object_description;
                    card.appendChild(desc);
                }

                itemsList.appendChild(card);
            });
        })
        .catch(err => {
            itemsStatus.textContent = 'Unable to list items.';
            console.error('Cannot load items:', err);
        });
    }

    function openItemDetail(depositId) {
        const modal = document.getElementById('container-item-detail-modal');
        if (!modal) return;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        const main = document.getElementById('item-detail-main');
        const meta = document.getElementById('item-detail-meta');
        const title = document.getElementById('item-detail-object');
        const filesList = document.getElementById('item-detail-files-list');
        const recoveredBtn = document.getElementById('item-mark-recovered');

        title.textContent = 'Loading…';
        main.innerHTML = '<p>Loading details…</p>';
        meta.innerHTML = '';
        filesList.innerHTML = '';

        recoveredBtn.disabled = true;

        fetch(`deposit-detail-api?deposit_id=${encodeURIComponent(depositId)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(text => {
            const data = JSON.parse(text);
            if (!data || data.error) throw new Error(data && data.error ? data.error : 'Invalid response');

            const deposit = data.deposit || {};
            const conteneur = data.conteneur || {};
            const files = Array.isArray(data.files) ? data.files : [];

            title.textContent = deposit.object_name || 'Deposit details';

            const userInfoBox = document.getElementById('item-detail-user-info');
            if (userInfoBox) {
                userInfoBox.innerHTML = '<p style="margin:0;">Loading user info…</p>';
            }

            if (deposit.user_id) {
                fetch(`../common/users-api?id=${encodeURIComponent(deposit.user_id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(txt => {
                        let userData = {};
                        try { userData = txt ? JSON.parse(txt) : {}; } catch (e) { userData = {}; }
                        if (userData && !userData.error) {
                            const user = userData;
                            const name = [user.first_name, user.last_name].filter(Boolean).join(' ') || user.username || '-';
                            const username = user.username || '-';
                            const email = user.email || '-';
                            const phone = user.phone || '';

                            if (userInfoBox) {
                                userInfoBox.innerHTML = `
                                    <h4 style="margin:0 0 8px;">User</h4>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:38px;height:38px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa-solid fa-user" style="color:#6b7280;"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight:700;">${escapeHtml(name)}</div>
                                            <div style="font-size:.85rem;color:#6b7280;">${escapeHtml(username)}</div>
                                            <div style="font-size:.85rem;color:#6b7280;">${escapeHtml(email)}</div>
                                            ${phone ? `<div style="font-size:.85rem;color:#6b7280;">${escapeHtml(phone)}</div>` : ''}
                                        </div>
                                    </div>
                                `;
                            }
                        } else {
                            if (userInfoBox) {
                                userInfoBox.innerHTML = '<p style="margin:0;color:#ef4444;">Unable to load user info.</p>';
                            }
                        }
                    })
                    .catch(() => {
                        if (userInfoBox) {
                            userInfoBox.innerHTML = '<p style="margin:0;color:#ef4444;">Unable to load user info.</p>';
                        }
                    });
            } else {
                if (userInfoBox) {
                    userInfoBox.innerHTML = '<p style="margin:0;color:#6b7280;">User not available.</p>';
                }
            }

            main.innerHTML = `
                <p><strong>Name:</strong> ${escapeHtml(deposit.object_name || '-')}</p>
                <p><strong>Description:</strong> ${escapeHtml(deposit.object_description || '-')}</p>
                <p><strong>Status:</strong> ${escapeHtml(mapStatusLabel(deposit.status || 0))}</p>
                <p><strong>Created:</strong> ${formatDate(deposit.created_at)}</p>
                <p><strong>Barcode:</strong> ${escapeHtml(deposit.barcode || 'N/A')}</p>
            `;

            meta.innerHTML = `
                <p><strong>Container:</strong> ${escapeHtml(conteneur.name || '-')}</p>
                <p><strong>Address:</strong> ${escapeHtml([conteneur.number, conteneur.road, conteneur.postal_code, conteneur.city].filter(Boolean).join(', ') || '-')}</p>
            `;

            renderDepositBarcode(deposit.barcode || '');

            if (files.length) {
                filesList.innerHTML = '';
                files.forEach(file => {
                    const fileUrl = '/PA/files/uploads/deposit/' + encodeURIComponent(file.filename || '');

                    const card = document.createElement('div');
                    card.style.display = 'grid';
                    card.style.gridTemplateRows = '120px auto';
                    card.style.border = '1px solid #e5e7eb';
                    card.style.borderRadius = '10px';
                    card.style.overflow = 'hidden';
                    card.style.background = '#fff';

                    const img = document.createElement('img');
                    img.src = fileUrl;
                    img.alt = file.original_name || file.filename || 'attachment';
                    img.style.width = '100%';
                    img.style.height = '120px';
                    img.style.objectFit = 'cover';
                    img.onerror = function() { img.style.display = 'none'; };
                    card.appendChild(img);

                    const meta = document.createElement('div');
                    meta.style.padding = '8px';
                    meta.style.display = 'flex';
                    meta.style.flexDirection = 'column';
                    meta.style.gap = '5px';

                    const label = document.createElement('div');
                    label.style.fontSize = '.825rem';
                    label.style.fontWeight = '600';
                    label.style.color = '#1f2937';
                    label.style.overflow = 'hidden';
                    label.style.whiteSpace = 'nowrap';
                    label.style.textOverflow = 'ellipsis';
                    label.textContent = file.original_name || file.filename || 'Attachment';

                    const actions = document.createElement('div');
                    actions.style.display = 'flex';
                    actions.style.justifyContent = 'space-between';

                    const openLink = document.createElement('a');
                    openLink.href = fileUrl;
                    openLink.target = '_blank';
                    openLink.className = 'btn-secondary';
                    openLink.style.fontSize = '.75rem';
                    openLink.textContent = 'Open';

                    const downloadLink = document.createElement('a');
                    downloadLink.href = fileUrl;
                    downloadLink.download = file.original_name || file.filename || '';
                    downloadLink.className = 'btn-secondary';
                    downloadLink.style.fontSize = '.75rem';
                    downloadLink.textContent = 'Download';

                    actions.appendChild(openLink);
                    actions.appendChild(downloadLink);

                    meta.appendChild(label);
                    meta.appendChild(actions);

                    card.appendChild(meta);
                    filesList.appendChild(card);
                });
            } else {
                filesList.innerHTML = '<p style="color:#6b7280;margin:0;">No files attached.</p>';
            }

            const downloadZipBtn = document.getElementById('item-download-zip');
            if (downloadZipBtn) {
                downloadZipBtn.onclick = function() {
                    window.location.href = 'deposit-download-files.php?deposit_id=' + encodeURIComponent(depositId);
                };
            }

            if (deposit.status === 4) {
                recoveredBtn.textContent = 'Mark as Recovered';
                recoveredBtn.disabled = false;
            } else if (deposit.status === 5) {
                recoveredBtn.textContent = 'Completed';
                recoveredBtn.disabled = true;
            } else if (deposit.status === 3) {
                recoveredBtn.textContent = 'Rejected';
                recoveredBtn.disabled = true;
            } else if (deposit.status === 1) {
                recoveredBtn.textContent = 'Pending approval';
                recoveredBtn.disabled = true;
            } else {
                recoveredBtn.textContent = 'Mark as Recovered';
                recoveredBtn.disabled = false;
            }

            recoveredBtn.onclick = function() {
                if (deposit.status === 5 || deposit.status === 3 || deposit.status === 1) return;

                let nextStatus;
                let confirmMsg;
                if (deposit.status === 4) {
                    nextStatus = 5;
                    confirmMsg = 'Mark this item as completed?';
                } else {
                    nextStatus = 4;
                    confirmMsg = 'Mark this item as deposited (recovered)?';
                }
                showCustomConfirmModal(confirmMsg).then(confirmed => {
                    if (!confirmed) return;

                    recoveredBtn.disabled = true;
                    recoveredBtn.textContent = 'Updating...';

                    setRecovered(depositId, nextStatus, function(updatedDeposit) {
                        deposit.status = updatedDeposit.status;
                        main.querySelector('p:nth-child(3)').innerHTML = '<strong>Status:</strong> ' + mapStatusLabel(updatedDeposit.status);
                        if (updatedDeposit.status === 5) {
                            recoveredBtn.textContent = 'Completed';
                            recoveredBtn.disabled = true;
                        } else if (updatedDeposit.status === 4) {
                            recoveredBtn.textContent = 'Mark as Recovered';
                            recoveredBtn.disabled = false;
                        }
                        applyFilters();
                        if (currentItemsContainerId) loadContainerItems(currentItemsContainerId);
                    });
                });
            };

        })
        .catch(err => {
            console.error('Unable to load deposit detail:', err);
            title.textContent = 'Unable to load';
            main.innerHTML = '<p class="error-message">Unable to load details.</p>';
            meta.innerHTML = '';
            filesList.innerHTML = '';
            recoveredBtn.disabled = true;
        });
    }

    function setRecovered(depositId, status, callback) {
        fetch('deposit-status-api', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: depositId, status: status })
        })
        .then(r => r.text())
        .then(text => {
            const data = JSON.parse(text);
            if (!data || data.error) throw new Error(data && data.error ? data.error : 'Invalid response');
            if (typeof callback === 'function') callback(data.deposit || {});
        })
        .catch(err => {
            showToast('Failed to update status', 'error');
            console.error('Failed mark recovered:', err);
        });
    }

    function renderDepositBarcode(barcodeText) {
        const area = document.getElementById('item-detail-barcode');
        const actions = document.getElementById('item-detail-barcode-actions');
        if (!area || !actions) return;

        if (!barcodeText) {
            area.innerHTML = '';
            actions.innerHTML = '';
            return;
        }

        const svgId = `deposit-barcode-svg-${Date.now()}`;
        area.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;"> 
                <svg id="${svgId}" width="280" height="80" aria-label="Deposit barcode" style="border:1px solid #e5e7eb;border-radius:8px;padding:4px;background:#fff;"></svg>
                <div style="font-size:.85rem;color:#374151;">${escapeHtml(barcodeText)}</div>
            </div>
        `;

        if (window.JsBarcode && typeof JsBarcode === 'function') {
            try {
                const svgEl = document.getElementById(svgId);
                if (svgEl) {
                    JsBarcode(svgEl, barcodeText, {
                        format: 'CODE128',
                        lineColor: '#000',
                        width: 2,
                        height: 60,
                        displayValue: false,
                        margin: 10
                    });
                }
            } catch (err) {
                console.warn('JsBarcode failed, fallback to external generator', err);
                area.innerHTML = `<img src="https://api.qrserver.com/v1/barcode?data=${encodeURIComponent(barcodeText)}&code=Code128&dpi=150" alt="barcode" style="max-width:100%;border:1px solid #e5e7eb;border-radius:8px;" />`;
            }
        } else {
            area.innerHTML = `<img src="https://api.qrserver.com/v1/barcode?data=${encodeURIComponent(barcodeText)}&code=Code128&dpi=150" alt="barcode" style="max-width:100%;border:1px solid #e5e7eb;border-radius:8px;" />`;
        }

        actions.innerHTML = `
            <button id="item-barcode-image" type="button" class="btn-secondary" style="font-size:.85rem;">Download PNG</button>
            <button id="item-barcode-pdf" type="button" class="btn-secondary" style="font-size:.85rem;">Download PDF</button>
            <button id="item-copy-barcode" type="button" class="btn-secondary" style="font-size:.85rem;">Copy</button>
        `;

        document.getElementById('item-barcode-image')?.addEventListener('click', function() {
            renderAndDownloadBarcodeFile(barcodeText, 'png');
        });

        document.getElementById('item-barcode-pdf')?.addEventListener('click', function() {
            renderAndDownloadBarcodeFile(barcodeText, 'pdf');
        });

        document.getElementById('item-copy-barcode')?.addEventListener('click', function() {
            navigator.clipboard?.writeText(barcodeText).then(() => {
                showToast('Barcode copied to clipboard', 'success');
            }).catch(() => {
                showToast('Copy failed', 'error');
            });
        });
    }

    function showToast(message, type = 'info') {
        let toast = document.createElement('div');
        toast.className = 'fs-toast ' + (type ? 'fs-toast-' + type : '');
        toast.textContent = message;
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.background = type === 'error' ? 'rgba(220, 38, 38, 0.95)' : 'rgba(15, 118, 110, 0.95)';
        toast.style.color = '#fff';
        toast.style.padding = '10px 14px';
        toast.style.borderRadius = '8px';
        toast.style.fontSize = '0.9rem';
        toast.style.zIndex = '13000';
        toast.style.boxShadow = '0 6px 18px rgba(0,0,0,.25)';
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.2s ease';

        document.body.appendChild(toast);
        requestAnimationFrame(function() { toast.style.opacity = '1'; });

        setTimeout(function() {
            toast.style.opacity = '0';
            setTimeout(function() { if (toast && toast.parentNode) toast.parentNode.removeChild(toast); }, 250);
        }, 2200);
    }

    function renderAndDownloadBarcodeFile(barcodeText, format) {
        const tmpSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        tmpSvg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        tmpSvg.setAttribute('width', '280');
        tmpSvg.setAttribute('height', '80');

        try {
            JsBarcode(tmpSvg, barcodeText, {
                format: 'CODE128',
                lineColor: '#000',
                width: 2,
                height: 60,
                displayValue: false,
                margin: 10
            });
        } catch (err) {
            alert('Unable to generate barcode image.');
            console.error(err);
            return;
        }

        const svgData = new XMLSerializer().serializeToString(tmpSvg);
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(svgBlob);

        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            if (format === 'png') {
                const dataUrl = canvas.toDataURL('image/png');
                downloadDataUrl(dataUrl, 'barcode.png');
            } else if (format === 'pdf') {
                if (window.jspdf && window.jspdf.jsPDF) {
                    const pdf = new window.jspdf.jsPDF({ unit: 'pt', format: [canvas.width + 20, canvas.height + 40] });
                    const dataUrl = canvas.toDataURL('image/png');
                    pdf.addImage(dataUrl, 'PNG', 10, 10, canvas.width, canvas.height);
                    pdf.save('barcode.pdf');
                } else {
                    alert('PDF export requires jsPDF library.');
                }
            }

            URL.revokeObjectURL(url);
        };
        img.onerror = function() {
            URL.revokeObjectURL(url);
            alert('Failed to render barcode image.');
        };
        img.src = url;
    }

    function downloadDataUrl(dataUrl, filename) {
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function distanceHaversine(lat1, lon1, lat2, lon2) {
        const toRad = x => x * Math.PI / 180;
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function mapStatusLabel(status) {
        switch (Number(status || 0)) {
            case 0:
            case 1: return 'Pending';
            case 2: return 'Accepted';
            case 3: return 'Rejected';
            case 4: return 'Deposited';
            case 5: return 'Completed';
            default: return 'Unknown';
        }
    }

    function showCustomConfirmModal(message, title = 'Confirm action') {
        return new Promise(function(resolve) {
            const modalId = 'container-confirm-action-modal';
            const modal = document.getElementById(modalId);
            if (!modal) {
                const result = window.confirm(message);
                resolve(result);
                return;
            }

            const titleEl = document.getElementById('container-confirm-action-title');
            const bodyEl = document.getElementById('container-confirm-action-body');
            const confirmBtn = document.getElementById('container-confirm-action-confirm');
            const cancelBtn = document.getElementById('container-confirm-action-cancel');

            if (titleEl) titleEl.textContent = title;
            if (bodyEl) bodyEl.textContent = message;

            function cleanup() {
                confirmBtn?.removeEventListener('click', onConfirm);
                cancelBtn?.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                closeModal(modalId);
            }

            function onConfirm() {
                cleanup();
                resolve(true);
            }

            function onCancel() {
                cleanup();
                resolve(false);
            }

            function onBackdrop(e) {
                if (e.target === modal) {
                    onCancel();
                }
            }

            confirmBtn?.addEventListener('click', onConfirm);
            cancelBtn?.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        });
    }

    function formatDate(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit' });
    }

    function openContainerModal(c) {
        const modal = document.getElementById('container-detail-modal');
        if (!modal) return;

        modal.querySelector('#container-modal-name').textContent = c.name || '-';
        modal.querySelector('#container-modal-address').textContent = [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ');
        modal.querySelector('#container-modal-city').textContent = c.city || '-';
        modal.querySelector('#container-modal-postal').textContent = c.postal_code || '-';
        modal.querySelector('#container-modal-created').textContent = c.created_at ? new Date(c.created_at).toLocaleDateString('fr-FR') : '-';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        const mapEl = document.getElementById('container-modal-map');
        if (!mapEl) return;

        const freshMap = document.createElement('div');
        freshMap.id = 'container-modal-map';
        freshMap.style.cssText = 'height:260px;border-radius:10px;overflow:hidden;margin-top:18px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;';
        freshMap.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Loading map…</span>';
        mapEl.replaceWith(freshMap);

        const query = [c.number, c.road, c.postal_code, c.city, 'France'].filter(Boolean).join(', ');

        fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`, {
            headers: { 'Accept-Language': 'en' }
        })
        .then(r => r.json())
        .then(results => {
            const target = document.getElementById('container-modal-map');
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
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            L.marker([lat, lng]).addTo(map).bindPopup(`<strong>${escapeHtml(c.name || '')}</strong><br>${escapeHtml(query)}`).openPopup();
            setTimeout(() => map.invalidateSize(), 250);
        })
        .catch(() => {
            const target = document.getElementById('container-modal-map');
            if (target) target.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Map unavailable</span>';
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const page = parseInt(params.get('page'), 10);
        return Number.isFinite(page) && page > 0 ? page : 1;
    }

    function updateUrlPage(page, replace) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(page));
        if (replace) {
            window.history.replaceState({}, '', url.toString());
        } else {
            window.history.pushState({}, '', url.toString());
        }
    }

    function renderSkeletons(containerEl, count) {
        const skeletons = [];
        for (let i = 0; i < count; i += 1) {
            skeletons.push(
                '<div class="skeleton-service-item">' +
                    '<div class="skeleton-service-header"><div class="skeleton skeleton-title"></div></div>' +
                    '<div class="skeleton skeleton-description"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                '</div>'
            );
        }
        containerEl.innerHTML = skeletons.join('');
    }
})();