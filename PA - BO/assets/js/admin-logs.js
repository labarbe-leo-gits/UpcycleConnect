document.addEventListener('DOMContentLoaded', function () {
    const files = Array.isArray(window.LOG_FILES) ? window.LOG_FILES : [];
    const tableBody = document.querySelector('#logs-table tbody');
    const filterFile = document.getElementById('logs-file-filter');
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
            row.innerHTML = `<td>${escapeHtml(entry.timestamp)}</td>
            <td><span class="log-level ${getLevelClass(entry.level)}">${escapeHtml(entry.level)}</span></td>
            <td><a class="ip-link" data-ip="${escapeHtml(entry.ip)}" href="javascript:void(0);">${escapeHtml(entry.ip)}</a></td>
            <td style="max-width:420px; word-wrap:break-word;">${escapeHtml(entry.message)}</td>
            <td>${escapeHtml(entry.file)}</td>
            <td><button class="row-delete-btn" data-file="${escapeHtml(entry.file)}" data-line="${entry.line}" title="Delete this entry"><i class="fa-solid fa-trash"></i></button></td>`;

            const ipLink = row.querySelector('.ip-link');
            if (ipLink) {
                ipLink.addEventListener('click', () => loadIpLocation(entry.ip, entry.message));
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
        pendingDelete = { fileName, lineIndex };
        deleteConfirmMessage.textContent = `Delete this entry? ${message ?? ''}`;
        showModal('delete-confirm-modal');
    }

    async function confirmDeleteLogEntry() {
        if (!pendingDelete) {
            hideModal('delete-confirm-modal');
            return;
        }

        deleteConfirmBtn.disabled = true;

        const formData = new URLSearchParams();
        formData.append('action', 'delete');
        formData.append('file', pendingDelete.fileName);
        formData.append('line', pendingDelete.lineIndex);

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            });
            const result = await response.json();
            if (!result.success) {
                alert('Unable to delete entry: ' + (result.message || 'unknown'));
            }
        } catch (err) {
            alert('Unable to delete entry: ' + err.message);
        }

        deleteConfirmBtn.disabled = false;
        hideModal('delete-confirm-modal');
        pendingDelete = null;

        currentOffset = 0;
        loadedEntries = [];
        await fetchLogs(false);
    }

    deleteConfirmClose?.addEventListener('click', () => hideModal('delete-confirm-modal'));
    deleteCancelBtn?.addEventListener('click', () => hideModal('delete-confirm-modal'));
    deleteConfirmBtn?.addEventListener('click', confirmDeleteLogEntry);

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