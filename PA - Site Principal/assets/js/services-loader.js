
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        loadServices();
    });

    function loadServices() {
        const container = document.getElementById('services-container');
        
        if (!container) {
            console.error('Services container not found');
            return;
        }

        fetch('services-api', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Error response:', text);
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                // console.log('Response text:', text);
                const services = JSON.parse(text);
                
                container.innerHTML = '';

                if (!services || services.length === 0) {
                    container.innerHTML = '<p>No services available at the moment.</p>';
                    return;
                }

                renderServices(services, container);
            })
            .catch(error => {
                console.error('Error loading services:', error);
                console.error('Error details:', error.message);
                container.innerHTML = '<p class="error-message">An error occurred while loading services. Please try again later.</p>';
            });
    }

    function renderServices(services, container) {
        services.forEach(service => {
            const serviceItem = createServiceElement(service);
            container.appendChild(serviceItem);
        });
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
        purchaseButton.textContent = service.priceValue > 0 ? 'Purchase' : 'Get';
        purchaseButton.onclick = function() {
            window.location.href = `order?product_uuid=${service.id}`;
        };

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
})();
