(function() {
    'use strict';

    let pageSize = 4;
    let currentPage = 1;
    let totalPages = 1;
    let searchTerm = '';
    let categoryFilter = '';
    let conditionFilter = '';
    let minPrice = '';
    let maxPrice = '';
    let sortOrder = '';

    document.addEventListener('DOMContentLoaded', function() {
        bindToolbar();
        loadCategories();
        currentPage = getPageFromUrl();
        readFiltersFromUrl();
        requestPage(currentPage, true);
    });

    function requestPage(page, replaceHistory) {
        const container = document.getElementById('offers-container');
        const pagination = document.getElementById('offers-pagination');

        if (!container) {
            console.error('Offers container not found');
            return;
        }

        renderSkeletons(container, pageSize);

        const url = `offers-api?${buildQueryParams(page)}`;
        fetch(url, {
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
                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error('Failed to parse offers API response as JSON', err, text);
                    throw err;
                }
                const offers = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
                const total = Number.isFinite(data.total) ? data.total : offers.length;
                totalPages = total > 0 ? Math.ceil(total / pageSize) : 1;

                if (!offers || offers.length === 0) {
                    container.innerHTML = '<p class="offers-empty">No offers available at the moment.</p>';
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

                renderOffers(offers, container);
                renderPagination(pagination);
            })
            .catch(error => {
                console.error('Error loading offers:', error);
                container.innerHTML = '<p class="error-message">An error occurred while loading offers. Please try again later.</p>';
                if (pagination) {
                    pagination.innerHTML = '';
                }
            });
    }

    function buildQueryParams(page) {
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('limit', String(pageSize));
        if (searchTerm) params.set('search', searchTerm);
        if (categoryFilter) params.set('category', categoryFilter);
        if (conditionFilter) params.set('condition', conditionFilter);
        if (minPrice) params.set('price_min', minPrice);
        if (maxPrice) params.set('price_max', maxPrice);
        if (sortOrder) params.set('sort', sortOrder);
        return params.toString();
    }

    function readFiltersFromUrl() {
        const params = new URLSearchParams(window.location.search);
        searchTerm = params.get('search') || '';
        categoryFilter = params.get('category') || '';
        conditionFilter = params.get('condition') || '';
        minPrice = params.get('price_min') || '';
        maxPrice = params.get('price_max') || '';
        sortOrder = params.get('sort') || '';

        const limitParam = parseInt(params.get('limit'), 10);
        if (!Number.isNaN(limitParam) && limitParam > 0) {
            pageSize = Math.min(limitParam, 50);
        }

        const searchInput = document.getElementById('offers-search');
        if (searchInput) searchInput.value = searchTerm;

        const categorySelect = document.getElementById('offers-category-filter');
        if (categorySelect) categorySelect.value = categoryFilter;

        const conditionSelect = document.getElementById('offers-condition-filter');
        if (conditionSelect) conditionSelect.value = conditionFilter;

        const minPriceInput = document.getElementById('offers-price-min');
        if (minPriceInput) minPriceInput.value = minPrice;

        const maxPriceInput = document.getElementById('offers-price-max');
        if (maxPriceInput) maxPriceInput.value = maxPrice;

        const sortSelect = document.getElementById('offers-sort');
        if (sortSelect) sortSelect.value = sortOrder;

        const pageSizeSelect = document.getElementById('offers-page-size');
        if (pageSizeSelect) pageSizeSelect.value = String(pageSize);
    }

    function resetFilters() {
        searchTerm = '';
        categoryFilter = '';
        conditionFilter = '';
        minPrice = '';
        maxPrice = '';
        sortOrder = '';

        const searchInput = document.getElementById('offers-search');
        if (searchInput) searchInput.value = '';

        const categorySelect = document.getElementById('offers-category-filter');
        if (categorySelect) categorySelect.value = '';

        const conditionSelect = document.getElementById('offers-condition-filter');
        if (conditionSelect) conditionSelect.value = '';

        const minPriceInput = document.getElementById('offers-price-min');
        if (minPriceInput) minPriceInput.value = '';

        const maxPriceInput = document.getElementById('offers-price-max');
        if (maxPriceInput) maxPriceInput.value = '';

        const sortSelect = document.getElementById('offers-sort');
        if (sortSelect) sortSelect.value = '';

        currentPage = 1;
        requestPage(currentPage, true);
    }

    function updateUrlPage(page, replace) {
        const url = new URL(window.location.href);
        const query = buildQueryParams(page);
        url.search = query;
        if (replace) {
            window.history.replaceState({}, '', url.toString());
        } else {
            window.history.pushState({}, '', url.toString());
        }
    }

    function bindToolbar() {
        const searchInput = document.getElementById('offers-search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                searchTerm = this.value.trim();
                currentPage = 1;
                requestPage(currentPage, true);
            });
        }

        const categorySelect = document.getElementById('offers-category-filter');
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                categoryFilter = this.value;
                currentPage = 1;
                requestPage(currentPage, true);
            });
        }

        const conditionSelect = document.getElementById('offers-condition-filter');
        if (conditionSelect) {
            conditionSelect.addEventListener('change', function() {
                conditionFilter = this.value;
                currentPage = 1;
                requestPage(currentPage, true);
            });
        }

        const minPriceInput = document.getElementById('offers-price-min');
        const maxPriceInput = document.getElementById('offers-price-max');
        if (minPriceInput) {
            minPriceInput.addEventListener('input', function() {
                minPrice = this.value;
                currentPage = 1;
                requestPage(currentPage, true);
            });
        }
        if (maxPriceInput) {
            maxPriceInput.addEventListener('input', function() {
                maxPrice = this.value;
                currentPage = 1;
                requestPage(currentPage, true);
            });
        }

        const sortSelect = document.getElementById('offers-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                sortOrder = this.value;
                currentPage = 1;
                requestPage(currentPage, true);
            });
        }

        const pageSizeSelect = document.getElementById('offers-page-size');
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function() {
                const value = parseInt(this.value, 10);
                if (!Number.isNaN(value) && value > 0) {
                    pageSize = Math.min(value, 50);
                    currentPage = 1;
                    requestPage(currentPage, true);
                }
            });
        }

        const resetBtn = document.getElementById('offers-reset-filters');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                resetFilters();
            });
        }
    }

    function loadCategories() {
        fetch('categories-list-api', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = text ? JSON.parse(text) : [];
                    const list = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
                    populateCategoryOptions(list);
                } catch (e) {
                    console.error('failed loading categories', e, text);
                }
            })
            .catch(err => console.error('error fetching categories', err));
    }

    function populateCategoryOptions(list) {
        const filter = document.getElementById('offers-category-filter');
        const addFormSelect = document.getElementById('offer-category');
        if (filter) {
            const current = filter.value;
            filter.innerHTML = '<option value="">All categories</option>';
            list.forEach(category => {
                const opt = document.createElement('option');
                opt.value = category.id;
                opt.textContent = category.name;
                filter.appendChild(opt);
            });
            filter.value = current;
        }
        if (addFormSelect) {
            const current = addFormSelect.value;
            addFormSelect.innerHTML = '';
            addFormSelect.appendChild(document.createElement('option')).value = '';
            addFormSelect.querySelector('option').textContent = '-- none --';
            list.forEach(category => {
                const opt = document.createElement('option');
                opt.value = category.id;
                opt.textContent = category.name;
                addFormSelect.appendChild(opt);
            });
            addFormSelect.value = current;
        }
    }

    function renderOffers(offers, container) {
        offers.forEach(offer => {
            const offerItem = createOfferElement(offer);
            container.appendChild(offerItem);
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
    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const pageParam = parseInt(params.get('page'), 10);
        if (Number.isNaN(pageParam) || pageParam < 1) {
            return 1;
        }
        return pageParam;
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

    function renderSkeletons(container, count) {
        const skeletons = [];

        for (let i = 0; i < count; i += 1) {
            skeletons.push(
                '<div class="skeleton-service-item">' +
                    '<div class="skeleton skeleton-image"></div>' +
                    '<div class="skeleton-service-header">' +
                        '<div class="skeleton skeleton-title"></div>' +
                    '</div>' +
                    '<div class="skeleton skeleton-description"></div>' +
                    '<div class="skeleton skeleton-description"></div>' +
                    '<div class="skeleton skeleton-price"></div>' +
                    '<div class="skeleton-buttons">' +
                        '<div class="skeleton skeleton-button"></div>' +
                        '<div class="skeleton skeleton-button"></div>' +
                    '</div>' +
                '</div>'
            );
        }

        container.innerHTML = skeletons.join('');
    }

    const ITEM_STATE_LABELS = {
        0: 'New',
        1: 'Like new',
        2: 'Good',
        3: 'Fair',
        4: 'Poor'
    };

    function createOfferElement(offer) {
        const offerDiv = document.createElement('div');
        offerDiv.className = 'service-item offer-item';

        const imageWrapper = document.createElement('div');
        imageWrapper.className = 'offer-image';
        imageWrapper.classList.add('loading');

        const image = document.createElement('img');
        image.src = offer.image || '../../assets/img/defaults/placeholder.png';
        image.alt = offer.title ? offer.title : 'Offer image';
        image.loading = 'lazy';
        image.onload = function() {
            imageWrapper.classList.remove('loading');
        };
        image.onerror = function() {
            image.src = '../../assets/img/defaults/placeholder.png';
            imageWrapper.classList.remove('loading');
        };

        imageWrapper.appendChild(image);
        offerDiv.appendChild(imageWrapper);

        const title = document.createElement('h3');
        title.innerHTML = `<i class="fa-solid fa-box-open"></i>${escapeHtml(offer.title)}`;
        offerDiv.appendChild(title);

        const metaRow = document.createElement('div');
        metaRow.className = 'offer-meta-row';
        let metaAdded = false;

        if (offer.category_name) {
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--category';
            chip.textContent = offer.category_name;
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (typeof offer.item_state !== 'undefined' && offer.item_state !== null) {
            const stateLabel = ITEM_STATE_LABELS[offer.item_state] || ('State ' + offer.item_state);
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--state';
            chip.textContent = stateLabel;
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (offer.user_type === 2) {
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--pro';
            chip.textContent = 'Pro';
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (offer.promoted) {
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--promoted';
            chip.textContent = 'Promoted';
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (metaAdded) {
            offerDiv.appendChild(metaRow);
        }

        if (offer.description) {
            const description = document.createElement('p');
            description.className = 'service-description';
            description.textContent = offer.description;
            offerDiv.appendChild(description);
        }

        const price = document.createElement('p');
        price.className = `service-price ${offer.priceClass || ''}`;
        if (offer.priceValue === 0) {
            price.innerHTML = `<i class="fa-solid fa-tag"></i>${escapeHtml(offer.price)}`;
        } else {
            price.textContent = offer.price;
        }
        offerDiv.appendChild(price);

        const buttonsWrapper = document.createElement('div');
        buttonsWrapper.className = 'service-buttons';

        const actionButton = document.createElement('button');
        actionButton.type = 'button';
        actionButton.className = 'offer-action-btn btn-primary';

        const currentUserId = typeof window !== 'undefined' ? window.currentUserId : '';
        const isOwner = currentUserId && offer.user_id && offer.user_id === currentUserId;

        if (isOwner) {
            actionButton.textContent = 'Your Offer';
            actionButton.disabled = true;
        } else {
            actionButton.textContent = offer.priceValue === 0 ? 'Get Now' : 'Buy Now';
            const orderLink = offer.id ? `order?product_uuid=${encodeURIComponent(offer.id)}` : '';
            if (orderLink) {
                actionButton.addEventListener('click', function() {
                    window.location.href = orderLink;
                });
            } else {
                actionButton.disabled = true;
            }
        }
        buttonsWrapper.appendChild(actionButton);

        const detailsButton = document.createElement('button');
        detailsButton.type = 'button';
        detailsButton.className = 'offer-details-btn btn-secondary';
        detailsButton.textContent = 'View Details';
        const detailsLink = offer.id ? `offer?uuid=${encodeURIComponent(offer.id)}` : '';
        if (detailsLink) {
            detailsButton.addEventListener('click', function() {
                window.location.href = detailsLink;
            });
        } else {
            detailsButton.disabled = true;
        }
        buttonsWrapper.appendChild(detailsButton);

        const isPro = typeof window !== 'undefined' && window.currentUserType === 2;
        const promotedLocal = isOfferPromotedLocally(offer.id);
        const promoted = !!offer.promoted || promotedLocal;

        if (promoted) {
            offer.promoted = true;
        }

        if (isOwner && isPro) {
            const promoteButton = document.createElement('button');
            promoteButton.type = 'button';
            promoteButton.className = 'offer-promote-btn btn-secondary';
            promoteButton.textContent = promoted ? 'Promoted' : 'Promote';
            promoteButton.disabled = promoted;
            promoteButton.addEventListener('click', function() {
                openPromoteModal(offer);
            });
            buttonsWrapper.appendChild(promoteButton);
        }

        offerDiv.appendChild(buttonsWrapper);

        return offerDiv;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getPromotedOfferIds() {
        try {
            const stored = localStorage.getItem('promotedOffers');
            if (!stored) return [];
            const parsed = JSON.parse(stored);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function isOfferPromotedLocally(offerId) {
        if (!offerId) return false;
        const list = getPromotedOfferIds();
        return list.includes(offerId);
    }

    function markOfferPromotedLocally(offerId) {
        if (!offerId) return;
        const list = getPromotedOfferIds();
        if (!list.includes(offerId)) {
            list.push(offerId);
            localStorage.setItem('promotedOffers', JSON.stringify(list));
        }
    }

    function openPromoteModal(offer) {
        const modal = document.getElementById('promote-modal');
        const offerIdInput = document.getElementById('promote-offer-id');
        const nameInput = document.getElementById('promote-name');
        const budgetInput = document.getElementById('promote-budget');
        const durationInput = document.getElementById('promote-duration');
        const descInput = document.getElementById('promote-description');
        const feedback = document.getElementById('promote-feedback');

        if (!modal || !offerIdInput || !nameInput || !budgetInput || !durationInput || !descInput) {
            return;
        }

        offerIdInput.value = offer.id || '';
        nameInput.value = 'Promote: ' + (offer.title || 'Offer');
        budgetInput.value = 10;
        durationInput.value = 7;
        descInput.value = '';
        if (feedback) feedback.innerHTML = '';

        modal.classList.add('is-open');
    }

    function closePromoteModal() {
        const modal = document.getElementById('promote-modal');
        if (!modal) return;
        modal.classList.remove('is-open');
    }

    function setPromoteFeedback(message, isError) {
        const feedback = document.getElementById('promote-feedback');
        if (!feedback) return;
        feedback.innerHTML = '<div class="' + (isError ? 'error-message' : 'success-message') + '">' + escapeHtml(message) + '</div>';
    }

    function initPromoteModal() {
        const modal = document.getElementById('promote-modal');
        if (!modal) return;

        const closeBtn = document.getElementById('close-promote-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', closePromoteModal);
        }

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closePromoteModal();
            }
        });

        const form = document.getElementById('promote-offer-form');
        if (!form) return;

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const offerId = document.getElementById('promote-offer-id').value;
            const name = document.getElementById('promote-name').value;
            const budget = parseFloat(document.getElementById('promote-budget').value);
            const durationDays = parseInt(document.getElementById('promote-duration').value, 10);
            const description = document.getElementById('promote-description').value;

            if (!offerId) {
                setPromoteFeedback('Unable to determine offer.', true);
                return;
            }

            if (!budget || budget < 10) {
                setPromoteFeedback('Budget must be at least €10 per day.', true);
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalLabel = submitBtn ? submitBtn.innerHTML : null;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
            }

            fetch('promote-offer-api', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    name: name,
                    budget: budget,
                    duration_days: durationDays,
                    description: description
                })
            })
            .then(res => res.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error('Promote offer response is not valid JSON', err, text);
                    throw err;
                }
                if (data && data.success) {
                   
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'promote-order';
                    form.style.display = 'none';

                    var fields = {
                        offer_id: offerId,
                        budget: budget,
                        duration_days: durationDays,
                        name: name,
                        description: description
                    };

                    Object.keys(fields).forEach(function(key) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = fields[key];
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                    return;
                }
                setPromoteFeedback(data.error || 'Unable to promote offer.', true);
            })
            .catch(err => {
                console.error('Error promoting offer', err);
                setPromoteFeedback('Network error. Please try again.', true);
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (originalLabel) submitBtn.innerHTML = originalLabel;
                }
            });
        });
    }

    initPromoteModal();

    window.loadOffers = function() {
        currentPage = 1;
        requestPage(1, true);
    };
})();
