(function() {
    'use strict';

    const initialSize = 8;
    const moreSize = 4;
    let offset = 0;
    let limit = initialSize;
    let totalCount = 0;
    let searchTerm = '';

    document.addEventListener('DOMContentLoaded', function() {
        bindToolbar();
        const params = new URLSearchParams(window.location.search);
        offset = 0;
        limit = initialSize;
        if (params.has('search')) {
            let val = params.get('search') || '';
            if (val === 'undefined' || val === 'null') val = '';
            searchTerm = val;
        }
        requestChunk(false);
    });

    function requestChunk(append) {
        const container = document.getElementById('users-container');
        const moreBtn = document.getElementById('users-show-more');
        if (!container) return;

        console.debug('requestChunk called', { offset, limit, searchTerm, append });

        if (!append) {
            renderSkeletons(container, initialSize);
        }

        let url = `users-list-api?offset=${offset}&limit=${limit}`;
        if (searchTerm && searchTerm !== 'undefined' && searchTerm !== 'null') {
            url += `&search=${encodeURIComponent(searchTerm)}`;
        }
        console.debug('fetch url', url);

        fetch(url)
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : {};
                const users = Array.isArray(data.items) ? data.items : [];
                const total = Number.isFinite(data.total) ? data.total : users.length;
                totalCount = total;

                if (!users || users.length === 0) {
                    container.innerHTML = '<p class="empty-list">No users found.</p>';
                    if (moreBtn) moreBtn.style.display = 'none';
                    return;
                }

                if (!append) {
                    container.innerHTML = '';
                }

                renderUsers(users, container);

                offset += users.length;
                if (isNaN(offset) || offset < 0) offset = 0;
                limit = moreSize;

                if (offset < totalCount) {
                    if (moreBtn) {
                        moreBtn.style.display = 'inline-block';
                        moreBtn.disabled = false;
                    }
                } else if (moreBtn) {
                    moreBtn.style.display = 'none';
                }

                updateUrlParams();
            })
            .catch(err => {
                console.error('Failed to load users', err);
                if (!append) container.innerHTML = '<p class="error-message">Unable to load users. Please try again later.</p>';
                if (moreBtn) moreBtn.style.display = 'none';
            });
    }

    function renderUsers(users, container) {
        const roleLabels = {1:'Customer',2:'Pro',3:'Admin'};
        users.forEach(u => {
            const card = document.createElement('div');
            card.className = 'service-item user-card';

            const header = document.createElement('div');
            header.className = 'service-header';

            const title = document.createElement('h3');

            const avatar = document.createElement('img');
            avatar.className = 'profile-pic';
            avatar.setAttribute('data-blob-src', u.profile_picture || '../../../files/uploads/user/defaultUser.png');
            avatar.alt = u.username || 'User';
            avatar.style.width = '40px';
            avatar.style.height = '40px';
            avatar.style.borderRadius = '50%';
            avatar.style.objectFit = 'cover';
            avatar.style.marginRight = '10px';

            const name = document.createElement('span');
            name.textContent = u.username || (u.first_name || '') + ' ' + (u.last_name || '');

            title.appendChild(avatar);
            title.appendChild(name);

            const role = roleLabels[u.user_type] || 'Unknown';
            const badge = document.createElement('span');
            badge.className = 'user-role-badge';
            badge.textContent = role;
            title.appendChild(badge);

            header.appendChild(title);
            card.appendChild(header);

            const seeMore = document.createElement('button');
            seeMore.type = 'button';
            seeMore.className = 'btn-secondary';
            seeMore.textContent = 'See more';
            seeMore.addEventListener('click', function() {
                openUserModal(u);
            });
            card.appendChild(seeMore);

            container.appendChild(card);
        });
    }



    function renderSkeletons(container, count) {
        const items = [];
        for (let i = 0; i < count; i++) {
            items.push(
                '<div class="skeleton-service-item">' +
                    '<div class="skeleton-service-header">' +
                        '<div class="skeleton skeleton-circle" style="width:40px;height:40px;border-radius:50%"></div>' +
                        '<div class="skeleton skeleton-title" style="width:60%"></div>' +
                    '</div>' +
                    '<div class="skeleton skeleton-button" style="width:80px;height:32px"></div>' +
                '</div>'
            );
        }
        container.innerHTML = items.join('');
    }

    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const p = parseInt(params.get('page'), 10);
        return (isNaN(p) || p < 1) ? 1 : p;
    }

    function bindToolbar() {
        const search = document.getElementById('user-search');
        const moreBtn = document.getElementById('users-show-more');
        const createBtn = document.getElementById('create-user');

        const urlParams = new URLSearchParams(window.location.search);
        if (search && urlParams.has('search')) {
            let val = urlParams.get('search') || '';
            if (val === 'undefined' || val === 'null') {
                val = '';
            }
            search.value = val;
            searchTerm = val;
        }

        if (search) {
            let timeout;
            search.addEventListener('input', function() {
                clearTimeout(timeout);
                searchTerm = this.value.trim();
                timeout = setTimeout(() => {
                    offset = 0;
                    limit = initialSize;
                    updateUrlParams();
                    requestChunk(false);
                }, 300);
            });
        }
        if (moreBtn) {
            moreBtn.addEventListener('click', function() {
                requestChunk(true);
            });
        }
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                openCreateUserModal();
            });
        }
        const userTypeSelect = document.getElementById('new-usertype');
        const companyGroup = document.getElementById('company-group');
        if (userTypeSelect && companyGroup) {
            userTypeSelect.addEventListener('change', function() {
                if (this.value === '2') {
                    companyGroup.style.display = 'block';
                } else {
                    companyGroup.style.display = 'none';
                }
            });
        }
    }
    function openUserModal(user) {
        const modal = document.getElementById('user-modal');
        const body = document.getElementById('user-modal-body');
        const actions = document.getElementById('user-modal-actions');
        const title = document.getElementById('user-modal-title');

        title.textContent = user.username || 'User details';
        body.innerHTML = generateUserDetailsHtml(user);
        actions.innerHTML = '';

        const btnDeactivate = createModalButton('Deactivate', 'btn-secondary', () => {
            alert('Deactivate user ' + (user.username || '')); 
        }, 'fa-solid fa-user-check');
        const btnBan = createModalButton('Ban', 'btn-danger', () => {
            alert('Ban user ' + (user.username || '')); 
        }, 'fa-solid fa-gavel');
        const btnDelete = createModalButton('Delete', 'btn-danger', () => {
            if (confirm('Are you sure you want to delete this user?')) {
                deleteUser(user.id);
            }
        }, 'fa-solid fa-trash');

        actions.appendChild(btnDeactivate);
        actions.appendChild(btnBan);
        actions.appendChild(btnDelete);

        const modalBody = body;
        modalBody.querySelectorAll('.btn-copy').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const text = this.getAttribute('data-copy') || '';
                if (!text) return;
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                    this.classList.add('copied');
                    const icon = this.querySelector('i');
                    if (icon) { icon.className = 'fa-solid fa-check'; }
                    const prevTitle = this.getAttribute('title') || '';
                    this.setAttribute('title', 'Copied!');
                    setTimeout(() => {
                        this.classList.remove('copied');
                        if (icon) { icon.className = 'fa-solid fa-copy'; }
                        this.setAttribute('title', prevTitle);
                    }, 1600);
                } catch (err) {
                    this.classList.add('copy-failed');
                    setTimeout(() => this.classList.remove('copy-failed'), 1400);
                    console.warn('Copy failed', err);
                }
            });
        });
        modalBody.querySelectorAll('.btn-edit-inline').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                enableInlineEditing(btn, user);
            });
        });

        showModal(modal);
    }

    function formatDate(dstr) {
        if (!dstr) return '-';
        const d = new Date(dstr);
        if (isNaN(d.getTime())) return dstr;
        const day = String(d.getDate()).padStart(2,'0');
        const mon = String(d.getMonth()+1).padStart(2,'0');
        const yr = d.getFullYear();
        return `${day}/${mon}/${yr}`;
    }

    function enableInlineEditing(btn, user) {
        const key = btn.getAttribute('data-edit');
        const row = btn.closest('tr');
        if (!row) return;
        const cell = row.children[1];
        const orig = cell.textContent || '';
        row.classList.add('editable-row');
        cell.innerHTML = '';
        const input = document.createElement('input');
        input.type = 'text';
        input.value = orig;
        input.style.width = '60%';
        cell.appendChild(input);
        const save = document.createElement('button');
        save.className = 'btn-copy';
        save.innerHTML = '<i class="fa-solid fa-check"></i>';
        const cancel = document.createElement('button');
        cancel.className = 'btn-copy';
        cancel.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        cell.appendChild(save);
        cell.appendChild(cancel);
        save.addEventListener('click', async function() {
            const newVal = input.value.trim();
            const payload = {};
            payload[key] = newVal;
            try {
                const resp = await fetch(`/users/${user.id}`, {
                    method: 'PATCH',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();
                if (resp.ok) {
                    cell.textContent = newVal;
                    const editBtn = document.createElement('button');
                    editBtn.className = 'btn-copy btn-edit-inline';
                    editBtn.setAttribute('data-edit', key);
                    editBtn.title = 'Edit ' + key.replace('_',' ');
                    editBtn.innerHTML = '<i class="fa-solid fa-pen"></i>';
                    editBtn.addEventListener('click', function(e){e.preventDefault(); enableInlineEditing(editBtn, user);});
                    cell.appendChild(editBtn);
                } else {
                    alert(data.error || 'Update failed');
                    cell.textContent = orig;
                }
            } catch (e) {
                alert('Request error');
                cell.textContent = orig;
            }
        });
        cancel.addEventListener('click', function() {
            cell.textContent = orig;
            const editBtn = document.createElement('button');
            editBtn.className = 'btn-copy btn-edit-inline';
            editBtn.setAttribute('data-edit', key);
            editBtn.title = 'Edit ' + key.replace('_',' ');
            editBtn.innerHTML = '<i class="fa-solid fa-pen"></i>';
            editBtn.addEventListener('click', function(e){e.preventDefault(); enableInlineEditing(editBtn, user);});
            cell.appendChild(editBtn);
        });
    }

    function generateUserDetailsHtml(u) {
        const roleLabels = {1:'Customer',2:'Pro',3:'Admin'};
        const fields = [
            { label: 'UUID', value: u.id || '-' , copy: true },
            { label: 'First name', value: u.first_name || '-' , edit: true },
            { label: 'Last name', value: u.last_name || '-' , edit: true },
            { label: 'Email', value: u.email || '-' , edit: true },
            { label: 'Type', value: roleLabels[u.user_type] || u.user_type || '-' },
            { label: 'Created', value: formatDate(u.created_at) }
        ];
        let html = '<table class="user-details-table" style="width:100%;border-collapse:collapse;">';
        fields.forEach(f => {
            html += '<tr>' +
                `<td style="padding:6px 0;font-weight:600;vertical-align:top;">${f.label}</td>` +
                `<td style="padding:6px 0;vertical-align:top;">${f.value}</td>`;
            if (f.copy) {
                html += `<td style="padding:6px 0;vertical-align:top;">` +
                    `<button class=\"btn-copy\" data-copy=\"${f.value}\" title=\"Copy ${f.label}\">` +
                    `<i class=\"fa-solid fa-copy\"></i></button></td>`;
            } else if (f.edit) {
                html += `<td style="padding:6px 0;vertical-align:top;">` +
                    `<button class=\"btn-copy btn-edit-inline\" data-edit=\"${f.label.toLowerCase().replace(' ','_')}\" title=\"Edit ${f.label}\">` +
                    `<i class=\"fa-solid fa-pen\"></i></button></td>`;
            }
            html += '</tr>';
        });
        html += '</table>';
        return html;
    }

    function createModalButton(text, cls, cb, iconClass) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = cls;
        if (iconClass) {
            btn.innerHTML = `<i class="${iconClass}"></i> ${text}`;
        } else {
            btn.textContent = text;
        }
        btn.addEventListener('click', cb);
        return btn;
    }

    function deleteUser(id) {
        alert('Delete user ' + id + ' (functionality not available)');
        closeModal();
        offset = 0;
        limit = initialSize;
        requestChunk(false);
    }

    function showModal(modal) {
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
    }

    function showToast(msg, timeout) {
        timeout = timeout || 4000;
        const t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => {
            t.style.transition = 'opacity 0.3s';
            t.style.opacity = '0';
            setTimeout(() => { try { document.body.removeChild(t); } catch(e){} }, 350);
        }, timeout);
    }

    function closeModal() {
        const modal = document.querySelector('.add-modal.is-open');
        if (modal) modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
    }

    document.addEventListener('click', function(ev) {
        if (ev.target.classList.contains('add-modal') || ev.target.classList.contains('close-button')) {
            closeModal();
        }
    });

    function openCreateUserModal() {
        const modal = document.getElementById('create-user-modal');
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        const form = document.getElementById('create-user-form');
        if (form) {
            form.reset();
            const errorBox = document.getElementById('create-user-error');
            if (errorBox) {
                errorBox.style.display = 'none';
                errorBox.textContent = '';
            }
            form.removeEventListener('submit', handleCreateUserSubmit);
            form.addEventListener('submit', handleCreateUserSubmit);
        }
        const userTypeSelect = document.getElementById('new-usertype');
        const companyGroup = document.getElementById('company-group');
        if (userTypeSelect && companyGroup) {
            companyGroup.style.display = userTypeSelect.value === '2' ? 'block' : 'none';
        }

        modal.querySelectorAll('.password-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const wrapper = toggle.closest('.password-wrapper');
                const input = wrapper ? wrapper.querySelector('input') : null;
                if (!input) return;
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                toggle.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            });
        });

        modal.querySelectorAll('.password-input[data-strength="true"]').forEach(function(input) {
            const meter = input.closest('.field').querySelector('.password-meter');
            const text = meter ? meter.querySelector('.password-meter-text') : null;
            if (!meter || !text) return;
            function meetsCriteria(value) {
                return {
                    length: value.length >= 8,
                    lower: /[a-z]/.test(value),
                    upper: /[A-Z]/.test(value),
                    number: /\d/.test(value),
                    special: /[^a-zA-Z0-9]/.test(value)
                };
            }
            function getStrength(value) {
                const c = meetsCriteria(value);
                const all = c.length && c.lower && c.upper && c.number && c.special;
                if (!value.length) return { label:'', className: '' };
                if (!all) return { label:'Weak', className:'is-weak' };
                if (value.length >= 12) return { label:'Strong', className:'is-strong' };
                return { label:'Medium', className:'is-medium' };
            }
            function updateMeter() {
                const val = input.value || '';
                const str = getStrength(val);
                meter.classList.remove('is-weak','is-medium','is-strong');
                if (!str.label) { text.textContent = 'Strength'; return; }
                meter.classList.add(str.className);
                text.textContent = 'Strength: ' + str.label;
            }
            input.addEventListener('input', updateMeter);
            updateMeter();
        });
    }

    function handleCreateUserSubmit(ev) {
        ev.preventDefault();
        const form = ev.target;
        const errorBox = document.getElementById('create-user-error');
        if (errorBox) {
            errorBox.style.display = 'none';
            errorBox.textContent = '';
        }
        // basic client-side validation
        if (form.password.value !== form.confirm_password.value) {
            if (errorBox) {
                errorBox.textContent = 'Password and confirmation do not match.';
                errorBox.style.display = 'block';
            }
            return;
        }

        const formData = new FormData(form);
        const params = new URLSearchParams();
        for (const [k,v] of formData.entries()) {
            params.append(k, v);
        }

        let url = new URL('create-user-api.php', window.location.href).href;
        url = encodeURI(url);
        console.log('posting create user to', url);

        fetch(url, {
            method: 'POST',
            body: params.toString(),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        }).then(r => r.text())
          .then(t => {
              const trimmed = t ? t.trim() : '';
              console.log('create-user response raw', trimmed);
              let res;
              try {
                  res = trimmed ? JSON.parse(trimmed) : {};
              } catch(parseErr) {
                  console.error('parse error on create-user', parseErr, 'raw:', trimmed);
                  if (errorBox) {
                      errorBox.textContent = 'Server returned invalid response.';
                      errorBox.style.display = 'block';
                  }
                  return;
              }
              if (res.error) {
                  if (errorBox) {
                      errorBox.textContent = res.error;
                      errorBox.style.display = 'block';
                  }
                  return;
              }
              closeModal();
              showToast('User created');
              offset = 0;
              limit = initialSize;
              requestChunk(false);
          }).catch(err => {
              if (errorBox) {
                  errorBox.textContent = 'Create failed: ' + err.message;
                  errorBox.style.display = 'block';
              } else {
                  alert('Create failed: ' + err.message);
              }
          });
    }

    function updateUrlParams() {
        const url = new URL(window.location.href);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        window.history.replaceState({}, '', url.toString());
    }

})();