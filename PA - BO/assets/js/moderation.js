const sourcesById = (sources || []).reduce((acc, s) => { acc[s.id] = s; return acc; }, {});
let currentSourceId = initialSourceId in sourcesById ? initialSourceId : (sources[0] && sources[0].id);

const sourceCache = {};
function setCache(sourceId, data) {
    sourceCache[sourceId] = {
        words: Array.isArray(data.words) ? data.words : [],
        connected: !!data.connected,
        loaded: true,
    };
}
setCache(currentSourceId, {words: initialWords || [], connected: sourcesById[currentSourceId]?.connected});

let words = sourceCache[currentSourceId].words.slice();
let filtered = words.slice();
let displayed = 0;
const perPage = 10;
const tbody = document.querySelector('#badwords-table tbody');

function showToast(msg, timeout = 3000) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);

    requestAnimationFrame(() => t.classList.add('show'));

    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 220);
    }, timeout);
}

function renderRows() {
    tbody.innerHTML = '';
    const connected = sourceCache[currentSourceId]?.connected;
    if (!connected) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="2" style="text-align:center;padding:20px;color:#6b7280;"><strong>This source is not connected.</strong><br/>Open <em>Sources</em> to connect and fetch words.</td>`;
        tbody.appendChild(tr);
        document.getElementById('load-more-btn').style.display = 'none';
        return;
    }

    if (filtered.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="2" style="text-align:center;padding:20px;color:#6b7280;">No results</td>';
        tbody.appendChild(tr);
        document.getElementById('load-more-btn').style.display = 'none';
        return;
    }

    const slice = filtered.slice(displayed, displayed + perPage);
    slice.forEach(w => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${w}</td><td style="text-align:center;"><span class="delete-word btn-danger" data-word="${w}"><i class="fa-solid fa-trash"></i></span></td>`;
        tr.addEventListener('mouseenter', () => tr.style.background = '#f9f9f9');
        tr.addEventListener('mouseleave', () => tr.style.background = '');
        tbody.appendChild(tr);
    });
    displayed += slice.length;
    if (displayed >= filtered.length) {
        document.getElementById('load-more-btn').style.display = 'none';
    } else {
        document.getElementById('load-more-btn').style.display = '';
    }
}

let pendingDelete = null;
function wireDelete() {
    document.querySelectorAll('.delete-word').forEach(el => {
        el.addEventListener('click', () => {
            pendingDelete = el.dataset.word;
            document.getElementById('delete-word-name').textContent = pendingDelete;
            const overlay = document.getElementById('delete-modal');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
            overlay.classList.add('is-visible');
            document.body.classList.add('modal-open');
        });
    });
}

function closeModal(id = 'delete-modal') {
    const overlay = document.getElementById(id);
    if (!overlay) return;

    overlay.setAttribute('aria-hidden', 'true');
    overlay.classList.remove('is-visible');
    document.body.classList.remove('modal-open');
    pendingDelete = null;

    setTimeout(() => {
        if (overlay.getAttribute('aria-hidden') === 'true') {
            overlay.style.display = 'none';
        }
    }, 220);
}

function openSourcesModal() {
    renderSourcesModal();
    const overlay = document.getElementById('sources-modal');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.add('is-visible');
    document.body.classList.add('modal-open');
}

function openAddSourceModal() {
    const overlay = document.getElementById('add-source-modal');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.add('is-visible');
    document.body.classList.add('modal-open');

    document.getElementById('add-source-error').textContent = '';
    document.getElementById('add-source-repo').value = '';
    document.getElementById('add-source-name').value = '';
}

function closeAddSourceModal() {
    const overlay = document.getElementById('add-source-modal');
    if (!overlay) return;
    overlay.setAttribute('aria-hidden', 'true');
    overlay.classList.remove('is-visible');
    document.body.classList.remove('modal-open');
}

