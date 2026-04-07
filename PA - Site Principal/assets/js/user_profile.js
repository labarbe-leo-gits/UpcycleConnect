const profileContainer = document.getElementById('profile-container');
const btnAddFriend = document.getElementById('btn-add-friend');
const modalFriendReq = document.getElementById('modal-friend-request');
const friendReqError = document.getElementById('friend-request-error');

function getAuthToken() {
    if (typeof window.API_TOKEN !== 'undefined' && window.API_TOKEN) {
        return window.API_TOKEN;
    }
    if (localStorage.getItem('jwt_token')) {
        return localStorage.getItem('jwt_token');
    }
    if (localStorage.getItem('token')) {
        return localStorage.getItem('token');
    }
    var match = document.cookie.match(/(?:^|; )token=([^;]+)/);
    if (match) {
        return decodeURIComponent(match[1]);
    }
    match = document.cookie.match(/(?:^|; )jwt_token=([^;]+)/);
    if (match) {
        return decodeURIComponent(match[1]);
    }
    return null;
}

function parseJwt(token) {
    try {
        var payload = token.split('.')[1];
        if (!payload) return null;
        payload = payload.replace(/-/g, '+').replace(/_/g, '/');
        var json = decodeURIComponent(atob(payload).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
        return JSON.parse(json);
    } catch (e) {
        return null;
    }
}

function getCurrentUserId() {
    var token = getAuthToken();
    if (!token) return null;
    var parsed = parseJwt(token);
    if (!parsed) return null;
    return parsed.user_id || parsed.sub || null;
}

async function authedFetch(url, options = {}) {
    let token = getAuthToken();
    if (!token) {
        throw new Error("You must be logged in.");
    }
    const headers = { 'Authorization': `Bearer ${token}` };
    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }
    options.headers = { ...headers, ...(options.headers || {}) };
    return fetch(`http://${window.location.hostname}:9999${url}`, options);
}

function drawGaugeCanvas(score) {
    var canvas = document.getElementById('upcycling-gauge-chart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var w = canvas.width;
    var h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    var radius = Math.min(w / 2, h) - 10;
    ctx.lineWidth = 10;
    ctx.strokeStyle = '#ddd';
    ctx.beginPath();
    ctx.arc(w / 2, h, radius, Math.PI, 0, false);
    ctx.stroke();
    var pct = Math.min(Math.max(score / 100, 0), 1);
    var color;
    if (score >= 100) {
        pct = 1;
        color = '#e11d48';
    } else if (score < 10) {
        color = '#10b981';
    } else if (score < 50) {
        color = '#facc15';
    } else if (score < 70) {
        color = '#f97316';
    } else {
        color = '#e11d48';
    }
    var endAngle = Math.PI + pct * Math.PI;
    ctx.strokeStyle = color;
    ctx.beginPath();
    ctx.arc(w / 2, h, radius, Math.PI, endAngle, false);
    ctx.stroke();
    ctx.strokeStyle = '#444';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w / 2, h);
    var nx = w / 2 + Math.cos(endAngle) * (radius - 5);
    var ny = h + Math.sin(endAngle) * (radius - 5);
    ctx.lineTo(nx, ny);
    ctx.stroke();
    ctx.fillStyle = '#444';
    ctx.beginPath();
    ctx.arc(w / 2, h, 4, 0, 2 * Math.PI);
    ctx.fill();
}

function updateGauge(score) {
    var targetPct = Math.min(Math.max(score / 100, 0), 1);
    var startPct = 0;
    var duration = 600;
    var startTime = null;
    function animate(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var currentPct = startPct + (targetPct - startPct) * progress;
        drawGaugeCanvas(currentPct * 100);
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    }
    requestAnimationFrame(animate);
}

function renderScoreGauge() {
    var scoreEl = document.getElementById('upcycling-score-value');
    if (!scoreEl) return;
    var text = scoreEl.textContent || '';
    var match = text.match(/^(\d+(?:\.\d+)?)/);
    if (!match) return;
    var score = parseFloat(match[1]);
    updateGauge(score);
}

