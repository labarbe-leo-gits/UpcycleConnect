let currentCampaignId = null;

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    // handle YYYY-MM-DD or full ISO timestamps
    const datePart = String(dateStr).split('T')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function setCampaignModalOpen(isOpen) {
    const modal = document.getElementById('campaign-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

async function loadCampaigns() {
    renderCampaignSkeletons(3);
    try {
        const response = await fetch('/api/partnership-campaigns');
        const text = await response.text();
        let campaigns = null;
        try {
            campaigns = JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response for partnership campaigns:', text);
            document.getElementById('campaigns-container').innerHTML = '<p class="no-data">Unable to load partnership campaigns</p>';
            return;
        }

        const list = (campaigns && Array.isArray(campaigns)) ? campaigns : ((campaigns && Array.isArray(campaigns.items)) ? campaigns.items : []);
        renderCampaigns(list);
    } catch (error) {
        console.error('Error loading campaigns:', error);
        document.getElementById('campaigns-container').innerHTML = '<div class="service-item" style="grid-column:1/-1;text-align:center;"><h3 style="justify-content:center;">Unable to load partnership campaigns</h3><p class="service-description">Please try again later.</p></div>';
    }
}

function renderCampaignSkeletons(count = 3) {
    const container = document.getElementById('campaigns-container');
    container.innerHTML = Array.from({ length: count }).map(() => `
        <div class="skeleton-service-item">
            <div class="skeleton skeleton-service-header">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-badge"></div>
            </div>
            <div class="skeleton skeleton-description" style="width:75%;"></div>
            <div class="skeleton skeleton-description" style="width:90%;"></div>
            <div class="skeleton skeleton-meta">
                <div class="skeleton"></div>
                <div class="skeleton"></div>
                <div class="skeleton"></div>
            </div>
            <div class="skeleton skeleton-buttons" style="display:flex;gap:10px;">
                <div class="skeleton skeleton-button" style="flex:1;"></div>
                <div class="skeleton skeleton-button" style="flex:1;"></div>
            </div>
        </div>
    `).join('');
}

function renderCampaigns(campaigns) {
    const container = document.getElementById('campaigns-container');
    if (!campaigns || campaigns.length === 0) {
        container.innerHTML = '<div class="service-item" style="grid-column:1/-1;text-align:center;"><h3 style="justify-content:center;">No partnership campaigns found</h3><p class="service-description">Create the first campaign to start managing partnerships.</p></div>';
        return;
    }

    const statusLabels = {
        0: 'Draft',
        1: 'Active',
        2: 'Paused',
        3: 'Cancelled'
    };

    container.innerHTML = campaigns.map(campaign => `
        <div class="service-item">
            <div class="service-header">
                <h3><i class="fa-solid fa-handshake"></i> ${campaign.partner_name}</h3>
                <span class="badge badge-oauth">${campaign.monthly_price}€/month</span>
                <span class="badge badge-${campaign.status === 1 ? 'oauth' : campaign.status === 0 ? 'warning' : 'secondary'}">${statusLabels[campaign.status] || 'Unknown'}</span>
            </div>
            ${campaign.partner_logo ? `<div style="margin:12px 0;"><img src="${campaign.partner_logo}" style="max-height:50px;max-width:100%;"></div>` : ''}
            <p class="service-description">${campaign.description || 'No description'}</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:12px 0;font-size:0.9em;">
                <p class="service-location"><i class="fa-solid fa-calendar"></i> <strong>Duration:</strong> ${formatDateDisplay(campaign.start_date)} to ${formatDateDisplay(campaign.end_date)}</p>
                <p class="service-location"><i class="fa-solid fa-layer-group"></i> <strong>Items:</strong> ${(campaign.items || []).length}</p>
            </div>
            ${campaign.website_url ? `<p class="service-location"><i class="fa-solid fa-globe"></i> <a href="${campaign.website_url}" target="_blank" style="color:inherit;text-decoration:underline;">${campaign.website_url}</a></p>` : ''}
            <div class="service-buttons">
                <button type="button" class="btn-secondary" onclick="editCampaign('${campaign.id}')">Edit</button>
                <button type="button" class="btn-secondary" onclick="manageCampaignItems('${campaign.id}')">Manage Items</button>
                <button type="button" class="btn-danger" onclick="deleteCampaign('${campaign.id}')">Delete</button>
            </div>
        </div>
    `).join('');
}

function openCreateCampaignModal() {
    currentCampaignId = null;
    document.getElementById('campaign-modal-title').innerText = 'New Partnership Campaign';
    document.getElementById('campaign-form').reset();
    setCampaignModalOpen(true);
}

function closeCampaignModal() {
    setCampaignModalOpen(false);
    currentCampaignId = null;
}

async function editCampaign(campaignId) {
    currentCampaignId = campaignId;
    try {
        const response = await fetch(`/api/partnership-campaign?id=${campaignId}`);
        const text = await response.text();
        let campaign = null;
        try {
            campaign = JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response for partnership campaign:', text);
            alert('Unable to load campaign details');
            return;
        }

        document.getElementById('campaign-modal-title').innerText = 'Edit Partnership Campaign';
        document.getElementById('campaign-partner-name').value = campaign.partner_name;
        document.getElementById('campaign-price').value = campaign.monthly_price;
        document.getElementById('campaign-start-date').value = campaign.start_date;
        document.getElementById('campaign-end-date').value = campaign.end_date;
        document.getElementById('campaign-description').value = campaign.description || '';
        document.getElementById('campaign-logo').value = campaign.partner_logo || '';
        document.getElementById('campaign-website').value = campaign.website_url || '';
        
        setCampaignModalOpen(true);
    } catch (error) {
        console.error('Error loading campaign:', error);
    }
}

async function deleteCampaign(campaignId) {
    if (!confirm('Are you sure you want to delete this campaign?')) return;
    
    try {
        const response = await fetch(`/api/partnership-campaign?id=${campaignId}`, {
            method: 'DELETE'
        });
        if (response.ok) {
            closeCampaignModal();
            loadCampaigns();
        }
    } catch (error) {
        console.error('Error deleting campaign:', error);
    }
}

let currentCampaignIdForItems = null;
let campaignItemsData = [];
let pendingRemoveItemId = null;

function setItemsModalOpen(isOpen) {
    const modal = document.getElementById('items-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

function closeItemsModal() {
    setItemsModalOpen(false);
    currentCampaignIdForItems = null;
    campaignItemsData = [];
}

function openRemoveConfirmation(itemId, itemTitle) {
    pendingRemoveItemId = itemId;
    document.getElementById('remove-confirmation-message').innerText = `Remove "${itemTitle}" from this campaign?`;
    document.getElementById('remove-confirmation-modal').classList.add('is-open');
    document.getElementById('remove-confirmation-modal').setAttribute('aria-hidden', 'false');
}

function closeRemoveConfirmation() {
    pendingRemoveItemId = null;
    const modal = document.getElementById('remove-confirmation-modal');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

async function confirmRemoveItem() {
    if (!pendingRemoveItemId) {
        closeRemoveConfirmation();
        return;
    }
    await removeItemFromCampaign(pendingRemoveItemId);
    closeRemoveConfirmation();
}

async function manageCampaignItems(campaignId) {
    currentCampaignIdForItems = campaignId;
    
    try {
        const response = await fetch(`/api/partnership-campaign?id=${campaignId}`);
        const text = await response.text();
        let campaign = null;
        try {
            campaign = JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response:', text);
            return;
        }

        if (!campaign || campaign.error) {
            alert('Campaign not found');
            return;
        }

        document.getElementById('items-modal-title').innerHTML = `<i class="fa-solid fa-layer-group"></i> Manage Items: ${campaign.partner_name}`;
        campaignItemsData = campaign.items || [];
        
        renderCampaignItems();
        loadAvailableOffers();
        setItemsModalOpen(true);
    } catch (error) {
        console.error('Error loading campaign for items:', error);
        alert('Error loading campaign');
    }
}

function renderCampaignItems() {
    const listEl = document.getElementById('items-list');
    
    if (!campaignItemsData || campaignItemsData.length === 0) {
        listEl.innerHTML = '<p style="color:#999;margin:0;">No items added yet</p>';
        return;
    }

    listEl.innerHTML = campaignItemsData.map((item, index) => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:white;border-radius:6px;border-left:4px solid #3d8b5e;">
            <div style="flex:1;">
                <div style="font-weight:600;color:#333;">${item.position_priority || '#' + (index + 1)}</div>
                <div style="font-size:0.9em;color:#666;">${item.annonce_title || 'Untitled offer'}</div>
            </div>
            <button type="button" class="btn-danger" onclick="openRemoveConfirmation('${item.id}', '${(item.annonce_title || 'Untitled offer').replace(/'/g, "\\'")}')" style="padding:6px 12px;font-size:0.85em;">
                <i class="fa-solid fa-trash"></i> Remove
            </button>
        </div>
    `).join('');
}

let selectedUsersForItems = [];

function loadAvailableOffers() {
    const searchInput = document.getElementById('user-search');
    searchInput.addEventListener('input', searchUsersForItems);
    document.addEventListener('click', (e) => {
        if (e.target !== searchInput && !e.target.closest('#user-search-results')) {
            document.getElementById('user-search-results').style.display = 'none';
        }
    });
}

async function searchUsersForItems(e) {
    const query = e.target.value.trim();
    const resultsDiv = document.getElementById('user-search-results');
    
    if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch('/pages/api/search-users.php?q=' + encodeURIComponent(query));
        const users = await response.json();
        
        if (!Array.isArray(users) || users.length === 0) {
            resultsDiv.innerHTML = '<div style="padding:10px;color:#999;text-align:center;">No users found</div>';
            resultsDiv.style.display = 'block';
            return;
        }
        
        resultsDiv.innerHTML = users.map(user => {
            const isSelected = selectedUsersForItems.some(u => u.id === user.id);
            return `
                <div style="padding:10px;border-bottom:1px solid #eee;cursor:pointer;background:${isSelected ? '#e8f5e9' : 'white'};hover-background:#f5f5f5;" onclick="selectUserForItems(${JSON.stringify(user).replace(/"/g, '&quot;')})">
                    <div style="font-weight:600;color:#333;">${user.name || user.email || 'Unknown'}</div>
                    <div style="font-size:0.8em;color:#999;">${user.email || ''}</div>
                </div>
            `;
        }).join('');
        
        resultsDiv.style.display = 'block';
    } catch (error) {
        console.error('Error searching users:', error);
        resultsDiv.innerHTML = '<div style="padding:10px;color:#999;text-align:center;">Error loading users</div>';
        resultsDiv.style.display = 'block';
    }
}

function selectUserForItems(user) {
    const isSelected = selectedUsersForItems.some(u => u.id === user.id);
    
    if (isSelected) {
        selectedUsersForItems = selectedUsersForItems.filter(u => u.id !== user.id);
    } else {
        selectedUsersForItems.push(user);
    }
    
    renderSelectedUserChips();
    loadOffersForSelectedUsers();
    document.getElementById('user-search').value = '';
    document.getElementById('user-search-results').style.display = 'none';
}

function renderSelectedUserChips() {
    const chipsDiv = document.getElementById('selected-users-chips');
    
    if (selectedUsersForItems.length === 0) {
        chipsDiv.innerHTML = '';
        return;
    }
    
    chipsDiv.innerHTML = selectedUsersForItems.map(user => `
        <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:#3d8b5e;color:white;border-radius:20px;font-size:0.9em;">
            <span>${user.name || user.email}</span>
            <button type="button" onclick="selectUserForItems(${JSON.stringify(user).replace(/"/g, '&quot;')})" style="background:none;border:none;color:white;cursor:pointer;font-size:1.1em;padding:0;margin:0;">×</button>
        </div>
    `).join('');
}

async function loadOffersForSelectedUsers() {
    const container = document.getElementById('items-available');
    
    if (selectedUsersForItems.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:#999;padding:20px;"><i class="fa-solid fa-users"></i> Select users to see their offers</div>';
        return;
    }
    
    container.innerHTML = '<div style="text-align:center;color:#999;padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading offers...</div>';
    
    try {
        const userIds = selectedUsersForItems.map(u => u.id).join(',');
        const response = await fetch('/pages/api/get-user-offers.php?user_ids=' + encodeURIComponent(userIds));
        const text = await response.text();
        let offers = [];
        
        try {
            offers = JSON.parse(text);
        } catch (e) {
            container.innerHTML = '<p style="color:#999;text-align:center;">Unable to load offers</p>';
            return;
        }

        const offersList = (offers && Array.isArray(offers)) ? offers : [];
        const visibleOffers = offersList.filter(offer => !campaignItemsData.some(item => item.annonce_id === offer.id));
        
        if (!visibleOffers || visibleOffers.length === 0) {
            container.innerHTML = '<p style="color:#999;text-align:center;">No offers from selected users</p>';
            return;
        }

        container.innerHTML = visibleOffers.map(offer => {
            return `
                <div style="padding:12px 14px;border:1px solid #ddd;border-radius:12px;background:white;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:#333;font-size:0.97em;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${offer.title || 'Untitled'}</div>
                            <div style="font-size:0.92em;color:#666;">€${parseFloat(offer.price || 0).toFixed(2)}</div>
                        </div>
                        <button type="button" class="btn-primary" onclick="addItemToCampaign('${offer.id}', '${(offer.title || 'Offer').replace(/'/g, "\\'")}', '${currentCampaignIdForItems}')" aria-label="Add offer ${offer.title || 'Untitled'}" style="width:38px;height:38px;border-radius:50%;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading offers:', error);
        container.innerHTML = '<p style="color:#999;text-align:center;">Error loading offers</p>';
    }
}