async function addCustomSource() {
    const repoInput = document.getElementById('add-source-repo');
    const nameInput = document.getElementById('add-source-name');
    const errorEl = document.getElementById('add-source-error');

    const repo = repoInput.value.trim();
    const name = nameInput.value.trim();
    if (!repo) {
        errorEl.textContent = 'Please enter a GitHub repo URL or owner/repo.';
        return;
    }

    errorEl.textContent = '';
    const submitBtn = document.getElementById('add-source-submit');
    submitBtn.disabled = true;

    try {
        const body = `action=add_source&repoUrl=${encodeURIComponent(repo)}&name=${encodeURIComponent(name)}`;
        const r = await fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
        const json = await r.json();
        if (!json.ok) {
            errorEl.textContent = json.error || 'Failed to add source';
            return;
        }

        const src = json.source;
        src.connected = false;
        sources.push(src);
        sourcesById[src.id] = src;

        showToast('Source added');
        setCurrentSource(src.id, {forceReload: true});
        renderSourcesModal();
        closeAddSourceModal();
    } catch (err) {
        errorEl.textContent = 'Network error';
        console.error(err);
    } finally {
        submitBtn.disabled = false;
    }
}

function renderSourcesModal() {
    const container = document.getElementById('sources-modal-body');
    container.innerHTML = '';

    const addRow = document.createElement('div');
    addRow.style.marginBottom = '14px';
    addRow.style.display = 'flex';
    addRow.style.justifyContent = 'center';

    const addButton = document.createElement('button');
    addButton.className = 'btn-secondary';
    addButton.type = 'button';
    addButton.textContent = 'Add your own source';
    addButton.addEventListener('click', openAddSourceModal);

    addRow.appendChild(addButton);
    container.appendChild(addRow);

    sources.forEach(src => {
        const cached = sourceCache[src.id] || {connected: src.connected, words: [], loaded: false};
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        row.style.gap = '12px';

        const left = document.createElement('div');
        left.style.flex = '1';
        left.innerHTML = `<div style="font-weight:600;">${src.name}</div><div style="font-size:.85rem;color:#555;">${src.repoUrl}</div>`;

        const right = document.createElement('div');
        right.style.display = 'flex';
        right.style.alignItems = 'center';
        right.style.gap = '8px';

        const status = document.createElement('span');
        status.className = 'help-icon';

        const chip = document.createElement('span');
        chip.className = 'pct-chip';
        chip.textContent = cached.connected ? 'Connected' : 'Disconnected';
        chip.style.background = cached.connected ? '#10b981' : '#6b7280';

        const tooltip = document.createElement('span');
        tooltip.className = 'help-tooltip';
        const count = cached.words?.length || 0;
        tooltip.textContent = cached.connected ? `${count} words` : 'Not connected';

        status.appendChild(chip);
        status.appendChild(tooltip);

        const btn = document.createElement('button');
        btn.className = 'btn-secondary';
        btn.type = 'button';
        if (cached.connected) {
            if (src.canDisconnect) {
                btn.textContent = 'Disconnect';
                btn.addEventListener('click', () => disconnectSource(src.id));
            } else {
                btn.textContent = 'Required';
                btn.disabled = true;
            }
        } else {
            btn.textContent = 'Connect';
            btn.addEventListener('click', () => syncSource(src.id));
        }

        right.appendChild(status);
        right.appendChild(btn);

        if (src.custom) {
            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn-secondary';
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => removeSource(src.id));
            right.appendChild(removeBtn);
        }

        row.appendChild(left);
        row.appendChild(right);
        container.appendChild(row);
    });
}

async function removeSource(sourceId) {
    const body = `action=remove_source&source=${encodeURIComponent(sourceId)}`;
    const r = await fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
    const json = await r.json();
    if (!json.ok) {
        showToast(json.error || 'Remove failed');
        return;
    }

    sources = sources.filter(s => s.id !== sourceId);
    delete sourcesById[sourceId];
    delete sourceCache[sourceId];

    if (currentSourceId === sourceId) {
        const next = sources[0];
        if (next) {
            setCurrentSource(next.id, {forceReload: true});
        }
    }

    renderSourcesModal();
    showToast('Source removed');
}


