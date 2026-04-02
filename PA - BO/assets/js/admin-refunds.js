(function () {
    'use strict';

    const container = document.getElementById('refunds-container');
    const refreshBtn = document.getElementById('refunds-refresh-btn');
    const statusMsg = document.getElementById('refunds-status-msg');
    const searchInput = document.getElementById('refunds-search');
    const statusFilter = document.getElementById('refunds-status-filter');
    const sortSelect = document.getElementById('refunds-sort');
    const clearFiltersBtn = document.getElementById('refunds-clear-filters');
    const pageInfo = document.getElementById('refunds-page-info');
    const pageDots = document.getElementById('refunds-page-dots');
    const pageSizeSelect = document.getElementById('refunds-page-size');
    const detailModal = document.getElementById('refund-detail-modal');
    const detailClose = document.getElementById('refund-detail-close');
    const detailBody = document.getElementById('refund-detail-body');
    const detailActions = document.getElementById('refund-detail-actions');

    let refundsData = [];
    let currentPage = 1;
    let pageSize = Number(pageSizeSelect?.value || 10);
    let totalPageCount = 1;

    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        loadRefunds();
    });

    function bindEvents() {
        if (refreshBtn) refreshBtn.addEventListener('click', () => loadRefunds(true));

        if (detailClose) detailClose.addEventListener('click', hideDetailModal);
        if (detailModal) {
            detailModal.addEventListener('click', function (e) {
                if (e.target === detailModal) hideDetailModal();
            });
        }

        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                showSkeleton();
                timer = setTimeout(() => { currentPage = 1; applyFilters(); }, 220);
            });
        }

        if (statusFilter) statusFilter.addEventListener('change', () => { currentPage = 1; showSkeleton(); applyFilters(); });
        if (sortSelect) sortSelect.addEventListener('change', () => { currentPage = 1; showSkeleton(); applyFilters(); });
        if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', () => { showSkeleton(); clearFilters(); });
        if (pageSizeSelect) pageSizeSelect.addEventListener('change', function () {
            pageSize = Number(this.value || 10);
            currentPage = 1;
            showSkeleton();
            applyFilters();
        });
    }

    function showSkeleton() {
        if (!container) return;
        container.innerHTML = '';
        for (let i = 0; i < 4; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton-service-item';
            sk.setAttribute('style', 'border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:14px;display:grid;gap:10px;');
            sk.innerHTML = `
                <div class="skeleton" style="height:18px;width:70%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
                <div class="skeleton" style="height:12px;width:90%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
                <div class="skeleton" style="height:12px;width:85%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
                <div class="skeleton" style="height:12px;width:60%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
            `;
            container.appendChild(sk);
        }
    }

    function loadRefunds(showReloading = false) {
        if (!container) return;

        if (showReloading) showStatus('Refreshing refund requests...', '#0b7285');

        showSkeleton();

        fetch('refunds-list-api.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(text => {
                let data = [];
                try {
                    data = text ? JSON.parse(text) : [];
                } catch (err) {
                    container.innerHTML = '<p class="error-message">Unable to parse refund request list.</p>';
                    showStatus('Loading failed', '#b00020');
                    return;
                }

                if (!Array.isArray(data)) {
                    container.innerHTML = '<p class="empty-list">No refund requests available.</p>';
                    refundsData = [];
                    return;
                }

                refundsData = data;
                applyFilters();
                clearStatus();
            })
            .catch(err => {
                console.error('Failed to load refund requests', err);
                container.innerHTML = '<p class="error-message">Unable to load refund requests.</p>';
                showStatus('Unable to load refund requests', '#b00020');
            });
    }

    function mapStatusLabel(status) {
        const labels = { 0: 'Pending', 1: 'Approved', 2: 'Rejected' };
        return labels[status] || 'Unknown';
    }

    function mapStatusClass(status) {
        const classes = { 0: 'refund-status-0', 1: 'refund-status-1', 2: 'refund-status-2' };
        return classes[status] || 'refund-status-0';
    }

    function renderRefundItems(items) {
        container.innerHTML = '';

        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'service-item';
            card.style.marginBottom = '10px';

            const statusLabel = mapStatusLabel(item.status);
            const statusClass = mapStatusClass(item.status);

            card.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <div>
                        <h3 style="margin:0;font-size:1.05rem;">${escapeHtml(item.order_title || item.order_id || 'Unknown order')}</h3>
                        <p style="margin:6px 0 0;font-size:.92rem;color:#374151;">${escapeHtml(item.reason || 'No reason provided')}</p>
                        <p style="margin:4px 0 0;font-size:.83rem;color:#6b7280;">User: ${escapeHtml(item.user_name || item.user_id || 'Unknown user')}</p>
                    </div>
                    <span class="refund-status-tag ${statusClass}">${escapeHtml(statusLabel)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:6px;">
                    <div style="font-size:.86rem;color:#6b7280;">Created ${escapeHtml(formatDateTime(item.created_at || item.updated_at || ''))}</div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button class="btn-secondary" data-action="details" data-id="${escapeHtml(item.id || '')}">Details</button>
                        ${item.status === 0 ? `<button class="btn-primary" data-action="approve" data-id="${escapeHtml(item.id || '')}">Approve</button><button class="btn-danger" data-action="reject" data-id="${escapeHtml(item.id || '')}">Reject</button>` : ''}
                    </div>
                </div>
            `;

            container.appendChild(card);
        });

        document.querySelectorAll('#refunds-container button[data-action]').forEach(btn => {
            btn.addEventListener('click', function () {
                const action = this.getAttribute('data-action');
                const id = this.getAttribute('data-id');
                if (!id) return;

                if (action === 'details') {
                    showDetailModal(id);
                } else if (action === 'approve') {
                    updateRefundStatus(id, 1);
                } else if (action === 'reject') {
                    updateRefundStatus(id, 2);
                }
            });
        });
    }

    function getFilteredRefunds() {
        if (!Array.isArray(refundsData)) return []; // defensive

        const query = (searchInput?.value || '').trim().toLowerCase();
        const statusValue = statusFilter?.value;

        return refundsData.filter(item => {
            const hasStatus = statusValue === '' || String(item.status) === statusValue;
            const searchable = [item.order_title || item.order_id || '', item.user_name || item.user_id || '', item.reason || ''].join(' ').toLowerCase();
            const hasQuery = query === '' || searchable.includes(query);
            return hasStatus && hasQuery;
        }).sort((a, b) => {
            const aDate = new Date(a.created_at || a.updated_at || 0).getTime();
            const bDate = new Date(b.created_at || b.updated_at || 0).getTime();
            if (sortSelect?.value === 'oldest') return aDate - bDate;
            return bDate - aDate;
        });
    }

    function applyFilters() {
        const filtered = getFilteredRefunds();
        const totalItems = filtered.length;
        totalPageCount = Math.max(1, Math.ceil(totalItems / pageSize));
        currentPage = Math.min(Math.max(1, currentPage), totalPageCount);

        if (pageInfo) {
            if (totalPageCount > 1) {
                pageInfo.textContent = `Page ${currentPage}/${totalPageCount} (${totalItems} ${totalItems === 1 ? 'item' : 'items'})`;
            } else {
                pageInfo.textContent = `${totalItems} ${totalItems === 1 ? 'item' : 'items'}`;
            }
        }

        if (pageDots) {
            pageDots.innerHTML = '';
            if (totalPageCount > 1) {
                Array.from({ length: totalPageCount }).forEach((_, idx) => {
                    const page = idx + 1;
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'page-btn' + (page === currentPage ? ' active' : '');
                    btn.textContent = String(page);
                    btn.addEventListener('click', () => {
                        if (currentPage === page) return;
                        currentPage = page;
                        applyFilters();
                    });
                    pageDots.appendChild(btn);
                });
            }
            pageDots.style.display = totalPageCount > 1 ? 'flex' : 'none';
        }

        if (totalItems === 0) {
            container.innerHTML = '<p class="empty-list">No refund requests match the filters.</p>';
            return;
        }

        const start = (currentPage - 1) * pageSize;
        const itemsToRender = filtered.slice(start, start + pageSize);
        renderRefundItems(itemsToRender);
    }

    function showDetailModal(refundId) {
        const item = refundsData.find(r => r.id === refundId);
        if (!item) {
            detailBody.innerHTML = '<p class="error-message">Refund not found.</p>';
            return;
        }

        detailBody.innerHTML = `
            <p><strong>Order:</strong> ${escapeHtml(item.order_title || item.order_id || 'N/A')}</p>
            <p><strong>User:</strong> ${escapeHtml(item.user_name || item.user_id || 'N/A')}</p>
            <p><strong>Status:</strong> <span class="refund-status-tag ${mapStatusClass(item.status)}">${escapeHtml(mapStatusLabel(item.status))}</span></p>
            <p><strong>Approver:</strong> ${escapeHtml(item.approver_name || (item.approver_id || 'N/A'))}</p>
            <p><strong>Reason:</strong> ${escapeHtml(item.reason || 'N/A')}</p>
            <p style="margin-top:12px;"><strong>Admin note:</strong></p>
            <textarea id="refund-admin-comment" maxlength="250" style="width:100%;min-height:80px;margin:6px 0 4px;padding:8px;border:1px solid #d1d5db;border-radius:8px;resize:vertical;">${escapeHtml(item.admin_comment || '')}</textarea>
            <div style="font-size:.84rem;color:#6b7280;margin-bottom:8px;">(<span id="refund-admin-comment-count">${escapeHtml((item.admin_comment || '').length.toString())}</span>/250 chars)</div>
            <p><strong>Created:</strong> ${escapeHtml(formatDateTime(item.created_at || ''))}</p>
            <p><strong>Updated:</strong> ${escapeHtml(formatDateTime(item.updated_at || ''))}</p>
        `;

        const commentArea = document.getElementById('refund-admin-comment');
        const charCount = document.getElementById('refund-admin-comment-count');
        if (commentArea && charCount) {
            commentArea.addEventListener('input', function () {
                const len = Math.min(250, this.value.length);
                charCount.textContent = String(len);
            });
        }

        detailActions.innerHTML = '';

        const saveComment = document.createElement('button');
        saveComment.className = 'btn-secondary';
        saveComment.textContent = 'Save comment';
        saveComment.type = 'button';
        saveComment.addEventListener('click', () => {
            const adminComment = document.getElementById('refund-admin-comment')?.value?.substr(0, 250) || '';
            updateRefundStatus(item.id, item.status, adminComment, { refresh: true, close: false });
        });

        detailActions.appendChild(saveComment);

        if (item.status === 0) {
            const approve = document.createElement('button');
            approve.className = 'btn-primary';
            approve.textContent = 'Approve';
            approve.type = 'button';
            approve.addEventListener('click', () => {
                const adminComment = document.getElementById('refund-admin-comment')?.value?.substr(0, 250) || '';
                updateRefundStatus(item.id, 1, adminComment);
            });

            const reject = document.createElement('button');
            reject.className = 'btn-danger';
            reject.textContent = 'Reject';
            reject.type = 'button';
            reject.addEventListener('click', () => {
                const adminComment = document.getElementById('refund-admin-comment')?.value?.substr(0, 250) || '';
                updateRefundStatus(item.id, 2, adminComment);
            });

            detailActions.appendChild(approve);
            detailActions.appendChild(reject);
        }

        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn-secondary';
        closeBtn.textContent = 'Close';
        closeBtn.type = 'button';
        closeBtn.addEventListener('click', hideDetailModal);

        detailActions.appendChild(closeBtn);

        showModal('refund-detail-modal');
    }

    function hideDetailModal() {
        if (detailModal) {
            detailModal.classList.remove('is-open');
            detailModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            setTimeout(() => {
                detailModal.style.display = 'none';
            }, 220);
        }
    }

    function showModal(modalId) {
        const modalEl = document.getElementById(modalId);
        if (!modalEl) return;
        modalEl.style.display = '';
        modalEl.classList.add('is-open');
        modalEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function updateRefundStatus(refundId, status, adminComment = '', options = { refresh: true, close: true }) {
        if (!refundId) return;

        const payload = JSON.stringify({ status: status, admin_comment: adminComment });
        fetch('refunds-update-api.php?id=' + encodeURIComponent(refundId), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: payload
        })
            .then(res => res.text())
            .then(text => {
                let data;
                try {
                    data = text ? JSON.parse(text) : null;
                } catch (err) {
                    showStatus('Unable to parse update response', '#b00020');
                    return;
                }

                if (data && data.error) {
                    showStatus(data.error, '#b00020');
                    return;
                }

                showStatus('Refund status updated', '#10b981');
                if (options.refresh) {
                    loadRefunds();
                }
                if (options.close) {
                    hideDetailModal();
                }
            })
            .catch(err => {
                console.error('Failed to update refund status', err);
                showStatus('Failed to update status', '#b00020');
            });
    }

    function showStatus(text, color) {
        if (!statusMsg) return;
        statusMsg.textContent = text;
        statusMsg.style.color = color;
        statusMsg.style.display = 'inline-block';
    }

    function clearStatus() {
        if (!statusMsg) return;
        statusMsg.style.display = 'none';
        statusMsg.textContent = '';
    }

    function clearFilters() {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (sortSelect) sortSelect.value = 'newest';
        currentPage = 1;
        applyFilters();
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (isNaN(d.getTime())) return value;
        return d.toLocaleString();
    }

})();
