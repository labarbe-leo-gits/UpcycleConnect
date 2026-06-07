(function () {
    'use strict';

    const TYPE_LABELS = {
        1: 'Customer',
        2: 'Pro'
    };

    let pendingItems = [];
    let filteredItems = [];
    let selectedPendingId = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindControls();
        loadPendingRegistrations();
    });

    function bindControls() {
        document.getElementById('pending-search')?.addEventListener('input', debounce(updateFilter, 180));
        document.getElementById('pending-type-filter')?.addEventListener('change', updateFilter);
        document.getElementById('pending-delete-close')?.addEventListener('click', () => hideModal('pending-delete-modal'));
        document.getElementById('pending-delete-cancel')?.addEventListener('click', () => hideModal('pending-delete-modal'));
        document.getElementById('pending-delete-confirm')?.addEventListener('click', deletePendingRegistration);
        document.getElementById('pending-detail-close')?.addEventListener('click', () => hideModal('pending-detail-modal'));
        document.getElementById('pending-detail-close-btn')?.addEventListener('click', () => hideModal('pending-detail-modal'));
        document.querySelectorAll('.add-modal').forEach((modal) => {
            modal.addEventListener('click', function (event) {
                if (event.target === this) {
                    hideModal(this.id);
                }
            });
        });
    }

    function loadPendingRegistrations() {
        const list = document.getElementById('pending-list');
        renderSkeletons(list, 4);
        list.classList.remove('empty-list');

        fetch('pending-registrations-api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((data) => {
                pendingItems = Array.isArray(data) ? data : [];
                filteredItems = pendingItems.slice();
                updateFilter();
            })
            .catch((error) => {
                console.error('Failed to load pending registrations', error);
                const list = document.getElementById('pending-list');
                list.innerHTML = '<p class="error-message">Unable to load pending registrations.</p>';
            });
    }

    function updateFilter() {
        const search = (document.getElementById('pending-search')?.value || '').trim().toLowerCase();
        const typeValue = document.getElementById('pending-type-filter')?.value || '';

        filteredItems = pendingItems.filter((item) => {
            const matchesType = typeValue === '' || String(item.user_type) === typeValue;
            const text = [item.username, item.email, item.first_name, item.last_name, item.company_name, item.siret].join(' ').toLowerCase();
            const matchesSearch = search === '' || text.includes(search);
            return matchesType && matchesSearch;
        });

        renderList();
    }

    function renderList() {
        const list = document.getElementById('pending-list');
        list.innerHTML = '';

        if (filteredItems.length === 0) {
            list.innerHTML = '<p class="empty-list">No pending registrations found.</p>';
            return;
        }

        filteredItems.forEach((item) => {
            list.appendChild(renderCard(item));
        });
    }

    function renderCard(item) {
        const card = document.createElement('div');
        card.className = 'service-item';

        const userTypeLabel = TYPE_LABELS[item.user_type] || 'Unknown';

        card.innerHTML = `
            <div class="service-header" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-user-check" style="color:#6b7280;font-size:1.15rem;"></i>
                    <div>
                        <h3>${escapeHtml(item.username)}</h3>
                        <p style="margin:4px 0 0;color:#6b7280;font-size:.95rem;">${escapeHtml(userTypeLabel)}</p>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn-secondary" type="button" data-id="${escapeHtml(item.id)}" data-name="${escapeHtml(item.username)}">View</button>
                    <button class="btn-danger" type="button" data-id="${escapeHtml(item.id)}" data-name="${escapeHtml(item.username)}">Delete</button>
                </div>
            </div>
        `;

        const buttons = card.querySelectorAll('button');
        buttons.forEach((button) => {
            const id = button.dataset.id;
            const name = button.dataset.name;
            if (button.classList.contains('btn-danger')) {
                button.addEventListener('click', () => showDeleteModal(id, name));
            } else {
                button.addEventListener('click', () => showDetailsModal(item));
            }
        });

        return card;
    }

    function showDetailsModal(item) {
        const body = document.getElementById('pending-detail-body');
        if (!body) {
            return;
        }

        const details = [
            `<div><strong>Username:</strong> ${escapeHtml(item.username)}</div>`,
            `<div><strong>Email:</strong> ${escapeHtml(item.email)}</div>`,
            `<div><strong>First name:</strong> ${escapeHtml(item.first_name)}</div>`,
            `<div><strong>Last name:</strong> ${escapeHtml(item.last_name)}</div>`,
            `<div><strong>Type:</strong> ${escapeHtml(TYPE_LABELS[item.user_type] || 'Unknown')}</div>`,
            item.company_name ? `<div><strong>Company:</strong> ${escapeHtml(item.company_name)}</div>` : '',
            item.siret ? `<div><strong>SIRET:</strong> ${escapeHtml(item.siret)}</div>` : '',
            
            `<div><strong>Created:</strong> ${escapeHtml(formatDate(item.created_at))}</div>`,
        ].filter(Boolean).join('');

        body.innerHTML = details;
        showModal('pending-detail-modal');
    }

    function showDeleteModal(id, name) {
        selectedPendingId = id;
        document.getElementById('pending-delete-name').textContent = name;
        showModal('pending-delete-modal');
    }

    function deletePendingRegistration() {
        if (!selectedPendingId) {
            return;
        }

        const confirmButton = document.getElementById('pending-delete-confirm');
        confirmButton.disabled = true;
        confirmButton.textContent = 'Deleting…';

        fetch(`pending-registration-delete-api.php?id=${encodeURIComponent(selectedPendingId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then((response) => response.json())
            .then((data) => {
                if (data && data.success) {
                    hideModal('pending-delete-modal');
                    pendingItems = pendingItems.filter((item) => item.id !== selectedPendingId);
                    updateFilter();
                    selectedPendingId = null;
                } else {
                    console.error('Delete failed', data);
                    showError('Unable to delete pending registration.');
                }
            })
            .catch((error) => {
                console.error('Delete pending registration error', error);
                showError('Unable to delete pending registration.');
            })
            .finally(() => {
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
            });
    }

    function renderSkeletons(container, count) {
        const template = `
            <div class="skeleton-service-item">
                <div class="skeleton-service-header">
                    <div class="skeleton skeleton-title" style="width:40%;"></div>
                    <div class="skeleton skeleton-circle" style="width:32px;height:32px;border-radius:50%;"></div>
                </div>
                <div class="skeleton-meta">
                    <div class="skeleton" style="height:18px;width:70%;border-radius:6px;"></div>
                    <div class="skeleton" style="height:18px;width:50%;border-radius:6px;"></div>
                </div>
                <div class="skeleton-buttons">
                    <div class="skeleton skeleton-button"></div>
                    <div class="skeleton skeleton-button"></div>
                </div>
            </div>
        `;
        container.innerHTML = Array(count).fill(template).join('');
    }

    function showError(message) {
        const errorEl = document.getElementById('pending-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-open');
        }
    }

    function hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-open');
        }
    }

    function escapeHtml(value) {
        if (!value && value !== 0) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }
})();
