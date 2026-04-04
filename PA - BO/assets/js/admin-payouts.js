(function () {
    'use strict';

    const container = document.getElementById('payouts-container');
    const refreshBtn = document.getElementById('payouts-refresh-btn');
    const statusMsg = document.getElementById('payouts-status-msg');
    const searchInput = document.getElementById('payouts-search');
    const statusFilter = document.getElementById('payouts-status-filter');
    const detailOverlay = document.getElementById('payout-detail-overlay');
    const detailClose = document.getElementById('payout-detail-close');
    const detailBody = document.getElementById('payout-detail-body');
    const detailActions = document.getElementById('payout-detail-actions');
    const detailTitle = document.getElementById('payout-detail-title');

    let payoutRequests = [];

    document.addEventListener('DOMContentLoaded', () => {
        bindEvents();
        loadPayoutRequests();
    });

    function bindEvents() {
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadPayoutRequests(true));
        }

        if (detailClose) {
            detailClose.addEventListener('click', hideDetailModal);
        }

        if (detailOverlay) {
            detailOverlay.addEventListener('click', event => {
                if (event.target === detailOverlay) {
                    hideDetailModal();
                }
            });
        }

        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                showSkeleton();
                timer = setTimeout(() => applyFilters(), 220);
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                showSkeleton();
                applyFilters();
            });
        }
    }

    function loadPayoutRequests(showReloading = false) {
        if (!container) return;

        if (showReloading) {
            showStatus('Refreshing payout requests...', '#0b7285');
        }

        showSkeleton();

        fetch('payouts-api.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(text => {
                let data = [];
                try {
                    data = text ? JSON.parse(text) : [];
                } catch (err) {
                    container.innerHTML = '<p class="error-message">Unable to parse payout requests response.</p>';
                    showStatus('Failed to load payout requests', '#b00020');
                    return;
                }

                if (!Array.isArray(data) || data.length === 0) {
                    container.innerHTML = '<p class="empty-list">No payout requests found.</p>';
                    payoutRequests = [];
                    clearStatus();
                    return;
                }

                payoutRequests = data;
                applyFilters();
                clearStatus();
            })
            .catch(err => {
                console.error('Failed to load payouts', err);
                container.innerHTML = '<p class="error-message">Unable to load payout requests.</p>';
                showStatus('Unable to load payout requests', '#b00020');
            });
    }

    function renderPayoutItems(items) {
        container.innerHTML = '';

        items.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));

        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'service-item';
            card.style.marginBottom = '12px';

            const statusLabel = mapStatusLabel(item.status);
            const statusClass = mapStatusClass(item.status);
            const amount = Number(item.amount || 0).toFixed(2);

            card.innerHTML = `
                <div class="service-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div>
                        <h3 style="margin:0;font-size:1.05rem;">Payout request</h3>
                        <p style="margin:4px 0 0;font-size:.92rem;color:#374151;">Request ID: ${escapeHtml(item.id || '')}</p>
                    </div>
                    <span class="deposit-status ${statusClass}">${escapeHtml(statusLabel)}</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-top:10px;align-items:center;">
                    <div style="font-size:.95rem;color:#111827;">Amount: <strong>€ ${escapeHtml(amount)}</strong></div>
                    <div style="font-size:.86rem;color:#6b7280;">Created: ${escapeHtml(formatDateTime(item.created_at || ''))}</div>
                </div>
                <div class="service-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                    <button class="btn-secondary" data-action="details" data-id="${escapeHtml(item.id || '')}">View details</button>
                    ${item.status === 0 ? `<button class="btn-primary" data-action="approve" data-id="${escapeHtml(item.id || '')}">Approve</button><button class="btn-danger" data-action="reject" data-id="${escapeHtml(item.id || '')}">Reject</button>` : ''}
                </div>
            `;

            container.appendChild(card);
        });

        container.querySelectorAll('button[data-action]').forEach(button => {
            button.addEventListener('click', () => {
                const action = button.getAttribute('data-action');
                const requestId = button.getAttribute('data-id');
                if (action === 'details') {
                    showPayoutDetails(requestId);
                } else if (action === 'approve') {
                    updatePayoutStatus(requestId, 1);
                } else if (action === 'reject') {
                    updatePayoutStatus(requestId, 2);
                }
            });
        });
    }

    function getFilteredPayouts() {
        if (!Array.isArray(payoutRequests)) return [];

        const query = (searchInput?.value || '').trim().toLowerCase();
        const statusValue = statusFilter?.value;

        return payoutRequests.filter(item => {
            const matchesStatus = statusValue === '' || String(item.status || '').trim() === statusValue;
            const searchable = [item.id, item.user_id, item.banking_details_id, item.amount, item.created_at].join(' ').toLowerCase();
            const matchesQuery = query === '' || searchable.includes(query);
            return matchesStatus && matchesQuery;
        });
    }

    function applyFilters() {
        const filtered = getFilteredPayouts();
        if (filtered.length === 0) {
            container.innerHTML = '<p class="empty-list">No payout requests match the current filters.</p>';
            return;
        }
        renderPayoutItems(filtered);
    }

    function showPayoutDetails(requestId) {
        const item = payoutRequests.find(r => r.id === requestId);
        if (!item) {
            statusMsg.textContent = 'Request not found.';
            statusMsg.style.color = '#b00020';
            return;
        }

        detailTitle.textContent = `Payout request ${item.id}`;
        detailBody.innerHTML = `
            <p><strong>Request ID:</strong> ${escapeHtml(item.id || '')}</p>
            <p><strong>User ID:</strong> ${escapeHtml(item.user_id || '')}</p>
            <p><strong>Amount:</strong> € ${escapeHtml(Number(item.amount || 0).toFixed(2))}</p>
            <p><strong>Status:</strong> <span class="deposit-status ${mapStatusClass(item.status)}">${escapeHtml(mapStatusLabel(item.status))}</span></p>
            <p><strong>Requested at:</strong> ${escapeHtml(formatDateTime(item.created_at || ''))}</p>
            <p><strong>Updated at:</strong> ${escapeHtml(formatDateTime(item.updated_at || ''))}</p>
            <div id="payout-detail-user"></div>
            <div id="payout-detail-banking" style="margin-top:16px;"></div>
        `;

        detailActions.innerHTML = '';
        if (item.status === 0) {
            detailActions.innerHTML = `
                <button class="btn-primary" id="payout-detail-approve">Approve</button>
                <button class="btn-danger" id="payout-detail-reject">Reject</button>
            `;
        }

        if (item.status === 0) {
            document.getElementById('payout-detail-approve').addEventListener('click', () => updatePayoutStatus(item.id, 1));
            document.getElementById('payout-detail-reject').addEventListener('click', () => updatePayoutStatus(item.id, 2));
        }

        fetchUserDetails(item.user_id);
        fetchBankingDetails(item.banking_details_id);
        showDetailModal();
    }

    function fetchUserDetails(userId) {
        const target = document.getElementById('payout-detail-user');
        if (!target) return;
        target.innerHTML = '<p><strong>User:</strong> Loading…</p>';

        fetch(`user-get-api.php?id=${encodeURIComponent(userId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    target.innerHTML = '<p class="error-message">Unable to load user details.</p>';
                    return;
                }
                target.innerHTML = `<p><strong>User:</strong> ${escapeHtml(data.first_name || '')} ${escapeHtml(data.last_name || '')} (${escapeHtml(data.username || data.email || data.id || '')})</p>`;
            })
            .catch(() => {
                target.innerHTML = '<p class="error-message">Unable to load user details.</p>';
            });
    }

    function fetchBankingDetails(bankingDetailsId) {
        const target = document.getElementById('payout-detail-banking');
        if (!target) return;
        target.innerHTML = '<p><strong>Banking details:</strong> Loading…</p>';

        fetch(`banking-details-api.php?id=${encodeURIComponent(bankingDetailsId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    target.innerHTML = '<p class="error-message">Unable to load banking details.</p>';
                    return;
                }

                const ibanValue = data.iban ? String(data.iban).trim() : '';
                const ribValue = data.rib ? String(data.rib).trim() : '';
                const bicValue = data.bic ? String(data.bic).trim() : '';
                const accountHolder = data.holder_name || data.account_holder_name || '';

                const rows = [];
                if (accountHolder) {
                    rows.push(`<p style="margin:0 0 8px;"><strong>Account holder:</strong> ${escapeHtml(accountHolder)}</p>`);
                }
                if (ibanValue) {
                    rows.push(`<p style="margin:0 0 8px;"><strong>IBAN:</strong> ${escapeHtml(ibanValue)}</p>`);
                }
                if (!ibanValue && ribValue) {
                    rows.push(`<p style="margin:0 0 8px;"><strong>RIB:</strong> ${escapeHtml(ribValue)}</p>`);
                }
                if (bicValue) {
                    rows.push(`<p style="margin:0 0 8px;"><strong>BIC:</strong> ${escapeHtml(bicValue)}</p>`);
                }

                if (rows.length === 0) {
                    target.innerHTML = '<p class="error-message">No banking information available.</p>';
                    return;
                }

                const cardHtml = `
                    <div style="border:1px solid #e5e7eb;border-radius:14px;padding:16px;background:#fafafa;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <div><strong>Banking details</strong></div>
                            <button type="button" class="btn-secondary" id="banking-toggle-btn">Show details</button>
                        </div>
                        <div id="banking-details-hidden" style="margin-top:14px;display:none;">
                            ${rows.join('')}
                        </div>
                        <p id="banking-details-mask" style="margin:14px 0 0;color:#6b7280;">Sensitive details are hidden for privacy.</p>
                    </div>
                `;

                target.innerHTML = cardHtml;
                const toggleBtn = document.getElementById('banking-toggle-btn');
                const hiddenSection = document.getElementById('banking-details-hidden');
                const maskText = document.getElementById('banking-details-mask');

                if (toggleBtn && hiddenSection && maskText) {
                    toggleBtn.addEventListener('click', () => {
                        const isHidden = hiddenSection.style.display === 'none';
                        hiddenSection.style.display = isHidden ? 'block' : 'none';
                        maskText.style.display = isHidden ? 'none' : 'block';
                        toggleBtn.textContent = isHidden ? 'Hide details' : 'Show details';
                    });
                }
            })
            .catch(() => {
                target.innerHTML = '<p class="error-message">Unable to load banking details.</p>';
            });
    }

    function updatePayoutStatus(requestId, status) {
        showStatus(status === 1 ? 'Approving payout request...' : 'Rejecting payout request...', '#0b7285');

        fetch(`payouts-update-status-api.php?id=${encodeURIComponent(requestId)}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ status })
        })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showStatus(data.error || 'Action failed', '#b00020');
                    return;
                }
                showStatus('Payout request updated successfully.', '#047857');
                hideDetailModal();
                loadPayoutRequests();
            })
            .catch(err => {
                console.error('Failed to update payout status', err);
                showStatus('Unable to update payout request.', '#b00020');
            });
    }

    function showDetailModal() {
        if (!detailOverlay) return;
        detailOverlay.classList.add('is-visible');
        document.body.classList.add('modal-open');
    }

    function hideDetailModal() {
        if (!detailOverlay) return;
        detailOverlay.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
    }

    function showSkeleton() {
        if (!container) return;
        container.innerHTML = '';
        for (let i = 0; i < 4; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton-service-item';
            sk.style.border = '1px solid #e5e7eb';
            sk.style.borderRadius = '10px';
            sk.style.background = '#fff';
            sk.style.padding = '18px';
            sk.style.marginBottom = '12px';
            sk.innerHTML = `
                <div class="skeleton" style="height:18px;width:40%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:80%;margin-bottom:8px;"></div>
                <div class="skeleton" style="height:14px;width:100%;margin-bottom:8px;"></div>
                <div class="skeleton" style="height:14px;width:65%;"></div>
            `;
            container.appendChild(sk);
        }
    }

    function mapStatusLabel(status) {
        return { 0: 'Pending', 1: 'Approved', 2: 'Rejected' }[status] || 'Unknown';
    }

    function mapStatusClass(status) {
        return { 0: 'deposit-status pending', 1: 'deposit-status accepted', 2: 'deposit-status rejected' }[status] || 'deposit-status';
    }

    function showStatus(message, color = '#000') {
        if (!statusMsg) return;
        statusMsg.textContent = message;
        statusMsg.style.color = color;
    }

    function clearStatus() {
        if (!statusMsg) return;
        statusMsg.textContent = '';
    }

    function formatDateTime(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return escapeHtml(value);
        }
        return date.toLocaleString();
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
