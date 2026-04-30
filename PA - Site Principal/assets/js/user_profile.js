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

function getApiBase() {
    if (typeof window.API_BASE === 'string' && window.API_BASE.trim() !== '') {
        console.log('[getApiBase] Using window.API_BASE:', window.API_BASE);
        return window.API_BASE.replace(/\/$/, '');
    }
    var headerApiBase = document.querySelector('header')?.dataset.apiBase;
    if (typeof headerApiBase === 'string' && headerApiBase.trim() !== '') {
        console.log('[getApiBase] Using header data-api-base:', headerApiBase);
        return headerApiBase.replace(/\/$/, '');
    }
    var fallback = 'http://' + window.location.hostname + ':9999';
    console.log('[getApiBase] Using fallback:', fallback);
    return fallback;
}

function authedFetch(url, options = {}) {
    let token = getAuthToken();
    if (!token) {
        throw new Error("You must be logged in.");
    }
    const headers = { 'Authorization': `Bearer ${token}` };
    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }
    options.headers = { ...headers, ...(options.headers || {}) };
    const base = getApiBase();
    const fullUrl = base + url;
    
    console.log('[authedFetch] URL:', fullUrl, 'Method:', options.method || 'GET');
    
    return fetch(fullUrl, options).catch(error => {
        console.error('[authedFetch] Network error:', error, 'URL was:', fullUrl);
        throw new Error(`Network error: ${error.message}. Could not reach API at ${base}`);
    });
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

var reviewState = {
    loaded: false,
    currentUserReviewId: null,
    currentReviewRating: 0,
    currentReviewComment: '',
    cachedReviewers: {}
};

function createRatingIcons(rating, count, baseClass) {
    var html = '';
    for (var i = 1; i <= count; i++) {
        var classes = ['fa-solid', 'fa-recycle'];
        if (i <= rating) classes.push('filled');
        html += '<i class="' + classes.join(' ') + '"></i>';
    }
    return '<span class="' + (baseClass || 'review-rating-icon-group') + '">' + html + '</span>';
}

function formatReviewDate(timestamp) {
    if (!timestamp) return '';
    var date = new Date(timestamp);
    if (isNaN(date.getTime())) {
        return timestamp.split('T')[0] || timestamp;
    }
    return date.toLocaleDateString();
}

function setReviewPickerValue(rating) {
    reviewState.currentReviewRating = rating;
    var picker = document.getElementById('review-rating-picker');
    if (!picker) return;
    picker.querySelectorAll('button').forEach(function(button) {
        var value = parseInt(button.dataset.rating, 10);
        button.classList.toggle('selected', value <= rating);
    });
}

function buildReviewRatingPicker(selectedRating) {
    var picker = document.getElementById('review-rating-picker');
    if (!picker) return;
    picker.innerHTML = '';
    for (var i = 1; i <= 5; i++) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.rating = i;
        btn.title = i + ' out of 5';
        btn.innerHTML = '<i class="fa-solid fa-recycle"></i>';
        btn.className = i <= selectedRating ? 'selected' : '';
        btn.addEventListener('click', function() {
            var value = parseInt(this.dataset.rating, 10);
            setReviewPickerValue(value);
        });
        picker.appendChild(btn);
    }
    if (selectedRating > 0) {
        setReviewPickerValue(selectedRating);
    }
}

async function lookupReviewerName(userId) {
    if (!userId) return 'Unknown reviewer';
    if (reviewState.cachedReviewers[userId]) {
        return reviewState.cachedReviewers[userId];
    }
    try {
        var res = await fetch(getApiBase() + '/users/' + encodeURIComponent(userId));
        if (!res.ok) {
            throw new Error('Unable to load reviewer');
        }
        var data = await res.json();
        var name = data.username || [data.first_name, data.last_name].filter(Boolean).join(' ') || data.id || 'Reviewer';
        reviewState.cachedReviewers[userId] = name;
        return name;
    } catch (e) {
        console.error('[lookupReviewerName] Error:', e);
        reviewState.cachedReviewers[userId] = 'Reviewer';
        return 'Reviewer';
    }
}

