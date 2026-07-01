document.addEventListener('DOMContentLoaded', () => {
    const API_URL = (window.API_BASE || (window.location.protocol + '//' + window.location.hostname + ':9999')).replace(/\/$/, '');
    const token = localStorage.getItem('token') || localStorage.getItem('jwt_token') || getCookie('token') || getCookie('jwt_token');
    const currentUserId = parseJwt(token)?.user_id;

    const requestsContainer = document.getElementById('friend-requests-carousel');
    const requestsPagination = document.getElementById('requests-pagination');
    const requestsPrev = document.getElementById('requests-prev');
    const requestsNext = document.getElementById('requests-next');
    const requestsSummary = document.getElementById('requests-status-summary');
    const friendsList = document.getElementById('friends-list');
    const friendsPagination = document.getElementById('friends-pagination');
    const friendsSummary = document.getElementById('friends-summary');
    const friendsError = document.getElementById('friends-error');

    const requestsPerPage = 3;
    const friendsPerPage = 8;
    let friendRequests = [];
    let acceptedFriends = [];
    let requestPage = 0;
    let friendsPage = 0;
    const userDetailsCache = {};

    if (!token || !currentUserId) {
        showError(t('common.you.must.be.logged.in.to.manage.friends', 'You must be logged in to manage friends.'));
        return;
    }

    renderRequestsSkeleton();
    renderFriendsSkeleton();
    loadFriends();

    requestsPrev.addEventListener('click', () => {
        if (requestPage > 0) {
            requestPage -= 1;
            renderRequestCarousel();
        }
    });

    requestsNext.addEventListener('click', () => {
        const pageCount = Math.ceil(friendRequests.length / requestsPerPage);
        if (requestPage < pageCount - 1) {
            requestPage += 1;
            renderRequestCarousel();
        }
    });

    async function loadFriends() {
        showError('');
        renderRequestsSkeleton();
        renderFriendsSkeleton();

        try {
            const res = await authFetch('/friends');
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                throw new Error(errorData.error || t('common.unable.to.load.friends', 'Unable to load friends.'));
            }

            const data = await res.json();
            const allRequests = Array.isArray(data) ? data : [];
            const enrichedRequests = await enrichFriendRows(allRequests);

            friendRequests = enrichedRequests.filter(item => item.status === 0);
            acceptedFriends = enrichedRequests.filter(item => item.status === 1);
            requestPage = 0;
            friendsPage = 0;

            renderRequestCarousel();
            renderFriendsPage();
        } catch (err) {
            showError(err.message || t('common.error.loading.friends', 'Error loading friends.'));
            requestsContainer.innerHTML = '<div class="empty-state">' + t('common.unable.to.load.friend.requests', 'Unable to load friend requests.') + '</div>';
            friendsList.innerHTML = '<div class="empty-state">' + t('common.unable.to.load.friends', 'Unable to load friends.') + '</div>';
            requestsPagination.innerHTML = '';
            friendsPagination.innerHTML = '';
            console.error(err);
        }
    }

    async function enrichFriendRows(rows) {
        const allIds = [...new Set(rows.map(row => getFriendUserId(row)))].filter(Boolean);
        await Promise.all(allIds.map(id => fetchUserDetails(id)));
        return rows.map(row => {
            const friendId = getFriendUserId(row);
            return {
                ...row,
                friend_uuid: friendId,
                friend_details: userDetailsCache[friendId] || null,
                direction: getRequestDirection(row)
            };
        });
    }

    function getFriendUserId(row) {
        if (!row || !row.user_id || !row.friend_id) {
            return null;
        }
        return row.user_id === currentUserId ? row.friend_id : row.user_id;
    }

    function getRequestDirection(row) {
        if (row.status !== 0) {
            return null;
        }
        return row.user_id === currentUserId ? 'outbound' : 'inbound';
    }

    async function fetchUserDetails(userId) {
        if (!userId || userDetailsCache[userId]) {
            return userDetailsCache[userId] || null;
        }

        try {
            const res = await authFetch(`/users/${encodeURIComponent(userId)}`);
            if (!res.ok) {
                throw new Error('Unable to load user details');
            }
            const user = await res.json();
            if (!user.profile_picture) {
                try {
                    const pictureRes = await authFetch(`/users/${encodeURIComponent(userId)}/profile-picture`);
                    if (pictureRes.ok) {
                        const pictureData = await pictureRes.json();
                        if (pictureData.profile_picture_url) {
                            user.profile_picture = pictureData.profile_picture_url;
                        }
                    }
                } catch (pictureErr) {
                    console.warn('Failed to fetch profile picture for', userId, pictureErr);
                }
            }
            userDetailsCache[userId] = user;
            return user;
        } catch (err) {
            console.warn('Failed to fetch user details for', userId, err);
            userDetailsCache[userId] = null;
            return null;
        }
    }

    function renderRequestsSkeleton() {
        requestsContainer.innerHTML = '';
        requestsPagination.innerHTML = '';
        const skeletonCount = 3;
        for (let i = 0; i < skeletonCount; i++) {
            const card = document.createElement('div');
            card.className = 'carousel-card skeleton-card';
            card.innerHTML = `
                <div class="skeleton skeleton-circle" style="width:56px; height:56px;"></div>
                <div class="request-skeleton-content">
                    <div class="skeleton skeleton-title" style="width: 45%; height: 18px;"></div>
                    <div class="skeleton skeleton-description" style="width: 70%; height: 12px; margin-top: 12px;"></div>
                    <div class="skeleton skeleton-description" style="width: 60%; height: 12px; margin-top: 6px;"></div>
                    <div class="skeleton skeleton-description" style="width: 50%; height: 12px; margin-top: 6px;"></div>
                </div>`;
            requestsContainer.appendChild(card);
        }
    }

    function renderFriendsSkeleton() {
        friendsList.innerHTML = '';
        friendsPagination.innerHTML = '';
        for (let i = 0; i < 6; i++) {
            const card = document.createElement('div');
            card.className = 'friend-card skeleton-card';
            card.innerHTML = `
                <div class="skeleton skeleton-circle" style="width:64px; height:64px;"></div>
                <div style="flex:1; display:flex; flex-direction:column; gap:10px; min-width:0;">
                    <div class="skeleton skeleton-title" style="width: 45%; height: 18px;"></div>
                    <div class="skeleton skeleton-description" style="width: 55%; height: 14px;"></div>
                    <div class="skeleton skeleton-description" style="width: 35%; height: 14px;"></div>
                </div>`;
            friendsList.appendChild(card);
        }
    }

    function renderRequestCarousel() {
        requestsContainer.innerHTML = '';
        const pageCount = Math.max(1, Math.ceil(friendRequests.length / requestsPerPage));
        if (friendRequests.length === 0) {
            requestsContainer.innerHTML = '<div class="empty-state">No pending friend requests right now.</div>';
            requestsPagination.innerHTML = '';
            requestsSummary.textContent = 'No requests.';
            requestsPrev.disabled = true;
            requestsNext.disabled = true;
            return;
        }

        const start = requestPage * requestsPerPage;
        const pageItems = friendRequests.slice(start, start + requestsPerPage);
        pageItems.forEach(item => requestsContainer.appendChild(createRequestCard(item)));

        requestsPrev.disabled = requestPage === 0;
        requestsNext.disabled = requestPage >= pageCount - 1;
        requestsSummary.textContent = `${friendRequests.length} request${friendRequests.length > 1 ? 's' : ''}`;
        const requestsCountChip = document.getElementById('requests-count-chip');
        if (requestsCountChip) {
            requestsCountChip.textContent = String(friendRequests.length);
        }

        requestsPagination.innerHTML = '';
        for (let i = 0; i < pageCount; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = i === requestPage ? 'page-btn active' : 'page-btn';
            btn.textContent = String(i + 1);
            btn.setAttribute('aria-label', `Go to page ${i + 1}`);
            btn.onclick = () => {
                requestPage = i;
                renderRequestCarousel();
            };
            requestsPagination.appendChild(btn);
        }
    }

    function renderFriendsPage() {
        friendsList.innerHTML = '';
        const pageCount = Math.max(1, Math.ceil(acceptedFriends.length / friendsPerPage));
        if (acceptedFriends.length === 0) {
            friendsList.innerHTML = '<div class="empty-state">No confirmed friends yet. Send a friend request or wait for someone to accept yours.</div>';
            friendsPagination.innerHTML = '';
            friendsSummary.textContent = '0 friends';
            return;
        }

        const start = friendsPage * friendsPerPage;
        const pageItems = acceptedFriends.slice(start, start + friendsPerPage);
        pageItems.forEach(item => friendsList.appendChild(createFriendCard(item)));

        friendsSummary.textContent = `${acceptedFriends.length} friend${acceptedFriends.length > 1 ? 's' : ''}`;
        friendsPagination.innerHTML = '';

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'page-btn';
        prev.textContent = 'Previous';
        prev.disabled = friendsPage === 0;
        prev.onclick = () => {
            if (friendsPage > 0) {
                friendsPage -= 1;
                renderFriendsPage();
            }
        };
        friendsPagination.appendChild(prev);

        for (let i = 0; i < pageCount; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = i === friendsPage ? 'page-btn active' : 'page-btn';
            btn.textContent = i + 1;
            btn.onclick = () => {
                friendsPage = i;
                renderFriendsPage();
            };
            friendsPagination.appendChild(btn);
        }

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'page-btn';
        next.textContent = 'Next';
        next.disabled = friendsPage >= pageCount - 1;
        next.onclick = () => {
            if (friendsPage < pageCount - 1) {
                friendsPage += 1;
                renderFriendsPage();
            }
        };
        friendsPagination.appendChild(next);
    }

    function createRequestCard(item) {
        const user = item.friend_details;
        const direction = item.direction === 'inbound' ? 'Incoming' : 'Outgoing';
        const badgeText = direction === 'inbound' ? 'Incoming request' : 'Outgoing request';
        const card = document.createElement('div');
        card.className = 'carousel-card';
        card.innerHTML = `
            <div class="request-card-header">
                <img src="${getProfileImage(user)}" alt="${escapeHtml(item.username)}" class="friend-avatar">
                <div class="request-card-title">
                    <strong>${escapeHtml(item.username)}</strong>
                    <span class="request-badge ${direction}">${escapeHtml(badgeText)}</span>
                    <span class="friend-type-badge ${getUserTypeClass(user?.user_type)}">${getUserTypeLabel(user?.user_type)}</span>
                </div>
            </div>
            <p class="request-message">${escapeHtml(item.message || 'No message provided.')}</p>
            <div class="request-actions">
                <a href="user?username=${encodeURIComponent(item.username)}" class="btn btn-secondary btn-sm request-action-btn"><i class="fa-solid fa-user"></i> Public profile</a>
                ${item.direction === 'inbound' ? `<button type="button" class="btn btn-primary btn-sm btn-accept request-action-btn" data-id="${escapeHtml(item.id)}" data-user-id="${escapeHtml(user?.id || '')}"><i class="fa-solid fa-user-check"></i> Accept</button><button type="button" class="btn btn-danger btn-sm btn-decline request-action-btn" data-id="${escapeHtml(item.id)}"><i class="fa-solid fa-times"></i> Decline</button>` : `<button type="button" class="btn btn-danger btn-sm btn-cancel request-action-btn" data-id="${escapeHtml(item.id)}"><i class="fa-solid fa-times"></i> Cancel</button>`}
            </div>
        `;

        card.querySelectorAll('.btn-accept').forEach(btn => btn.onclick = handleAcceptRequest);
        card.querySelectorAll('.btn-decline').forEach(btn => btn.onclick = handleDeclineRequest);
        card.querySelectorAll('.btn-cancel').forEach(btn => btn.onclick = handleDeclineRequest);

        return card;
    }

    function createFriendCard(item) {
        const user = item.friend_details;
        const card = document.createElement('div');
        card.className = 'friend-card';
        card.innerHTML = `
            <img src="${getProfileImage(user)}" alt="${escapeHtml(item.username)}" class="friend-avatar">
            <div class="friend-info">
                <div class="friend-header-row">
                    <div>
                        <strong>${escapeHtml(item.username)}</strong>
                        <div class="friend-name">${escapeHtml(item.first_name || '')} ${escapeHtml(item.last_name || '')}</div>
                    </div>
                    <span class="friend-type-badge ${getUserTypeClass(user?.user_type)}">${getUserTypeLabel(user?.user_type)}</span>
                </div>
                <div class="friend-actions">
                    <a href="user?username=${encodeURIComponent(item.username)}" class="btn btn-secondary btn-height-limited btn-sm">Public profile</a>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-friend" data-id="${escapeHtml(item.id)}">Remove</button>
                </div>
            </div>
        `;
        const removeBtn = card.querySelector('.btn-remove-friend');
        if (removeBtn) removeBtn.onclick = handleRemoveFriend;
        return card;
    }

    function getProfileImage(user) {
        if (user && user.profile_picture) {
            if (user.profile_picture.startsWith('http') || user.profile_picture.startsWith('/')) {
                return user.profile_picture;
            }
            return '/files/uploads/user/' + user.profile_picture;
        }
        return '/files/uploads/user/defaultUser.png';
    }

    async function handleAcceptRequest(event) {
        const id = event.currentTarget.getAttribute('data-id');
        const otherUserId = event.currentTarget.getAttribute('data-user-id');
        if (!id) return;
        
        await runFriendAction(`/friends/${encodeURIComponent(id)}/accept`, { method: 'PUT' });
        
        if (otherUserId && currentUserId) {
            try {
                console.log('[Friends] Creating discussion with:', otherUserId);
                const res = await authFetch(`/users/${encodeURIComponent(currentUserId)}/discussions`, {
                    method: 'POST',
                    body: JSON.stringify({ user1_id: currentUserId, user2_id: otherUserId })
                });
                if (res.ok) {
                    console.log('[Friends] Discussion created successfully');
                } else {
                    console.error('[Friends] Failed to create discussion:', res.status);
                }
            } catch (e) {
                console.error('[Friends] Error creating discussion:', e);
            }
        }
    }

    async function handleDeclineRequest(event) {
        const id = event.currentTarget.getAttribute('data-id');
        if (!id) return;
        await runFriendAction(`/friends/${encodeURIComponent(id)}`, { method: 'DELETE' });
    }

    async function handleRemoveFriend(event) {
        const id = event.currentTarget.getAttribute('data-id');
        if (!id) return;
        await runFriendAction(`/friends/${encodeURIComponent(id)}`, { method: 'DELETE' });
    }

    async function runFriendAction(endpoint, options) {
        try {
            const res = await authFetch(endpoint, options);
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                throw new Error(errorData.error || 'Unable to complete action.');
            }
            await loadFriends();
        } catch (err) {
            showError(err.message || 'Action failed.');
            console.error(err);
        }
    }

    function getUserTypeLabel(type) {
        switch (type) {
            case 2:
                return 'Professional';
            case 3:
                return 'Admin';
            case 4:
                return 'Employee';
            default:
                return 'Customer';
        }
    }

    function getUserTypeClass(type) {
        switch (type) {
            case 2:
                return 'professional';
            case 3:
                return 'admin';
            case 4:
                return 'employee';
            default:
                return 'customer';
        }
    }

    function showError(message) {
        if (!friendsError) return;
        if (!message) {
            friendsError.classList.add('d-none');
            friendsError.textContent = '';
            return;
        }
        friendsError.textContent = message;
        friendsError.classList.remove('d-none');
    }

    function authFetch(endpoint, options = {}) {
        const url = new URL(`${API_URL}${endpoint}`);
        if (token) {
            url.searchParams.set('token', token);
        }
        return fetch(url.toString(), {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
    }

    function escapeHtml(value) {
        if (typeof value !== 'string') {
            return value || '';
        }
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    function parseJwt(token) {
        if (!token) { return {}; }
        try {
            const [, payload] = token.split('.');
            return JSON.parse(decodeURIComponent(atob(payload).split('').map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join('')));
        } catch (err) {
            return {};
        }
    }
});