function initProfilePictureLoader() {
    var picSection = document.querySelector('.profile-picture-section');
    var profileImg = document.getElementById('profile-pic-preview');
    if (!picSection || !profileImg) return;

    function markLoaded() {
        picSection.classList.add('loaded');
    }

    var placeholder = 'data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==';
    if (profileImg.complete && profileImg.naturalWidth > 1 && profileImg.src && profileImg.src !== placeholder) {
        markLoaded();
        return;
    }

    profileImg.addEventListener('load', function onLoad() {
        markLoaded();
        profileImg.removeEventListener('load', onLoad);
    });

    profileImg.addEventListener('error', function onError() {
        markLoaded();
        profileImg.removeEventListener('error', onError);
    });
}

function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildOfferCard(item) {
    var card = document.createElement('div');
    card.className = 'acc-card acc-card--annonce';
    card.setAttribute('role', 'listitem');

    var title = escapeHtml(item.title || 'Untitled offer');
    var category = escapeHtml(item.category_name || 'General');
    var price = parseFloat(item.price || 0).toFixed(2);
    var description = String(item.description || '');
    var desc = description.length > 90 ? escapeHtml(description.slice(0, 90) + '…') : escapeHtml(description);
    var image = item.image || null;

    var imageHtml = image
        ? '<div class="acc-card-thumb"><img src="' + encodeURI(image) + '" alt="" loading="lazy"></div>'
        : '<div class="acc-card-thumb acc-card-thumb--placeholder"><i class="fa-solid fa-image"></i></div>';

    card.innerHTML =
        imageHtml +
        '<div class="acc-card-body">' +
          '<div class="acc-card-title">' + title + '</div>' +
          '<div class="acc-card-meta"><span class="acc-status status-available">Available</span><span class="offer-chip offer-chip--category">' + category + '</span></div>' +
          '<div class="acc-card-amount">€ ' + price + ' <span class="acc-ttc-tag">TTC</span></div>' +
          '<div class="acc-card-date" style="margin-top:0.5rem;">' + (desc ? '<i class="fa-regular fa-note-sticky"></i> ' + desc : '') + '</div>' +
        '</div>';
    return card;
}

function buildProjectCard(item) {
    var card = document.createElement('div');
    card.className = 'acc-card acc-card--project';
    card.setAttribute('role', 'listitem');

    var title = escapeHtml(item.title || 'Untitled project');
    var description = String(item.description || '');
    var desc = description.length > 90 ? escapeHtml(description.slice(0, 90) + '…') : escapeHtml(description);
    var status = (parseInt(item.status || 0, 10) === 1) ? 'Published' : 'Draft';
    var date = item.created_at ? escapeHtml(item.created_at.split('T')[0]) : '';
    var viewUrl = 'updoc-view?id=' + encodeURIComponent(item.id || '');

    card.innerHTML =
        '<div class="acc-card-body">' +
          '<div class="acc-card-title">' + title + '</div>' +
          '<div class="acc-card-meta"><span class="acc-status ' + (status === 'Published' ? 'status-available' : 'status-unavailable') + '">' + escapeHtml(status) + '</span></div>' +
          (desc ? '<div class="acc-card-date" style="margin-top:0.5rem;">' + desc + '</div>' : '') +
          (date ? '<div class="acc-card-date" style="margin-top:0.35rem;"><i class="fa-regular fa-calendar"></i> ' + date + '</div>' : '') +
          '<div class="updoc-proj-card-actions" style="margin-top:0.75rem;"><a class="updoc-proj-action-btn updoc-proj-view-btn" href="' + escapeHtml(viewUrl) + '"><i class="fa-solid fa-eye"></i> View</a></div>' +
        '</div>';
    return card;
}

