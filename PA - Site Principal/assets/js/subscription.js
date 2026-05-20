(function () {
    async function apiFetch(path, options = {}) {
        const normalizedPath = path.startsWith('/') ? path.slice(1) : path;
        const response = await fetch(normalizedPath, {
            ...options,
            headers: {
                ...(options.headers || {}),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const text = await response.text();
        let data = null;

        if (text) {
            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error(text);
            }
        }

        if (!response.ok) {
            throw new Error((data && data.error) ? data.error : `HTTP ${response.status}`);
        }

        return data;
    }

    function formatPrice(tier) {
        const amount = Number(tier.monthly_price ?? 0);
        const currency = (tier.currency || 'EUR').toUpperCase();
        if (amount <= 0) {
            return '€0 / month';
        }
        const suffix = currency === 'EUR' ? '€' : currency;
        return `${amount.toFixed(2)} ${suffix} / month`;
    }

    function tierFeatures(tier) {
        const items = [];
        if (tier.features) {
            if (Array.isArray(tier.features)) {
                items.push(...tier.features);
            } else if (typeof tier.features === 'string') {
                try {
                    const parsed = JSON.parse(tier.features);
                    if (Array.isArray(parsed)) items.push(...parsed);
                } catch (error) {
                    items.push(tier.features);
                }
            }
        }
        return items;
    }

    function renderTiers(tiers, activeTierId) {
        const container = document.getElementById('tiers-grid');
        if (!container) return;

        if (!Array.isArray(tiers) || tiers.length === 0) {
            container.innerHTML = '<p class="empty-state">No plans available right now.</p>';
            return;
        }

        const sorted = [...tiers].sort((a, b) => Number(a.tier_level ?? 0) - Number(b.tier_level ?? 0));
        const featuredTier = sorted.find(tier => Number(tier.monthly_price ?? 0) > 0) || sorted[sorted.length - 1];

        container.innerHTML = sorted.map(tier => {
            const isActive = activeTierId && String(activeTierId) === String(tier.id);
            const priceValue = Number(tier.monthly_price ?? 0);
            const hasCheckout = priceValue <= 0 ? false : Boolean(tier.stripe_price_id);
            const featureList = tierFeatures(tier)
                .map(feature => `<li><i class="fas fa-check"></i> <span>${feature}</span></li>`)
                .join('');
            const badge = isActive ? 'Current plan' : (featuredTier && String(featuredTier.id) === String(tier.id) ? 'Recommended' : '');
            const buttonLabel = priceValue <= 0
                ? 'Current plan'
                : (!hasCheckout ? 'Configure in admin' : (isActive ? 'Current plan' : 'Choose plan'));

            return `
                <article class="plan ${priceValue > 0 ? 'premium' : 'free'}${isActive ? ' active' : ''}" data-tier-id="${tier.id}">
                    ${badge ? `<div class="popular-badge">${badge}</div>` : ''}
                    <h2>${tier.name}</h2>
                    <p class="price">${formatPrice(tier)}</p>
                    <p class="tier-description">${tier.description || ''}</p>
                    <ul>
                        ${featureList || '<li><i class="fas fa-check"></i> <span>Included access</span></li>'}
                    </ul>
                    <button class="btn ${priceValue > 0 ? 'btn-primary' : 'btn-outline'} btn-lg tier-select-btn" data-tier-id="${tier.id}" ${priceValue <= 0 || isActive || !hasCheckout ? 'disabled' : ''}>
                        <i class="fas fa-crown"></i> <span>${buttonLabel}</span>
                    </button>
                    ${isActive ? '<span class="current-plan">Your current plan</span>' : ''}
                </article>
            `;
        }).join('');

        container.querySelectorAll('.tier-select-btn').forEach(button => {
            button.addEventListener('click', async () => {
                const tierId = button.dataset.tierId;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                try {
                    const res = await apiFetch('/create-subscription-checkout', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tier_id: tierId })
                    });
                    if (res.checkout_url) {
                        window.location.href = res.checkout_url;
                        return;
                    }
                    throw new Error(res.error || 'Unable to start checkout');
                } catch (error) {
                    alert(error.message || 'Network error.');
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-crown"></i> <span>Choose plan</span>';
                }
            });
        });
    }

    function wireButtons() {
        const btnSubscribe = document.getElementById('btn-subscribe');
        const btnManage    = document.getElementById('btn-manage');

        if (btnSubscribe) {
            btnSubscribe.addEventListener('click', async () => {
                btnSubscribe.disabled = true;
                btnSubscribe.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                try {
                    const res  = await fetch('create-subscription-checkout', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({})
                    });
                    const data = await res.json();
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnSubscribe.disabled = false;
                        btnSubscribe.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                    }
                } catch (e) {
                    alert('Network error.');
                    btnSubscribe.disabled = false;
                    btnSubscribe.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                }
            });
        }
        if (btnManage) {
            btnManage.addEventListener('click', async () => {
                btnManage.disabled = true;
                btnManage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                try {
                    const res  = await fetch(btnManage.dataset.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({})
                    });
                    const data = await res.json();
                    if (data.portal_url) {
                        window.location.href = data.portal_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnManage.disabled = false;
                        btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage my subscription';
                    }
                } catch (e) {
                    alert('Network error.');
                    btnManage.disabled = false;
                    btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage my subscription';
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        const loader = document.getElementById('initial-loader');
        if (loader) loader.style.display = 'none';

        try {
            const [statusData, tiersData] = await Promise.all([
                apiFetch('/subscription-api'),
                apiFetch('/subscription-tiers-api')
            ]);

            document.getElementById('sub-loading').classList.add('hidden');

            const currentTierId = statusData.current_tier_id || null;
            renderTiers(tiersData, currentTierId);

            if (statusData.is_premium) {
                document.getElementById('sub-premium').classList.remove('hidden');
            } else {
                document.getElementById('sub-freemium').classList.remove('hidden');
            }

            wireButtons();
        } catch (e) {
            console.error('Subscription page load failed', e);
            document.getElementById('sub-loading').innerHTML =
                '<p class="empty-state">Unable to load subscription status.</p>';
        }
    });
})();
