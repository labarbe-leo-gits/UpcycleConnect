(function () {
    var currentSecret = '';

    function mfaPost(formData) {
        formData.append('form_type', formData.get('form_type_val'));
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(function (r) { return r.json(); });
    }

    function postMFA(formType, extra) {
        var fd = new FormData();
        fd.append('form_type', formType);
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); });
    }

    function setFeedback(el, msg, isError) {
        if (!el) return;
        el.innerHTML = '<div class="' + (isError ? 'error-message' : 'success-message') + '">' + msg + '</div>';
    }

    var setupBtn = document.getElementById('mfa-setup-btn');
    if (setupBtn) {
        setupBtn.addEventListener('click', function () {
            setupBtn.disabled = true;
            setupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            postMFA('mfa_setup').then(function (data) {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                if (!data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Error.', true);
                    return;
                }
                currentSecret = data.secret;
                document.getElementById('mfa-secret-display').textContent = data.secret;
                var qrEl = document.getElementById('mfa-qr-code');
                qrEl.innerHTML = '';
                function renderQR() {
                    if (typeof QRCode === 'undefined') { setTimeout(renderQR, 100); return; }
                    new QRCode(qrEl, { text: data.otp_url, width: 200, height: 200 });
                }
                renderQR();
                document.getElementById('mfa-setup-panel').style.display = 'block';
                setupBtn.style.display = 'none';
            }).catch(function () {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var enableBtn = document.getElementById('mfa-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            var code = (document.getElementById('mfa-verify-code').value || '').trim();
            if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Please enter a valid 6-digit code.', true);
                return;
            }
            enableBtn.disabled = true;
            enableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            postMFA('mfa_enable', { secret: currentSecret, code: code }).then(function (data) {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-setup-feedback'), '2FA enabled successfully! Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-setup-feedback'), data.message || 'Invalid code.', true);
                }
            }).catch(function () {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var disableBtn = document.getElementById('mfa-disable-btn');
    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to disable 2FA? This will make your account less secure.')) return;
            disableBtn.disabled = true;
            disableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Disabling...';
            postMFA('mfa_disable').then(function (data) {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), '2FA disabled. Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Unable to disable 2FA.', true);
                }
            }).catch(function () {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var otpInput = document.getElementById('mfa-verify-code');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
})();

(function () {
    'use strict';

    var LIMIT = 6;
    var state = { page: 1, total: 0, loaded: false, loading: false };
    var cache = {};
    var UPDOC_BASE_PATH = typeof window.UPDOC_BASE_PATH !== 'undefined' ? window.UPDOC_BASE_PATH : '';
    var UPDOC_API_PATH  = typeof window.UPDOC_API_PATH !== 'undefined' ? window.UPDOC_API_PATH : 'updoc-api';

    var tabBtn   = document.querySelector('[data-tab="myupdoc"]');
    var grid     = document.getElementById('updoc-project-grid');
    var skelGrid = document.getElementById('updoc-skel-grid');
    var emptyMsg = document.getElementById('updoc-empty-msg');
    var pagination = document.getElementById('updoc-pagination');
    var prevBtn  = document.getElementById('updoc-prev-btn');
    var nextBtn  = document.getElementById('updoc-next-btn');
    var pageInfo = document.getElementById('updoc-page-info');

    if (!tabBtn || !grid) return;

    /* ---- Delete modal helpers ---- */
    var delModal   = document.getElementById('updoc-delete-modal');
    var delName    = document.getElementById('updoc-delete-name');
    var delConfirm = document.getElementById('updoc-delete-confirm');
    var delCancel  = document.getElementById('updoc-delete-cancel');
    var delClose   = document.getElementById('updoc-delete-close');
    var pendingDelete = null;

    function openDeleteModal(title, callback) {
        pendingDelete = callback;
        delName.textContent = title || 'this project';
        delModal.classList.add('is-visible');
        delModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }
    function closeDeleteModal() {
        pendingDelete = null;
        delModal.classList.remove('is-visible');
        delModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }
    delConfirm.addEventListener('click', function () {
        if (pendingDelete) pendingDelete();
        closeDeleteModal();
    });
    delCancel.addEventListener('click', closeDeleteModal);
    delClose.addEventListener('click', closeDeleteModal);
    delModal.addEventListener('click', function (e) {
        if (e.target === delModal) closeDeleteModal();
    });

    function setNavState() {
        prevBtn.disabled = state.page <= 1;
        nextBtn.disabled = state.page * LIMIT >= state.total;
        var from = (state.page - 1) * LIMIT + 1;
        var to   = Math.min(state.page * LIMIT, state.total);
        pageInfo.textContent = state.total > 0 ? (from + '–' + to + ' of ' + state.total) : '';
        pagination.style.display = state.total > LIMIT ? 'flex' : 'none';
    }

    function show(el, display) { el.style.display = display || 'block'; }
    function hide(el) { el.style.display = 'none'; }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) { return escHtml(str); }

    function fmtDate(str) {
        if (!str) return '';
        var ts = Date.parse(str);
        if (isNaN(ts)) return str;
        return new Date(ts).toLocaleDateString('fr-FR');
    }

    function buildProjectCard(item) {
        var el = document.createElement('div');
        el.className = 'updoc-proj-card';

        var statusNum  = parseInt(item.status ?? 0);
        var statusLabel = statusNum === 1 ? 'Published' : 'Draft';
        var statusCls   = statusNum === 1 ? 'published' : 'draft';
        var desc   = item.description ? item.description.replace(/[#*_`]/g, '').trim() : '';
        var date   = fmtDate(item.created_at);
        var projId = escAttr(item.id || '');

        var aiGenerated = parseInt(item.ai_generated ?? 0) === 1;
        var viewUrl = UPDOC_BASE_PATH + 'updoc-view?id=' + projId;
        var editUrl = UPDOC_BASE_PATH + 'updoc?id=' + projId;

        el.innerHTML =
            '<div class="updoc-proj-card-body">' +
              '<div class="updoc-proj-card-title">' + escHtml(item.title || 'Untitled') + '</div>' +
              (desc ? '<div class="updoc-proj-card-desc">' + escHtml(desc) + '</div>' : '') +
              '<div class="updoc-proj-card-meta">' +
                '<span class="updoc-proj-status ' + statusCls + '">' + escHtml(statusLabel) + '</span>' +
                (aiGenerated ? '<span class="updoc-ai-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> AI</span>' : '') +
                (date ? '<span><i class="fa-regular fa-calendar"></i> ' + escHtml(date) + '</span>' : '') +
              '</div>' +
            '</div>' +
            '<div class="updoc-proj-card-actions">' +
              '<a href="' + viewUrl + '" class="updoc-proj-action-btn">' +
                '<i class="fa-solid fa-eye"></i> View' +
              '</a>' +
              '<a href="' + editUrl + '" class="updoc-proj-action-btn">' +
                '<i class="fa-solid fa-pen"></i> Edit' +
              '</a>' +
              '<button type="button" class="updoc-proj-action-btn danger" data-delete-id="' + projId + '">' +
                '<i class="fa-solid fa-trash"></i> Delete' +
              '</button>' +
            '</div>';

        el.querySelector('.danger').addEventListener('click', function () {
            openDeleteModal(item.title, function () {
                fetch(UPDOC_API_PATH, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ action: 'delete_project', project_id: item.id })
                })
                .then(function (r) { return r.json(); })
                .then(function () {
                    el.remove();
                    state.total = Math.max(0, state.total - 1);
                    if (grid.children.length === 0) {
                        hide(grid);
                        show(emptyMsg);
                    }
                    setNavState();
                    cache = {};
                });
            });
        });

        return el;
    }

    function fetchPage(page) {
        if (state.loading) return;
        if (cache[page]) {
            state.page = page;
            render(cache[page].items, cache[page].total);
            return;
        }
        state.loading = true;
        hide(grid);
        hide(emptyMsg);
        show(skelGrid, 'grid');

        var apiUrl = (window.profileSectionApiPath || 'profile-section-api')
            + '?section=projects&page=' + page + '&limit=' + LIMIT;

        fetch(apiUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            state.loading = false;
            state.loaded  = true;
            state.page    = page;
            state.total   = data.total || 0;
            cache[page]   = { items: data.items || [], total: data.total || 0 };
            hide(skelGrid);
            render(data.items || [], data.total || 0);
        })
        .catch(function () {
            state.loading = false;
            hide(skelGrid);
            show(emptyMsg);
            emptyMsg.textContent = 'Unable to load projects. Please try again.';
        });
    }

    function render(items, total) {
        grid.innerHTML = '';
        state.total = total;
        if (!items.length) {
            hide(grid);
            show(emptyMsg);
            pagination.style.display = 'none';
            return;
        }
        hide(emptyMsg);
        show(grid, 'grid');
        items.forEach(function (item) {
            grid.appendChild(buildProjectCard(item));
        });
        setNavState();
    }

    tabBtn.addEventListener('click', function () {
        if (!state.loaded && !state.loading) {
            fetchPage(1);
        }
    });

    if (prevBtn) prevBtn.addEventListener('click', function () { fetchPage(state.page - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { fetchPage(state.page + 1); });

})();