async function renderReviewsTab() {
    var reviewsList = document.getElementById('reviews-list');
    var reviewsEmpty = document.getElementById('reviews-empty');
    var avgScoreEl = document.getElementById('reviews-average-score');
    var avgStarsEl = document.getElementById('reviews-average-stars');
    var avgCountEl = document.getElementById('reviews-average-count');
    var formContainer = document.getElementById('review-form-container');
    var actionTitle = document.getElementById('reviews-action-title');
    var actionNote = document.getElementById('reviews-action-note');
    var formTitle = document.getElementById('review-form-title');
    var formNote = document.getElementById('review-form-note');
    var formError = document.getElementById('review-form-error');

    if (!reviewsList || !avgScoreEl || !avgStarsEl || !avgCountEl || !formContainer) {
        return;
    }

    reviewsList.innerHTML = '';
    reviewsEmpty.textContent = 'Loading reviews...';
    reviewsEmpty.style.display = 'block';
    formError.textContent = '';

    var currentUserId = getCurrentUserId();
    var isOwnProfile = currentUserId && window.publicUserId === currentUserId;

    if (isOwnProfile) {
        actionTitle.textContent = 'Your profile cannot be reviewed by yourself';
        actionNote.textContent = 'Users can review your profile from their accounts.';
        formContainer.style.display = 'none';
    }

    try {
        var res = await fetch(getApiBase() + '/users/' + encodeURIComponent(window.publicUserId) + '/reviews');
        if (!res.ok) {
            throw new Error('Unable to load reviews');
        }
        var reviews = await res.json();
        if (!Array.isArray(reviews)) {
            reviews = [];
        }
        var average = 0;
        if (reviews.length > 0) {
            average = reviews.reduce(function(sum, review) {
                return sum + (review.rating || 0);
            }, 0) / reviews.length;
        }
        avgScoreEl.textContent = reviews.length > 0 ? average.toFixed(1) + ' / 5' : '0.0 / 5';
        avgStarsEl.innerHTML = createRatingIcons(Math.round(average), 5, 'review-average-stars');
        avgCountEl.textContent = reviews.length > 0 ? reviews.length + ' review' + (reviews.length > 1 ? 's' : '') : 'No reviews yet';

        if (!reviews.length) {
            reviewsEmpty.textContent = 'No reviews have been submitted for this user yet.';
            reviewsEmpty.style.display = 'block';
        } else {
            reviewsEmpty.style.display = 'none';
        }

        var currentUserReview = null;
        if (currentUserId) {
            currentUserReview = reviews.find(function(review) {
                return review.reviewer_id === currentUserId;
            });
        }
        reviewState.currentUserReviewId = currentUserReview ? currentUserReview.id : null;
        reviewState.currentReviewRating = currentUserReview ? currentUserReview.rating : 0;
        reviewState.currentReviewComment = currentUserReview ? (currentUserReview.comment || '') : '';

        if (!isOwnProfile) {
            if (!getAuthToken()) {
                formContainer.style.display = 'none';
                actionTitle.textContent = 'Log in to leave a review';
                actionNote.textContent = 'Only authenticated users can submit reviews.';
            } else {
                formContainer.style.display = 'block';
                formTitle.textContent = currentUserReview ? 'Edit your review' : 'Leave a review';
                formNote.textContent = currentUserReview ? 'Update your score and comment anytime.' : 'Click a recycling icon to choose a rating.';
            }
        }

        if (reviews.length > 0) {
            var fragment = document.createDocumentFragment();
            for (var i = 0; i < reviews.length; i++) {
                var review = reviews[i];
                var card = document.createElement('article');
                card.className = 'review-card';
                var authorName = review.reviewer_id === currentUserId ? 'You' : 'Reviewer';
                card.innerHTML =
                    '<div class="review-card-header">' +
                        '<div>' +
                            '<p class="review-card-author">' + escapeHtml(authorName) + '</p>' +
                            '<p class="review-card-meta"><span class="review-author" data-reviewer-id="' + escapeHtml(review.reviewer_id) + '">' + (authorName === 'You' ? 'You' : 'Reviewer') + '</span> • ' + escapeHtml(formatReviewDate(review.updated_at || review.created_at)) + '</p>' +
                        '</div>' +
                        '<div>' + createRatingIcons(review.rating || 0, 5, 'review-rating-icon') + '</div>' +
                    '</div>' +
                    '<div class="review-card-comment">' + escapeHtml(review.comment || '') + '</div>';
                fragment.appendChild(card);
            }
            reviewsList.appendChild(fragment);
            var reviewerSpans = reviewsList.querySelectorAll('.review-author[data-reviewer-id]');
            reviewerSpans.forEach(function(span) {
                var reviewerId = span.dataset.reviewerId;
                if (!reviewerId || reviewerId === currentUserId) {
                    if (reviewerId === currentUserId) span.textContent = 'You';
                    return;
                }
                lookupReviewerName(reviewerId).then(function(name) {
                    span.textContent = name;
                });
            });
        }

        buildReviewRatingPicker(reviewState.currentReviewRating);
        var commentField = document.getElementById('review-comment');
        if (commentField) {
            commentField.value = reviewState.currentReviewComment;
        }

        var deleteButton = document.getElementById('btn-delete-review');
        if (deleteButton) {
            deleteButton.style.display = reviewState.currentUserReviewId ? 'block' : 'none';
        }
    } catch (e) {
        console.error('[renderReviewsTab] Error loading reviews:', e);
        reviewsEmpty.textContent = 'Unable to load reviews at the moment.';
        reviewsEmpty.style.display = 'block';
        avgScoreEl.textContent = '0.0 / 5';
        avgStarsEl.innerHTML = createRatingIcons(0, 5, 'review-average-stars');
        avgCountEl.textContent = 'Unable to load reviews';
    }
}

