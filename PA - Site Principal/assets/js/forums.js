(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        setupSearchAndFilters();
        loadTopForums();
        loadAllForumsInitial();
        setupCreateForumModal();
    });

    let allPage = 1;
    const allLimit = 5;
    let allTotal = null;
    let allLoading = false;
    let currentSort = 'trending';
    let currentSearch = '';
    let allForums = [];

    function setupSearchAndFilters() {
        const searchInput = document.getElementById('forums-search');
        const sortSelect = document.getElementById('forums-sort');
        const resetBtn = document.getElementById('reset-filters');

        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                currentSearch = this.value.trim().toLowerCase();
                searchTimeout = setTimeout(function() {
                    console.log('Search triggered:', currentSearch);
                    filterAndDisplayForums();
                }, 300);
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                currentSort = this.value;
                console.log('Sort changed to:', currentSort);
                
                if (currentSort === 'posts-desc' || currentSort === 'posts-asc') {
                    filterAndDisplayForums();
                } else {
                    loadAllForumsInitial();
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                currentSearch = '';
                currentSort = 'trending';
                if (searchInput) searchInput.value = '';
                if (sortSelect) sortSelect.value = 'trending';
                loadAllForumsInitial();
            });
        }
    }

    function filterAndDisplayForums() {
        const list = document.getElementById('forums-all-list');
        if (!list) return;

        let filtered = allForums;
        if (currentSearch) {
            filtered = allForums.filter(f => {
                const title = (f.title || '').toLowerCase();
                const desc = (f.description || '').toLowerCase();
                return title.includes(currentSearch) || desc.includes(currentSearch);
            });
        }

        if (currentSort === 'posts-desc') {
            filtered.sort((a, b) => (b.post_count || 0) - (a.post_count || 0));
        } else if (currentSort === 'posts-asc') {
            filtered.sort((a, b) => (a.post_count || 0) - (b.post_count || 0));
        }

        if (!filtered.length) {
            list.innerHTML = '<div class="deposit-empty"><p>' + t('common.no.forums.found', 'No forums found.') + '</p></div>';
            return;
        }

        list.innerHTML = '';
        filtered.forEach(function(f) {
            list.appendChild(renderAllForumItem(f));
        });
    }

    function loadTopForums() {
        const container = document.getElementById('forums-top3');
        if (!container) return;

        const topSkeleton = `
            <div class="forum-card">
                <div class="skeleton skeleton-title" style="width:55%"></div>
                <div class="skeleton skeleton-description" style="width:80%;height:12px;margin-top:8px"></div>
                <div class="skeleton skeleton-description" style="width:40%;height:12px;margin-top:8px"></div>
                <div class="skeleton skeleton-description" style="height:36px;margin-top:12px"></div>
            </div>
        `;
        container.innerHTML = topSkeleton.repeat(3);

        fetch('forums-api?page=1&limit=3&sort=trending', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : {};
                const items = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);

                if (!items.length) {
                    container.innerHTML = '<div class="deposit-empty"><p>' + t('common.no.forums.yet.create.one', 'No forums yet. Be the first to create one!') + '</p></div>';
                    return;
                }

                container.innerHTML = '';
                items.forEach(forum => container.appendChild(createForumCard(forum)));
            })
            .catch(err => {
                console.error('Failed to load forums', err);
                container.innerHTML = '<p class="error-message">' + t('common.unable.to.load.forums', 'Unable to load forums. Please try again later.') + '</p>';
            });
    }

    function createForumCard(f) {
        const card = document.createElement('div');
        card.className = 'forum-card';

        const title = document.createElement('h3');
        title.innerHTML = `<i class=\"fa-solid fa-comments\"></i> ${escapeHtml(f.title)}`;
        card.appendChild(title);

        if (f.post_count !== undefined && f.post_count !== null) {
            const stats = document.createElement('div');
            stats.className = 'forum-stats';
            const count = document.createElement('span');
            count.className = 'forum-count';
            count.textContent = `${f.post_count} post${f.post_count > 1 ? 's' : ''}`;
            stats.appendChild(count);
            card.appendChild(stats);
        }

        if (f.description) {
            const desc = document.createElement('p');
            desc.className = 'forum-description';
            desc.textContent = f.description;
            card.appendChild(desc);
        }

        if (f.latest_post) {
            const preview = document.createElement('div');
            preview.className = 'forum-latest-post';
            const text = String(f.latest_post || '');
            preview.textContent = (text.length > 160) ? text.substring(0, 157) + '...' : text;
            card.appendChild(preview);
        }

        const meta = document.createElement('div');
        meta.className = 'forum-meta';
        const updated = document.createElement('span');
        updated.innerHTML = `<i class=\"fa-regular fa-clock\"></i>&nbsp;${formatDate(f.updated_at || f.created_at || '')}`;
        meta.appendChild(updated);

        const viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'btn-secondary';
        viewBtn.textContent = t('common.open.forum', 'Open Forum');
        viewBtn.addEventListener('click', function() {
            window.location.href = `forum?uuid=${encodeURIComponent(f.id)}`;
        });
        meta.appendChild(viewBtn);

        card.appendChild(meta);
        return card;
    }

    function loadAllForumsInitial() {
        const list = document.getElementById('forums-all-list');
        const seeMore = document.getElementById('forums-see-more');
        if (!list || !seeMore) return;

        allPage = 1;
        allTotal = null;

        const itemSkeleton = `
            <div class="forum-item">
                <div style="flex:1">
                    <div class="skeleton skeleton-title" style="width:40%"></div>
                    <div class="skeleton skeleton-description" style="width:70%;height:12px;margin-top:8px"></div>
                </div>
                <div style="width:120px"></div>
            </div>
        `;
        list.innerHTML = itemSkeleton.repeat(allLimit);
        seeMore.disabled = true;
        seeMore.textContent = t('common.see.more', 'See more');
        seeMore.style.display = 'none';

        loadAllForumsPage(allPage, allLimit);


        const newSeeMore = seeMore.cloneNode(true);
        seeMore.parentNode.replaceChild(newSeeMore, seeMore);
        
        newSeeMore.addEventListener('click', function() {
            if (!allLoading) loadAllForumsPage(allPage + 1, allLimit);
        });
    }

    function loadAllForumsPage(page, limit) {
        const list = document.getElementById('forums-all-list');
        const seeMore = document.getElementById('forums-see-more');
        if (!list || !seeMore) return;
        allLoading = true;
        seeMore.disabled = true;
if (page > 1) seeMore.textContent = t('common.loading', 'Loading...');


        let apiSort = (currentSort === 'posts-desc' || currentSort === 'posts-asc') ? 'trending' : currentSort;
        let url = `forums-api?page=${page}&limit=${limit}&sort=${encodeURIComponent(apiSort)}`;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : { items: [] };
                const items = Array.isArray(data.items) ? data.items : [];
                allTotal = Number.isFinite(data.total) ? data.total : (allTotal || 0);

                if (page === 1) {
                    allForums = [];
                    list.innerHTML = '';
                }

                if (!items.length && page === 1) {
                    list.innerHTML = '<div class="deposit-empty"><p>' + t('common.no.forums.found', 'No forums found.') + '</p></div>';
                    seeMore.style.display = 'none';
                    return;
                }


                allForums = allForums.concat(items);

                if (!currentSearch && currentSort !== 'posts-desc' && currentSort !== 'posts-asc') {
                    items.forEach(function(f) {
                        list.appendChild(renderAllForumItem(f));
                    });
                } else {
                    filterAndDisplayForums();
                }

                allPage = page;


                const hasMore = (allPage * limit) < allTotal && items.length >= limit;
                
                if (hasMore) {
                    seeMore.style.display = 'block';
                    seeMore.disabled = false;
                    seeMore.textContent = t('common.see.more', 'See more');
                } else {
                    seeMore.style.display = 'none';
                }
            })
            .catch(err => {
                console.error('Failed to load more forums', err);
                const errNode = document.createElement('div');
                errNode.className = 'error-message';
                errNode.textContent = t('common.unable.to.load.more.forums', 'Unable to load more forums.');
                list.appendChild(errNode);
                seeMore.style.display = 'none';
            })
            .finally(() => { allLoading = false; });
    }

    function renderAllForumItem(f) {
        const item = document.createElement('div');
        item.className = 'forum-item';

        const left = document.createElement('div');
        left.style.flex = '1';

        const title = document.createElement('div');
        title.className = 'forum-item-title';
        title.innerHTML = `<strong>${escapeHtml(f.title)}</strong>`;
        left.appendChild(title);

        if (f.description) {
            const d = document.createElement('div');
            d.className = 'forum-item-desc';
            d.textContent = f.description;
            left.appendChild(d);
        }

        if (f.latest_post) {
            const p = document.createElement('div');
            p.className = 'forum-item-latest';
            const txt = String(f.latest_post || '');
            p.textContent = (txt.length > 140) ? txt.substring(0, 137) + '...' : txt;
            left.appendChild(p);
        }

        const meta = document.createElement('div');
        meta.className = 'forum-item-meta';
        let metaHTML = `<span class=\"muted\">${formatDate(f.updated_at || f.created_at || '')}</span>`;
        
        if (f.post_count !== undefined && f.post_count !== null) {
            metaHTML += ` <span class=\"muted\" style="margin-left: 16px;"><i class="fa-solid fa-comments" style="margin-right: 4px; color: #10b981;"></i>${f.post_count} post${f.post_count !== 1 ? 's' : ''}</span>`;
        }
        
        meta.innerHTML = metaHTML;
        left.appendChild(meta);

        const right = document.createElement('div');
        right.className = 'forum-item-actions';
        const openBtn = document.createElement('button');
        openBtn.type = 'button';
        openBtn.className = 'btn-secondary';
        openBtn.textContent = 'Open';
        openBtn.addEventListener('click', function() { window.location.href = `forum?uuid=${encodeURIComponent(f.id)}`; });
        right.appendChild(openBtn);

        if (window.currentUserId && (f.created_by === window.currentUserId || window.currentUserType == 4)) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn-icon delete-forum';
            deleteBtn.innerHTML = '<i class="fa-regular fa-trash-alt"></i>';
            deleteBtn.title = 'Delete forum';
            deleteBtn.addEventListener('click', function() {
                setupDeleteForumModal(f.id);
            });
            right.appendChild(deleteBtn);
        }

        item.appendChild(left);
        item.appendChild(right);
        return item;
    }

    function setupDeleteForumModal(forumId) {
        const modal = document.getElementById('delete-forum-modal');
        const form = document.getElementById('delete-forum-form');
        const closeBtn = document.getElementById('close-delete-forum');
        
        if (!modal || !form) return;

        let lastFocused = null;
        lastFocused = document.activeElement;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
            form.onsubmit = null;
        }

        if (closeBtn) closeBtn.onclick = closeModal;
        modal.onclick = function(e) { if (e.target === modal) closeModal(); };

        form.onsubmit = function(e) {
            e.preventDefault();
            deleteForum(forumId, closeModal);
        };

        document.onkeydown = function(e) {
            if (!modal.classList.contains('is-open')) return;
            if (e.key === 'Escape') closeModal();
        };
    }

    function deleteForum(forumId, onClose) {
        const form = document.getElementById('delete-forum-form');
        const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        }

        fetch(`forums-api?forum_id=${encodeURIComponent(forumId)}&_method=DELETE`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.text())
        .then(text => {
            if (text) {
                try {
                    const data = JSON.parse(text);
                    if (data.error) {
                        alert('Error deleting forum: ' + data.error);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Forum';
                        }
                        return;
                    }
                } catch (err) {
                    console.error('Unexpected response:', text, err);
                }
            }
            onClose();
            location.reload();
        })
        .catch(err => {
            console.error('Failed to delete forum', err);
            alert('Unable to delete forum. Please try again.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Forum';
            }
        });
    }

    function setupCreateForumModal() {
        const openBtn = document.getElementById('create-forum');
        const modal = document.getElementById('add-forum-modal');
        const closeBtn = document.getElementById('close-add-forum');
        const form = document.getElementById('add-forum-form');
        const errorDiv = document.getElementById('add-forum-error');
        if (!openBtn || !modal || !form) return;

        let lastFocused = null;
        openBtn.addEventListener('click', function() {
            lastFocused = document.activeElement;
            modal.classList.add('is-open');
            document.body.classList.add('modal-open');
            modal.setAttribute('aria-hidden', 'false');
            const focusTarget = modal.querySelector('#forum-title') || modal.querySelector('input, textarea, select');
            if (focusTarget) focusTarget.focus();
        });

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function(e) { if (!modal.classList.contains('is-open')) return; if (e.key === 'Escape') closeModal(); if (e.key === 'Tab') trapFocus(e); });

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
            if (errorDiv) { errorDiv.style.display = 'none'; errorDiv.textContent = ''; }
            form.reset();
        }

        function trapFocus(e) {
            var focusable = Array.prototype.slice.call(modal.querySelectorAll('button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])'))
                .filter(function(el) { return !el.hasAttribute('disabled'); });
            if (focusable.length === 0) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); return; }
            if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }

        form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            if (errorDiv) { errorDiv.style.display = 'none'; errorDiv.textContent = ''; }
            const title = (document.getElementById('forum-title').value || '').trim();
            const desc = (document.getElementById('forum-description').value || '').trim();
            const submitBtn = document.getElementById('add-forum-submit');

            const errors = [];
            if (!title) errors.push('Title is required');
            if (!desc) errors.push('Description is required');
            if (errors.length) { if (errorDiv) { errorDiv.textContent = errors.join('. '); errorDiv.style.display = 'block'; } return; }

            submitBtn.disabled = true;
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner"></i> Creating...';

            fetch('create-forum', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ title: title, description: desc }) })
                .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch (e) { throw new Error('Invalid server response'); } })
                .then(res => {
                    if (res.error) throw new Error(res.error);
                    closeModal();
                    loadTopForums();
                    loadAllForumsInitial();
                })
                .catch(err => { if (errorDiv) { errorDiv.textContent = err.message || 'Failed to create forum'; errorDiv.style.display = 'block'; } })
                .finally(() => { submitBtn.disabled = false; submitBtn.innerHTML = originalHtml; });
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

})();