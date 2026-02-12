(function() {
    'use strict';

    const pageSize = 4;
    let allOffers = [];
    let currentPage = 1;

    document.addEventListener('DOMContentLoaded', function() {
        loadOffers();
    });

    function loadOffers() {
        const container = document.getElementById('offers-container');
        const pagination = document.getElementById('offers-pagination');

        if (!container) {
            console.error('Offers container not found');
            return;
        }

        fetch('offers-api', {
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
                const offers = JSON.parse(text);

                container.innerHTML = '';
                if (pagination) {
                    pagination.innerHTML = '';
                }

                if (!offers || offers.length === 0) {
                    container.innerHTML = '<p>No offers available at the moment.</p>';
                    return;
                }

                allOffers = offers;
                currentPage = getPageFromUrl();
                clampCurrentPage();
                updateUrlPage(currentPage, true);
                renderPage(container, pagination);
            })
            .catch(error => {
                console.error('Error loading offers:', error);
                container.innerHTML = '<p class="error-message">An error occurred while loading offers. Please try again later.</p>';
                if (pagination) {
                    pagination.innerHTML = '';
                }
            });
    }

    function renderPage(container, pagination) {
        renderSkeletons(container, pageSize);

        const start = (currentPage - 1) * pageSize;
        const pageOffers = allOffers.slice(start, start + pageSize);

        window.setTimeout(function() {
            container.innerHTML = '';

            if (pageOffers.length === 0) {
                container.innerHTML = '<p>No offers available at the moment.</p>';
                if (pagination) {
                    pagination.innerHTML = '';
                }
                return;
            }

            pageOffers.forEach(offer => {
                const offerItem = createOfferElement(offer);
                container.appendChild(offerItem);
            });

            renderPagination(pagination);
        }, 180);
    }

    function renderPagination(pagination) {
        if (!pagination) {
            return;
        }

        const totalPages = Math.ceil(allOffers.length / pageSize);
        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        const prevButton = createPageButton('Prev', currentPage === 1, function() {
            if (currentPage > 1) {
                currentPage -= 1;
                updateUrlPage(currentPage);
                renderPage(document.getElementById('offers-container'), pagination);
            }
        });
        pagination.appendChild(prevButton);

        for (let i = 1; i <= totalPages; i += 1) {
            const pageButton = createPageButton(String(i), false, function() {
                currentPage = i;
                updateUrlPage(currentPage);
                renderPage(document.getElementById('offers-container'), pagination);
            });
            if (i === currentPage) {
                pageButton.classList.add('active');
                pageButton.setAttribute('aria-current', 'page');
            }
            pagination.appendChild(pageButton);
        }

        const nextButton = createPageButton('Next', currentPage === totalPages, function() {
            if (currentPage < totalPages) {
                currentPage += 1;
                updateUrlPage(currentPage);
                renderPage(document.getElementById('offers-container'), pagination);
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

    function clampCurrentPage() {
        const totalPages = Math.max(1, Math.ceil(allOffers.length / pageSize));
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
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

        return offerDiv;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
