function setCommissionModalOpen(isOpen) {
    const modal = document.getElementById('commission-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

function openCommissionModal() {
    setCommissionModalOpen(true);
}

function closeCommissionModal() {
    setCommissionModalOpen(false);
}

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const datePart = String(dateStr).split('T')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

async function loadSettings() {
    try {
        const response = await fetch('/api/commission-settings');
        const settings = await response.json();
        const current = Array.isArray(settings) ? settings[0] : settings;

        if (!current) {
            return;
        }

        document.getElementById('commission-min').value = current.commission_rate_min ?? 5;
        document.getElementById('commission-max').value = current.commission_rate_max ?? 10;
        document.getElementById('commission-effective-from').value = current.effective_from || new Date().toISOString().slice(0, 10);
        document.getElementById('commission-effective-to').value = current.effective_to || '';

        const settingsDiv = document.getElementById('current-settings-container');
        settingsDiv.innerHTML = `
            <div class="service-item">
                <div class="service-header">
                    <h3><i class="fa-solid fa-sliders"></i> Current Settings</h3>
                    <span class="badge badge-oauth">Active</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:8px 0;">
                    <div>
                        <p class="service-location"><i class="fa-solid fa-percent"></i> <strong>Min Rate:</strong> ${Number(current.commission_rate_min ?? 5).toFixed(2)}%</p>
                        <p class="service-location"><i class="fa-solid fa-percent"></i> <strong>Max Rate:</strong> ${Number(current.commission_rate_max ?? 10).toFixed(2)}%</p>
                    </div>
                    <div>
                        <p class="service-location"><i class="fa-solid fa-calendar"></i> <strong>From:</strong> ${formatDateDisplay(current.effective_from) || '-'}</p>
                        <p class="service-location"><i class="fa-solid fa-calendar"></i> <strong>To:</strong> ${formatDateDisplay(current.effective_to) || 'No end date'}</p>
                    </div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error loading settings:', error);
        document.getElementById('current-settings-container').innerHTML = '<div class="service-item"><p style="color:#6b7280;text-align:center;"><i class="fa-solid fa-exclamation-circle"></i> Unable to load commission settings</p></div>';
    }
}

async function loadTransactions() {
    try {
        const sellerFilter = document.getElementById('seller-filter').value;
        const url = sellerFilter ? `/api/commission-transactions?seller_id=${sellerFilter}` : '/api/commission-transactions';
        const response = await fetch(url);
        const transactions = await response.json();
        renderTransactions(Array.isArray(transactions) ? transactions : []);
    } catch (error) {
        console.error('Error loading transactions:', error);
        document.getElementById('transactions-container').innerHTML = '<p class="no-data">Unable to load commission transactions</p>';
    }
}

function renderTransactions(transactions) {
    const container = document.getElementById('transactions-container');
    if (!transactions || transactions.length === 0) {
        container.innerHTML = '<div class="service-item" style="grid-column:1/-1;text-align:center;"><h3 style="justify-content:center;">No commission transactions found</h3><p class="service-description">Transactions will appear here once orders are processed.</p></div>';
        return;
    }

    container.innerHTML = transactions.map(trans => `
        <div class="service-item">
            <div class="service-header">
                <h3><i class="fa-solid fa-receipt"></i> Order: ${trans.order_id.substring(0, 8)}...</h3>
                <span class="badge badge-${trans.status === 1 ? 'oauth' : 'warning'}">${trans.status === 1 ? 'Paid' : 'Pending'}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:8px 0;">
                <div>
                    <p class="service-location"><i class="fa-solid fa-euro-sign"></i> <strong>Amount:</strong> ${trans.amount_before_commission.toFixed(2)}€</p>
                    <p class="service-location"><i class="fa-solid fa-percent"></i> <strong>Rate:</strong> ${trans.commission_rate.toFixed(2)}%</p>
                </div>
                <div>
                    <p class="service-location"><i class="fa-solid fa-euro-sign"></i> <strong>Commission:</strong> ${trans.commission_amount.toFixed(2)}€</p>
                    <p class="service-location"><i class="fa-solid fa-euro-sign"></i> <strong>After:</strong> ${trans.amount_after_commission.toFixed(2)}€</p>
                </div>
            </div>
            ${trans.notes ? `<p class="service-description" style="margin-top:8px;"><strong>Notes:</strong> ${trans.notes}</p>` : ''}
            <div class="service-buttons">
                <button type="button" class="btn-secondary" onclick="viewTransaction('${trans.id}')">View</button>
            </div>
        </div>
    `).join('');
}

function statusLabel(status) {
    switch (status) {
        case 1:
            return 'Paid';
        case 0:
        default:
            return 'Pending';
    }
}

function setTransactionDetailsModalOpen(isOpen) {
    const modal = document.getElementById('transaction-details-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

function closeTransactionDetailsModal() {
    setTransactionDetailsModalOpen(false);
}

async function viewTransaction(transId) {
    try {
        const response = await fetch(`/api/commission-transaction?id=${transId}`);
        const transaction = await response.json();
        if (!transaction || transaction.error) {
            console.error('Unable to load transaction details:', transaction.error || transaction);
            return;
        }

        const container = document.getElementById('transaction-details-content');
        container.innerHTML = `
            <div style="display:grid;gap:14px;">
                <div><strong>Order ID:</strong> ${transaction.order_id}</div>
                <div><strong>Seller:</strong> ${transaction.seller_username || transaction.seller_id}</div>
                <div><strong>Buyer:</strong> ${transaction.buyer_username || 'Unknown'}</div>
                <div><strong>Amount before commission:</strong> ${transaction.amount_before_commission.toFixed(2)}€</div>
                <div><strong>Commission rate:</strong> ${transaction.commission_rate.toFixed(2)}%</div>
                <div><strong>Commission amount:</strong> ${transaction.commission_amount.toFixed(2)}€</div>
                <div><strong>Amount after commission:</strong> ${transaction.amount_after_commission.toFixed(2)}€</div>
                <div><strong>Created at:</strong> ${formatDateDisplay(transaction.created_at)}</div>
                <div><strong>Updated at:</strong> ${formatDateDisplay(transaction.updated_at)}</div>
            </div>
        `;

        setTransactionDetailsModalOpen(true);
    } catch (error) {
        console.error('Error loading transaction details:', error);
    }
}

document.getElementById('commission-settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const response = await fetch('/api/commission-settings', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                commission_rate_min: parseFloat(document.getElementById('commission-min').value),
                commission_rate_max: parseFloat(document.getElementById('commission-max').value),
                effective_from: document.getElementById('commission-effective-from').value,
                effective_to: document.getElementById('commission-effective-to').value || null
            })
        });

        if (response.ok) {
            alert('Commission settings updated successfully');
            closeCommissionModal();
            loadSettings();
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        alert('Error saving commission settings');
    }
});

document.getElementById('main-content').style.visibility = 'visible';
document.getElementById('initial-loader').style.display = 'none';
loadSettings();
loadTransactions();