function setReviewSubmitLoading(isLoading) {
    var submitButton = document.getElementById('btn-submit-review');
    if (!submitButton) return;
    if (isLoading) {
        submitButton.dataset.originalText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
    } else {
        submitButton.disabled = false;
        submitButton.innerHTML = submitButton.dataset.originalText || 'Submit review';
    }
}

async function submitReview() {
    var commentField = document.getElementById('review-comment');
    var formError = document.getElementById('review-form-error');
    if (!commentField || !formError) return;
    formError.textContent = '';

    if (!window.publicUserId || !getAuthToken()) {
        formError.textContent = 'You must be logged in to submit a review.';
        return;
    }
    if (window.publicUserId === getCurrentUserId()) {
        formError.textContent = 'You cannot review yourself.';
        return;
    }
    if (!reviewState.currentReviewRating || reviewState.currentReviewRating < 1) {
        formError.textContent = 'Please choose a rating between 1 and 5.';
        return;
    }

    var payload = {
        rating: reviewState.currentReviewRating,
        comment: commentField.value.trim() || ''
    };

    try {
        setReviewSubmitLoading(true);
        var method = reviewState.currentUserReviewId ? 'PATCH' : 'POST';
        var url = '/users/' + encodeURIComponent(window.publicUserId) + '/reviews';
        if (reviewState.currentUserReviewId) {
            url += '/' + encodeURIComponent(reviewState.currentUserReviewId);
        }
        var res = await authedFetch(url, {
            method: method,
            body: JSON.stringify(payload)
        });

        if (!res.ok) {
            var errorText = 'Unable to save your review.';
            try {
                var data = await res.json();
                if (data && data.error) errorText = data.error;
            } catch (_) {}
            throw new Error(errorText);
        }
        await renderReviewsTab();
    } catch (e) {
        console.error('[submitReview] Error:', e);
        formError.textContent = e.message || 'An error occurred while saving your review.';
    } finally {
        setReviewSubmitLoading(false);
    }
}

