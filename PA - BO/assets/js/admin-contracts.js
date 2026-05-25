(function () {
    'use strict';

    const contractsContainer = document.getElementById('contracts-container');
    const refreshBtn = document.getElementById('contracts-refresh-btn');
    const searchInput = document.getElementById('contracts-search');
    const typeFilter = document.getElementById('contracts-type-filter');
    const statusFilter = document.getElementById('contracts-status-filter');
    const statusMsg = document.getElementById('contracts-status-msg');
    const detailModal = document.getElementById('contract-detail-modal');
    const detailBody = document.getElementById('contract-detail-body');
    const pdfButton = document.getElementById('contract-detail-pdf-btn');

    let contractsData = [];
    let selectedContractId = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        showPage();
        loadContracts();
    });

    function bindEvents() {
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadContracts(true));
        }

        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', function () {
                clearTimeout(timeout);
                timeout = setTimeout(renderFilteredContracts, 180);
            });
        }

        [typeFilter, statusFilter].forEach(el => {
            if (el) {
                el.addEventListener('change', renderFilteredContracts);
            }
        });

        detailModal?.addEventListener('click', function (event) {
            if (event.target === this) {
                hideModal();
            }
        });

        document.querySelectorAll('.contract-detail-close').forEach(button => {
            button.addEventListener('click', hideModal);
        });

        if (pdfButton) {
            pdfButton.addEventListener('click', function () {
                if (!selectedContractId) return;
                window.open(`contract-pdf.php?contract_id=${encodeURIComponent(selectedContractId)}`, '_blank');
            });
        }
    }

    function showStatus(message, color = '#2563eb') {
        if (!statusMsg) return;
        statusMsg.textContent = message;
        statusMsg.style.color = color;
        statusMsg.style.display = 'block';
    }

    function hideStatus() {
        if (!statusMsg) return;
        statusMsg.style.display = 'none';
    }

    function loadContracts(reload = false) {
        if (!contractsContainer) return;
        showStatus(reload ? 'Refreshing contracts…' : 'Loading contracts…');
        contractsContainer.innerHTML = '';

        fetch('contracts-api.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (!Array.isArray(data)) {
                    contractsContainer.innerHTML = '<p class="error-message">Unable to load contract list.</p>';
                    showStatus('Failed to parse contract list', '#b91c1c');
                    return;
                }
                contractsData = data;
                renderFilteredContracts();
                hideStatus();
            })
            .catch(error => {
                console.error(error);
                contractsContainer.innerHTML = '<p class="error-message">Unable to load contract list.</p>';
                showStatus('Failed to load contracts', '#b91c1c');
            })
            .finally(removeLoader);
    }

    function showPage() {
        const loader = document.getElementById('initial-loader');
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            mainContent.style.visibility = 'visible';
        }
        if (loader) {
            loader.style.display = 'none';
        }
    }

    function removeLoader() {
        const loader = document.getElementById('initial-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    function renderFilteredContracts() {
        if (!contractsContainer) return;
        const query = (searchInput?.value || '').trim().toLowerCase();
        const typeValue = typeFilter?.value;
        const statusValue = statusFilter?.value;

        const filtered = contractsData.filter(item => {
            const matchesType = !typeValue || String(item.contract_type || '').trim() === typeValue;
            const matchesStatus = !statusValue || String(item.status || '').trim() === statusValue;
            const text = [item.contract_ref, item.subscription_id, item.user_first_name, item.user_last_name, item.user_email, item.username, item.currency].join(' ').toLowerCase();
            const matchesQuery = !query || text.includes(query);
            return matchesType && matchesStatus && matchesQuery;
        });

        if (filtered.length === 0) {
            contractsContainer.innerHTML = '<p class="empty-list">No contracts found.</p>';
            return;
        }

        contractsContainer.innerHTML = '';
        filtered.forEach(contract => {
            contractsContainer.appendChild(createContractCard(contract));
        });
    }

    function createContractCard(contract) {
        const card = document.createElement('div');
        card.className = 'service-item';

        const statusClass = contract.status === 1 ? 'deposit-status approved' : 'deposit-status rejected';
        const userLabel = [contract.user_first_name, contract.user_last_name].filter(Boolean).join(' ') || contract.username || contract.user_email || 'Unknown user';

        card.innerHTML = `
            <div class="service-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <h3 style="margin:0;">${escapeHtml(contract.contract_ref || contract.subscription_id || contract.id || 'Contract')}</h3>
                    <p style="margin:4px 0 0;color:#4b5563;">${escapeHtml(userLabel)}</p>
                </div>
                <span class="deposit-status ${statusClass}">${escapeHtml(mapStatusLabel(contract.status))}</span>
            </div>
            <div class="contract-meta">
                <div><strong>Type:</strong> ${escapeHtml(mapContractType(contract.contract_type))}</div>
                <div><strong>Amount:</strong> ${escapeHtml(formatAmount(contract.amount, contract.currency))}</div>
                <div><strong>Period:</strong> ${escapeHtml(contract.start_date || '-') } → ${escapeHtml(contract.end_date || '-')}</div>
                <div><strong>Created:</strong> ${escapeHtml(contract.created_at || '-')}</div>
            </div>
            <div class="service-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                <button class="btn-secondary" data-action="details" data-id="${escapeHtml(contract.id)}">Details</button>
                <button class="btn-primary" data-action="download" data-id="${escapeHtml(contract.id)}"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        `;

        card.querySelectorAll('button[data-action]').forEach(button => {
            button.addEventListener('click', function () {
                const action = this.dataset.action;
                const id = this.dataset.id;
                if (action === 'details') {
                    showContractDetails(id);
                } else if (action === 'download') {
                    window.open(`contract-pdf.php?contract_id=${encodeURIComponent(id)}`, '_blank');
                }
            });
        });

        return card;
    }

    function showContractDetails(contractId) {
        const contract = contractsData.find(item => item.id === contractId);
        if (!contract) {
            return;
        }
        selectedContractId = contractId;

        const userLabel = [contract.user_first_name, contract.user_last_name].filter(Boolean).join(' ') || contract.username || contract.user_email || 'Unknown user';
        const metadataLines = contract.metadata && typeof contract.metadata === 'object' ? Object.entries(contract.metadata).map(([key, value]) => `<div><strong>${escapeHtml(key)}:</strong> ${escapeHtml(value === null ? 'null' : (typeof value === 'object' ? JSON.stringify(value) : String(value)))}</div>`) : [];

        detailBody.innerHTML = `
            <p><strong>Contract ref:</strong> ${escapeHtml(contract.contract_ref || contract.subscription_id || contract.id)}</p>
            <p><strong>User:</strong> ${escapeHtml(userLabel)}</p>
            <p><strong>Email:</strong> ${escapeHtml(contract.user_email || '-')}</p>
            <p><strong>Type:</strong> ${escapeHtml(mapContractType(contract.contract_type))}</p>
            <p><strong>Status:</strong> ${escapeHtml(mapStatusLabel(contract.status))}</p>
            <p><strong>Amount:</strong> ${escapeHtml(formatAmount(contract.amount, contract.currency))}</p>
            <p><strong>Billing interval:</strong> ${escapeHtml(contract.billing_interval || '-')}</p>
            <p><strong>Start date:</strong> ${escapeHtml(contract.start_date || '-')}</p>
            <p><strong>End date:</strong> ${escapeHtml(contract.end_date || '-')}</p>
            <p><strong>Created at:</strong> ${escapeHtml(contract.created_at || '-')}</p>
            <p><strong>Updated at:</strong> ${escapeHtml(contract.updated_at || '-')}</p>
            ${metadataLines.length ? `<div style="margin-top:12px;"><strong>Metadata</strong>${metadataLines.join('')}</div>` : ''}
        `;

        showModal();
    }

    function showModal() {
        if (!detailModal) return;
        detailModal.setAttribute('aria-hidden', 'false');
        detailModal.style.display = 'flex';
    }

    function hideModal() {
        if (!detailModal) return;
        detailModal.setAttribute('aria-hidden', 'true');
        detailModal.style.display = 'none';
        selectedContractId = null;
    }

    function mapStatusLabel(status) {
        if (status === 1 || String(status) === '1') return 'Active';
        if (status === 0 || String(status) === '0') return 'Inactive';
        return 'Unknown';
    }

    function mapContractType(type) {
        if (type === 2 || String(type) === '2') return 'Promotion';
        if (type === 1 || String(type) === '1') return 'Subscription';
        return 'Contract';
    }

    function formatAmount(amount, currency) {
        if (amount === undefined || amount === null || amount === 0) {
            return '-';
        }
        return `${Number(amount).toFixed(2)} ${currency || ''}`.trim();
    }

    function escapeHtml(text) {
        if (text === undefined || text === null) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