function initOffersAccordion() {
    var root = document.getElementById('acc-annonces');
    if (!root) return;
    var toggle = root.querySelector('.accordion-toggle');
    var body = root.querySelector('.accordion-body');
    var skel = root.querySelector('.acc-skeleton-row');
    var carousel = root.querySelector('.acc-carousel');
    var track = root.querySelector('.acc-track');
    var prevBtn = root.querySelector('.acc-prev');
    var nextBtn = root.querySelector('.acc-next');
    var pageInfo = root.querySelector('.acc-page-info');
    var emptyMsg = root.querySelector('.acc-empty');
    var state = { loaded: false, page: 1, limit: 4, total: Array.isArray(window.publicOffers) ? window.publicOffers.length : 0 };

    function show(el, display) { if (el) el.style.display = display || 'block'; }
    function hide(el) { if (el) el.style.display = 'none'; }

    function updateNavigation() {
        if (prevBtn) prevBtn.disabled = state.page <= 1;
        if (nextBtn) nextBtn.disabled = state.page * state.limit >= state.total;
        if (pageInfo) {
            var from = state.total > 0 ? (state.page - 1) * state.limit + 1 : 0;
            var to = Math.min(state.page * state.limit, state.total);
            pageInfo.textContent = state.total > 0 ? from + '–' + to + ' of ' + state.total : '';
        }
    }

    function renderPage(page) {
        state.page = page;
        var offers = Array.isArray(window.publicOffers) ? window.publicOffers : [];
        var start = (state.page - 1) * state.limit;
        var items = offers.slice(start, start + state.limit);
        track.innerHTML = '';
        hide(skel);
        if (!items.length) {
            hide(carousel);
            show(emptyMsg);
            return;
        }
        hide(emptyMsg);
        show(carousel);
        items.forEach(function(item) {
            track.appendChild(buildOfferCard(item));
        });
        updateNavigation();
    }

    function openAccordion() {
        if (!body || !toggle) return;
        if (root.classList.contains('is-open')) {
            hide(body);
            toggle.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
            return;
        }
        show(body, 'block');
        toggle.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        if (!state.loaded) {
            show(skel, 'grid');
            hide(carousel);
            hide(emptyMsg);
            setTimeout(function() {
                renderPage(1);
                state.loaded = true;
            }, 250);
        }
    }

    if (toggle) {
        toggle.addEventListener('click', openAccordion);
    }
    if (prevBtn) prevBtn.addEventListener('click', function() { renderPage(Math.max(1, state.page - 1)); });
    if (nextBtn) nextBtn.addEventListener('click', function() { renderPage(state.page + 1); });
}

function initProjectsAccordion() {
    var root = document.getElementById('acc-projects');
    if (!root) return;
    var toggle = root.querySelector('.accordion-toggle');
    var body = root.querySelector('.accordion-body');
    var skel = root.querySelector('.acc-skeleton-row');
    var carousel = root.querySelector('.acc-carousel');
    var track = root.querySelector('.acc-track');
    var prevBtn = root.querySelector('.acc-prev');
    var nextBtn = root.querySelector('.acc-next');
    var pageInfo = root.querySelector('.acc-page-info');
    var emptyMsg = root.querySelector('.acc-empty');
    var state = { loaded: false, page: 1, limit: 4, total: Array.isArray(window.publicProjects) ? window.publicProjects.length : 0 };

    function show(el, display) { if (el) el.style.display = display || 'block'; }
    function hide(el) { if (el) el.style.display = 'none'; }

    function updateNavigation() {
        if (prevBtn) prevBtn.disabled = state.page <= 1;
        if (nextBtn) nextBtn.disabled = state.page * state.limit >= state.total;
        if (pageInfo) {
            var from = state.total > 0 ? (state.page - 1) * state.limit + 1 : 0;
            var to = Math.min(state.page * state.limit, state.total);
            pageInfo.textContent = state.total > 0 ? from + '–' + to + ' of ' + state.total : '';
        }
    }

    function renderPage(page) {
        state.page = page;
        var projects = Array.isArray(window.publicProjects) ? window.publicProjects : [];
        var start = (state.page - 1) * state.limit;
        var items = projects.slice(start, start + state.limit);
        track.innerHTML = '';
        hide(skel);
        if (!items.length) {
            hide(carousel);
            show(emptyMsg);
            return;
        }
        hide(emptyMsg);
        show(carousel);
        items.forEach(function(item) {
            track.appendChild(buildProjectCard(item));
        });
        updateNavigation();
    }

    function openAccordion() {
        if (!body || !toggle) return;
        if (root.classList.contains('is-open')) {
            hide(body);
            toggle.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
            return;
        }
        show(body, 'block');
        toggle.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        if (!state.loaded) {
            show(skel, 'grid');
            hide(carousel);
            hide(emptyMsg);
            setTimeout(function() {
                renderPage(1);
                state.loaded = true;
            }, 250);
        }
    }

    if (toggle) {
        toggle.addEventListener('click', openAccordion);
    }
    if (prevBtn) prevBtn.addEventListener('click', function() { renderPage(Math.max(1, state.page - 1)); });
    if (nextBtn) nextBtn.addEventListener('click', function() { renderPage(state.page + 1); });
}