function openDeleteReviewModal() {
    var modal = document.getElementById('modal-delete-review');
    if (!modal) return;
    var deleteError = document.getElementById('delete-review-error');
    if (deleteError) {
        deleteError.textContent = '';
        deleteError.classList.add('d-none');
    }
    openModal(modal);
}

async function deleteReview() {
    var modal = document.getElementById('modal-delete-review');
    var deleteError = document.getElementById('delete-review-error');
    var formError = document.getElementById('review-form-error');
    if (!formError) return;
    formError.textContent = '';
    if (!reviewState.currentUserReviewId) {
        return;
    }
    try {
        if (modal) {
            var confirmButton = document.getElementById('btn-confirm-delete-review');
            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.dataset.originalText = confirmButton.innerHTML;
                confirmButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Deleting...';
            }
        }
        var res = await authedFetch('/users/' + encodeURIComponent(window.publicUserId) + '/reviews/' + encodeURIComponent(reviewState.currentUserReviewId), {
            method: 'DELETE'
        });
        if (!res.ok) {
            var errorText = 'Unable to remove your review.';
            try {
                var data = await res.json();
                if (data && data.error) errorText = data.error;
            } catch (_) {}
            throw new Error(errorText);
        }
        if (modal) {
            closeModal(modal);
        }
        reviewState.currentUserReviewId = null;
        reviewState.currentReviewRating = 0;
        reviewState.currentReviewComment = '';
        buildReviewRatingPicker(0);
        var commentField = document.getElementById('review-comment');
        if (commentField) commentField.value = '';
        await renderReviewsTab();
    } catch (e) {
        console.error('[deleteReview] Error:', e);
        if (deleteError) {
            deleteError.textContent = e.message || 'An error occurred while deleting your review.';
            deleteError.classList.remove('d-none');
        } else {
            formError.textContent = e.message || 'An error occurred while deleting your review.';
        }
    } finally {
        if (modal) {
            var confirmButton = document.getElementById('btn-confirm-delete-review');
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.innerHTML = confirmButton.dataset.originalText || 'Delete';
            }
        }
    }
}

async function initReviewsTab() {
    if (reviewState.loaded) {
        return;
    }
    reviewState.loaded = true;
    buildReviewRatingPicker(0);
    var submitButton = document.getElementById('btn-submit-review');
    var deleteButton = document.getElementById('btn-delete-review');
    var confirmDeleteButton = document.getElementById('btn-confirm-delete-review');
    if (submitButton) {
        submitButton.addEventListener('click', submitReview);
    }
    if (deleteButton) {
        deleteButton.addEventListener('click', openDeleteReviewModal);
    }
    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', deleteReview);
    }
    await renderReviewsTab();
}

function buildOfferCard(item) {
    var card = document.createElement('div');
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
    
    if (m && m.id === 'modal-friend-request') {
        const formState = document.getElementById('friend-request-form-state');
        const successState = document.getElementById('friend-request-success-state');
        const formActions = document.getElementById('friend-request-form-actions');
        const successActions = document.getElementById('friend-request-success-actions');
        
        if (formState) formState.classList.remove('d-none');
        if (successState) successState.classList.add('d-none');
        if (formActions) formActions.classList.remove('d-none');
        if (successActions) successActions.classList.add('d-none');
    }
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
        
        const formState = document.getElementById('friend-request-form-state');
        const successState = document.getElementById('friend-request-success-state');
        const formActions = document.getElementById('friend-request-form-actions');
        const successActions = document.getElementById('friend-request-success-actions');
        
        if (formState) formState.classList.remove('d-none');
        if (successState) successState.classList.add('d-none');
        if (formActions) formActions.classList.remove('d-none');
        if (successActions) successActions.classList.add('d-none');
        
        openModal(modalFriendReq);
    };
}

