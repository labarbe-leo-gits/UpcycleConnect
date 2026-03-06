(function() {
    'use strict';

    const pageSize = 4;
    let currentPage = 1;
    let totalPages = 1;

    document.addEventListener('DOMContentLoaded', function() {
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
    });

    function requestPage(page, replaceHistory) {
        const container = document.getElementById('containers-container');
        const pagination = document.getElementById('containers-pagination');

        if (!container) {
            console.error('Containers container not found');
            return;
        }

        renderSkeletons(container, pageSize);

        fetch(`containers-api?page=${page}&limit=${pageSize}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                const data = JSON.parse(text);
                const items = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total = Number.isFinite(data.total) ? data.total : items.length;
                totalPages = total > 0 ? Math.ceil(total / pageSize) : 1;

                if (!items || items.length === 0) {
                    container.innerHTML = '<p class="empty-containers">No containers available at the moment.</p>';
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

                renderContainers(items, container);
                renderPagination(pagination);
            })
            .catch(error => {
                console.error('Error loading containers:', error);
                container.innerHTML = '<p class="error-message">An error occurred while loading containers. Please try again later.</p>';
                if (pagination) pagination.innerHTML = '';
            });
    }

    function renderContainers(containers, containerEl) {
        containers.forEach(c => {
            const contItem = createContainerElement(c);
            containerEl.appendChild(contItem);
        });
    }

    function renderPagination(pagination) {
        if (!pagination) {
            return;
        }

        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        const prevButton = createPageButton('Prev', currentPage === 1, function() {
            if (currentPage > 1) {
                requestPage(currentPage - 1);
            }
        });
        pagination.appendChild(prevButton);

        for (let i = 1; i <= totalPages; i += 1) {
            const pageButton = createPageButton(String(i), false, function() {
                requestPage(i);
            });
            if (i === currentPage) {
                pageButton.classList.add('active');
                pageButton.setAttribute('aria-current', 'page');
            }
            pagination.appendChild(pageButton);
        }

        const nextButton = createPageButton('Next', currentPage === totalPages, function() {
            if (currentPage < totalPages) {
                requestPage(currentPage + 1);
            }
        });
        pagination.appendChild(nextButton);
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
        let addrParts = [];
        if (c.road) addrParts.push(c.road);
        if (c.number) addrParts.push(c.number);
        if (c.city) addrParts.push(c.city);
        if (c.postal_code) addrParts.push(c.postal_code);
        address.textContent = addrParts.join(' ');
        div.appendChild(address);

        const button = document.createElement('button');
        button.className = 'btn-primary';
        button.textContent = 'Open';
        button.onclick = function() { openContainerModal(c); };
        div.appendChild(button);

        return div;
    }

    function openContainerModal(c) {
        const modal = document.getElementById('container-detail-modal');
        if (!modal) return;

        modal.querySelector('#container-modal-name').textContent =
            c.name || '-';
        modal.querySelector('#container-modal-address').textContent =
            [c.number, c.road, c.postal_code, c.city].filter(Boolean).join(', ');
        modal.querySelector('#container-modal-city').textContent =
            c.city || '-';
        modal.querySelector('#container-modal-postal').textContent =
            c.postal_code || '-';
        modal.querySelector('#container-modal-created').textContent =
            c.created_at ? new Date(c.created_at).toLocaleDateString('fr-FR') : '-';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        const mapEl = document.getElementById('container-modal-map');
        if (!mapEl) return;

        if (mapEl._leaflet_id) {
            try { L.map(mapEl).remove(); } catch(e) {}
        }
        const freshMap = document.createElement('div');
        freshMap.id = 'container-modal-map';
        freshMap.style.cssText = 'height:260px;border-radius:10px;overflow:hidden;margin-top:18px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;';
        freshMap.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Loading map…</span>';
        mapEl.replaceWith(freshMap);

        const query = [c.number, c.road, c.postal_code, c.city, 'France']
            .filter(Boolean).join(', ');

        fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`, {
            headers: { 'Accept-Language': 'en' }
        })
        .then(r => r.json())
        .then(results => {
            const target = document.getElementById('container-modal-map');
            if (!target) return;
            if (!results || results.length === 0) {
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
            const marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup(`<strong>${escapeHtml(c.name || '')}</strong><br>${escapeHtml(query)}`).openPopup();

            setTimeout(() => map.invalidateSize(), 250);
        })
        .catch(() => {
            const target = document.getElementById('container-modal-map');
            if (target) target.innerHTML = '<span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Map unavailable</span>';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('container-detail-modal');
        if (!modal) return;

        modal.querySelector('#container-modal-close')?.addEventListener('click', function() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const pageParam = parseInt(params.get('page'), 10);
        if (Number.isNaN(pageParam) || pageParam < 1) {
            return 1;
        }
        return pageParam;
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
                    '<div class="skeleton-service-header">' +
                        '<div class="skeleton skeleton-title"></div>' +
                    '</div>' +
                    '<div class="skeleton skeleton-description"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                '</div>'
            );
        }

        containerEl.innerHTML = skeletons.join('');
    }
})();