function openModal(m) {
    m.classList.add('is-visible');
    m.classList.add('visible');
    m.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}
function closeModal(m) {
    m.classList.remove('is-visible');
    m.classList.remove('visible');
    m.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

if (btnAddFriend) {
    btnAddFriend.onclick = () => {
        const messageField = document.getElementById('friend-request-message');
        if (messageField) {
            messageField.value = '';
        }
        if (friendReqError) {
            friendReqError.classList.add('d-none');
        }
        openModal(modalFriendReq);
    };
}

document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
    btn.onclick = (e) => closeModal(e.target.closest('.modal-overlay'));
});

const btnConfirmFriendRequest = document.getElementById('btn-confirm-friend-request');
if (btnConfirmFriendRequest) {
    btnConfirmFriendRequest.onclick = async () => {
        const messageField = document.getElementById('friend-request-message');
        const message = messageField ? messageField.value.trim() : '';
        try {
            const res = await authedFetch('/friends', {
                method: 'POST',
                body: JSON.stringify({ username: window.targetUsername, message: message })
            });
            
            if (res.ok) {
                closeModal(modalFriendReq);
                alert('Friend request sent!');
                if (btnAddFriend) {
                    btnAddFriend.disabled = true;
                    btnAddFriend.innerHTML = '<i class="fas fa-check"></i> Request Sent';
                }
            } else {
                const data = await res.json();
                throw new Error(data.error || 'Failed to send request.');
            }
        } catch (e) {
            if (friendReqError) {
                friendReqError.textContent = e.message;
                friendReqError.classList.remove('d-none');
            }
        }
    };
}

document.addEventListener('DOMContentLoaded', () => {
    if (profileContainer && typeof loadProfile === 'function') {
        loadProfile();
    }
    renderScoreGauge();
    initProfilePictureLoader();

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(tc => {
                tc.style.display = 'none';
            });
            const tabPanel = document.getElementById(target + '-tab');
            if (tabPanel) {
                tabPanel.style.display = '';
            }
            if (target === 'upcyclingScore') {
                renderScoreGauge();
            }
        });
    });

    document.querySelectorAll('.btn-copy').forEach(function(btn) {
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
            }
        });
    });

    initOffersAccordion();
    initProjectsAccordion();
    hideFriendButtonIfAlreadyRequested();
});

async function hideFriendButtonIfAlreadyRequested() {
    if (!btnAddFriend || !window.publicUserId) return;
    var currentUserId = getCurrentUserId();
    if (!currentUserId) return;

    try {
        const res = await authedFetch('/friends/status/' + encodeURIComponent(window.publicUserId));
        if (!res.ok) return;
        const data = await res.json();
        if (data.exists) {
            btnAddFriend.style.display = 'none';
        }
    } catch (e) {
        console.error('Could not verify friendship status', e);
    }
}
