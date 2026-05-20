let currentTierId = null;
let pendingDeleteTier = null;
const apiHeader = document.querySelector('header');
const apiBase = (apiHeader?.dataset.apiBase || '').replace(/\/$/, '');
const apiToken = window.API_TOKEN || '';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeAttribute(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function setTierModalOpen(isOpen) {
    const modal = document.getElementById('tier-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

function setDeleteTierModalOpen(isOpen) {
    const modal = document.getElementById('tier-delete-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

async function apiFetch(path, options = {}) {
    const response = await fetch(`${apiBase}${path}`, {
        ...options,
        headers: {
            ...(options.headers || {}),
            ...(apiToken ? { Authorization: `Bearer ${apiToken}` } : {}),
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

async function loadTiers() {
    try {
        const tiers = await apiFetch('/subscription-tiers');
        renderTiers(tiers);
    } catch (error) {
        console.error('Error loading tiers:', error);
    }
}

function renderTiers(tiers) {
    const container = document.getElementById('tiers-container');
    if (!tiers || tiers.length === 0) {
        container.innerHTML = '<div class="service-item" style="grid-column:1/-1;text-align:center;"><h3 style="justify-content:center;">No subscription tiers found</h3><p class="service-description">Create the first tier to start organizing subscription access.</p></div>';
        return;
    }

    container.innerHTML = tiers.map(tier => `
        <div class="service-item">
            <div class="service-header">
                <h3><i class="fa-solid fa-layer-group"></i> ${escapeHtml(tier.name)}</h3>
                <span class="badge badge-oauth">${escapeHtml(tier.monthly_price)}€/month</span>
            </div>
            <p class="service-description">${escapeHtml(tier.description || 'No description')}</p>
            <p class="service-location"><i class="fa-solid fa-hashtag"></i> Tier level ${escapeHtml(tier.tier_level ?? 'n/a')}</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                ${tier.dashboard_access ? '<span class="service-type-badge type-other"><i class="fa-solid fa-check"></i> Dashboard</span>' : ''}
                ${tier.analytics_access ? '<span class="service-type-badge type-other"><i class="fa-solid fa-check"></i> Analytics</span>' : ''}
                ${tier.material_stats ? '<span class="service-type-badge type-other"><i class="fa-solid fa-check"></i> Material Stats</span>' : ''}
                ${tier.collection_alerts ? '<span class="service-type-badge type-other"><i class="fa-solid fa-check"></i> Alerts</span>' : ''}
            </div>
            <div class="service-buttons">
                <button type="button" class="btn-secondary" onclick="editTier('${tier.id}')">Edit</button>
                ${tier.is_system ? '<span class="badge badge-oauth" style="text-align:center;">Default</span>' : `<button type="button" class="btn-danger" style="justify-content:center;" data-tier-id="${escapeAttribute(tier.id)}" data-tier-name="${escapeAttribute(tier.name)}" onclick="openDeleteTierModal(this.dataset.tierId, this.dataset.tierName)">Delete</button>`}
            </div>
        </div>
    `).join('');
}

function openCreateTierModal() {
    currentTierId = null;
    document.getElementById('tier-modal-title').innerText = 'New Subscription Tier';
    document.getElementById('tier-form').reset();
    document.getElementById('tier-dashboard').checked = true;
    document.getElementById('tier-analytics').checked = true;
    document.getElementById('tier-material-stats').checked = true;
    document.getElementById('tier-collection-alerts').checked = true;
    setTierModalOpen(true);
}

function closeTierModal() {
    setTierModalOpen(false);
    currentTierId = null;
}

async function editTier(tierId) {
    currentTierId = tierId;
    try {
        const tier = await apiFetch(`/subscription-tier?id=${tierId}`);
        
        document.getElementById('tier-modal-title').innerText = 'Edit Subscription Tier';
        document.getElementById('tier-name').value = tier.name;
        document.getElementById('tier-level').value = tier.tier_level;
        document.getElementById('tier-price').value = tier.monthly_price;
        document.getElementById('tier-stripe-price-id').value = tier.stripe_price_id || '';
        document.getElementById('tier-description').value = tier.description || '';
        document.getElementById('tier-features').value = formatFeaturesForEditing(tier.features || []);
        document.getElementById('tier-dashboard').checked = tier.dashboard_access;
        document.getElementById('tier-analytics').checked = tier.analytics_access;
        document.getElementById('tier-material-stats').checked = tier.material_stats;
        document.getElementById('tier-collection-alerts').checked = tier.collection_alerts;
        
        setTierModalOpen(true);
    } catch (error) {
        console.error('Error loading tier:', error);
    }
}

async function deleteTier(tierId) {
    try {
        await apiFetch(`/subscription-tier?id=${tierId}`, {
            method: 'DELETE'
        });
        closeDeleteTierModal();
        loadTiers();
    } catch (error) {
        console.error('Error deleting tier:', error);
        const errorEl = document.getElementById('tier-delete-error');
        if (errorEl) {
            errorEl.textContent = 'Unable to delete this tier.';
            errorEl.style.display = 'block';
        }
    }
}

function openDeleteTierModal(tierId, tierName) {
    pendingDeleteTier = tierId;
    const nameEl = document.getElementById('tier-delete-name');
    const errorEl = document.getElementById('tier-delete-error');
    if (nameEl) {
        nameEl.textContent = tierName;
    }
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
    }
    setDeleteTierModalOpen(true);
}

function closeDeleteTierModal() {
    pendingDeleteTier = null;
    setDeleteTierModalOpen(false);
}

document.getElementById('tier-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const features = parseFeaturesInput(document.getElementById('tier-features').value);

    const tierData = {
        name: document.getElementById('tier-name').value,
        tier_level: parseInt(document.getElementById('tier-level').value),
        monthly_price: parseFloat(document.getElementById('tier-price').value),
        currency: 'EUR',
        stripe_price_id: document.getElementById('tier-stripe-price-id').value,
        description: document.getElementById('tier-description').value,
        features: features,
        dashboard_access: document.getElementById('tier-dashboard').checked,
        analytics_access: document.getElementById('tier-analytics').checked,
        material_stats: document.getElementById('tier-material-stats').checked,
        collection_alerts: document.getElementById('tier-collection-alerts').checked,
    };

    try {
        const method = currentTierId ? 'PUT' : 'POST';
        const url = currentTierId ? `/subscription-tier?id=${currentTierId}` : '/subscription-tier';
        
        await apiFetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tierData)
        });

        closeTierModal();
        loadTiers();
    } catch (error) {
        console.error('Error saving tier:', error);
    }
});

function parseFeaturesInput(value) {
    return String(value || '')
        .split(/\r?\n/)
        .map(line => line.trim())
        .filter(Boolean);
}

function formatFeaturesForEditing(features) {
    if (Array.isArray(features)) {
        return features.map(feature => String(feature).trim()).filter(Boolean).join('\n');
    }

    if (typeof features === 'string') {
        try {
            const parsed = JSON.parse(features);
            if (Array.isArray(parsed)) {
                return parsed.map(feature => String(feature).trim()).filter(Boolean).join('\n');
            }
        } catch (error) {
            return features;
        }

        return features;
    }

    return '';
}

document.getElementById('tier-modal').addEventListener('click', (event) => {
    if (event.target.id === 'tier-modal') {
        closeTierModal();
    }
});

document.getElementById('tier-delete-modal').addEventListener('click', (event) => {
    if (event.target.id === 'tier-delete-modal') {
        closeDeleteTierModal();
    }
});

document.getElementById('tier-delete-confirm').addEventListener('click', async () => {
    if (!pendingDeleteTier) {
        return;
    }
    await deleteTier(pendingDeleteTier);
});

window.openDeleteTierModal = openDeleteTierModal;

window.addEventListener('keydown', (event) => {
    const tierModalOpen = document.getElementById('tier-modal').classList.contains('is-open');
    const deleteModalOpen = document.getElementById('tier-delete-modal').classList.contains('is-open');
    if (event.key === 'Escape' && tierModalOpen) {
        closeTierModal();
    } else if (event.key === 'Escape' && deleteModalOpen) {
        closeDeleteTierModal();
    }
});

document.getElementById('main-content').style.visibility = 'visible';
document.getElementById('initial-loader').style.display = 'none';
loadTiers();