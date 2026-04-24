(function() {
    'use strict';

    const apiBase = window.API_BASE || 'http://localhost:9999';
    const apiToken = window.API_TOKEN || '';
    const currentUserId = window.CURRENT_USER_ID || '';

    const listEl = document.getElementById('pending-list');
    const emptyEl = document.getElementById('pending-empty');
    const showMoreBtn = document.getElementById('pending-show-more');
    const searchInput = document.getElementById('approval-search');
    const refreshBtn = document.getElementById('approval-refresh');
    const actionModal = document.getElementById('approval-action-modal');
    const actionModalTitle = document.getElementById('approval-action-modal-title');
    const actionModalMessage = document.getElementById('approval-action-modal-message');
    const actionModalConfirmBtn = document.getElementById('approval-action-modal-confirm');
    const actionModalCancelBtn = document.getElementById('approval-action-modal-cancel');
    const actionModalCloseBtn = document.getElementById('approval-action-modal-close');

    let currentPage = 1;
    let totalItems = 0;
    const limit = 12;
    let currentSearch = '';
    let pendingActionId = null;
    let pendingActionStatus = null;

    async function init() {
        bindEvents();
        await loadPending();
    }

    function bindEvents() {
        searchInput.addEventListener('input', debounce((event) => {
            currentSearch = event.target.value.trim();
            currentPage = 1;
            loadPending();
        }, 300));

        refreshBtn.addEventListener('click', () => {
            currentPage = 1;
            currentSearch = searchInput.value.trim();
            loadPending();
        });

        actionModalConfirmBtn?.addEventListener('click', () => {
            if (pendingActionId && pendingActionStatus) {
                updateStatus(pendingActionId, pendingActionStatus);
            }
        });

        actionModalCancelBtn?.addEventListener('click', closeActionModal);
        actionModalCloseBtn?.addEventListener('click', closeActionModal);
        actionModal?.addEventListener('click', (event) => {
            if (event.target === actionModal) {
                closeActionModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && actionModal?.classList.contains('is-visible')) {
                closeActionModal();
            }
        });

        showMoreBtn.addEventListener('click', () => {
            if (currentPage * limit < totalItems) {
                loadPending(currentPage + 1);
            }
        });
    }

    function debounce(fn, delay) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    async function loadPending(page = 1) {
        try {
            currentPage = page;
            listEl.innerHTML = '<div class="skeleton-service-item"><div class="skeleton skeleton-header" style="height:20px;width:40%;margin-bottom:12px;"></div><div class="skeleton skeleton-description" style="height:70px;"></div></div>';
            emptyEl.style.display = 'none';

            const params = new URLSearchParams({
                page: String(page),
                limit: String(limit),
            });
            if (currentSearch) {
                params.set('search', currentSearch);
            }

            const response = await fetch(`${apiBase}/formations/pending?${params.toString()}`, {
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load pending validations');
            }

            const data = await response.json();
            totalItems = data.total || 0;
            renderPending(data.items || []);
            updatePagination(data.page, Math.ceil(totalItems / limit));
        } catch (err) {
            listEl.innerHTML = '';
            showToast(err.message || 'Failed to load pending validations', 'error');
        }
    }

    function renderPending(items) {
        listEl.innerHTML = '';

        if (!items || items.length === 0) {
            emptyEl.style.display = 'block';
            showMoreBtn.style.display = 'none';
            return;
        }

        emptyEl.style.display = 'none';

        items.forEach(formation => {
            const card = createPendingCard(formation);
            listEl.appendChild(card);
        });
    }

    function createPendingCard(formation) {
        const card = document.createElement('article');
        card.className = 'formation-card approval-card';

        const creatorName = formation.creator_first_name || formation.creator_username || 'Unknown creator';
        const creatorLabel = `${formation.creator_first_name || ''} ${formation.creator_last_name || ''}`.trim() || formation.creator_username || 'Unknown creator';

        card.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3>${escapeHtml(formation.name || formation.title || 'Untitled formation')}</h3>
                    <small>${escapeHtml(creatorLabel)}</small>
                </div>
                <div>${getStatusBadge(formation.status)}</div>
            </div>
            <p style="margin:1rem 0 0.5rem; color:#4b5563;">${escapeHtml(formation.description || '')}</p>
            <div class="approval-card-meta">
                <div><span><strong>Date:</strong></span><span>${escapeHtml(formatDate(formation.service_date || formation.event_date || 'N/A'))}</span></div>
                <div><span><strong>Meeting:</strong></span><span>${escapeHtml(formatMeetingType(formation.meeting_type || formation.meetingType || 'none'))}</span></div>
                <div><span><strong>Location:</strong></span><span>${escapeHtml(formation.service_city || formation.event_city || 'Online')}</span></div>
                <div><span><strong>Participants:</strong></span><span>${formation.maximum_participants ? formation.maximum_participants : 'Unlimited'}</span></div>
            </div>
            <div class="approval-card-actions">
                <button type="button" class="btn-primary" data-action="approve">Approve</button>
                <button type="button" class="btn-secondary" data-action="reject">Reject</button>
            </div>
        `;

        const approveBtn = card.querySelector('[data-action="approve"]');
        const rejectBtn = card.querySelector('[data-action="reject"]');

        approveBtn.addEventListener('click', () => openActionModal(formation.id, 'published'));
        rejectBtn.addEventListener('click', () => openActionModal(formation.id, 'rejected'));

        return card;
    }

    function formatDate(value) {
        if (!value || value === 'N/A') {
            return 'N/A';
        }
        const parts = value.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function getStatusBadge(status) {
        if (status === 'published') {
            return '<span class="status-badge published">Published</span>';
        }
        if (status === 'rejected') {
            return '<span class="status-badge" style="background:#fee2e2;color:#b91c1c;">Rejected</span>';
        }
        return '<span class="status-badge draft">Draft</span>';
    }

    function formatMeetingType(type) {
        if (!type || type === 'none') {
            return 'None';
        }
        if (type === 'zoom') {
            return 'Zoom';
        }
        return 'Other';
    }

    function openActionModal(id, status) {
        pendingActionId = id;
        pendingActionStatus = status;
        const actionLabel = status === 'published' ? 'Approve' : 'Reject';
        const message = status === 'published'
            ? 'Approve this draft formation and notify the creator that it is now published.'
            : 'Reject this draft formation and notify the creator so they can update it.';

        actionModalTitle.textContent = `${actionLabel} formation`;
        actionModalMessage.textContent = message;
        actionModalConfirmBtn.textContent = actionLabel;
        actionModalConfirmBtn.classList.toggle('btn-primary', status === 'published');
        actionModalConfirmBtn.classList.toggle('btn-secondary', status === 'rejected');
        actionModal?.classList.add('is-visible');
        actionModal?.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeActionModal() {
        pendingActionId = null;
        pendingActionStatus = null;

        actionModal?.classList.remove('is-visible');
        actionModal?.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    async function updateStatus(id, status) {
        const actionLabel = status === 'published' ? 'approved' : 'rejected';
        closeActionModal();
        try {
            const response = await fetch(`${apiBase}/formations/${id}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}`,
                },
                body: JSON.stringify({ status }),
            });

            if (!response.ok) {
                let message = 'Unable to update formation status';
                try {
                    const errorData = await response.json();
                    if (errorData && errorData.error) {
                        message = errorData.error;
                    }
                } catch (_err) {
                    // ignore parse errors
                }
                throw new Error(message);
            }

            showToast(`Formation ${actionLabel} successfully`, 'success');
            await loadPending(currentPage);
        } catch (err) {
            showToast(err.message || 'Failed to update status', 'error');
        }
    }

    function updatePagination(page, totalPages) {
        showMoreBtn.style.display = page < totalPages ? 'inline-flex' : 'none';
    }

    function escapeHtml(value) {
        if (typeof value !== 'string') {
            return value || '';
        }
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.addEventListener('DOMContentLoaded', init);
})();
