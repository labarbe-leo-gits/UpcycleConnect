(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        loadTopForums();
        loadAllForumsInitial();
        setupCreateForumModal();
    });

    let allPage = 1;
    const allLimit = 5;
    let allTotal = null;
    let allLoading = false;

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
                    container.innerHTML = '<div class="deposit-empty"><p>No forums yet. Be the first to create one!</p></div>';
                    return;
                }

                container.innerHTML = '';
                items.forEach(forum => container.appendChild(createForumCard(forum)));
            })
            .catch(err => {
                console.error('Failed to load forums', err);
                container.innerHTML = '<p class="error-message">Unable to load forums. Please try again later.</p>';
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
        viewBtn.textContent = 'Open Forum';
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
        seeMore.textContent = 'Loading...';

        loadAllForumsPage(allPage, allLimit);

        seeMore.addEventListener('click', function() {
            if (!allLoading) loadAllForumsPage(allPage + 1, allLimit);
        });
    }

    function loadAllForumsPage(page, limit) {
        const list = document.getElementById('forums-all-list');
        const seeMore = document.getElementById('forums-see-more');
        if (!list || !seeMore) return;
        allLoading = true;
        seeMore.disabled = true;
        seeMore.textContent = 'Loading...';

        fetch(`forums-api?page=${page}&limit=${limit}&sort=trending`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(text => {
                const data = text ? JSON.parse(text) : { items: [] };
                const items = Array.isArray(data.items) ? data.items : [];
                allTotal = Number.isFinite(data.total) ? data.total : (allTotal || 0);

                if (page === 1) {
                    list.innerHTML = '';
                }

                if (!items.length && page === 1) {
                    list.innerHTML = '<div class="deposit-empty"><p>No forums yet.</p></div>';
                    seeMore.style.display = 'none';
                    return;
                }

                items.forEach(function(f) {
                    list.appendChild(renderAllForumItem(f));
                });

                allPage = page;

                if ((allPage * limit) >= allTotal || items.length < limit) {
                    seeMore.style.display = 'none';
                } else {
                    seeMore.disabled = false;
                    seeMore.textContent = 'See more';
                }
            })
            .catch(err => {
                console.error('Failed to load more forums', err);
                const errNode = document.createElement('div');
                errNode.className = 'error-message';
                errNode.textContent = 'Unable to load more forums.';
                list.appendChild(errNode);
                seeMore.disabled = false;
                seeMore.textContent = 'See more';
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
        meta.innerHTML = `<span class=\"muted\">${formatDate(f.updated_at || f.created_at || '')}</span>`;
        left.appendChild(meta);

        const right = document.createElement('div');
        right.className = 'forum-item-actions';
        const openBtn = document.createElement('button');
        openBtn.type = 'button';
        openBtn.className = 'btn-secondary';
        openBtn.textContent = 'Open';
        openBtn.addEventListener('click', function() { window.location.href = `forum?uuid=${encodeURIComponent(f.id)}`; });
        right.appendChild(openBtn);

        item.appendChild(left);
        item.appendChild(right);
        return item;
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