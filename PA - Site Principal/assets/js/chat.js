document.addEventListener('DOMContentLoaded', () => {
    const API_URL = "http://" + window.location.hostname + ":9999";
    const WS_URL = "ws://" + window.location.hostname + ":9999/ws";
    
    const token = localStorage.getItem('token') || getCookie('token');
    const currentUserId = parseJwt(token)?.user_id;

    let activeChat = { id: null, type: null, title: null, image: null };
    let ws = null;

    const chatList = document.getElementById('chat-list');
    const activeView = document.getElementById('chat-active-view');
    const titleView = document.getElementById('active-chat-title');
    const activeImageView = document.getElementById('active-chat-image');
    const messagesContainer = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const btnAddMember = document.getElementById('btn-add-member');

    if (!token || !currentUserId) {
        showCustomError("You must be logged in to use the chat.");
        return;
    }
    
    initWebSocket();
    loadDiscussionsAndGroups();

    function initWebSocket() {
        ws = new WebSocket(`${WS_URL}?token=${token}`);

        ws.onopen = () => console.log("[WS] Connected to chat server");
        
        ws.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (
                    (msg.target_type === 'global' && activeChat.type === 'global') ||
                    (msg.target_type === 'group' && msg.target_id === activeChat.id) ||
                    (msg.target_type === 'user' && (msg.target_id === activeChat.id || msg.sender_id === activeChat.id))
                ) {
                    appendMessage(msg);
                    scrollToBottom();
                } else {
                    loadDiscussionsAndGroups();
                }
            } catch(e) {
                console.error("Unknown message format", event.data);
            }
        };

        ws.onclose = () => {
            console.log("[WS] Disconnected. Reconnecting in 5s...");
            setTimeout(initWebSocket, 5000);
        };
    }

    async function authFetch(endpoint, options = {}) {
        return fetch(`${API_URL}${endpoint}`, {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
    }

    async function loadDiscussionsAndGroups() {
        chatList.innerHTML = '<div class="chat-spinner">Loading...</div>';
        try {
            const [resGroups, resUsers] = await Promise.all([
                authFetch('/groups'),
                authFetch(`/users/${currentUserId}/discussions`)
            ]);

            const groups = resGroups.ok ? await resGroups.json() : [];
            const discussions = resUsers.ok ? await resUsers.json() : [];

            renderSidebar(groups, discussions);
        } catch (err) {
            chatList.innerHTML = "<p>Network error</p>";
        }
    }

    function renderSidebar(groups, discussions) {
        chatList.innerHTML = '';
        
        const globalItem = document.createElement('div');
        globalItem.className = `chat-item ${activeChat.type === 'global' ? 'active' : ''}`;
        globalItem.innerHTML = `<i class="fa-solid fa-globe" style="margin-right: 8px;"></i> Global Chat`;
        globalItem.onclick = () => openChat('global', 'global', 'Global Chat', null);
        chatList.appendChild(globalItem);

        const sep1 = document.createElement('div');
        sep1.className = 'chat-sidebar-separator';
        sep1.textContent = 'Groups';
        sep1.style = 'padding: 10px 15px 5px; font-size: 0.8em; text-transform: uppercase; color: #888; font-weight: bold;';
        chatList.appendChild(sep1);

        if (groups && groups.length) {
            groups.forEach(g => {
                const item = document.createElement('div');
                item.className = `chat-item ${activeChat.id === g.id && activeChat.type === 'group' ? 'active' : ''}`;
                
                let iconHtml = '<i class="fa-solid fa-users" style="margin-right: 8px;"></i>';
                if (g.image_url) {
                    iconHtml = `<img src="${escapeHtml(g.image_url)}" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; object-fit: cover;" onerror="this.outerHTML='<i class=\\'fa-solid fa-users\\' style=\\'margin-right: 8px;\\'></i>'">`;
                }

                item.innerHTML = `${iconHtml} ${escapeHtml(g.title)}`;
                item.onclick = () => openChat(g.id, 'group', g.title, g.image_url);
                chatList.appendChild(item);
            });
        }

        const sep2 = document.createElement('div');
        sep2.className = 'chat-sidebar-separator';
        sep2.textContent = 'Direct Messages';
        sep2.style = 'padding: 10px 15px 5px; font-size: 0.8em; text-transform: uppercase; color: #888; font-weight: bold;';
        chatList.appendChild(sep2);

        if (discussions && discussions.length) {
            discussions.forEach(d => {
                const otherUser = (d.user1_id === currentUserId) ? d.user2_id : d.user1_id;
                const item = document.createElement('div');
                item.className = `chat-item ${activeChat.id === d.id && activeChat.type === 'user' ? 'active' : ''}`;
                item.innerHTML = `<i class="fa-solid fa-user" style="margin-right: 8px;"></i> User ${otherUser.substring(0,8)}...`;
                item.onclick = () => openChat(d.id, 'user', `User ${otherUser.substring(0,8)}...`, null);
                chatList.appendChild(item);
            });
        }
    }

    async function openChat(targetId, targetType, title, imageUrl) {
        activeChat = { id: targetId, type: targetType, title: title, image: imageUrl };
        
        document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
        
        document.querySelector('.chat-main-placeholder').classList.add('d-none');
        activeView.classList.remove('d-none');
        titleView.textContent = title;
        
        if (imageUrl) {
            activeImageView.src = imageUrl;
            activeImageView.style.display = 'block';
        } else {
            activeImageView.style.display = 'none';
        }

        messagesContainer.innerHTML = '<div class="chat-spinner">Loading messages...</div>';

        if (targetType === 'group') {
            btnAddMember.classList.remove('d-none');
        } else {
            btnAddMember.classList.add('d-none');
        }

        let path = '';
        if (targetType === 'global') {
            path = `/global/messages`;
        } else if (targetType === 'group') {
            path = `/groups/${targetId}/messages`;
        } else {
            path = `/discussions/${targetId}/messages`;
        }
        
        try {
            const res = await authFetch(path);
            const messages = await res.json();
            
            messagesContainer.innerHTML = '';
            
            if (messages && messages.length > 0) {
                messages.forEach(appendMessage);
            } else {
                messagesContainer.innerHTML = '<div class="chat-empty">No messages yet.</div>';
            }
            scrollToBottom();
            
            loadDiscussionsAndGroups();
        } catch(e) {
            messagesContainer.innerHTML = '<p>Error loading messages</p>';
        }
    }

    function appendMessage(msg) {
        const emptyDiv = messagesContainer.querySelector('.chat-empty');
        if (emptyDiv) emptyDiv.remove();

        const isMine = msg.sender_id === currentUserId;
        const msgEl = document.createElement('div');
        msgEl.className = `chat-bubble-container ${isMine ? 'mine' : 'other'}`;
        msgEl.innerHTML = `
            <div class="chat-bubble">
                ${!isMine ? `<small class="sender-name"><i class="fa-solid fa-user-circle"></i> User ${msg.sender_id.substring(0,8)}</small>` : ''}
                <div class="content">${escapeHtml(msg.content)}</div>
            </div>
        `;
        messagesContainer.appendChild(msgEl);
    }

    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const content = chatInput.value.trim();
        if (!content || !activeChat.type) return;

        const payload = {
            action: 'send_message',
            target_type: activeChat.type,
            target_id: activeChat.id || "",
            content: content
        };

        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify(payload));
            
            appendMessage({ sender_id: currentUserId, content: content });
            scrollToBottom();
            
            chatInput.value = '';
        } else {
            showCustomError('Connection to server lost.');
        }
    });

    const modalCreate = document.getElementById('modal-create-group');
    const modalAdd = document.getElementById('modal-add-member');

    document.getElementById('btn-create-group').onclick = () => openModal(modalCreate);
    let availableFriendsToInvite = [];
    const addMemberSuggestions = document.getElementById('add-member-suggestions');
    const newMemberInput = document.getElementById('new-member-id');

    btnAddMember.onclick = async () => {
        openModal(modalAdd);
        newMemberInput.value = '';
        addMemberSuggestions.style.display = 'none';
        
        try {
            const res = await authFetch('/friends');
            if (res.ok) {
                const allFriends = await res.json();
                availableFriendsToInvite = allFriends.filter(f => f.status === 1);
            }
        } catch (e) {
            console.error("Failed to fetch friends for invite", e);
            availableFriendsToInvite = [];
        }
    };

    newMemberInput.addEventListener('input', (e) => {
        const val = e.target.value.trim().toLowerCase();
        addMemberSuggestions.innerHTML = '';
        if (!val) {
            addMemberSuggestions.style.display = 'none';
            return;
        }

        const matches = availableFriendsToInvite.filter(f => f.username.toLowerCase().includes(val));
        
        if (matches.length > 0) {
            matches.forEach(m => {
                const item = document.createElement('div');
                item.style = "padding: 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0;";
                item.innerHTML = `<strong>${escapeHtml(m.username)}</strong> <small class="text-muted">(${escapeHtml(m.first_name)} ${escapeHtml(m.last_name)})</small>`;
                item.onmouseenter = () => item.style.backgroundColor = '#f0f0f0';
                item.onmouseleave = () => item.style.backgroundColor = 'transparent';
                item.onclick = () => {
                    newMemberInput.value = m.username;
                    addMemberSuggestions.style.display = 'none';
                };
                addMemberSuggestions.appendChild(item);
            });
            addMemberSuggestions.style.display = 'block';
        } else {
            const empty = document.createElement('div');
            empty.style = "padding: 10px; color: #888; font-style: italic;";
            empty.textContent = "No friends found.";
            addMemberSuggestions.appendChild(empty);
            addMemberSuggestions.style.display = 'block';
        }
    });

    document.addEventListener('click', (e) => {
        if (!newMemberInput.contains(e.target) && !addMemberSuggestions.contains(e.target)) {
            addMemberSuggestions.style.display = 'none';
        }
    });

    document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
        btn.onclick = (e) => closeModal(e.target.closest('.modal-overlay'));
    });

    document.getElementById('btn-confirm-create-group').onclick = async () => {
        const title = document.getElementById('group-name').value.trim();
        const imageUrl = document.getElementById('group-image').value.trim();
        const errDiv = document.getElementById('create-group-error');
        if(!title) return;

        try {
            const body = { title: title };
            if (imageUrl) {
                body.image_url = imageUrl;
            }
            
            const response = await authFetch('/groups', { 
                method: 'POST', 
                body: JSON.stringify(body) 
            });
            if (response.ok) {
                closeModal(modalCreate);
                loadDiscussionsAndGroups();
                document.getElementById('group-name').value = '';
                document.getElementById('group-image').value = '';
                errDiv.classList.add('d-none');
            } else {
                throw new Error("Error");
            }
        } catch(e) {
            errDiv.textContent = "Error creating group";
            errDiv.classList.remove('d-none');
        }
    };

    document.getElementById('btn-confirm-add-member').onclick = async () => {
        const username = document.getElementById('new-member-id').value.trim();
        const errDiv = document.getElementById('add-member-error');
        if(!username || activeChat.type !== 'group') return;

        try {
            const response = await authFetch(`/groups/${activeChat.id}/members`, { 
                method: 'POST', 
                body: JSON.stringify({ username: username }) 
            });
            
            if (response.ok) {
                closeModal(modalAdd);
                document.getElementById('new-member-id').value = '';
                errDiv.classList.add('d-none');
            } else {
                const data = await response.json();
                throw new Error(data.error || "Error");
            }
        } catch(e) {
            errDiv.textContent = "Error: Invalid username (" + e.message + ")";
            errDiv.classList.remove('d-none');
        }
    };

    const modalFriends = document.getElementById('modal-friends');
    const btnOpenFriends = document.getElementById('btn-open-friends');
    if (btnOpenFriends) {
        btnOpenFriends.onclick = () => {
            openModal(modalFriends);
            loadFriends();
        };
    }

    const btnSendFriendReq = document.getElementById('btn-send-friend-request');
    const friendActionMsg = document.getElementById('friend-action-msg');
    if (btnSendFriendReq) {
        btnSendFriendReq.onclick = async () => {
            const username = document.getElementById('friend-username-input').value.trim();
            if (!username) return;

            try {
                const res = await authFetch('/friends', {
                    method: 'POST',
                    body: JSON.stringify({ username })
                });
                const data = await res.json();
                if (res.ok) {
                    friendActionMsg.textContent = "Friend request sent!";
                    friendActionMsg.style.color = "green";
                    friendActionMsg.style.display = "block";
                    document.getElementById('friend-username-input').value = '';
                    loadFriends();
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            } catch(err) {
                friendActionMsg.textContent = "Error: " + err.message;
                friendActionMsg.style.color = "red";
                friendActionMsg.style.display = "block";
            }
            setTimeout(() => { friendActionMsg.style.display = "none"; }, 3000);
        };
    }

    async function loadFriends() {
        const pendingList = document.getElementById('pending-friends-list');
        const acceptedList = document.getElementById('accepted-friends-list');
        const pendingSection = document.getElementById('pending-friends-section');
        
        pendingList.innerHTML = 'Loading...';
        acceptedList.innerHTML = 'Loading...';

        try {
            const res = await authFetch('/friends');
            if (!res.ok) throw new Error('Bad network');
            const data = await res.json();
            
            pendingList.innerHTML = '';
            acceptedList.innerHTML = '';
            
            const pendingArr = data?.filter(f => f.status === 0) || [];
            const acceptedArr = data?.filter(f => f.status === 1) || [];

            if (pendingArr.length > 0) {
                pendingSection.style.display = 'block';
                pendingArr.forEach(f => {
                    const amIReceiver = (f.friend_id === currentUserId);
                    const div = document.createElement('div');
                    div.style = "display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f9f9f9; border-radius: 5px;";
                    div.innerHTML = `
                        <div>
                            <strong>${escapeHtml(f.username)}</strong><br>
                            <small class="text-muted">Status: Pending</small>
                        </div>
                        <div>
                            ${amIReceiver ? `<button class="btn btn-primary btn-sm btn-accept-friend" data-id="${f.id}"><i class="fas fa-check"></i> Accept</button>` : 
                                            `<button class="btn btn-secondary btn-sm" disabled>Sent</button>`}
                            <button class="btn btn-secondary btn-sm btn-remove-friend" data-id="${f.id}"><i class="fas fa-times"></i> ${amIReceiver ? 'Decline' : 'Cancel'}</button>
                        </div>
                    `;
                    pendingList.appendChild(div);
                });
            } else {
                pendingSection.style.display = 'none';
            }

            if (acceptedArr.length > 0) {
                acceptedArr.forEach(f => {
                    const div = document.createElement('div');
                    div.style = "display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f9f9f9; border-radius: 5px;";
                    div.innerHTML = `
                        <div>
                            <strong>${escapeHtml(f.username)}</strong><br>
                            <small>${escapeHtml(f.first_name)} ${escapeHtml(f.last_name)}</small>
                        </div>
                        <button class="btn btn-secondary btn-sm btn-remove-friend" data-id="${f.id}"><i class="fas fa-user-minus"></i> Remove</button>
                    `;
                    acceptedList.appendChild(div);
                });
            } else {
                acceptedList.innerHTML = '<span class="text-muted">You have no friends yet.</span>';
            }

            document.querySelectorAll('.btn-accept-friend').forEach(b => {
                b.onclick = async (e) => {
                    const fid = e.currentTarget.getAttribute('data-id');
                    await authFetch(`/friends/${fid}/accept`, { method: 'PUT' });
                    loadFriends(); 
                };
            });

            document.querySelectorAll('.btn-remove-friend').forEach(b => {
                b.onclick = async (e) => {
                    const fid = e.currentTarget.getAttribute('data-id');
                    await authFetch(`/friends/${fid}`, { method: 'DELETE' });
                    loadFriends(); 
                };
            });

        } catch (e) {
            acceptedList.innerHTML = 'Error loading friends.';
            pendingList.innerHTML = 'Error.';
            console.error(e);
        }
    }

    function openModal(m) {
        m.classList.add('is-open', 'is-visible');
        m.setAttribute('aria-hidden', 'false');
    }
    function closeModal(m) {
        m.classList.remove('is-open', 'is-visible');
        m.setAttribute('aria-hidden', 'true');
    }
    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
    function getCookie(n){let a=` ; ${document.cookie}`.match(`;\\s*${n}=([^;]+)`);return a?a[1]:''}
    function parseJwt(token){try{return JSON.parse(atob(token.split('.')[1]));}catch(e){return null;}}
    function showCustomError(msg) {
        const placeholder = document.querySelector('.chat-main-placeholder');
        if (placeholder) {
            placeholder.textContent = msg;
            placeholder.style.color = "red";
        }
    }
});
