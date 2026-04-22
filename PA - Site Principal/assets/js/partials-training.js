(function() {
    'use strict';

    const apiBase = window.API_BASE || 'http://localhost:9999';
    const apiToken = window.API_TOKEN || '';
    const currentUserId = window.CURRENT_USER_ID || '';
    const userManagerId = window.USER_MANAGER_ID || null;
    const hasManager = !!userManagerId;

    const listEl = document.getElementById('formations-list');
    const emptyEl = document.getElementById('formations-empty');
    const modal = document.getElementById('formation-form-modal');
    const form = document.getElementById('formation-form');
    const showMoreBtn = document.getElementById('formations-show-more');

    let currentPage = 1;
    let totalItems = 0;
    let schedules = [];
    let editingId = null;

    async function init() {

        if (hasManager) {
            const approvalField = document.getElementById('form-approval-field');
            
            if (approvalField) {
                approvalField.innerHTML = `
                    <div style="padding: 12px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 4px; color: #92400e;">
                        <i class="fa-solid fa-info-circle"></i> 
                        <strong>Manager approval required:</strong> This formation will be sent to your manager for review before publishing.
                    </div>
                `;
            }
        }
        
        await loadFormationTypes();
        await loadFormations();
        bindEvents();
    }

    async function loadFormationTypes() {
        try {
            const response = await fetch(`${apiBase}/typesPrestation`, {
                headers: { 'Authorization': `Bearer ${apiToken}` }
            });
            if (!response.ok) throw new Error('Failed to load types');
            
            const types = await response.json();
            const typeSelect = document.getElementById('form-type');
            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                typeSelect.appendChild(option);
            });
        } catch (err) {
            console.error('Error loading types:', err);
        }
    }

    async function loadFormations(page = 1) {
        try {
            currentPage = page;
            const limit = 12;
            const offset = (page - 1) * limit;

            const response = await fetch(
                `${apiBase}/formations?creator_id=${currentUserId}&page=${page}&limit=${limit}`,
                { headers: { 'Authorization': `Bearer ${apiToken}` } }
            );

            if (!response.ok) throw new Error('Failed to load formations');

            const data = await response.json();
            totalItems = data.total;
            renderFormations(data.items);
            updatePagination(data.page, Math.ceil(data.total / limit));
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    function renderFormations(items) {
        listEl.innerHTML = '';

        if (!items || items.length === 0) {
            emptyEl.style.display = 'block';
            return;
        }

        emptyEl.style.display = 'none';

        items.forEach(formation => {
            const card = createFormationCard(formation);
            listEl.appendChild(card);
        });
    }

    function createFormationCard(formation) {
        const card = document.createElement('div');
        card.className = 'formation-card';
        card.style.cssText = `
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            margin-bottom: 12px;
        `;

        const statusBadge = getStatusBadge(formation.status);
        const dateObj = new Date(formation.service_date);
        const dateStr = dateObj.toLocaleDateString('fr-FR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        card.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 4px 0; font-size: 1.1em; color: #1f2937;">${escapeHtml(formation.name)}</h3>
                    <small style="color: #6b7280;">${dateStr}</small>
                </div>
                ${statusBadge}
            </div>
            <p style="margin: 8px 0; color: #374151; font-size: 0.95em;">${escapeHtml(formation.description).substring(0, 100)}...</p>
            <div style="display: flex; gap: 8px; margin-top: 12px; font-size: 0.9em; color: #6b7280;">
                <span><i class="fa-solid fa-euro-sign"></i> ${formation.price.toFixed(2)}€</span>
                <span><i class="fa-solid fa-users"></i> ${formation.maximum_participants ? formation.maximum_participants : 'Unlimited'}</span>
                <span><i class="fa-solid fa-map-pin"></i> ${formation.service_city || (formation.meeting_type === 'zoom' ? 'Online' : 'TBD')}</span>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 12px;">
                <button class="edit-btn" data-id="${formation.id}" style="flex: 1; padding: 8px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer;">
                    <i class="fa-solid fa-edit"></i> Edit
                </button>
                <button class="delete-btn" data-id="${formation.id}" style="flex: 1; padding: 8px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; cursor: pointer; color: #dc2626;">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        `;

        return card;
    }

    function getStatusBadge(status) {
        const badges = {
            'published': '<span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 500;">Published</span>',
            'draft': '<span style="background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 500;">Draft</span>'
        };
        return badges[status] || badges['published'];
    }

    function updatePagination(page, totalPages) {
        showMoreBtn.style.display = (page < totalPages) ? 'block' : 'none';
    }

    function bindEvents() {
        document.getElementById('create-formation-btn').addEventListener('click', () => {
            schedules = [];
            form.reset();
            document.getElementById('form-schedules-list').innerHTML = '';
            const approvalCheckbox = document.getElementById('form-needs-approval');
            if (approvalCheckbox) {
                approvalCheckbox.checked = false;
            }
            openModal();
        });

        document.getElementById('formation-form-modal-close').addEventListener('click', closeModal);
        document.getElementById('formation-form-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        form.addEventListener('submit', handleFormSubmit);

        document.querySelectorAll('.form-loc-opt').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.form-loc-opt').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                const mode = btn.dataset.mode;
                document.getElementById('form-address-fields').style.display = mode === 'online' ? 'none' : 'block';
            });
        });

        document.querySelectorAll('.form-meet-opt').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.form-meet-opt').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                const type = btn.dataset.type;
                
                document.getElementById('form-meeting-url-wrap').style.display = type === 'other' ? 'block' : 'none';

                if (type !== 'other') {
                    document.getElementById('form-meeting-url').value = '';
                }
            });
        });

        document.getElementById('add-form-schedule-btn').addEventListener('click', (e) => {
            e.preventDefault();
            openScheduleModal();
        });

        document.getElementById('schedule-modal-close').addEventListener('click', closeScheduleModal);
        document.getElementById('schedule-modal-cancel').addEventListener('click', closeScheduleModal);
        document.getElementById('schedule-modal-save').addEventListener('click', addScheduleSlot);

        showMoreBtn.addEventListener('click', () => {
            loadFormations(currentPage + 1);
        });

        listEl.addEventListener('click', (e) => {
            if (e.target.closest('.edit-btn')) {
                const id = e.target.closest('.edit-btn').dataset.id;
                editFormation(id);
            } else if (e.target.closest('.delete-btn')) {
                const id = e.target.closest('.delete-btn').dataset.id;
                deleteFormation(id);
            }
        });
    }

    async function handleFormSubmit(e) {
        e.preventDefault();

        if (schedules.length === 0) {
            showFormError('At least one time slot is required');
            return;
        }

        const formData = new FormData(form);
        let status = 'published';
        if (hasManager) {
            status = 'draft';
        } else {
            const checkbox = document.getElementById('form-needs-approval');
            status = checkbox && checkbox.checked ? 'draft' : 'published';
        }
        
        const payload = {
            name: formData.get('name'),
            description: formData.get('description'),
            price: parseFloat(formData.get('price')),
            type_id: formData.get('type'),
            service_date: formData.get('service_date'),
            service_road: formData.get('service_road') || '',
            service_city: formData.get('service_city') || '',
            service_zip: formData.get('service_zip') || '',
            maximum_participants: formData.get('maximum_participants') ? parseInt(formData.get('maximum_participants')) : null,
            meeting_type: document.querySelector('.form-meet-opt.is-active').dataset.type,
            online_meeting_link: formData.get('online_meeting_link') || '',
            status: status,
            created_by: currentUserId,
            schedules: schedules
        };

        try {
            const method = editingId ? 'PATCH' : 'POST';
            const url = editingId ? `${apiBase}/products/services/${editingId}` : `${apiBase}/formations`;
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${apiToken}`
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save formation');
            }

            showToast(editingId ? 'Formation updated successfully!' : 'Formation created successfully!', 'success');
            closeModal();
            loadFormations();
        } catch (err) {
            showFormError(err.message);
        }
    }

    async function editFormation(id) {
        try {
            const response = await fetch(`${apiBase}/products/services/${id}`, {
                headers: { 'Authorization': `Bearer ${apiToken}` }
            });

            if (!response.ok) throw new Error('Failed to load formation');
            const formation = await response.json();

            editingId = id;
            document.getElementById('formation-form-title').textContent = 'Edit Formation';
            document.getElementById('formation-form-submit').textContent = 'Update';
            
            form.querySelector('#form-name').value = formation.name || '';
            form.querySelector('#form-description').value = formation.description || '';
            form.querySelector('#form-price').value = formation.price || 0;
            form.querySelector('#form-type').value = formation.type_id || '';
            form.querySelector('#form-date').value = formation.service_date || '';
            form.querySelector('#form-road').value = formation.service_road || '';
            form.querySelector('#form-city').value = formation.service_city || '';
            form.querySelector('#form-zip').value = formation.service_zip || '';
            form.querySelector('#form-max-participants').value = formation.maximum_participants || '';

            const locMode = formation.service_city ? 'office' : 'online';
            document.querySelectorAll('.form-loc-opt').forEach(b => b.classList.toggle('is-active', b.dataset.mode === locMode));
            document.getElementById('form-address-fields').style.display = locMode === 'office' ? 'block' : 'none';

            const meetingType = formation.meeting_type || 'none';
            document.querySelectorAll('.form-meet-opt').forEach(b => b.classList.toggle('is-active', b.dataset.type === meetingType));
            document.getElementById('form-meeting-url-wrap').style.display = meetingType === 'other' ? 'block' : 'none';
            document.getElementById('form-meeting-url').value = formation.online_meeting_link || '';

            if (!hasManager) {
                const isDraft = formation.status === 'draft';
                document.getElementById('form-needs-approval').checked = isDraft;
            }

            schedules = (formation.schedules || []).map(s => ({ hour: s.hour, is_available: s.is_available }));
            renderSchedules();

            openModal();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    function addScheduleSlot() {
        const timeInput = document.getElementById('schedule-time');
        if (!timeInput.value) {
            showScheduleError('Please select a time');
            return;
        }

        const [hours] = timeInput.value.split(':');
        const hour = parseInt(hours);

        if (schedules.some(s => s.hour === hour)) {
            showScheduleError('This time slot already exists');
            return;
        }

        schedules.push({ hour, is_available: true });
        schedules.sort((a, b) => a.hour - b.hour);

        renderSchedules();
        closeScheduleModal();
    }

    function renderSchedules() {
        const list = document.getElementById('form-schedules-list');
        list.innerHTML = '';

        schedules.forEach((sched, idx) => {
            const div = document.createElement('div');
            div.style.cssText = 'display: flex; align-items: center; gap: 8px; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px;';
            div.innerHTML = `
                <span>${String(sched.hour).padStart(2, '0')}:00</span>
                <button type="button" class="remove-schedule-btn" data-idx="${idx}" style="margin-left: auto; background: none; border: none; cursor: pointer; color: #6b7280;">
                    <i class="fa-solid fa-times"></i>
                </button>
            `;
            list.appendChild(div);
        });

        document.querySelectorAll('.remove-schedule-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                schedules.splice(btn.dataset.idx, 1);
                renderSchedules();
            });
        });
    }

    async function deleteFormation(id) {
        if (!confirm('Are you sure you want to delete this formation?')) return;

        try {
            const response = await fetch(`${apiBase}/products/services/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${apiToken}` }
            });

            if (!response.ok) throw new Error('Failed to delete');
            showToast('Formation deleted successfully', 'success');
            loadFormations();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    function openModal() {
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        document.getElementById('formation-form-error').style.display = 'none';
        
        editingId = null;
        form.reset();
        schedules = [];
        renderSchedules();
        document.getElementById('formation-form-title').textContent = 'Create Formation';
        document.getElementById('formation-form-submit').textContent = 'Create';
        
        if (!hasManager) {
            document.getElementById('form-needs-approval').checked = false;
        }
        
        document.querySelector('.form-loc-opt.is-active')?.classList.remove('is-active');
        document.querySelectorAll('.form-loc-opt')[0]?.classList.add('is-active');
        document.getElementById('form-address-fields').style.display = 'none';
        document.querySelector('.form-meet-opt.is-active')?.classList.remove('is-active');
        document.querySelectorAll('.form-meet-opt')[0]?.classList.add('is-active');
        document.getElementById('form-meeting-url-wrap').style.display = 'none';
    }

    function openScheduleModal() {
        document.getElementById('schedule-modal').classList.add('is-open');
        document.body.classList.add('modal-open');
        document.getElementById('schedule-time').focus();
    }

    function closeScheduleModal() {
        document.getElementById('schedule-modal').classList.remove('is-open');
        document.body.classList.remove('modal-open');
        document.getElementById('schedule-time').value = '';
        showScheduleError('');
    }

    function showFormError(msg) {
        const el = document.getElementById('formation-form-error');
        el.textContent = msg;
        el.style.display = 'block';
    }

    function showScheduleError(msg) {
        const el = document.getElementById('schedule-modal-error');
        el.textContent = msg;
        el.style.display = msg ? 'block' : 'none';
    }

    function showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    init();
})();