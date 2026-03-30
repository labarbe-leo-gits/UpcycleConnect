document.addEventListener('DOMContentLoaded', function () {
    const files = Array.isArray(window.LOG_FILES) ? window.LOG_FILES : [];
    const tableBody = document.querySelector('#logs-table tbody');
    const filterFile = document.getElementById('logs-file-filter');
    const filterLevel = document.getElementById('logs-level-filter');
    const filterSearch = document.getElementById('logs-search');
    const loadMoreBtn = document.getElementById('logs-load-more');
    const logsCount = document.getElementById('logs-count');
    const ipMapEl = document.getElementById('ip-map');
    const ipMapInfo = document.getElementById('ip-map-info');
    const ipMapStatus = document.getElementById('ip-map-status');
    const ipMapClose = document.getElementById('ip-map-close');

    let mapInstance = null;
    let loadedEntries = [];
    let totalEntries = 0;
    let pageSize = parseInt(localStorage.getItem('adminLogsPageSize') || '40', 10);
    let currentOffset = 0;
    const pageSizeSelector = document.getElementById('logs-page-size');
    const columnToggles = document.querySelectorAll('.column-toggle');

    const columnDropdownButton = document.getElementById('column-dropdown-button');
    const columnDropdownMenu = document.getElementById('column-dropdown-menu');

    if (columnToggles.length) {
        columnToggles.forEach(checkbox => {
            const col = checkbox.getAttribute('data-col');
            setColumnVisibility(`col-${col}`, checkbox.checked);
            checkbox.addEventListener('change', function () {
                const colInner = this.getAttribute('data-col');
                setColumnVisibility(`col-${colInner}`, this.checked);
            });
        });
    }

    if (columnDropdownButton && columnDropdownMenu) {
        columnDropdownButton.addEventListener('click', () => {
            const isOpen = columnDropdownMenu.style.display === 'block';
            columnDropdownMenu.style.display = isOpen ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!columnDropdownMenu.contains(e.target) && e.target !== columnDropdownButton) {
                columnDropdownMenu.style.display = 'none';
            }
        });
    }

    const liveModeHelpText = 'Enable live refresh of the log file. Your performance can be impacted.';
    const liveModeWrap = document.querySelector('.live-mode-wrap');
    if (liveModeWrap) {
        const helpCard = buildHelpCard(liveModeHelpText);
        const switchElement = liveModeWrap.querySelector('.switch');
        if (switchElement) {
            liveModeWrap.insertBefore(helpCard, switchElement);
        } else {
            liveModeWrap.appendChild(helpCard);
        }
    }

    const resetFiltersBtn = document.getElementById('offers-reset-filters');
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', () => {
            filterFile.value = 'all';
            if (filterLevel) filterLevel.value = 'all';
            filterSearch.value = '';
            const checkboxes = document.querySelectorAll('.column-toggle');
            checkboxes.forEach(ch => {
                ch.checked = true;
                setColumnVisibility(`col-${ch.getAttribute('data-col')}`, true);
            });
            currentOffset = 0;
            loadedEntries = [];
            fetchLogs(false);
        });
    }

    if (pageSizeSelector) {
        pageSizeSelector.value = String(pageSize);
        pageSizeSelector.addEventListener('change', () => {
            const newVal = parseInt(pageSizeSelector.value, 10);
            if (Number.isFinite(newVal) && newVal > 0) {
                pageSize = newVal;
                localStorage.setItem('adminLogsPageSize', String(pageSize));
                currentOffset = 0;
                loadedEntries = [];
                fetchLogs(false);
            }
        });
    }

    function getLevelClass(level) {
        const normalized = (level || '').toLowerCase();
        if (normalized.includes('error') || normalized.includes('err')) return 'error';
        if (normalized.includes('warn')) return 'warn';
        return 'info';
    }

    function formatTimestamp(timestamp) {
        if (!timestamp) return '';
        const d = new Date(timestamp);
        if (Number.isNaN(d.getTime())) {
            const match = timestamp.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/);
            if (match) {
                return `${match[1]} ${match[2]}`;
            }
            return timestamp;
        }
        return d.toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function buildHelpCard(message) {
        const wrapper = document.createElement('div');
        wrapper.className = 'help-icon';

        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-circle-info';
        icon.setAttribute('aria-hidden', 'true');

        const tooltip = document.createElement('span');
        tooltip.className = 'help-tooltip';
        tooltip.textContent = message;

        wrapper.appendChild(icon);
        wrapper.appendChild(tooltip);
        return wrapper;
    }

    function escapeHtml(text) {
        if (text === undefined || text === null) {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderRows(entries, append = false) {
        if (!append) {
            tableBody.innerHTML = '';
        }

        if (!entries || entries.length === 0) {
            if (!append) {
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#6b7280; padding:20px;">No log lines found</td></tr>';
            }
            return;
        }

        entries.forEach(entry => {
            const row = document.createElement('tr');
            row.innerHTML = `<td class="col-timestamp">${escapeHtml(formatTimestamp(entry.timestamp))}</td>
            <td class="col-level"><span class="log-level ${getLevelClass(entry.level)}">${escapeHtml(entry.level)}</span></td>
            <td class="col-ip"><a class="ip-link" data-ip="${escapeHtml(entry.ip)}" href="javascript:void(0);">${escapeHtml(entry.ip)}</a></td>
            <td class="col-message" style="max-width:420px; word-wrap:break-word;">${escapeHtml(entry.message)}</td>
            <td class="col-file">${escapeHtml(entry.file)}</td>
            <td>
                <button class="row-view-btn" data-file="${escapeHtml(entry.file)}" data-line="${entry.line}" data-message="${escapeHtml(entry.message)}" data-timestamp="${escapeHtml(entry.timestamp)}" title="View full message"><i class="fa-solid fa-eye"></i></button>
                <button class="row-delete-btn" data-file="${escapeHtml(entry.file)}" data-line="${entry.line}" title="Delete this entry"><i class="fa-solid fa-trash"></i></button>
            </td>`;

            const ipLink = row.querySelector('.ip-link');
            if (ipLink) {
                ipLink.addEventListener('click', () => loadIpLocation(entry.ip, entry.message));
            }

            const viewBtn = row.querySelector('.row-view-btn');
            if (viewBtn) {
                viewBtn.addEventListener('click', () => {
                    const message = entry.message || '';
                    const file = entry.file || '';
                    const timestamp = formatTimestamp(entry.timestamp || '');
                    document.getElementById('view-message-content').textContent = message;
                    document.getElementById('view-message-file').textContent = file;
                    document.getElementById('view-message-timestamp').textContent = timestamp;
                    showModal('view-message-modal');
                });
            }

            const deleteBtn = row.querySelector('.row-delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => deleteLogEntry(entry.file, entry.line));
            }

            tableBody.appendChild(row);
        });
    }

    function updateCount() {
        logsCount.textContent = `Showing ${loadedEntries.length} of ${totalEntries} entries`;
    }

    function enableLoadMore() {
        if (loadedEntries.length < totalEntries) {
            loadMoreBtn.style.display = 'inline-block';
            loadMoreBtn.disabled = false;
        } else {
            loadMoreBtn.style.display = 'none';
        }
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

    function setColumnVisibility(column, visible) {
        const th = document.querySelector(`th.${column}`);
        const tds = document.querySelectorAll(`td.${column}`);
        if (th) th.style.display = visible ? '' : 'none';
        tds.forEach(td => td.style.display = visible ? '' : 'none');
    }

    function clearMapPlaceholder() {
        if (!ipMapEl) return;
        ipMapEl.classList.remove('map-unavailable');
        ipMapEl.innerHTML = '';
    }

    function setMapPlaceholderError(text) {
        if (!ipMapEl) return;
        clearMapPlaceholder();
        ipMapEl.classList.add('map-unavailable');
        ipMapEl.innerHTML = `<div class="map-error-text">${escapeHtml(text)}</div>`;
    }

    function isPrivateIp(ip) {
        if (!ip || typeof ip !== 'string') return false;
        if (ip === 'localhost' || ip === '127.0.0.1' || ip === '::1') return true;
        const parts = ip.split('.').map(Number);
        if (parts.length === 4 && parts.every(p => Number.isFinite(p) && p >= 0 && p < 256)) {
            if (parts[0] === 10) return true;
            if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return true;
            if (parts[0] === 192 && parts[1] === 168) return true;
            if (parts[0] === 169 && parts[1] === 254) return true;
        }
        return false;
    }

    async function loadIpLocation(ip, logMessage) {
        if (!ip) return;

        if (isPrivateIp(ip)) {
            ipMapStatus.textContent = 'Private/local address cannot be geolocated: ' + ip;
            ipMapInfo.innerHTML = `<strong>IP:</strong> ${escapeHtml(ip)}<br><strong>Note:</strong> local/private IP addresses are not mappable by public geolocation APIs.`;
            if (logMessage) {
                ipMapInfo.innerHTML += `<blockquote class="log-quote">${escapeHtml(logMessage)}</blockquote>`;
            }
            ipMapInfo.innerHTML += `<blockquote class="error-quote">Local/private IP address detected</blockquote>`;
            setMapPlaceholderError('Local/private IP geolocation unavailable.');
            showModal('ip-map-modal');
            return;
        }

        clearMapPlaceholder();
        ipMapStatus.textContent = 'Resolving location for ' + ip + '...';
        ipMapInfo.textContent = '';

        if (mapInstance) {
            try { mapInstance.remove(); } catch (e) {};
            mapInstance = null;
        }

        showModal('ip-map-modal');

        try {
            const providers = [
                {
                    name: 'ip-api',
                    url: 'https://ip-api.com/json/' + encodeURIComponent(ip) + '?fields=status,message,country,regionName,city,lat,lon,query,isp',
                    parse: data => ({
                        ok: data && data.status === 'success' && data.lat && data.lon,
                        lat: parseFloat(data.lat),
                        lon: parseFloat(data.lon),
                        city: data.city || '',
                        region: data.regionName || '',
                        country: data.country || '',
                        query: data.query || ip,
                        isp: data.isp || '',
                        reason: data.message || ''
                    })
                },
                {
                    name: 'ipwhois',
                    url: 'https://ipwhois.app/json/' + encodeURIComponent(ip) + '?lang=en',
                    parse: data => ({
                        ok: data && data.success === true && data.latitude && data.longitude,
                        lat: parseFloat(data.latitude),
                        lon: parseFloat(data.longitude),
                        city: data.city || '',
                        region: data.region || '',
                        country: data.country || '',
                        query: data.ip || ip,
                        isp: data.isp || '',
                        reason: data.message || ''
                    })
                },
                {
                    name: 'ipapi',
                    url: 'https://ipapi.co/' + encodeURIComponent(ip) + '/json',
                    parse: data => ({
                        ok: data && !data.error && data.latitude && data.longitude,
                        lat: parseFloat(data.latitude),
                        lon: parseFloat(data.longitude),
                        city: data.city || '',
                        region: data.region || '',
                        country: data.country_name || '',
                        query: data.ip || ip,
                        isp: data.org || data.asn || '',
                        reason: data.reason || ''
                    })
                }
            ];

            let found = null;
            for (const provider of providers) {
                try {
                    const response = await fetch(provider.url);
                    if (!response.ok) continue;
                    const data = await response.json();
                    const parsed = provider.parse(data);
                    if (parsed.ok && Number.isFinite(parsed.lat) && Number.isFinite(parsed.lon)) {
                        found = parsed;
                        break;
                    }
                } catch (err) {
                    // try next provider
                }
            }

            if (!found) {
                ipMapStatus.textContent = 'Unable to locate this IP with free APIs (no key required).';
                setMapPlaceholderError('Public geolocation unavailable for this IP.');
                return;
            }

            clearMapPlaceholder();
            const coords = [found.lat, found.lon];
            ipMapStatus.textContent = 'Location found: ' + [found.city, found.region, found.country].filter(Boolean).join(', ');
            ipMapInfo.innerHTML = `<strong>IP:</strong> ${found.query}<br>
                <strong>City:</strong> ${found.city || 'n/a'}<br>
                <strong>Region:</strong> ${found.region || 'n/a'}<br>
                <strong>Country:</strong> ${found.country || 'n/a'}<br>
                <strong>Latitude:</strong> ${Number.isFinite(found.lat) ? found.lat.toFixed(6) : 'n/a'}<br>
                <strong>Longitude:</strong> ${Number.isFinite(found.lon) ? found.lon.toFixed(6) : 'n/a'}<br>
                <strong>ISP:</strong> ${found.isp || 'n/a'}`;
            if (logMessage) {
                ipMapInfo.innerHTML += `<blockquote class="log-quote">${escapeHtml(logMessage)}</blockquote>`;
            }
            if (found.reason) {
                ipMapInfo.innerHTML += `<blockquote class="info-quote">${escapeHtml(found.reason)}</blockquote>`;
            }

            mapInstance = L.map('ip-map', {scrollWheelZoom: false}).setView(coords, 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(mapInstance);
            L.marker(coords).addTo(mapInstance).bindPopup('IP: ' + found.query).openPopup();

        } catch (err) {
            ipMapStatus.textContent = 'Error fetching geolocation: ' + (err.message || 'network error');
        }
    }

    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const deleteConfirmMessage = document.getElementById('delete-confirm-message');
    const deleteConfirmClose = document.getElementById('delete-confirm-close');
    const deleteCancelBtn = document.getElementById('delete-cancel-btn');
    const deleteConfirmBtn = document.getElementById('delete-confirm-btn');

    let pendingDelete = null;

    function askDeleteLogEntry(fileName, lineIndex, message) {
        pendingDelete = { type: 'entry', fileName, lineIndex };
        deleteConfirmMessage.textContent = `Delete this entry? ${message ?? ''}`;
        showModal('delete-confirm-modal');
    }

    function askDeleteLogFile(fileName) {
        pendingDelete = { type: 'file', fileName };
        deleteConfirmMessage.textContent = `Clear entire log file "${fileName}"? This is irreversible.`;
        showModal('delete-confirm-modal');
    }

    function showInfoMessage(message) {
        const infoModal = document.getElementById('info-modal');
        const infoMessage = document.getElementById('info-message');
        if (!infoModal || !infoMessage) return;
        infoMessage.textContent = message;
        showModal('info-modal');
    }

    async function confirmDeleteLogEntry() {
        if (!pendingDelete) {
            hideModal('delete-confirm-modal');
            return;
        }

        deleteConfirmBtn.disabled = true;

        try {
            let response, result;

            if (pendingDelete.type === 'entry') {
                const formData = new URLSearchParams();
                formData.append('action', 'delete');
                formData.append('file', pendingDelete.fileName);
                formData.append('line', pendingDelete.lineIndex);

                response = await fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                });

                result = await response.json();
                if (!result.success) {
                    showInfoMessage('Unable to delete entry: ' + (result.message || 'unknown'));
                }
            } else if (pendingDelete.type === 'file') {
                response = await fetch(window.location.pathname + '?file=' + encodeURIComponent(pendingDelete.fileName), {
                    method: 'DELETE'
                });

                result = await response.json();
                if (!result.success) {
                    showInfoMessage('Unable to clear log file: ' + (result.message || 'unknown'));
                }
            }
        } catch (err) {
            showInfoMessage('Unable to delete entry: ' + err.message);
        }

        deleteConfirmBtn.disabled = false;
        hideModal('delete-confirm-modal');
        pendingDelete = null;

        currentOffset = 0;
        loadedEntries = [];
        await fetchLogs(false);
    }

    const deleteLogFileBtn = document.getElementById('delete-log-file-btn');
    async function deleteCurrentLogFile() {
        if (!filterFile.value || filterFile.value === 'all') {
            showInfoMessage('Please select a specific log file to clear.');
            return;
        }

        askDeleteLogFile(filterFile.value);
    }

    deleteLogFileBtn?.addEventListener('click', deleteCurrentLogFile);

    deleteConfirmClose?.addEventListener('click', () => hideModal('delete-confirm-modal'));
    deleteCancelBtn?.addEventListener('click', () => hideModal('delete-confirm-modal'));
    deleteConfirmBtn?.addEventListener('click', confirmDeleteLogEntry);

    const infoClose = document.getElementById('info-close');
    const infoCloseBtn = document.getElementById('info-close-btn');
    infoClose?.addEventListener('click', () => hideModal('info-modal'));
    infoCloseBtn?.addEventListener('click', () => hideModal('info-modal'));

    const viewMessageModal = document.getElementById('view-message-modal');
    const viewMessageClose = document.getElementById('view-message-close');
    const viewMessageClose2 = document.getElementById('view-message-close2');

    viewMessageClose?.addEventListener('click', () => hideModal('view-message-modal'));
    viewMessageClose2?.addEventListener('click', () => hideModal('view-message-modal'));

    async function deleteLogEntry(fileName, lineIndex) {
        if (!fileName || typeof lineIndex !== 'number') {
            return;
        }

        askDeleteLogEntry(fileName, lineIndex);
    }

    async function fetchLogs(append = false) {
        const selectedFile = filterFile.value;
        const searchTerm = filterSearch.value.trim();

        const offset = append ? currentOffset : 0;
        const params = new URLSearchParams();
        params.set('ajax', '1');
        params.set('offset', String(offset));
        params.set('limit', String(pageSize));
        if (selectedFile && selectedFile !== 'all') {
            params.set('file', selectedFile);
        }
        if (filterLevel && filterLevel.value && filterLevel.value !== 'all') {
            params.set('level', filterLevel.value);
        }
        if (searchTerm) {
            params.set('search', searchTerm);
        }

        const url = window.location.pathname + '?' + params.toString();

        loadMoreBtn.disabled = true;

        const resp = await fetch(url);
        const data = await resp.json();

        if (!append) {
            loadedEntries = [];
            tableBody.innerHTML = '';
        }

        if (Array.isArray(data.entries)) {
            if (append) {
                loadedEntries = loadedEntries.concat(data.entries);
            } else {
                loadedEntries = data.entries;
            }
            renderRows(data.entries, append);
        }

        totalEntries = data.total || 0;
        currentOffset = loadedEntries.length;

        updateCount();
        enableLoadMore();
    }

    function applyFilters() {
        currentOffset = 0;
        loadedEntries = [];
        fetchLogs(false);
    }

    function updateCount() {
        logsCount.textContent = `Showing ${loadedEntries.length} of ${totalEntries} entries`;
    }

    function enableLoadMore() {
        if (loadedEntries.length < totalEntries) {
            loadMoreBtn.style.display = 'inline-block';
            loadMoreBtn.disabled = false;
        } else {
            loadMoreBtn.style.display = 'none';
        }
    }

    filterFile.addEventListener('change', applyFilters);
    if (filterLevel) {
        filterLevel.addEventListener('change', applyFilters);
    }
    filterSearch.addEventListener('input', () => {
        clearTimeout(window.logsSearchTimeout);
        window.logsSearchTimeout = setTimeout(applyFilters, 350);
    });

    const liveModeCheckbox = document.getElementById('logs-live-mode');
    let liveModeTimer = null;

    function setLiveMode(enabled) {
        if (enabled) {
            if (liveModeTimer) return;
            liveModeTimer = setInterval(() => {
                fetchLogs(false);
            }, 6000);
        } else {
            if (liveModeTimer) {
                clearInterval(liveModeTimer);
                liveModeTimer = null;
            }
        }
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

    if (liveModeCheckbox) {
        const saved = localStorage.getItem('adminLogsLiveMode');
        const enabled = saved === 'true';
        liveModeCheckbox.checked = enabled;
        setLiveMode(enabled);

        liveModeCheckbox.addEventListener('change', (e) => {
            const isOn = e.target.checked;
            localStorage.setItem('adminLogsLiveMode', String(isOn));
            setLiveMode(isOn);
        });
    }

    loadMoreBtn.addEventListener('click', () => fetchLogs(true));

    function populateFileFilter(fileList) {
        filterFile.innerHTML = '<option value="all">All log files</option>';
        (fileList || []).forEach(file => {
            const opt = document.createElement('option');
            opt.value = file;
            opt.textContent = file;
            filterFile.appendChild(opt);
        });
    }

    populateFileFilter(files);
    fetchLogs(false);

    ipMapClose?.addEventListener('click', () => {
        hideModal('ip-map-modal');
        if (mapInstance) {
            try { mapInstance.remove(); } catch (e) {};
            mapInstance = null;
        }
    });
});