(function() {
    'use strict';

    
    let currentPostId = null;
    let currentPostAuthorId = null;

    document.addEventListener('DOMContentLoaded', function() {
        const params = new URLSearchParams(window.location.search);
        const forumId = params.get('uuid');
        if (!forumId) return;
        loadPosts(forumId);

        const openBtn = document.getElementById('add-post');
        const modal = document.querySelector('.add-modal');
        const closeBtn = document.getElementById('close-add-modal');
        const form = document.getElementById('add-post-form');
        const textarea = document.getElementById('post-content');
        const postCharCount = document.getElementById('post-char-count');
        const editForm = document.getElementById('edit-post-form');
        const editTextarea = document.getElementById('edit-post-content');
        const editCharCount = document.getElementById('edit-post-char-count');
        const deleteForm = document.getElementById('delete-post-form');
        const newPostTitle = document.querySelector('#new-post-modal h2');
        const focusableSelector = 'button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])';
        let lastFocused = null;

        function openModal(elem) {
            const m = elem || modal;
            if (!m) return;
            lastFocused = document.activeElement;
            m.classList.add('is-open');
            document.body.classList.add('modal-open');
            m.setAttribute('aria-hidden','false');
            const target = m.querySelector(textarea ? '#post-content' : focusableSelector);
            if (target) target.focus();
        }
        function closeModal(elem) {
            const m = elem || modal;
            if (!m) return;
            m.classList.remove('is-open');
            document.body.classList.remove('modal-open');
            m.setAttribute('aria-hidden','true');
            if (lastFocused && typeof lastFocused.focus==='function') lastFocused.focus();
            if (m === modal) {
                replyTo = null;
                if (newPostTitle) newPostTitle.textContent = 'New Post';
                if (textarea) {
                    textarea.value = '';
                    updateCharCount(textarea, postCharCount);
                }
            }
            if (m === editModal) {
                if (editTextarea) {
                    editTextarea.value = '';
                    updateCharCount(editTextarea, editCharCount);
                }
            }
            if (m === deleteModal) {
                currentPostId = null;
            }
        }
        function trapFocus(e) {
            if (!modal || !modal.classList.contains('is-open')) return;
            const focusable = Array.prototype.slice.call(modal.querySelectorAll(focusableSelector))
                .filter(el=>!el.hasAttribute('disabled'));
            if (focusable.length===0) return;
            const first = focusable[0];
            const last = focusable[focusable.length-1];
            if (e.shiftKey && document.activeElement===first) {
                e.preventDefault(); last.focus(); return;
            }
            if (!e.shiftKey && document.activeElement===last) {
                e.preventDefault(); first.focus();
            }
        }

        if (openBtn) openBtn.addEventListener('click', () => {
            replyTo = null;
            if (newPostTitle) newPostTitle.textContent = 'New Post';
            if (textarea) textarea.value = '';
            openModal(modal);
        });
        if (closeBtn) closeBtn.addEventListener('click', () => closeModal(modal));
        if (modal) {
            modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(modal); });
        }
        document.addEventListener('keydown', function(e){
            if (!modal || !modal.classList.contains('is-open')) return;
            if (e.key==='Escape') { e.preventDefault(); closeModal(modal); return; }
            if (e.key==='Tab') trapFocus(e);
        });

        const editModal = document.getElementById('edit-post-modal');
        const deleteModal = document.getElementById('delete-post-modal');
        const closeEditBtns = editModal ? editModal.querySelectorAll('.close-edit-modal') : [];
        const closeDeleteBtns = deleteModal ? deleteModal.querySelectorAll('.close-delete-modal') : [];
        closeEditBtns.forEach(b=>b.addEventListener('click',()=>{ closeModal(editModal); currentPostId = null; currentPostAuthorId = null; }));
        closeDeleteBtns.forEach(b=>b.addEventListener('click',()=>{ closeModal(deleteModal); currentPostId = null; currentPostAuthorId = null; }));

        let replyTo = null;
        document.addEventListener('click', function(e) {
            const replyBtn = e.target.closest('.reply-post');
            if (replyBtn) {
                const postDiv = replyBtn.closest('.forum-post');
                if (postDiv) {
                    replyTo = postDiv.getAttribute('data-post-id');
                } else {
                    replyTo = null;
                }
                if (newPostTitle) {
                    if (replyTo && postDiv) {
                        const uname = postDiv.querySelector('.username')?.textContent || '';
                        newPostTitle.textContent = uname ? `Reply to ${uname}` : 'Reply';
                    } else {
                        newPostTitle.textContent = 'New Post';
                    }
                }
                openModal(modal);
                return;
            }

            const editBtn = e.target.closest('.edit-post');
            if (editBtn) {
                console.log('edit button clicked');
                const postDiv = editBtn.closest('.forum-post');
                if (postDiv) {
                    currentPostId = postDiv.getAttribute('data-post-id');
                    currentPostAuthorId = postDiv.getAttribute('data-author-id');
                    console.log('setting currentPostId/AuthorId', currentPostId, currentPostAuthorId);
                    const content = postDiv.querySelector('.content')?.textContent || '';
                    document.getElementById('edit-post-content').value = content;
                    updateCharCount(editTextarea, editCharCount);
                }
                openModal(editModal);
                return;
            }

            const delBtn = e.target.closest('.delete-post');
            if (delBtn) {
                console.log('delete button clicked');
                const postDiv = delBtn.closest('.forum-post');
                if (postDiv) {
                    currentPostId = postDiv.getAttribute('data-post-id');
                    currentPostAuthorId = postDiv.getAttribute('data-author-id');
                    console.log('preparing to delete', currentPostId, currentPostAuthorId);
                }
                openModal(deleteModal);
                return;
            }
        });

        function updateCharCount(el, counterEl) {
            if (!el || !counterEl) return;
            const len = el.value.length;
            counterEl.textContent = `${len} / 300`;
            const invalid = len < 5 || len > 300;
            counterEl.classList.toggle('invalid', invalid);
            const formEl = el.closest('form');
            if (formEl) {
                const btn = formEl.querySelector('button[type="submit"]');
                if (btn) btn.disabled = invalid;
            }
        }

        if (textarea) {
            textarea.addEventListener('input', () => updateCharCount(textarea, postCharCount));
        }
        if (editTextarea) {
            editTextarea.addEventListener('input', () => updateCharCount(editTextarea, editCharCount));
        }

        document.addEventListener('click', function(e) {
            const a = e.target.closest('a.post-anchor, a.jump-to-post');
            if (!a) return;
            e.preventDefault();
            const tid = a.dataset.targetId;
            if (!tid) return;
            const tgt = document.getElementById('post-' + tid);
            if (tgt) {
                tgt.scrollIntoView({behavior:'smooth', block:'start'});
                tgt.classList.add('highlight');
                setTimeout(()=>tgt.classList.remove('highlight'), 2000);
            }
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!forumId) return;
                const content = textarea.value.trim();
                if (content.length < 5 || content.length > 300) {
                    alert('Content must be between 5 and 300 characters');
                    return;
                }
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting';
                const payload = { content: content, forum_id: forumId };
                if (window.currentUserId) {
                    payload.author_id = window.currentUserId;
                }
                if (replyTo) {
                    payload.parent_id = replyTo;
                }
                fetch(`forums-api?forum_id=${encodeURIComponent(forumId)}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                }).then(r => r.text())
                    .then(text => {
                        if (text) {
                            try {
                                const obj = JSON.parse(text);
                                if (obj.error) {
                                    alert(obj.error);
                                }
                            } catch (e) {
                                console.error('Unexpected response creating post:', text, e);
                            }
                        }
                    })
                  .then(() => {
                      closeModal();
                      textarea.value = '';
                      replyTo = null;
                      if (newPostTitle) newPostTitle.textContent = 'New Post';
                      loadPosts(forumId);
                  }).catch(err => {
                      console.error('Failed to create post', err);
                      alert(err.message || 'Unable to submit post');
                  }).finally(() => {
                      submitBtn.disabled = false;
                      submitBtn.innerHTML = originalText;
                  });
            });
        }

        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('edit form submit triggered');
                const c = editTextarea.value.trim();
                if (c.length < 5 || c.length > 300) {
                    alert('Content must be between 5 and 300 characters');
                    return;
                }
                if (!forumId || !currentPostId) {
                    alert('Unable to determine which post to update');
                    return;
                }
                const submitBtn = editForm.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving';
                }

                const payload = { content: c, forum_id: forumId };
                if (currentPostAuthorId) payload.author_id = currentPostAuthorId;
                fetch(`forums-api?forum_id=${encodeURIComponent(forumId)}&post_id=${encodeURIComponent(currentPostId)}&_method=PATCH`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                })
                .then(r => {
                    if (!r.ok) throw new Error('Server returned ' + r.status);
                    return r.text();
                })
                .then(text => {
                    if (text) {
                        try {
                            const obj = JSON.parse(text);
                            if (obj.error) {
                                alert(obj.error);
                            }
                        } catch (err) {
                            console.error('Unexpected response updating post:', text, err);
                        }
                    }
                })
                .then(() => {
                    closeModal(editModal);
                    loadPosts(forumId);
                    currentPostId = null;
                })
                .catch(err => {
                    console.error('Failed to update post', err);
                    alert(err.message || 'Unable to update post');
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                });
            });
        }

        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('delete form submit triggered');
                if (!forumId || !currentPostId) {
                    alert('Unable to determine which post to delete');
                    return;
                }
                const submitBtn = deleteForm.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting';
                }

                let url = `forums-api?forum_id=${encodeURIComponent(forumId)}&post_id=${encodeURIComponent(currentPostId)}`;
                if (currentPostAuthorId) {
                    url += `&author_id=${encodeURIComponent(currentPostAuthorId)}`;
                }
                console.log('delete request', url);
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => {
                    if (!r.ok) throw new Error('Server returned ' + r.status);
                    return r.text();
                })
                .then(text => {
                    if (text) {
                        try {
                            const obj = JSON.parse(text);
                            if (obj.error) {
                                alert(obj.error);
                            }
                        } catch (err) {
                            console.error('Unexpected response deleting post:', text, err);
                        }
                    }
                })
                .then(() => {
                    closeModal(deleteModal);
                    loadPosts(forumId);
                    currentPostId = null;
                })
                .catch(err => {
                    console.error('Failed to delete post', err);
                    alert(err.message || 'Unable to delete post');
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                });
            });
        }

    });

    function loadPosts(forumId) {
        currentPostId = null;
        const container = document.getElementById('forum-posts');
        if (!container) return;

        const skeletonHtml = generatePostSkeleton();
        container.innerHTML = skeletonHtml.repeat(3);
        fetch(`forums-api?forum_id=${encodeURIComponent(forumId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(text => {
                let posts = [];
                let parsed = null;
                if (text) {
                    try {
                        parsed = JSON.parse(text);
                        if (parsed && typeof parsed === 'object' && Array.isArray(parsed.items)) {
                            posts = parsed.items;
                        } else if (Array.isArray(parsed)) {
                            posts = parsed;
                        } else {
                            console.warn('Unexpected posts response structure', parsed);
                        }
                    } catch (e) {
                        console.error('Invalid JSON loading posts:', text);
                        throw e;
                    }
                }
                const countElem = document.getElementById('forum-post-count');
                if (!Array.isArray(posts) || posts.length === 0) {
                    container.innerHTML = '<p class="error-message">No posts yet.</p>';
                    if (countElem) countElem.textContent = '0';
                    return;
                }
                container.innerHTML = '';
                if (countElem) {
                    if (parsed && parsed.total !== undefined && Number.isFinite(parsed.total)) {
                        countElem.textContent = String(parsed.total);
                    } else {
                        countElem.textContent = String(posts.length);
                    }
                }
                const postsById = {};
                posts.forEach(p => { if (p.id) postsById[p.id] = p; });
                posts.forEach(p => renderPost(container, p, postsById));
            })
            .catch(err => {
                console.error('Failed to load forum posts', err);
                container.innerHTML = '<p class="error-message">Unable to load posts. Please try again later.</p>';
            });
    }

    const userCache = {};

    function renderPost(container, post, postsById) {
        console.log('renderPost', post, 'currentUserId', window.currentUserId);
        const postEl = document.createElement('div');
        postEl.className = 'forum-post';
        if (post.id) {
            postEl.setAttribute('data-post-id', post.id);
            postEl.id = 'post-' + post.id;
        }
        if (post.author_id) {
            postEl.setAttribute('data-author-id', post.author_id);
        }
        const nilUuid = '00000000-0000-0000-0000-000000000000';
        const isReply = post.parent_id && post.parent_id !== nilUuid;
        if (isReply) {
            postEl.classList.add('reply');
        }

        const avatar = document.createElement('img');
        avatar.src = '../../../files/uploads/user/defaultUser.png';
        avatar.alt = 'Profile Picture';
        avatar.className = 'avatar skeleton';
        avatar.setAttribute('data-blob-src', "../../../files/uploads/user/defaultUser.png");
        postEl.appendChild(avatar);

        const body = document.createElement('div');
        body.className = 'post-body';
        postEl.appendChild(body);

        const userEl = document.createElement('div');
        userEl.className = 'username skeleton';
        userEl.textContent = 'Unknown';
        body.appendChild(userEl);

        if (isReply) {
            const replyInfo = document.createElement('div');
            replyInfo.className = 'reply-info';
            replyInfo.textContent = '↳ In reply to ';
            const link = document.createElement('a');
            link.href = '#';
            link.className = 'jump-to-post';
            link.dataset.targetId = post.parent_id;
            link.textContent = 'post #' + post.parent_id.slice(0,8);
            replyInfo.appendChild(link);
            body.appendChild(replyInfo);

            if (postsById && postsById[post.parent_id] && postsById[post.parent_id].content) {
                const quoted = document.createElement('pre');
                quoted.className = 'quote';
                quoted.textContent = postsById[post.parent_id].content;
                body.appendChild(quoted);
            }
        }

        const contentEl = document.createElement('div');
        contentEl.className = 'content skeleton';
        body.appendChild(contentEl);

        const metaEl = document.createElement('div');
        metaEl.className = 'meta';
        const createdEl = document.createElement('span');
        createdEl.className = 'skeleton';
        createdEl.innerHTML = `<i class="fa-regular fa-clock"></i>&nbsp;`;
        metaEl.appendChild(createdEl);
        const updatedEl = document.createElement('span');
        updatedEl.className = 'skeleton';
        updatedEl.style.marginLeft = '12px';
        updatedEl.innerHTML = `<i class="fa-solid fa-pencil"></i>&nbsp;`;
        metaEl.appendChild(updatedEl);

        if (post.id) {
            const idLink = document.createElement('a');
            idLink.href = '#';
            idLink.className = 'post-anchor';
            idLink.textContent = '#' + post.id.slice(0,8);
            idLink.dataset.targetId = post.id;
            idLink.style.marginLeft = '12px';
            metaEl.appendChild(idLink);
        }
        body.appendChild(metaEl);

        const actions = document.createElement('div');
        actions.className = 'post-actions';
        const btnReply = document.createElement('button');
        btnReply.type = 'button';
        btnReply.className = 'btn-icon reply-post';
        btnReply.innerHTML = '<i class="fa-solid fa-reply"></i>';
        const btnEdit = document.createElement('button');
        btnEdit.type = 'button';
        btnEdit.className = 'btn-icon edit-post';
        btnEdit.innerHTML = '<i class="fa-regular fa-pen-to-square"></i>';
        const btnDelete = document.createElement('button');
        btnDelete.type = 'button';
        btnDelete.className = 'btn-icon delete-post';
        btnDelete.innerHTML = '<i class="fa-regular fa-trash-alt"></i>';
        actions.appendChild(btnReply);
        actions.appendChild(btnEdit);
        actions.appendChild(btnDelete);
        postEl.appendChild(actions);

        if (!window.currentUserId) {
            console.log('hiding actions because no user');
            actions.style.display = 'none';
        } else if (post.author_id && window.currentUserId !== post.author_id) {
            console.log('hiding edit/delete because currentUserId', window.currentUserId, '!= post.author_id', post.author_id);
            btnEdit.style.display = 'none';
            btnDelete.style.display = 'none';
        } else {
            console.log('showing actions for user', window.currentUserId, 'post author', post.author_id);
        }
        container.appendChild(postEl);

        contentEl.textContent = post.content || '';
        contentEl.classList.remove('skeleton');
        createdEl.append(formatDate(post.created_at));
        createdEl.classList.remove('skeleton');
        if (post.updated_at && post.updated_at !== post.created_at) {
            updatedEl.append(formatDate(post.updated_at));
            updatedEl.classList.remove('skeleton');
        } else {
            updatedEl.remove();
        }

        const authorId = post.author_id;
        if (authorId) {
            if (userCache[authorId]) {
                applyUserInfo(userCache[authorId]);
            } else {
                fetch(`users-api?id=${encodeURIComponent(authorId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(text => {
                        try {
                            const user = text ? JSON.parse(text) : null;
                            if (user && typeof user === 'object') {
                                userCache[authorId] = user;
                                applyUserInfo(user);
                            }
                        } catch(e) {
                            console.warn('Unable to parse user for post', e, text);
                        }
                    })
                    .catch(err => console.warn('Unable to fetch user for post', err));
            }
        }

        function applyUserInfo(user) {
            if (user.username) {
                userEl.textContent = user.username;
                userEl.classList.remove('skeleton');
            }
            if (user.profile_picture) {
                avatar.setAttribute('data-blob-src', "../../../files/uploads/user/" + user.profile_picture);
            } else {
                avatar.setAttribute('data-blob-src', "../../../files/uploads/user/defaultUser.png");
            }
            avatar.classList.remove('skeleton');
        }
    }

    function generatePostSkeleton() {
        return `
        <div class="forum-post">
            <div class="avatar skeleton"></div>
            <div class="post-body">
                <div class="username skeleton" style="width:120px;height:16px"></div>
                <div class="content skeleton" style="width:100%;height:40px;margin-top:6px"></div>
                <div class="meta" style="margin-top:8px">
                    <span class="skeleton" style="width:80px;height:12px;display:inline-block"></span>
                    <span class="skeleton" style="width:80px;height:12px;display:inline-block;margin-left:12px"></span>
                </div>
            </div>
        </div>
        `;
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

})();
