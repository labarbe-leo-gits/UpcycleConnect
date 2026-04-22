(function() {
    'use strict';

    const listEl = document.getElementById('tips-list');
    const emptyEl = document.getElementById('tips-empty');
    const modal = document.getElementById('tip-modal');
    const deleteModal = document.getElementById('delete-tip-modal');
    const deleteMessage = document.getElementById('delete-tip-message');
    const deleteConfirmButton = document.getElementById('confirm-delete-tip');
    const deleteCancelButton = document.getElementById('cancel-delete-tip');
    const deleteCloseButton = document.getElementById('close-delete-tip-modal');
    let pendingDeleteId = null;
    const form = document.getElementById('tip-form');
    const titleField = document.getElementById('tip-title');
    const descField = document.getElementById('tip-description');
    const idField = document.getElementById('tip-id');
    const modalTitle = document.getElementById('tip-modal-title');
    const errorEl = document.getElementById('tip-error');

    function showModal(edit = false, tip = null) {
        if (edit && tip) {
            modalTitle.textContent = 'Edit Tip';
            idField.value = tip.id || '';
            titleField.value = tip.title || '';
            descField.value = tip.description || '';
        } else {
            modalTitle.textContent = 'Create Tip';
            idField.value = '';
            titleField.value = '';
            descField.value = '';
        }
        errorEl.style.display = 'none';
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
    }

    function bindModalButtons() {
        document.getElementById('add-tip').addEventListener('click', () => {
            window.location.href = 'tips-create';
        });
        document.getElementById('close-tip-modal').addEventListener('click', closeModal);
        document.getElementById('cancel-tip').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });
        deleteCloseButton.addEventListener('click', closeDeleteModal);
        deleteCancelButton.addEventListener('click', closeDeleteModal);
        deleteConfirmButton.addEventListener('click', deleteSelectedTip);

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const id = idField.value.trim();
            const title = titleField.value.trim();
            const description = descField.value.trim();

            if (!title || !description) {
                showError('Title and description are required.');
                return;
            }

            const payload = { title, description };
            let method = 'POST';
            let target = 'tips-api';
            if (id) {
                method = 'PATCH';
                payload.id = id;
            }

            fetch(target, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(async (res) => {
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error((body && body.error) ? body.error : 'An error occurred.');
                }
                closeModal();
                loadTips();
            })
            .catch((err) => showToastMessage(err.message, 'error'));
        });
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    function showToastMessage(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log(message);
        }
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        pendingDeleteId = null;
    }

    function deleteSelectedTip() {
        if (!pendingDeleteId) {
            closeDeleteModal();
            return;
        }

        fetch('tips-api', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `id=${encodeURIComponent(pendingDeleteId)}`
        })
        .then(async (res) => {
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error((body && body.error) ? body.error : 'An error occurred.');
            }
            closeDeleteModal();
            loadTips();
        })
        .catch((err) => {
            closeDeleteModal();
            showToastMessage(err.message, 'error');
        });
    }

    function renderTips(items) {
        listEl.innerHTML = '';

        if (!items || !items.length) {
            emptyEl.style.display = 'block';
            return;
        }

        emptyEl.style.display = 'none';

        items.forEach((tip) => {
            const card = document.createElement('div');
            card.className = 'tip-item';

            card.innerHTML = `
                <h3>${escapeHtml(tip.title || 'Untitled tip')}</h3>
                <p class="tip-meta">Created by: ${escapeHtml(tip.created_by_name || tip.created_by || 'Anonymous')}</p>
                <div class="tip-actions">
                    <button class="btn-secondary edit-tip" data-tip-id="${tip.id}">Edit</button>
                    <button class="btn-danger delete-tip" data-tip-id="${tip.id}">Delete</button>
                </div>
            `;

            card.querySelector('.edit-tip').addEventListener('click', () => {
                window.location.href = `tips-create?id=${encodeURIComponent(tip.id)}`;
            });
            card.querySelector('.delete-tip').addEventListener('click', () => {
                pendingDeleteId = tip.id;
                deleteMessage.textContent = `Delete tip: "${tip.title || 'Untitled'}" ?`;
                deleteModal.classList.add('is-open');
                document.body.classList.add('modal-open');
            });

            listEl.appendChild(card);
        });
    }

    function renderSkeletons(count = 4) {
        const skeletonTemplate = `
            <div class="skeleton-tip-item">
                <div class="skeleton skeleton-title" style="width: 50%; height: 18px;"></div>
                <div class="skeleton skeleton-description" style="width: 100%; height: 14px; margin-top: 8px;"></div>
                <div class="skeleton skeleton-description" style="width: 90%; height: 14px; margin-top: 6px;"></div>
                <div class="skeleton skeleton-meta" style="width: 35%; height: 12px; margin-top: 10px;"></div>
            </div>
        `;
        listEl.innerHTML = skeletonTemplate.repeat(count);
        emptyEl.style.display = 'none';
    }

    function loadTips() {
        renderSkeletons(4);
        fetch('tips-api?page=1&limit=100', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                showError(data.error);
                return;
            }
            renderTips(data.items || []);
        })
        .catch(err => {
            console.error(err);
            showError('Impossible de charger les conseils.');
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function() {
        bindModalButtons();
        loadTips();
    });
})();
