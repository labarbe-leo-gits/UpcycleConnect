
(function() {
    'use strict';

    const pageSize = 4;
    let currentPage = 1;
    let totalPages = 1;
    let searchTerm = '';
    let typeFilter = '';

    document.addEventListener('DOMContentLoaded', function() {
        bindToolbar();
        loadTypes();
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
    });

    function requestPage(page, replaceHistory) {
        const container = document.getElementById('services-container');
        const pagination = document.getElementById('services-pagination');
        
        if (!container) {
            console.error('Services container not found');
            return;
        }

        renderSkeletons(container, pageSize);
        renderPaginationSkeletons(pagination);

        let url = `services-api?page=${page}&limit=${pageSize}`;
        if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
        if (typeFilter) url += `&type=${encodeURIComponent(typeFilter)}`;
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Error response:', text);
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                const data = JSON.parse(text);
// populate type select once from response later? will load separately
                const services = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total = Number.isFinite(data.total) ? data.total : services.length;
                totalPages = total > 0 ? Math.ceil(total / pageSize) : 1;

                if (!services || services.length === 0) {
                    container.innerHTML = '<p class="empty-services">No services available at the moment.</p>';
                    if (pagination) {
                        pagination.innerHTML = '';
                    }
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
                if (pagination) {
                    pagination.innerHTML = '';
                }

                renderServices(services, container);
                renderPagination(pagination);
            })
            .catch(error => {
                console.error('Error loading services:', error);
                console.error('Error details:', error.message);
                container.innerHTML = '<p class="error-message">An error occurred while loading services. Please try again later.</p>';
                if (pagination) {
                    pagination.innerHTML = '';
                }
            });
    }

    function renderServices(services, container) {
        services.forEach(service => {
            const serviceItem = createServiceElement(service);
            container.appendChild(serviceItem);
        });
    }

    function bindToolbar() {
        document.getElementById('service-search')?.addEventListener('input', function() {
            searchTerm = this.value.trim();
            currentPage = 1;
            requestPage(currentPage, true);
        });
        document.getElementById('service-type-filter')?.addEventListener('change', function() {
            typeFilter = this.value;
            currentPage = 1;
            requestPage(currentPage, true);
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

    function populateTypeOptions(list) {
        const filter = document.getElementById('service-type-filter');
        if (!filter) return;
        filter.innerHTML = '<option value="">All types</option>';
        list.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            filter.appendChild(opt);
        });
    }

    function loadTypes() {
        fetch('type-prestations-list-api', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = text ? JSON.parse(text) : [];
                    const list = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
                    populateTypeOptions(list);
                } catch (e) {
                    console.error('failed loading types', e, text);
                }
            })
            .catch(err => console.error('error fetching types', err));
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

    function createServiceElement(service) {
        const serviceDiv = document.createElement('div');
        serviceDiv.className = 'service-item';

        const header = document.createElement('div');
        header.className = 'service-header';

        const title = document.createElement('h3');
        title.innerHTML = `<i class="fa-solid fa-briefcase"></i>${escapeHtml(service.name)}`;

        const badge = document.createElement('span');
        badge.className = `service-type-badge ${service.typeClass}`;
        badge.innerHTML = `<i class="fa-solid ${service.typeIcon}"></i>${service.typeLabel}`;

        header.appendChild(title);
        header.appendChild(badge);

        serviceDiv.appendChild(header);

        if (service.service_date) {
            const date = document.createElement('p');
            date.className = 'service-date';
            date.innerHTML = `<i class="fa-regular fa-calendar"></i>${escapeHtml(service.service_date)}`;
            serviceDiv.appendChild(date);
        }

        if (service.creatorName) {
            const creator = document.createElement('p');
            creator.className = 'service-creator';
            creator.innerHTML = `<i class="fa-solid fa-user"></i>By ${escapeHtml(service.creatorName)}`;
            serviceDiv.appendChild(creator);
        }

        if (service.maximumParticipants !== null && service.maximumParticipants !== undefined) {
            const maxParticipants = Number(service.maximumParticipants);
            const currentParticipants = Number(service.currentParticipants || 0);
            const spotsLeft = Math.max(0, maxParticipants - currentParticipants);
            const spots = document.createElement('p');
            spots.className = 'service-date';
            spots.innerHTML = `<i class="fa-solid fa-users"></i>${spotsLeft} spot${spotsLeft === 1 ? '' : 's'} left`;
            serviceDiv.appendChild(spots);
        }

        const road = service.service_road || '';
        const city = service.service_city || '';
        const zip  = service.service_zip  || '';
        const location = document.createElement('p');
        location.className = 'service-location';
        if (road || city) {
            const parts = [road, [zip, city].filter(Boolean).join(' ')].filter(Boolean);
            location.innerHTML = `<i class="fa-solid fa-location-dot"></i>${escapeHtml(parts.join(', '))}`;
        } else {
            location.classList.add('online');
            location.innerHTML = `<i class="fa-solid fa-wifi"></i>Online`;
        }
        serviceDiv.appendChild(location);

        const price = document.createElement('p');
        price.className = `service-price ${service.priceClass}`;
        if (service.priceValue === 0) {
            price.innerHTML = `<i class="fa-solid fa-tag"></i>${escapeHtml(service.price)}`;
        } else {
            price.textContent = service.price;
        }
        serviceDiv.appendChild(price);

        const buttonsContainer = document.createElement('div');
        buttonsContainer.className = 'service-buttons';

        const purchaseButton = document.createElement('button');
        purchaseButton.className = 'btn-primary';

        if (service.booked) {
            purchaseButton.textContent = 'Booked';
            purchaseButton.disabled = true;
            purchaseButton.classList.add('btn-disabled');
        } else {
            purchaseButton.textContent = service.priceValue > 0 ? 'Purchase' : 'Get';
            purchaseButton.onclick = function() {
                window.location.href = `order?product_uuid=${service.id}`;
            };
        }

        const detailsButton = document.createElement('button');
        detailsButton.className = 'btn-secondary';
        detailsButton.textContent = 'See details';
        detailsButton.onclick = function() {
            window.location.href = `service?uuid=${service.id}`;
        };

        buttonsContainer.appendChild(purchaseButton);
        buttonsContainer.appendChild(detailsButton);
        serviceDiv.appendChild(buttonsContainer);

        return serviceDiv;
    }

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

    function renderSkeletons(container, count) {
        const card =
            '<div class="skeleton-service-item">' +
                '<div class="skeleton-service-header">' +
                    '<div class="skeleton skeleton-title"></div>' +
                    '<div class="skeleton skeleton-badge"></div>' +
                '</div>' +
                '<div class="skeleton skeleton-description"></div>' +
                '<div class="skeleton skeleton-description"></div>' +
                '<div class="skeleton skeleton-date"></div>' +
                '<div class="skeleton skeleton-creator"></div>' +
                '<div class="skeleton skeleton-location"></div>' +
                '<div class="skeleton skeleton-price"></div>' +
                '<div class="skeleton-buttons">' +
                    '<div class="skeleton skeleton-button"></div>' +
                    '<div class="skeleton skeleton-button"></div>' +
                '</div>' +
            '</div>';
        container.innerHTML = Array(count).fill(card).join('');
    }

    function renderPaginationSkeletons(pagination) {
        if (!pagination) return;
        pagination.innerHTML =
            '<div class="skeleton-pagination">' +
                '<div class="skeleton"></div>' +
                '<div class="skeleton"></div>' +
                '<div class="skeleton"></div>' +
                '<div class="skeleton"></div>' +
                '<div class="skeleton"></div>' +
            '</div>';
    }
})();