async function addItemToCampaign(annonceId, annonceTitle, campaignId) {
    try {
        const response = await fetch(`/pages/api/partnership-campaign-item.php?campaign_id=${campaignId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                annonce_id: annonceId,
                position_type: 'featured',
                position_priority: (campaignItemsData.length + 1)
            })
        });

        const text = await response.text();
        let result = null;
        try {
            result = JSON.parse(text);
        } catch (e) {
            result = {};
        }

        if (result.error) {
            alert('Error adding item: ' + result.error);
            return;
        }

        campaignItemsData.push({
            id: result.id || annonceId,
            annonce_id: annonceId,
            annonce_title: annonceTitle,
            position_priority: result.position_priority || (campaignItemsData.length + 1)
        });

        renderCampaignItems();
        loadOffersForSelectedUsers();
    } catch (error) {
        console.error('Error adding item:', error);
        alert('Error adding item to campaign');
    }
}

async function removeItemFromCampaign(itemId) {
    try {
        const response = await fetch(`/pages/api/partnership-campaign-item.php?item_id=${itemId}`, {
            method: 'DELETE'
        });

        const text = await response.text();
        let result = null;
        try {
            result = JSON.parse(text);
        } catch (e) {
            result = {};
        }

        if (result.error) {
            alert('Error removing item: ' + result.error);
            return;
        }

        campaignItemsData = campaignItemsData.filter(item => item.id !== itemId && item.annonce_id !== itemId);
        renderCampaignItems();
        loadOffersForSelectedUsers();
    } catch (error) {
        console.error('Error removing item:', error);
        alert('Error removing item from campaign');
    }
}

document.getElementById('campaign-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const campaignData = {
        partner_name: document.getElementById('campaign-partner-name').value,
        monthly_price: parseFloat(document.getElementById('campaign-price').value),
        currency: 'EUR',
        description: document.getElementById('campaign-description').value,
        partner_logo: document.getElementById('campaign-logo').value || null,
        website_url: document.getElementById('campaign-website').value || null,
        start_date: document.getElementById('campaign-start-date').value,
        end_date: document.getElementById('campaign-end-date').value,
        status: 0
    };

    try {
        const method = currentCampaignId ? 'PUT' : 'POST';
        const url = currentCampaignId ? `/api/partnership-campaign?id=${currentCampaignId}` : '/api/partnership-campaign';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(campaignData)
        });

        if (response.ok) {
            closeCampaignModal();
            loadCampaigns();
        }
    } catch (error) {
        console.error('Error saving campaign:', error);
    }
});

document.getElementById('main-content').style.visibility = 'visible';
document.getElementById('initial-loader').style.display = 'none';
loadCampaigns();