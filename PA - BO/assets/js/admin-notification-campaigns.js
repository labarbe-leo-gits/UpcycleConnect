(function () {
    'use strict';

    const initialLimit = 10;
    let currentPage = 1;
    let limit = initialLimit;
    let searchTerm = '';
    let statusFilter = '';
    let targetFilter = '';
    let activeDeleteId = '';
    let activeSendId = '';
    let debounceTimer = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();
        bindModals();
        bindForm();
        fetchCampaigns();
    });

    function bindToolbar() {
        const createBtn = document.getElementById('create-campaign-btn');
        const searchInput = document.getElementById('campaign-search');
        const statusSelect = document.getElementById('campaign-status-filter');
        const targetSelect = document.getElementById('campaign-target-filter');

        if (createBtn) {
            createBtn.addEventListener('click', function () {
                openCreateModal();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                searchTerm = this.value.trim();
                debounceTimer = setTimeout(function () {
                    currentPage = 1;
                    fetchCampaigns();
                }, 250);
            });
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', function () {
                statusFilter = this.value;
                currentPage = 1;
                fetchCampaigns();
            });
        }

        if (targetSelect) {
            targetSelect.addEventListener('change', function () {
                targetFilter = this.value;
                currentPage = 1;
                fetchCampaigns();
            });
        }

        const container = document.getElementById('campaigns-container');
        if (container) {
            container.addEventListener('click', function (e) {
                const row = e.target.closest('.admin-list-item');
                if (!row) {
                    return;
                }
                const id = row.dataset.id;
                const title = row.dataset.title || '';
                const status = parseInt(row.dataset.status || '0', 10);

                if (e.target.closest('.btn-edit')) {
                    openEditModal(id);
                }
                if (e.target.closest('.btn-delete')) {
                    openDeleteModal(id, title);
                }
                if (e.target.closest('.btn-send')) {
                    openSendModal(id, title);
                }
                if (status === 2 && e.target.closest('.btn-send')) {
                    closeModal('campaign-send-modal');
                }
            });
        }
    }

    function bindModals() {
        safeBind('campaign-form-modal-close', function () { closeModal('campaign-form-modal'); });
        safeBind('campaign-form-cancel', function () { closeModal('campaign-form-modal'); });

        safeBind('campaign-delete-close', function () { closeModal('campaign-delete-modal'); });
        safeBind('campaign-delete-cancel', function () { closeModal('campaign-delete-modal'); });
        safeBind('campaign-delete-confirm', confirmDelete);

        safeBind('campaign-send-close', function () { closeModal('campaign-send-modal'); });
        safeBind('campaign-send-cancel', function () { closeModal('campaign-send-modal'); });
        safeBind('campaign-send-confirm', confirmSend);

        safeBind('campaign-form-send', function () {
            const id = document.getElementById('campaign-id').value;
            const title = document.getElementById('campaign-title').value.trim() || 'this campaign';
            if (!id) {
                showFormError('Save the campaign first, then send it.');
                return;
            }
            openSendModal(id, title);
        });

        ['campaign-form-modal', 'campaign-delete-modal', 'campaign-send-modal'].forEach(function (id) {
            const modal = document.getElementById(id);
            if (!modal) {
                return;
            }
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal(id);
                }
            });
        });
    }

    function bindForm() {
        const form = document.getElementById('campaign-form');
        const statusSelect = document.getElementById('campaign-status');
        if (statusSelect) {
            statusSelect.addEventListener('change', refreshScheduleField);
        }
        if (form) {
            form.addEventListener('submit', saveCampaign);
        }
    }

    function openCreateModal() {
        document.getElementById('campaign-form-title').textContent = 'Create Notification Campaign';
        document.getElementById('campaign-id').value = '';
        document.getElementById('campaign-title').value = '';
        document.getElementById('campaign-message').value = '';
        document.getElementById('campaign-target').value = '0';
        document.getElementById('campaign-status').value = '0';
        document.getElementById('campaign-scheduled-at').value = '';
        document.getElementById('campaign-form-send').style.display = 'none';
        hideFormError();
        refreshScheduleField();
        openModal('campaign-form-modal');
    }

    function openEditModal(id) {
        fetch('notifications-campaigns-api.php?action=get&id=' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.campaign) {
                    throw new Error(data.error || 'Unable to load campaign');
                }

                const campaign = data.campaign;
                document.getElementById('campaign-form-title').textContent = 'Edit Notification Campaign';
                document.getElementById('campaign-id').value = campaign.id;
                document.getElementById('campaign-title').value = campaign.title || '';
                document.getElementById('campaign-message').value = campaign.message || '';
                document.getElementById('campaign-target').value = String(campaign.target_user_type || 0);
                document.getElementById('campaign-status').value = String(campaign.status === 1 ? 1 : 0);
                document.getElementById('campaign-scheduled-at').value = toDatetimeLocal(campaign.scheduled_at);
                document.getElementById('campaign-form-send').style.display = campaign.status === 2 ? 'none' : 'inline-block';
                hideFormError();
                refreshScheduleField();
                openModal('campaign-form-modal');
            })
            .catch(function (err) {
                alert(err.message || 'Unable to load campaign');
            });
    }

    function saveCampaign(e) {
        e.preventDefault();

        const id = document.getElementById('campaign-id').value.trim();
        const title = document.getElementById('campaign-title').value.trim();
        const message = document.getElementById('campaign-message').value.trim();
        const targetUserType = parseInt(document.getElementById('campaign-target').value, 10);
        const status = parseInt(document.getElementById('campaign-status').value, 10);
        const scheduledAt = document.getElementById('campaign-scheduled-at').value;

        if (!title || !message) {
            showFormError('Title and message are required.');
            return;
        }
        if (status === 1 && !scheduledAt) {
            showFormError('Scheduled datetime is required for scheduled campaigns.');
            return;
        }

        hideFormError();

        const payload = {
            action: id ? 'update' : 'create',
            id: id,
            title: title,
            message: message,
            target_user_type: targetUserType,
            status: status
        };

        if (status === 1 && scheduledAt) {
            payload.scheduled_at = fromDatetimeLocal(scheduledAt);
        }

        fetch('notifications-campaigns-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || 'Save failed');
                }
                closeModal('campaign-form-modal');
                fetchCampaigns();
            })
            .catch(function (err) {
                showFormError(err.message || 'Save failed');
            });
    }

    function openDeleteModal(id, title) {
        activeDeleteId = id;
        document.getElementById('campaign-delete-title').textContent = title;
        const error = document.getElementById('campaign-delete-error');
        error.style.display = 'none';
        error.textContent = '';
        openModal('campaign-delete-modal');
    }

    function confirmDelete() {
        if (!activeDeleteId) {
            return;
        }

        fetch('notifications-campaigns-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: activeDeleteId })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || 'Delete failed');
                }
                closeModal('campaign-delete-modal');
                activeDeleteId = '';
                fetchCampaigns();
            })
            .catch(function (err) {
                const error = document.getElementById('campaign-delete-error');
                error.textContent = err.message || 'Delete failed';
                error.style.display = 'block';
            });
    }

    function openSendModal(id, title) {
        activeSendId = id;
        document.getElementById('campaign-send-title').textContent = title;
        const error = document.getElementById('campaign-send-error');
        error.style.display = 'none';
        error.textContent = '';
        openModal('campaign-send-modal');
    }

    function confirmSend() {
        if (!activeSendId) {
            return;
        }

        const btn = document.getElementById('campaign-send-confirm');
        const initialHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Sending...';

        fetch('notifications-campaigns-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send', id: activeSendId })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || 'Send failed');
                }
                closeModal('campaign-send-modal');
                closeModal('campaign-form-modal');
                activeSendId = '';
                fetchCampaigns();
            })
            .catch(function (err) {
                const error = document.getElementById('campaign-send-error');
                error.textContent = err.message || 'Send failed';
                error.style.display = 'block';
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = initialHtml;
            });
    }

    function fetchCampaigns() {
        const params = new URLSearchParams({
            action: 'list',
            page: String(currentPage),
            limit: String(limit)
        });
        if (searchTerm) {
            params.set('search', searchTerm);
        }
        if (statusFilter !== '') {
            params.set('status', statusFilter);
        }
        if (targetFilter !== '') {
            params.set('target_user_type', targetFilter);
        }

        fetch('notifications-campaigns-api.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || 'Load failed');
                }
                renderCampaigns(data.campaigns || []);
                renderPagination(data.pagination || { page: 1, total_pages: 1 });
            })
            .catch(function (err) {
                const container = document.getElementById('campaigns-container');
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><p>' + escapeHtml(err.message || 'Load failed') + '</p></div>';
            });
    }

    function renderCampaigns(campaigns) {
        const container = document.getElementById('campaigns-container');
        container.querySelectorAll('.skeleton-service-item').forEach(function (el) { el.remove(); });

        if (!campaigns.length) {
            container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-bullhorn"></i><p>No campaigns found</p></div>';
            return;
        }

        container.innerHTML = campaigns.map(function (campaign) {
            const status = Number(campaign.status || 0);
            const sent = Number(campaign.sent_count || 0);
            const failed = Number(campaign.failed_count || 0);
            const read = Number(campaign.read_count || 0);
            const recipients = Number(campaign.recipient_count || 0);

            const statusLabel = statusToLabel(status);
            const targetLabel = targetToLabel(Number(campaign.target_user_type || 0));
            const createdAt = campaign.created_at ? new Date(campaign.created_at).toLocaleString() : '-';
            const scheduledAt = campaign.scheduled_at ? new Date(campaign.scheduled_at).toLocaleString() : '-';

            const canEdit = status !== 2;
            return '<div class="admin-list-item" data-id="' + escapeHtml(campaign.id) + '" data-title="' + escapeHtml(campaign.title || '') + '" data-status="' + status + '">' +
                '<div class="admin-list-item-header">' +
                    '<h3>' + escapeHtml(campaign.title || '') + '</h3>' +
                    '<p>' +
                        '<span class="status-badge status-' + status + '">' + escapeHtml(statusLabel) + '</span>' +
                        '<span class="status-badge" style="margin-left:6px;background:#e2e8f0;color:#334155;">' + escapeHtml(targetLabel) + '</span>' +
                        ' <span style="margin-left:8px;">Recipients: ' + recipients + ' | Sent: ' + sent + ' | Failed: ' + failed + ' | Read: ' + read + '</span>' +
                    '</p>' +
                    '<p style="margin-top:6px;">Created: ' + escapeHtml(createdAt) + ' | Scheduled: ' + escapeHtml(scheduledAt) + '</p>' +
                '</div>' +
                '<div class="admin-list-actions">' +
                    (canEdit ? '<button class="btn-icon btn-edit" title="Edit"><i class="fa-solid fa-pencil"></i></button>' : '') +
                    (canEdit ? '<button class="btn-icon btn-send" title="Send now"><i class="fa-solid fa-paper-plane"></i></button>' : '') +
                    (canEdit ? '<button class="btn-icon btn-icon-danger btn-delete" title="Delete"><i class="fa-solid fa-trash"></i></button>' : '') +
                '</div>' +
            '</div>';
        }).join('');
    }

    function renderPagination(pagination) {
        const container = document.getElementById('campaigns-pagination');
        const page = Number(pagination.page || 1);
        const totalPages = Number(pagination.total_pages || 1);

        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="pagination">';
        if (page > 1) {
            html += '<button class="btn-pagination" data-page="' + (page - 1) + '">Previous</button>';
        }

        const start = Math.max(1, page - 2);
        const end = Math.min(totalPages, page + 2);
        for (let p = start; p <= end; p++) {
            if (p === page) {
                html += '<span class="pagination-current">' + p + '</span>';
            } else {
                html += '<button class="btn-pagination" data-page="' + p + '">' + p + '</button>';
            }
        }

        if (page < totalPages) {
            html += '<button class="btn-pagination" data-page="' + (page + 1) + '">Next</button>';
        }
        html += '</div>';

        container.innerHTML = html;
        container.querySelectorAll('button[data-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentPage = Number(btn.dataset.page || '1');
                fetchCampaigns();
            });
        });
    }

    function refreshScheduleField() {
        const status = Number(document.getElementById('campaign-status').value || '0');
        const wrapper = document.getElementById('campaign-schedule-field');
        const input = document.getElementById('campaign-scheduled-at');
        if (status === 1) {
            wrapper.style.display = 'block';
            input.required = true;
        } else {
            wrapper.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function statusToLabel(status) {
        if (status === 1) return 'Scheduled';
        if (status === 2) return 'Sent';
        if (status === 3) return 'Failed';
        return 'Draft';
    }

    function targetToLabel(target) {
        if (target === 1) return 'Customers';
        if (target === 2) return 'Professionals';
        return 'All Users';
    }

    function showFormError(message) {
        const el = document.getElementById('campaign-form-error');
        el.textContent = message;
        el.style.display = 'block';
    }

    function hideFormError() {
        const el = document.getElementById('campaign-form-error');
        el.textContent = '';
        el.style.display = 'none';
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) {
            return;
        }
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) {
            return;
        }
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
    }

    function safeBind(id, fn) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('click', fn);
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toDatetimeLocal(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hour = String(date.getHours()).padStart(2, '0');
        const minute = String(date.getMinutes()).padStart(2, '0');
        return year + '-' + month + '-' + day + 'T' + hour + ':' + minute;
    }

    function fromDatetimeLocal(value) {
        if (!value) {
            return '';
        }
        return value.replace('T', ' ') + ':00';
    }
})();