function setCurrentSource(sourceId, {forceReload = false} = {}) {
    if (!sourcesById[sourceId]) {
        return;
    }
    currentSourceId = sourceId;
    updateSourceHeader();
    loadSourceData(sourceId, {forceReload}).then(() => {
        words = sourceCache[sourceId].words.slice();
        filtered = words.slice();
        displayed = 0;
        document.getElementById('search-box').value = '';
        renderRows();
        wireDelete();
        updateLoadMoreVisibility();
    }).catch(err => {
        console.error(err);
        showToast('Failed to load source');
    });
    const url = new URL(window.location.href);
    url.searchParams.set('source', sourceId);
    window.history.replaceState({}, '', url);
}

function updateSourceHeader() {
    const src = sourcesById[currentSourceId];
    if (!src) return;
    const link = document.getElementById('current-source-link');
    link.href = src.repoUrl;
    link.textContent = src.name;

    const syncText = document.getElementById('sync-text');
    if (syncText) {
        syncText.textContent = `Sync ${src.name}`;
    }
}

async function loadSourceData(sourceId, {forceReload = false} = {}) {
    const cached = sourceCache[sourceId];
    if (cached && cached.loaded && !forceReload) {
        return cached;
    }

    const body = `action=load&source=${encodeURIComponent(sourceId)}`;
    const resp = await fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
    const json = await resp.json();
    if (!json.ok) {
        throw new Error(json.error || 'load failed');
    }
    setCache(sourceId, {words: json.words || [], connected: json.connected});
    return sourceCache[sourceId];
}

function updateLoadMoreVisibility() {
    const btn = document.getElementById('load-more-btn');
    if (!sourceCache[currentSourceId]?.connected) {
        btn.style.display = 'none';
        return;
    }
    btn.style.display = displayed >= filtered.length ? 'none' : '';
}

async function syncSource(sourceId) {
    const status = document.getElementById('sync-status');
    const spinner = document.getElementById('sync-spinner');
    const text = document.getElementById('sync-text');
    const btn = document.getElementById('sync-btn');

    status.style.visibility = 'hidden';
    btn.disabled = true;
    text.style.display = 'none';
    spinner.style.display = 'inline-block';

    const body = `action=sync&source=${encodeURIComponent(sourceId)}`;
    try {
        const r = await fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
        const resp = await r.json();
        spinner.style.display = 'none';
        text.style.display = '';
        btn.disabled = false;
            if (resp.ok) {
            showToast(`Sync successful (${resp.count} words)`);
            await loadSourceData(sourceId, {forceReload: true});
            if (currentSourceId === sourceId) {
                setCurrentSource(sourceId, {forceReload: true});
            }
            renderSourcesModal();
        } else {
            showToast('Sync failed: ' + (resp.error || 'unknown'));
        }
    } catch (err) {
        spinner.style.display = 'none';
        text.style.display = '';
        btn.disabled = false;
        console.error(err);
        showToast('Sync error');
    }
}

async function disconnectSource(sourceId) {
    const body = `action=disconnect&source=${encodeURIComponent(sourceId)}`;
    await fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
    setCache(sourceId, {words: [], connected: false});
    if (currentSourceId === sourceId) {
        setCurrentSource(sourceId, {forceReload: true});
    }
    renderSourcesModal();
}

