(function() {
    'use strict';

    const pageSize = 3;
    let currentPage = 1;
    let totalPages = 1;

    document.addEventListener('DOMContentLoaded', function() {
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
        setupModalHandlers();
        setupAddDepositModal();
    });

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
                throw parseErr; // trigger catch below
            }
            // API may return an error object
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

            card.appendChild(actions);
            container.appendChild(card);
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
            const files = Array.isArray(data.files) ? data.files : [];
            if (filesSection && gallery && downloads) {
                if (files.length > 0) {
                    gallery.innerHTML = '';
                    downloads.innerHTML = '';
                    files.forEach(function(f) {
                        const imgUrl = '/PA/files/uploads/deposit/' + encodeURIComponent(f.filename);
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
                    });
                    filesSection.style.display = '';
                } else {
                    filesSection.style.display = 'none';
                    gallery.innerHTML = '';
                    downloads.innerHTML = '';
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
                const imgSrc = (conteneur.image && conteneur.image.length) ? conteneur.image : '../../assets/img/defaults/container.png';

                let imgHtml = '';
                if (typeof imgSrc === 'string' && imgSrc.indexOf('data:') === 0) {
                    imgHtml = `<img src="${imgSrc}" alt="Conteneur" />`;
                } else {
                    imgHtml = `<img data-blob-src="${imgSrc}" alt="Conteneur" />`;
                }

                depositImageEl.innerHTML = imgHtml;

                const imgNode = depositImageEl.querySelector('img');
                if (imgNode) {
                    const markLoaded = () => { imgNode.classList.add('fade-in'); };
                    const onError = () => { imgNode.src = '../../assets/img/defaults/container.png'; imgNode.classList.add('fade-in'); };

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
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`;
        return fetch(url, { headers: { 'Accept-Language': 'en' } })
            .then(r => {
                if (!r.ok) {
                    return null;
                }
                return r.text().then(text => {
                    try {
                        const results = JSON.parse(text);
                        if (!Array.isArray(results) || results.length === 0) {
                            return null;
                        }
                        return { lat: parseFloat(results[0].lat), lon: parseFloat(results[0].lon) };
                    } catch (err) {
                        return null;
                    }
                });
            })
            .catch(err => {
                return null;
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
            selectedFiles = [];
            updateDropzoneState();
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

            readFilesAsBase64(filesPayload_files).then(function(filesPayload) {
                return fetch('create-deposit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ conteneur_id: conteneurId, object_name: name, object_description: desc, files: filesPayload })
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
            case 1: return 'Accepted';
            case 2: return 'Rejected';
            case 3: return 'Completed';
            default: return 'Pending';
        }
    }

    function mapStatusClass(status) {
        switch (parseInt(status || 0, 10)) {
            case 1: return 'accepted';
            case 2: return 'rejected';
            case 3: return 'completed';
            default: return 'pending';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    window.openDepositDetails = openDetailsModal;
})();
