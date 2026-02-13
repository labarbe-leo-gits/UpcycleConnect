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
        const container = document.getElementById('offers-container');
        const pagination = document.getElementById('offers-pagination');

        if (!container) {
            console.error('Offers container not found');
            return;
        }

        renderSkeletons(container, pageSize);

        fetch(`offers-api?page=${page}&limit=${pageSize}`, {
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

    function updateUrlPage(page, replace) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(page));
        if (replace) {
            window.history.replaceState({}, '', url.toString());
        } else {
            window.history.pushState({}, '', url.toString());
        }
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

        offerDiv.appendChild(buttonsWrapper);

        return offerDiv;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