function initNavigation() {
    document.getElementById('source-prev').addEventListener('click', () => {
        const idx = sources.findIndex(s => s.id === currentSourceId);
        const next = sources[(idx - 1 + sources.length) % sources.length];
        setCurrentSource(next.id);
    });
    document.getElementById('source-next').addEventListener('click', () => {
        const idx = sources.findIndex(s => s.id === currentSourceId);
        const next = sources[(idx + 1) % sources.length];
        setCurrentSource(next.id);
    });
    document.getElementById('sources-btn').addEventListener('click', openSourcesModal);
    document.getElementById('sources-modal-close').addEventListener('click', () => closeModal('sources-modal'));
    document.getElementById('sources-modal-close-bottom').addEventListener('click', () => closeModal('sources-modal'));
    document.getElementById('sources-modal').addEventListener('click', e => {
        if (e.target.id === 'sources-modal') {
            closeModal('sources-modal');
        }
    });
    document.getElementById('add-source-modal-close').addEventListener('click', closeAddSourceModal);
    document.getElementById('add-source-cancel').addEventListener('click', closeAddSourceModal);
    document.getElementById('add-source-submit').addEventListener('click', addCustomSource);
    document.getElementById('add-source-modal').addEventListener('click', e => {
        if (e.target.id === 'add-source-modal') {
            closeAddSourceModal();
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (document.getElementById('sources-modal').getAttribute('aria-hidden') === 'false') {
                closeModal('sources-modal');
            }
            if (document.getElementById('add-source-modal').getAttribute('aria-hidden') === 'false') {
                closeAddSourceModal();
            }
        }
    });
}

function initDeleteModal() {
    document.getElementById('delete-modal').addEventListener('click', e => {
        if (e.target.id === 'delete-modal') {
            closeModal();
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('delete-modal').getAttribute('aria-hidden') === 'false') {
            closeModal();
        }
    });
    document.getElementById('delete-modal-close').addEventListener('click', () => closeModal());
    document.getElementById('delete-cancel').addEventListener('click', () => closeModal());
    document.getElementById('delete-confirm').addEventListener('click', () => {
        if (!pendingDelete) return;
        const body = `action=delete&source=${encodeURIComponent(currentSourceId)}&word=${encodeURIComponent(pendingDelete)}`;
        fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body})
            .then(r => r.json()).then(resp => {
                if (resp.ok) {
                    closeModal();
                    setCurrentSource(currentSourceId, {forceReload: true});
                } else {
                    showToast(resp.error || 'Delete failed');
                }
            }).catch(err => {
                console.error(err);
                showToast('Delete error');
            });
    });
}

function initSearch() {
    const searchBox = document.getElementById('search-box');
    const spinner = document.getElementById('search-spinner');
    let searchTimeout;
    let spinnerVisibleSince = 0;

    searchBox.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        if (spinner.style.display !== 'inline-block') {
            spinner.style.display = 'inline-block';
            spinnerVisibleSince = Date.now();
        }
        searchTimeout = setTimeout(() => {
            const term = searchBox.value.trim().toLowerCase();
            if (term === '') {
                filtered = words.slice();
            } else {
                filtered = words.filter(w => w.toLowerCase().includes(term));
            }
            displayed = 0;
            tbody.innerHTML = '';
            renderRows();
            wireDelete();
            updateLoadMoreVisibility();
            const elapsed = Date.now() - spinnerVisibleSince;
            const remaining = 300 - elapsed;
            if (remaining > 0) {
                setTimeout(() => { spinner.style.display = 'none'; }, remaining);
            } else {
                spinner.style.display = 'none';
            }
        }, 150);
    });
}

function initLoadMore() {
    document.getElementById('load-more-btn').addEventListener('click', () => {
        const btn = document.getElementById('load-more-btn');
        const spinner = document.getElementById('load-more-spinner');
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        setTimeout(() => {
            renderRows();
            wireDelete();
            btn.disabled = false;
            spinner.style.display = 'none';
            updateLoadMoreVisibility();
        }, 200);
    });
}

function initSync() {
    document.getElementById('sync-btn').addEventListener('click', () => syncSource(currentSourceId));
}

function init() {
    updateSourceHeader();
    initNavigation();
    initDeleteModal();
    initSearch();
    initLoadMore();
    initSync();
    setCurrentSource(currentSourceId, {forceReload: false});
}

init();

