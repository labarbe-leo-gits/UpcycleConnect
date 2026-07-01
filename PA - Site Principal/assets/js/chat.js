document.addEventListener('DOMContentLoaded', () => {
    const API_URL = (window.API_BASE || (window.location.protocol + '//' + window.location.hostname + ':9999')).replace(/\/$/, '');
    const WS_URL = API_URL.replace(/^http:/, 'ws:').replace(/^https:/, 'wss:') + '/ws';
    
    const token = localStorage.getItem('token') || getCookie('token');
    const currentUserId = parseJwt(token)?.user_id;

    let activeChat = { id: null, type: null, title: null, image: null };
    let ws = null;
    let currentGroups = [];
    let currentDiscussions = [];
    const senderUsernameCache = new Map();

    const chatList = document.getElementById('chat-list');
    const chatSearchInput = document.getElementById('chat-search');
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
                
                if (msg.action === 'member_added') {
                    console.log("[WS] Member added to group:", msg.target_id);

                    loadDiscussionsAndGroups();

                    if (activeChat.type === 'group' && activeChat.id === msg.target_id) {
                        openChat(activeChat.id, 'group', activeChat.title, activeChat.image);
                    }
                    return;
                }
                
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

    async function fetchUsername(userId) {
        if (!userId) {
            return null;
        }
        if (senderUsernameCache.has(userId)) {
            return senderUsernameCache.get(userId);
        }

        try {
            const res = await authFetch(`/users/${encodeURIComponent(userId)}`);
            if (!res.ok) {
                return null;
            }
            const user = await res.json();
            const username = user?.username || null;
            if (username) {
                senderUsernameCache.set(userId, username);
            }
            return username;
        } catch (e) {
            return null;
        }
    }

    function formatSenderLabel(msg) {
        if (msg.sender_username) {
            if (msg.sender_id) {
                senderUsernameCache.set(msg.sender_id, msg.sender_username);
            }
            return msg.sender_username;
        }
        if (msg.sender_id && senderUsernameCache.has(msg.sender_id)) {
            return senderUsernameCache.get(msg.sender_id);
        }
        if (msg.sender_id && msg.sender_id.length >= 8) {
            return `User ${msg.sender_id.substring(0,8)}`;
        }
        return 'Unknown';
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

            currentGroups = groups;
            currentDiscussions = discussions;

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
                const otherUsername = (d.user1_id === currentUserId) ? d.user2_username : d.user1_username;
                const displayName = (otherUsername && otherUsername.trim()) ? otherUsername : `User ${otherUser.substring(0,8)}...`;
                const item = document.createElement('div');
                item.className = `chat-item ${activeChat.id === d.id && activeChat.type === 'user' ? 'active' : ''}`;
                item.innerHTML = `<i class="fa-solid fa-user" style="margin-right: 8px;"></i> ${escapeHtml(displayName)}`;
                item.onclick = () => openChat(d.id, 'user', displayName, null);
                chatList.appendChild(item);
            });
        }
    }

    function filterChats(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        
        let filteredGroups = currentGroups;
        let filteredDiscussions = currentDiscussions;
        
        if (term) {
            filteredGroups = currentGroups.filter(g => 
                g.title.toLowerCase().includes(term)
            );
            filteredDiscussions = currentDiscussions.filter(d => {
                const otherUsername = (d.user1_id === currentUserId) ? d.user2_username : d.user1_username;
                return otherUsername.toLowerCase().includes(term);
            });
        }
        
        renderSidebar(filteredGroups, filteredDiscussions);
    }

    chatSearchInput.addEventListener('input', (e) => {
        filterChats(e.target.value);
    });

    async function openChat(targetId, targetType, title, imageUrl) {
        activeChat = { id: targetId, type: targetType, title: title, image: imageUrl };
        
        document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
        
        document.querySelector('.chat-main-placeholder').classList.add('d-none');
        activeView.classList.remove('d-none');
        titleView.textContent = title;
        
        const topHeader = document.getElementById('chat-top-header');
        const topTitle = document.getElementById('top-chat-title');
        const topImage = document.getElementById('top-chat-image');
        const btnAddMemberTop = document.getElementById('btn-add-member-top');
        
        topHeader.style.display = 'block';
        topTitle.textContent = title;
        
        if (imageUrl) {
            activeImageView.src = imageUrl;
            activeImageView.style.display = 'block';
            topImage.src = imageUrl;
            topImage.style.display = 'block';
        } else {
            activeImageView.style.display = 'none';
            topImage.style.display = 'none';
        }

        messagesContainer.innerHTML = '<div class="chat-spinner">Loading messages...</div>';

        if (targetType === 'group') {
            btnAddMember.classList.remove('d-none');
            btnAddMemberTop.style.display = 'inline-block';
        } else {
            btnAddMember.classList.add('d-none');
            btnAddMemberTop.style.display = 'none';
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
        
        let attachmentsHtml = '';
        let contentHtml = '';
        
        if (msg.content && msg.content.startsWith('__FILE_ATTACHMENT__')) {
            const fileName = msg.content.replace('__FILE_ATTACHMENT__', '');
            let mimeType = 'application/octet-stream';
            
            if (msg.attachments && msg.attachments.length > 0) {
                mimeType = msg.attachments[0].file_type || mimeType;
            }
            
            const fileIcon = getFileIcon(mimeType);
            contentHtml = `<div class="content" style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                <i class="fas ${fileIcon}" style="font-size: 1.1em;"></i>
                <span>${escapeHtml(fileName)}</span>
            </div>`;
        } else {
            const escapedContent = escapeHtml(msg.content).replace(/\n/g, '<br>');
            contentHtml = `<div class="content">${escapedContent}</div>`;
        }
        
        if (msg.attachments && msg.attachments.length > 0) {
            attachmentsHtml = '<div class="chat-attachments">';
            msg.attachments.forEach(att => {
                const fileName = att.file_name || 'Download';
                const fileSize = att.file_size ? (att.file_size / 1024 / 1024).toFixed(2) + ' MB' : 'Unknown size';
                const filePath = att.file_path || '';
                const mimeType = att.file_type || 'application/octet-stream';
                const fileIcon = getFileIcon(mimeType);
                
                if (mimeType.startsWith('image/')) {
                    attachmentsHtml += `
                        <div class="attachment-image">
                            <img src="${escapeHtml(filePath)}" alt="${escapeHtml(fileName)}" loading="lazy" style="max-width: 100%; max-height: 300px; border-radius: 6px; cursor: pointer;" onclick="window.open('${escapeHtml(filePath)}', '_blank')">
                            <div class="attachment-image-info">
                                <span>${escapeHtml(fileName)}</span>
                                <a href="${escapeHtml(filePath)}" download class="attachment-dl">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    `;
                } else if (mimeType.startsWith('video/')) {
                    attachmentsHtml += `
                        <div class="attachment-video">
                            <video width="300" height="200" controls style="border-radius: 6px;">
                                <source src="${escapeHtml(filePath)}" type="${mimeType}">
                                Your browser does not support the video tag.
                            </video>
                            <div class="attachment-file-info">
                                <div class="attachment-name">${escapeHtml(fileName)}</div>
                                <div class="attachment-size">${fileSize}</div>
                            </div>
                        </div>
                    `;
                } else {
                    // Default file attachment
                    attachmentsHtml += `
                        <div class="attachment-item">
                            <i class="fas ${fileIcon}"></i>
                            <div class="attachment-info">
                                <div class="attachment-name">${escapeHtml(fileName)}</div>
                                <div class="attachment-size">${fileSize}</div>
                            </div>
                            <a href="${escapeHtml(filePath)}" download class="attachment-dl">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    `;
                }
            });
            attachmentsHtml += '</div>';
        }
        
        const senderLabel = formatSenderLabel(msg);
        msgEl.innerHTML = `
            <div class="chat-bubble">
                ${!isMine ? `<small class="sender-name"><i class="fa-solid fa-user-circle"></i> <span class=\"sender-name-text\">${escapeHtml(senderLabel)}</span></small>` : ''}
                ${contentHtml}
                ${attachmentsHtml}
            </div>
        `;
        messagesContainer.appendChild(msgEl);

        if (!isMine && senderLabel.startsWith('User ') && msg.sender_id) {
            fetchUsername(msg.sender_id).then(username => {
                if (username) {
                    const senderNameSpan = msgEl.querySelector('.sender-name-text');
                    if (senderNameSpan) {
                        senderNameSpan.textContent = username;
                    }
                }
            });
        }

        scrollToBottom();
    }


    chatInput.addEventListener('input', () => {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
    });

    async function submitMessage() {
        const content = chatInput.value.trim();
        
        if (!content && !selectedFile) return;
        if (!activeChat.type) return;


        if (selectedFile) {
            await uploadFileAndSendMessage(selectedFile, content);
        } else if (content) {

            const payload = {
                action: 'send_message',
                target_type: activeChat.type,
                target_id: activeChat.id || "",
                content: content
            };

            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify(payload));
                chatInput.value = '';
                chatInput.style.height = 'auto';
            } else {
                showCustomError('Connection to server lost.');
            }
        }
    }

    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitMessage();
        }
    });

    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        submitMessage();
    });

    const btnGifSelector = document.getElementById('btn-gif-selector');
    const modalGifSelector = document.getElementById('modal-gif-selector');
    const gifSearchInput = document.getElementById('gif-search-input');
    const gifGrid = document.getElementById('gif-grid');
    
    async function loadTrendingGifs() {
        try {
            gifGrid.innerHTML = '<div class="gif-loading">' + t('common.loading.trending.gifs', 'Loading trending GIFs...') + '</div>';
            const response = await fetch('../../pages/api/gifs-new.php');
            const data = await response.json();
            displayGifs(data.gifs);
        } catch (e) {
            console.error('Failed to load GIFs:', e);
            gifGrid.innerHTML = '<div class="gif-empty">Failed to load GIFs. Please try again.</div>';
        }
    }
    
    async function searchGifs(query) {
        if (!query.trim()) {
            await loadTrendingGifs();
            return;
        }
        
        try {
            gifGrid.innerHTML = '<div class="gif-loading">' + t('common.searching', 'Searching...') + '</div>';
            const response = await fetch(`../../pages/api/gifs-new.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (!data.gifs || data.gifs.length === 0) {
                gifGrid.innerHTML = '<div class="gif-empty">' + t('common.no.gifs.found', 'No GIFs found for') + ' "' + query + '"</div>';
            } else {
                displayGifs(data.gifs);
            }
        } catch (e) {
            console.error('Failed to search GIFs:', e);
            gifGrid.innerHTML = '<div class="gif-empty">' + t('common.search.failed', 'Search failed. Please try again.') + '</div>';
        }
    }
    
    function displayGifs(gifs) {
        gifGrid.innerHTML = '';
        gifs.forEach(gifUrl => {
            const item = document.createElement('div');
            item.className = 'gif-item';
            item.innerHTML = `<img src="${gifUrl}" alt="GIF" loading="lazy">`;
            item.onclick = () => insertGif(gifUrl);
            gifGrid.appendChild(item);
        });
    }
    
    function insertGif(gifUrl) {
        chatInput.value = `${chatInput.value}\n${gifUrl}`;
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
        closeModal(modalGifSelector);
        chatInput.focus();
    }
    
    if (btnGifSelector) {
        btnGifSelector.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(modalGifSelector);
            loadTrendingGifs();
        });
    }
    
    let gifSearchTimeout;
    if (gifSearchInput) {
        gifSearchInput.addEventListener('input', (e) => {
            clearTimeout(gifSearchTimeout);
            gifSearchTimeout = setTimeout(() => {
                searchGifs(e.target.value);
            }, 300);
        });
    }

    const btnFileUpload = document.getElementById('btn-file-upload');
    const fileInput = document.getElementById('file-input');
    const filePreview = document.getElementById('file-preview');
    const filePreviewItem = document.getElementById('file-preview-item');
    const filePreviewRemove = document.getElementById('file-preview-remove');
    
    let selectedFile = null;

    if (btnFileUpload) {
        btnFileUpload.addEventListener('click', (e) => {
            e.preventDefault();
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                showCustomError('File too large. Maximum 10MB allowed.');
                fileInput.value = '';
                return;
            }

            selectedFile = file;
            showFilePreview(file);
        });
    }

    function showFilePreview(file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileIcon = getFileIcon(file.type);
        
        console.log('File preview - Type:', file.type, 'Is image:', file.type.startsWith('image/'));
        
        if (file.type.startsWith('image/')) {
            const blobUrl = URL.createObjectURL(file);
            filePreviewItem.innerHTML = `
                <img src="${blobUrl}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px;" alt="preview">
                <div style="flex: 1; min-width: 0;">
                    <span style="font-weight: 500; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(file.name)}</span>
                    <small style="color: #999; display: block;">${fileSize} MB</small>
                </div>
            `;
        } else if (file.type.startsWith('video/')) {
            const blobUrl = URL.createObjectURL(file);
            filePreviewItem.innerHTML = `
                <video style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; background: #000; margin-right: 10px;">
                    <source src="${blobUrl}" type="${file.type}">
                </video>
                <div style="flex: 1; min-width: 0;">
                    <span style="font-weight: 500; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(file.name)}</span>
                    <small style="color: #999; display: block;">${fileSize} MB</small>
                </div>
            `;
        } else {
            filePreviewItem.innerHTML = `
                <i class="fas ${fileIcon}"></i>
                <div style="flex: 1; min-width: 0;">
                    <span style="font-weight: 500; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(file.name)}</span>
                    <small style="color: #999; display: block;">${fileSize} MB</small>
                </div>
            `;
        }
        filePreview.style.display = 'flex';
    }

    function getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return 'fa-image';
        if (mimeType === 'application/pdf') return 'fa-file-pdf';
        if (mimeType.includes('word')) return 'fa-file-word';
        if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'fa-file-excel';
        if (mimeType.startsWith('video/')) return 'fa-file-video';
        if (mimeType.startsWith('audio/')) return 'fa-file-audio';
        if (mimeType === 'application/zip') return 'fa-file-archive';
        return 'fa-file';
    }

    if (filePreviewRemove) {
        filePreviewRemove.addEventListener('click', () => {

            if (selectedFile && (selectedFile.type.startsWith('image/') || selectedFile.type.startsWith('video/'))) {
                const imgs = filePreviewItem.querySelectorAll('img, video');
                imgs.forEach(el => {
                    if (el.src && el.src.startsWith('blob:')) {
                        URL.revokeObjectURL(el.src);
                    }
                });
            }
            selectedFile = null;
            fileInput.value = '';
            filePreview.style.display = 'none';
        });
    }

    async function uploadFileAndSendMessage(file, textContent) {
        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch('../../pages/api/upload-file.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                },
                body: formData
            });

            if (!response.ok) {
                const error = await response.json();
                showCustomError(`Upload failed: ${error.error}`);
                return;
            }

            const uploadResult = await response.json();
            const fileData = uploadResult.file;

            const payload = {
                action: 'send_message',
                target_type: activeChat.type,
                target_id: activeChat.id || "",
                content: textContent || `__FILE_ATTACHMENT__${fileData.name}`,
                attachments: [{
                    file_name: fileData.name,
                    file_size: fileData.size,
                    file_type: fileData.type,
                    file_path: fileData.path
                }]
            };

            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify(payload));
                chatInput.value = '';
                chatInput.style.height = 'auto';
                
                const imgs = filePreviewItem.querySelectorAll('img, video');
                imgs.forEach(el => {
                    if (el.src && el.src.startsWith('blob:')) {
                        URL.revokeObjectURL(el.src);
                    }
                });
                
                selectedFile = null;
                fileInput.value = '';
                filePreview.style.display = 'none';
            } else {
                showCustomError('Connection to server lost.');
            }
        } catch (err) {
            console.error('File upload error:', err);
            showCustomError('File upload failed.');
        }
    }

    const modalCreate = document.getElementById('modal-create-group');
    const modalAdd = document.getElementById('modal-add-member');

    if (document.getElementById('btn-create-group')) {
        document.getElementById('btn-create-group').onclick = () => openModal(modalCreate);
    }
    
    let availableFriendsToInvite = [];
    const addMemberSuggestions = document.getElementById('add-member-suggestions');
    const newMemberInput = document.getElementById('new-member-id');

    if (btnAddMember) {
        btnAddMember.onclick = async () => {
            console.log('[Invite] Opening add member modal');
            openModal(modalAdd);
            newMemberInput.value = '';
            if (addMemberSuggestions) addMemberSuggestions.style.display = 'none';
            
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
    } else {
        console.warn('[Invite] btnAddMember not found');
    }

    document.addEventListener('click', (e) => {
        const btnAddMemberTop = e.target.closest('#btn-add-member-top');
        if (btnAddMemberTop) {
            console.log('[Invite Top] Opening add member modal');
            openModal(modalAdd);
            if (newMemberInput) newMemberInput.value = '';
            if (addMemberSuggestions) addMemberSuggestions.style.display = 'none';
            
            authFetch('/friends')
                .then(res => res.ok ? res.json() : [])
                .then(allFriends => {
                    availableFriendsToInvite = allFriends.filter(f => f.status === 1);
                })
                .catch(e => {
                    console.error("Failed to fetch friends for invite", e);
                    availableFriendsToInvite = [];
                });
        }
    });

    if (newMemberInput) {
        newMemberInput.addEventListener('input', (e) => {
            const val = e.target.value.trim().toLowerCase();
            if (addMemberSuggestions) addMemberSuggestions.innerHTML = '';
            if (!val) {
                if (addMemberSuggestions) addMemberSuggestions.style.display = 'none';
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
                        if (addMemberSuggestions) addMemberSuggestions.style.display = 'none';
                    };
                    if (addMemberSuggestions) addMemberSuggestions.appendChild(item);
                });
                if (addMemberSuggestions) addMemberSuggestions.style.display = 'block';
            } else {
                const empty = document.createElement('div');
                empty.style = "padding: 10px; color: #888; font-style: italic;";
                empty.textContent = "No friends found.";
                if (addMemberSuggestions) {
                    addMemberSuggestions.appendChild(empty);
                    addMemberSuggestions.style.display = 'block';
                }
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (newMemberInput && addMemberSuggestions && !newMemberInput.contains(e.target) && !addMemberSuggestions.contains(e.target)) {
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
                console.log("[Add Member] Success, refreshing...");
                closeModal(modalAdd);
                document.getElementById('new-member-id').value = '';
                errDiv.classList.add('d-none');
                
                await loadDiscussionsAndGroups();
                
                if (activeChat.type === 'group' && activeChat.id) {
                    await openChat(activeChat.id, 'group', activeChat.title, activeChat.image);
                }
            } else {
                const data = await response.json();
                throw new Error(data.error || "Error");
            }
        } catch(e) {
            console.error("[Add Member] Error:", e);
            errDiv.textContent = "Error: Invalid username (" + e.message + ")";
            errDiv.classList.remove('d-none');
        }
    };

    const modalFriends = document.getElementById('modal-friends');
    const btnOpenFriends = document.getElementById('btn-open-friends');
    
    let allFriends = [];
    let friendsDisplayCount = 5;
    let currentFriendsPage = 0;
    
    if (btnOpenFriends && modalFriends) {
        btnOpenFriends.onclick = () => {
            console.log('[Friends] Opening modal');
            openModal(modalFriends);
            currentFriendsPage = 0;
            loadFriends();
        };
    } else {
        console.warn('[Friends] Button or modal not found:', { btnOpenFriends: !!btnOpenFriends, modalFriends: !!modalFriends });
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
        const noFriendsMsg = document.getElementById('no-friends-msg');
        const loadMoreBtn = document.getElementById('load-more-friends');
        const pendingSection = document.getElementById('pending-friends-section');
        
        if (!pendingList || !acceptedList) {
            console.error('[Friends] List elements not found');
            return;
        }
        
        pendingList.innerHTML = 'Loading...';
        acceptedList.innerHTML = 'Loading...';

        try {
            const res = await authFetch('/friends');
            if (!res.ok) throw new Error('Bad network');
            const data = await res.json();
            
            pendingList.innerHTML = '';
            acceptedList.innerHTML = '';
            
            const pendingArr = data?.filter(f => f.status === 0) || [];
            allFriends = data?.filter(f => f.status === 1) || [];

            renderPaginatedFriends();

            // Fetch profile pictures in background (non-blocking)
            const allFriendsToUpdate = [...pendingArr, ...allFriends];
            allFriendsToUpdate.forEach(f => {
                if (!f.profile_picture) {
                    authFetch(`/users/${encodeURIComponent(f.user_id === currentUserId ? f.friend_id : f.user_id)}/profile-picture`)
                        .then(res => res.ok ? res.json() : null)
                        .then(data => {
                            if (data?.profile_picture_url) {
                                f.profile_picture = data.profile_picture_url;
                                renderPaginatedFriends();
                            }
                        })
                        .catch(err => console.warn('Failed to fetch profile picture:', err));
                }
            });

            if (pendingArr.length > 0) {
                pendingSection.style.display = 'block';
                pendingArr.forEach(f => {
                    const amIReceiver = (f.friend_id === currentUserId);
                    const otherUserId = amIReceiver ? f.user_id : f.friend_id;
                    const pfp = f.profile_picture || '../../../files/uploads/user/defaultUser.png';
                    const div = document.createElement('div');
                    div.style = "display: flex; justify-content: space-between; align-items: center; padding: 12px; background: linear-gradient(135deg, #f0f7ff 0%, #e0f2ff 100%); border-radius: 8px; border: 1px solid #bfdbfe;";
                    div.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                            <img src="${pfp}" alt="${escapeHtml(f.username)}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6;">
                            <div>
                                <strong>${escapeHtml(f.username)}</strong><br>
                                <small class="text-muted" style="font-size: 0.75em;">Pending</small>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            ${amIReceiver ? `<button class="btn btn-icon btn-accept-friend" data-id="${f.id}" data-user-id="${otherUserId}" title="Accept" aria-label="Accept friend request"><i class="fas fa-check" style="color: #10b981;"></i></button>` : 
                                            `<button class="btn btn-icon" disabled style="color: #d1d5db; cursor: not-allowed;"><i class="fas fa-check"></i></button>`}
                            <button class="btn btn-icon btn-remove-friend" data-id="${f.id}" title="${amIReceiver ? 'Decline' : 'Cancel'}" aria-label="${amIReceiver ? 'Decline' : 'Cancel'} friend request"><i class="fas fa-times" style="color: #ef4444;"></i></button>
                        </div>
                    `;
                    pendingList.appendChild(div);
                });
            } else {
                pendingSection.style.display = 'none';
            }

            renderPaginatedFriends();

            document.querySelectorAll('.btn-accept-friend').forEach(b => {
                b.onclick = async (e) => {
                    const fid = e.currentTarget.getAttribute('data-id');
                    const otherUserId = e.currentTarget.getAttribute('data-user-id');
                    
                    await authFetch(`/friends/${fid}/accept`, { method: 'PUT' });
                    
                    if (otherUserId && currentUserId) {
                        try {
                            console.log('[Chat Friends] Creating discussion with:', otherUserId);
                            const res = await authFetch(`/users/${currentUserId}/discussions`, {
                                method: 'POST',
                                body: JSON.stringify({ user1_id: currentUserId, user2_id: otherUserId })
                            });
                            if (res.ok) {
                                console.log('[Chat Friends] Discussion created successfully');
                            } else {
                                console.error('[Chat Friends] Failed to create discussion:', res.status);
                            }
                        } catch (err) {
                            console.error('[Chat Friends] Error creating discussion:', err);
                        }
                    }
                    
                    loadFriends();
                };
            });

            document.querySelectorAll('.btn-start-convo').forEach(b => {
                b.onclick = async (e) => {
                    const otherUserId = e.currentTarget.getAttribute('data-user-id');
                    if (!otherUserId || !currentUserId) return;
                    
                    try {
                        console.log('[Chat Friends] Creating or opening discussion with:', otherUserId);
                        const res = await authFetch(`/users/${currentUserId}/discussions`, {
                            method: 'POST',
                            body: JSON.stringify({ user1_id: currentUserId, user2_id: otherUserId })
                        });
                        if (res.ok) {
                            const data = await res.json();
                            console.log('[Chat Friends] Discussion ready:', data.id);
                            
                            await loadDiscussionsAndGroups();
                            
                            const modal = document.getElementById('modal-friends');
                            if (modal) closeModal(modal);
                        } else {
                            console.error('[Chat Friends] Failed to create discussion:', res.status);
                            alert('Error starting conversation');
                        }
                    } catch (err) {
                        console.error('[Chat Friends] Error:', err);
                        alert('Error starting conversation: ' + err.message);
                    }
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
            acceptedList.innerHTML = '<div class="empty-state">' + t('common.error.loading.friends', 'Error loading friends.') + '</div>';
            pendingList.innerHTML = 'Error.';
            console.error(e);
        }
    }

    function renderPaginatedFriends() {
        const acceptedList = document.getElementById('accepted-friends-list');
        const noFriendsMsg = document.getElementById('no-friends-msg');
        const loadMoreBtn = document.getElementById('load-more-friends');
        const searchInput = document.getElementById('friend-search-input');

        if (!acceptedList) {
            console.warn('[Friends] acceptedList not found');
            return;
        }

        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const filteredFriends = allFriends.filter(f => {
            const fname = (f.first_name || '').toLowerCase();
            const lname = (f.last_name || '').toLowerCase();
            const uname = (f.username || '').toLowerCase();
            return uname.includes(searchTerm) || fname.includes(searchTerm) || lname.includes(searchTerm);
        });

        acceptedList.innerHTML = '';

        if (filteredFriends.length === 0) {
            if (noFriendsMsg) noFriendsMsg.style.display = 'block';
            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            return;
        }

        if (noFriendsMsg) noFriendsMsg.style.display = 'none';

        const displayFriends = filteredFriends.slice(0, (currentFriendsPage + 1) * friendsDisplayCount);

        displayFriends.forEach(f => {
            const otherUserId = f.user_id === currentUserId ? f.friend_id : f.user_id;
            const pfp = f.profile_picture || '../../../files/uploads/user/defaultUser.png';
            const div = document.createElement('div');
            div.style = "display: flex; justify-content: space-between; align-items: center; padding: 12px; background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%); border-radius: 8px; border: 1px solid #e5e7eb; transition: all 0.3s ease; cursor: pointer;";
            div.onmouseenter = () => div.style.boxShadow = "0 4px 12px rgba(0, 0, 0, 0.1)";
            div.onmouseleave = () => div.style.boxShadow = "none";
            
            div.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                    <img src="${pfp}" alt="${escapeHtml(f.username)}" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6; flex-shrink: 0;">
                    <div style="flex: 1; min-width: 0;">
                        <strong style="display: block; font-size: 0.95em;">${escapeHtml(f.username)}</strong>
                        <small style="color: #6b7280; font-size: 0.85em;">${escapeHtml(f.first_name)} ${escapeHtml(f.last_name)}</small>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-icon btn-start-convo" data-user-id="${otherUserId}" title="Message" aria-label="Start conversation"><i class="fas fa-comments" style="color: #10b981;"></i></button>
                    <button class="btn btn-icon btn-remove-friend" data-id="${f.id}" title="Remove" aria-label="Remove friend"><i class="fas fa-user-minus" style="color: #10b981;"></i></button>
                </div>
            `;
            acceptedList.appendChild(div);
        });

        if (displayFriends.length < filteredFriends.length) {
            if (loadMoreBtn) loadMoreBtn.style.display = 'block';
        } else {
            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
        }

        document.querySelectorAll('.btn-start-convo').forEach(b => {
            b.onclick = async (e) => {
                e.stopPropagation();
                const otherUserId = e.currentTarget.getAttribute('data-user-id');
                if (!otherUserId || !currentUserId) return;
                
                try {
                    console.log('[Chat Friends] Creating or opening discussion with:', otherUserId);
                    const res = await authFetch(`/users/${currentUserId}/discussions`, {
                        method: 'POST',
                        body: JSON.stringify({ user1_id: currentUserId, user2_id: otherUserId })
                    });
                    if (res.ok) {
                        const data = await res.json();
                        console.log('[Chat Friends] Discussion ready:', data.id);
                        
                        await loadDiscussionsAndGroups();
                        
                        const modal = document.getElementById('modal-friends');
                        if (modal) closeModal(modal);
                    } else {
                        console.error('[Chat Friends] Failed to create discussion:', res.status);
                        alert('Error starting conversation');
                    }
                } catch (err) {
                    console.error('[Chat Friends] Error:', err);
                    alert('Error starting conversation: ' + err.message);
                }
            };
        });

        document.querySelectorAll('.btn-remove-friend').forEach(b => {
            b.onclick = async (e) => {
                e.stopPropagation();
                const fid = e.currentTarget.getAttribute('data-id');
                await authFetch(`/friends/${fid}`, { method: 'DELETE' });
                loadFriends(); 
            };
        });
    }

    const searchInput = document.getElementById('friend-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            currentFriendsPage = 0;
            renderPaginatedFriends();
        });
    }

    const loadMoreBtn = document.getElementById('btn-load-more-friends');
    if (loadMoreBtn) {
        loadMoreBtn.onclick = () => {
            currentFriendsPage++;
            renderPaginatedFriends();
        };
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

        requestAnimationFrame(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });

        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 100);
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
