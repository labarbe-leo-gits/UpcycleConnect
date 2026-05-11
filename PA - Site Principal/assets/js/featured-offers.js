(function() {
    'use strict';

    function t(key, defaultText) {
        if (typeof getTranslationValue === 'function' && window.currentTranslations) {
            return getTranslationValue(key, window.currentTranslations, window.currentFallback) || defaultText || '';
        }
        return defaultText || '';
    }

    function resolveOfferImageUrl(image) {
        const placeholder = '../../assets/img/defaults/placeholder.png';
        if (!image || typeof image !== 'string') {
            return placeholder;
        }

        const trimmed = image.trim();
        if (trimmed === '') {
            return placeholder;
        }

        if (/^(https?:)?\/\//i.test(trimmed) || trimmed.startsWith('/')) {
            return trimmed;
        }

        return trimmed;
    }

    function makeOfferCard(offer) {
        const card = document.createElement('article');
        card.className = 'service-item offer-item';

        const imageWrapper = document.createElement('div');
        imageWrapper.className = 'offer-image loading';

        const img = document.createElement('img');
        img.src = resolveOfferImageUrl(offer.image);
        img.alt = offer.title || t('public.index.offer_image_alt', 'Offer image');
        img.loading = 'lazy';
        img.onload = function() {
            imageWrapper.classList.remove('loading');
        };
        img.onerror = function() {
            this.src = '../../assets/img/defaults/placeholder.png';
            imageWrapper.classList.remove('loading');
        };
        imageWrapper.appendChild(img);

        card.appendChild(imageWrapper);

        const title = document.createElement('h3');
        title.innerHTML = `<i class="fa-solid fa-box-open"></i> ${offer.title || 'Untitled'}`;
        card.appendChild(title);

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
            const stateLabel = {
                0: t('public.index.offer_state_new', 'New'),
                1: t('public.index.offer_state_like_new', 'Like new'),
                2: t('public.index.offer_state_good', 'Good'),
                3: t('public.index.offer_state_fair', 'Fair'),
                4: t('public.index.offer_state_poor', 'Poor')
            }[offer.item_state] || t('public.index.offer_state_unknown', 'State ' + offer.item_state);
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--state';
            chip.textContent = stateLabel;
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (offer.user_type === 2) {
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--pro';
            chip.textContent = t('public.index.offer_tag_pro', 'Pro');
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (offer.promoted) {
            const chip = document.createElement('span');
            chip.className = 'offer-chip offer-chip--promoted';
            chip.textContent = t('public.index.offer_tag_promoted', 'Promoted');
            metaRow.appendChild(chip);
            metaAdded = true;
        }

        if (metaAdded) {
            card.appendChild(metaRow);
        }

        const desc = document.createElement('p');
        desc.className = 'service-description';
        desc.textContent = offer.description || '';
        card.appendChild(desc);

        const price = document.createElement('p');
        price.className = 'service-price ' + (offer.priceValue === 0 ? 'free' : '');
        price.textContent = offer.price || 'Free';
        card.appendChild(price);

        const buttons = document.createElement('div');
        buttons.className = 'service-buttons';

        const actionButton = document.createElement('button');
        actionButton.type = 'button';
        actionButton.className = 'offer-action-btn btn-primary';
        actionButton.textContent = offer.priceValue === 0 ? t('public.index.action_get_now', 'Get Now') : t('public.index.action_buy_now', 'Buy Now');

        const orderUrl = offer.id ? '../common/order?product_uuid=' + encodeURIComponent(offer.id) : null;
        if (orderUrl) {
            actionButton.addEventListener('click', function() {
                window.location.href = orderUrl;
            });
        } else {
            actionButton.disabled = true;
        }

        buttons.appendChild(actionButton);

        const detailsBtn = document.createElement('button');
        detailsBtn.type = 'button';
        detailsBtn.className = 'btn-secondary';
        detailsBtn.textContent = t('public.index.view_details', 'View details');
        detailsBtn.addEventListener('click', function() {
            window.location.href = '../common/offer?uuid=' + encodeURIComponent(offer.id);
        });

        buttons.appendChild(detailsBtn);

        const translateBtn = document.createElement('button');
        translateBtn.type = 'button';
        translateBtn.className = 'btn-secondary';
        translateBtn.textContent = t('public.index.translate_offer', 'Translate');
        translateBtn.dataset.state = 'original';
        translateBtn.addEventListener('click', async function() {
            if (!window.currentLocale || window.currentLocale === 'en') {
                return;
            }
            const currentlyTranslated = translateBtn.dataset.state === 'translated';
            if (currentlyTranslated) {
                title.innerHTML = `<i class="fa-solid fa-box-open"></i> ${translateBtn.dataset.originalTitle}`;
                desc.textContent = translateBtn.dataset.originalDescription;
                translateBtn.textContent = t('public.index.translate_offer', 'Translate');
                translateBtn.dataset.state = 'original';
                return;
            }

            if (translateBtn.dataset.translatedTitle && translateBtn.dataset.translatedDescription) {
                title.innerHTML = `<i class="fa-solid fa-box-open"></i> ${translateBtn.dataset.translatedTitle}`;
                desc.textContent = translateBtn.dataset.translatedDescription;
                translateBtn.textContent = t('public.index.show_original', 'Show original');
                translateBtn.dataset.state = 'translated';
                return;
            }

            translateBtn.disabled = true;
            translateBtn.textContent = t('public.index.translating', 'Translating...');
            const targetLang = window.currentLocale || 'en';
            try {
                const response = await fetch('../common/translate-text-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        text: offer.description || '',
                        title: offer.title || '',
                        target: targetLang
                    })
                });
                const data = await response.json();
                if (response.ok && data.translated && data.translated.title && data.translated.description) {
                    translateBtn.dataset.originalTitle = offer.title || '';
                    translateBtn.dataset.originalDescription = offer.description || '';
                    translateBtn.dataset.translatedTitle = data.translated.title;
                    translateBtn.dataset.translatedDescription = data.translated.description;
                    title.innerHTML = `<i class="fa-solid fa-box-open"></i> ${data.translated.title}`;
                    desc.textContent = data.translated.description;
                    translateBtn.textContent = t('public.index.show_original', 'Show original');
                    translateBtn.dataset.state = 'translated';
                } else {
                    const message = data.error || t('public.index.translation_failed', 'Translation failed');
                    alert(message);
                }
            } catch (err) {
                console.error('Offer translation failed', err);
                alert(t('public.index.translation_failed', 'Translation failed'));
            } finally {
                translateBtn.disabled = false;
            }
        });

        buttons.appendChild(translateBtn);

        card.appendChild(buttons);

        return card;
    }

    function renderFeatured(offers, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        if (!Array.isArray(offers) || offers.length === 0) {
            container.innerHTML = '<p>' + t('public.index.no_offers_available', 'No offers available.') + '</p>';
            return;
        }

        container.innerHTML = '';
        offers.forEach(offer => container.appendChild(makeOfferCard(offer)));
    }

    function fetchFeatured() {
        const rowPromoted = document.getElementById('featured-promoted-row');
        const rowRandom = document.getElementById('featured-random-row');

        const loader = '<article class="service-item offer-item skeleton-card">' +
            '<div class="offer-image skeleton"></div>' +
            '<h3 class="skeleton skeleton-text" style="width: 55%;"></h3>' +
            '<p class="service-description skeleton skeleton-text" style="width: 70%; height: 14px;"></p>' +
            '<p class="service-description skeleton skeleton-text" style="width: 45%; height: 14px;"></p>' +
            '<p class="service-price skeleton skeleton-text" style="width: 40%; height: 18px;"></p>' +
            '<div class="service-buttons" style="justify-content: space-between;">' +
                '<span class="skeleton skeleton-button" style="width: 48%; height: 36px;"></span>' +
                '<span class="skeleton skeleton-button" style="width: 48%; height: 36px;"></span>' +
            '</div>' +
        '</article>';

        if (rowPromoted) rowPromoted.innerHTML = loader.repeat(4);
        if (rowRandom) rowRandom.innerHTML = loader.repeat(4);

        fetch('../common/featured-offers-api', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error('Invalid JSON from featured-offers-api:', err, '\n--- RAW ---\n', text);
                throw err;
            }
            if (data.error) {
                if (rowPromoted) rowPromoted.innerHTML = '<p>' + data.error + '</p>';
                if (rowRandom) rowRandom.innerHTML = '<p>' + data.error + '</p>';
                return;
            }
            renderFeatured((data.promoted || []).slice(0, 4), 'featured-promoted-row');
            renderFeatured((data.random || []).slice(0, 4), 'featured-random-row');
        })
        .catch(err => {
            console.error('Failed to load featured offers', err);
            const message = t('public.index.unable_load_featured_offers', 'Unable to load featured offers at this time.');
            if (rowPromoted) rowPromoted.innerHTML = '<p>' + message + '</p>';
            if (rowRandom) rowRandom.innerHTML = '<p>' + message + '</p>';
        });
    }

    document.addEventListener('DOMContentLoaded', fetchFeatured);
})();