document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
    btn.onclick = (e) => closeModal(e.target.closest('.modal-overlay'));
});

const btnStartDiscussion = document.getElementById('btn-start-discussion');
if (btnStartDiscussion) {
    btnStartDiscussion.onclick = async () => {
        try {
            const currentUserId = getCurrentUserId();
            console.log('[Start Discussion] Creating discussion with user:', window.publicUserId);
            const res = await authedFetch('/users/' + encodeURIComponent(currentUserId) + '/discussions', {
                method: 'POST',
                body: JSON.stringify({ user1_id: currentUserId, user2_id: window.publicUserId })
            });
            
            if (res.ok) {
                const data = await res.json();
                console.log('[Start Discussion] Created discussion:', data);

                if (data.id) {
                    window.location.href = '../common/chat.php';
                }
            } else {
                const errorData = await res.json();
                throw new Error(errorData.error || 'Failed to create discussion');
            }
        } catch (e) {
            console.error('[Start Discussion] Error:', e);
            alert('Error starting discussion: ' + e.message);
        }
    };
}

const btnConfirmFriendRequest = document.getElementById('btn-confirm-friend-request');
if (btnConfirmFriendRequest) {
    btnConfirmFriendRequest.onclick = async () => {
        const messageField = document.getElementById('friend-request-message');
        const message = messageField ? messageField.value.trim() : '';
        try {
            console.log('[Friend Request] Sending request to:', window.targetUsername, 'Message:', message);
            const res = await authedFetch('/friends', {
                method: 'POST',
                body: JSON.stringify({ username: window.targetUsername, message: message })
            });
            
            console.log('[Friend Request] Response status:', res.status, res.statusText);
            
            if (res.ok) {

                const formState = document.getElementById('friend-request-form-state');
                const successState = document.getElementById('friend-request-success-state');
                const formActions = document.getElementById('friend-request-form-actions');
                const successActions = document.getElementById('friend-request-success-actions');
                
                if (formState) formState.classList.add('d-none');
                if (successState) successState.classList.remove('d-none');
                if (formActions) formActions.classList.add('d-none');
                if (successActions) successActions.classList.remove('d-none');
                
                if (btnAddFriend) {
                    btnAddFriend.disabled = true;
                    btnAddFriend.innerHTML = '<i class="fas fa-check"></i> Request Sent';
                }
            } else {
                let errorMsg = 'Failed to send request.';
                try {
                    const data = await res.json();
                    if (data.error) {
                        errorMsg = data.error;
                    }
                } catch (e) {
                    errorMsg = `HTTP ${res.status}: ${res.statusText}`;
                }
                throw new Error(errorMsg);
            }
        } catch (e) {
            console.error('[Friend Request] Error:', e);
            if (friendReqError) {
                friendReqError.textContent = e.message || 'An error occurred while sending the friend request.';
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
            if (target === 'reviews') {
                initReviewsTab();
            }
        });
    });

    if (document.querySelector('.tab-btn.active[data-tab="reviews"]')) {
        initReviewsTab();
    }

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

    var offersRoot = document.getElementById('acc-annonces');
    if (!offersRoot || !offersRoot.dataset.profileSectionInit) {
        initOffersAccordion();
    }
    var projectsRoot = document.getElementById('acc-projects');
    if (!projectsRoot || !projectsRoot.dataset.profileSectionInit) {
        initProjectsAccordion();
    }
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
        } else {
            
            btnAddFriend.style.display = 'inline-flex';
            btnAddFriend.disabled = false;
            btnAddFriend.innerHTML = '<i class="fas fa-user-plus"></i> Become Friend';
        }
    } catch (e) {
        console.error('Could not verify friendship status', e);
    }
}
