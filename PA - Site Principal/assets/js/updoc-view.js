(function () {
    'use strict';

    var PROJECT_ID      = '';
    var CURRENT_USER_ID = '';
    var IS_OWNER        = false;
    var UPDOC_API_PATH  = 'updoc-api';

    var STEPS_PER_PAGE  = 5;
    var allSteps        = [];
    var currentPage     = 1;
    var liked           = false;

    var likeBtn   = document.getElementById('like-btn');
    var likeCount = document.getElementById('like-count');

    function initViewData() {
        var data = typeof window.UPDOC_VIEW_DATA !== 'undefined' ? window.UPDOC_VIEW_DATA : {};
        PROJECT_ID = data.projectId || '';
        CURRENT_USER_ID = data.currentUserId || '';
        IS_OWNER = !!data.isOwner;
        UPDOC_API_PATH = typeof window.UPDOC_API_PATH !== 'undefined' ? window.UPDOC_API_PATH : 'updoc-api';
    }

    function postAPI(action, body) {
        body.action = action;
        return fetch(UPDOC_API_PATH, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.text().then(function (text) {
                if (!text) {
                    return {};
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return {};
                }
            });
        });
    }

    function setLikeLoading(isLoading) {
        if (!likeBtn) {
            return;
        }
        likeBtn.disabled = isLoading;
        likeBtn.classList.toggle('loading', isLoading);
        if (isLoading) {
            likeBtn.setAttribute('aria-busy', 'true');
        } else {
            likeBtn.removeAttribute('aria-busy');
        }
    }

    function setupLikeActions() {
        if (!likeBtn || !likeCount || !PROJECT_ID) {
            return;
        }

        postAPI('get_like_status', { project_id: PROJECT_ID })
        .then(function (data) {
            liked = !!data.liked;
            likeBtn.classList.toggle('liked', liked);
            likeCount.textContent = data.count || 0;
        })
        .catch(function () {
            // ignore like status errors
        });

        likeBtn.addEventListener('click', function () {
            var action = liked ? 'unlike_project' : 'like_project';
            setLikeLoading(true);

            postAPI(action, { project_id: PROJECT_ID })
            .then(function (data) {
                if (data && typeof data.liked === 'boolean') {
                    liked = data.liked;
                } else {
                    liked = !liked;
                }
                likeBtn.classList.toggle('liked', liked);

                if (data && typeof data.count === 'number') {
                    likeCount.textContent = data.count;
                } else {
                    var n = parseInt(likeCount.textContent, 10) || 0;
                    likeCount.textContent = liked ? n + 1 : Math.max(0, n - 1);
                }
            })
            .catch(function () {
                // ignore like click errors
            })
            .finally(function () {
                setLikeLoading(false);
            });
        });
    }

    function start() {
        initViewData();
        initCommentControls();
        setupLikeActions();
        fetchSteps();
        fetchComments();
        var loader = document.getElementById('initial-loader');
        if (loader) {
            loader.style.opacity = '0';
            loader.style.transition = 'opacity .3s';
            setTimeout(function () { loader.remove(); }, 350);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    function openModal(el) {
        el.classList.add('is-visible');
        document.body.classList.add('modal-open');
    }
    function closeModalEl(el) {
        el.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
    }

    var commentList;
    var noMsg;

    var addModal;
    var addInput;
    var openCommentBtn;
    var commentAddClose;
    var commentAddCancel;
    var commentAddSubmit;

    var editModal;
    var editInput;
    var editingCommentId = null;
    var commentEditClose;
    var commentEditCancel;
    var commentEditSubmit;

    var deleteModal;
    var deletingCommentId = null;
    var commentDeleteClose;
    var commentDeleteCancel;
    var commentDeleteConfirm;

    function initCommentControls() {
        commentList  = document.getElementById('comment-list');
        noMsg        = document.getElementById('no-comments-msg');

        addModal  = document.getElementById('comment-add-modal');
        addInput  = document.getElementById('comment-add-input');
        openCommentBtn = document.getElementById('open-comment-modal-btn');
        commentAddClose = document.getElementById('comment-add-close');
        commentAddCancel = document.getElementById('comment-add-cancel');
        commentAddSubmit = document.getElementById('comment-add-submit');

        if (openCommentBtn && addModal && addInput) {
            openCommentBtn.addEventListener('click', function () {
                addInput.value = '';
                openModal(addModal);
                setTimeout(function () { addInput.focus(); }, 80);
            });
        }

        function closeAddModal() { if (addModal) closeModalEl(addModal); }
        if (commentAddClose) commentAddClose.addEventListener('click', closeAddModal);
        if (commentAddCancel) commentAddCancel.addEventListener('click', closeAddModal);
        if (addModal) {
            addModal.addEventListener('click', function (e) { if (e.target === addModal) closeAddModal(); });
        }

        if (commentAddSubmit) {
            commentAddSubmit.addEventListener('click', function () {
                if (!addInput) return;
                var content = addInput.value.trim();
                if (!content) return;
                var btn = this;
                btn.disabled = true;

                postAPI('create_comment', { project_id: PROJECT_ID, content: content })
                .then(function (data) {
                    btn.disabled = false;
                    if (data.error) return;
                    if (noMsg) noMsg.style.display = 'none';
                    if (commentList) {
                        var el = buildCommentEl(data);
                        commentList.appendChild(el);
                        commentList.style.display = '';
                    }
                    var commentsSkeleton = document.getElementById('comments-skeleton');
                    if (commentsSkeleton) commentsSkeleton.style.display = 'none';
                    var cnt = document.getElementById('comment-count');
                    if (cnt) {
                        var n = parseInt(cnt.textContent.replace(/\D/g, ''), 10) || 0;
                        cnt.textContent = '(' + (n + 1) + ')';
                    }
                    closeAddModal();
                })
                .catch(function () { btn.disabled = false; });
            });
        }

        editModal        = document.getElementById('comment-edit-modal');
        editInput        = document.getElementById('comment-edit-input');
        editingCommentId = null;

        function closeEditModal() { if (editModal) closeModalEl(editModal); editingCommentId = null; }
        commentEditClose = document.getElementById('comment-edit-close');
        commentEditCancel = document.getElementById('comment-edit-cancel');
        if (commentEditClose) commentEditClose.addEventListener('click', closeEditModal);
        if (commentEditCancel) commentEditCancel.addEventListener('click', closeEditModal);
        if (editModal) {
            editModal.addEventListener('click', function (e) { if (e.target === editModal) closeEditModal(); });
        }

        if (commentList) {
            commentList.addEventListener('click', function (e) {
                var editBtn = e.target.closest('.edit-comment-btn');
                if (editBtn && editInput) {
                    var cId = editBtn.dataset.commentId;
                    var commentEl = document.getElementById('comment-' + cId);
                    if (!commentEl) return;
                    editingCommentId = cId;
                    editInput.value = commentEl.dataset.content || (commentEl.querySelector('.updoc-comment-text') || {}).textContent || '';
                    openModal(editModal);
                    setTimeout(function () { if (editInput) editInput.focus(); }, 80);
                }
            });
        }

        commentEditSubmit = document.getElementById('comment-edit-submit');
        if (commentEditSubmit) {
            commentEditSubmit.addEventListener('click', function () {
                if (!editInput) return;
                var content = editInput.value.trim();
                if (!content || !editingCommentId) return;
                var btn = this;
                btn.disabled = true;

                postAPI('update_comment', { project_id: PROJECT_ID, comment_id: editingCommentId, content: content })
                .then(function (data) {
                    btn.disabled = false;
                    if (data.error) return;
                    var commentEl = document.getElementById('comment-' + editingCommentId);
                    if (commentEl) {
                        var textEl = commentEl.querySelector('.updoc-comment-text');
                        if (textEl) textEl.textContent = content;
                        commentEl.dataset.content = content;
                    }
                    closeEditModal();
                })
                .catch(function () { btn.disabled = false; });
            });
        }

        deleteModal       = document.getElementById('comment-delete-modal');
        deletingCommentId = null;

        function closeDeleteModal() { if (deleteModal) closeModalEl(deleteModal); deletingCommentId = null; }
        commentDeleteClose = document.getElementById('comment-delete-close');
        commentDeleteCancel = document.getElementById('comment-delete-cancel');
        commentDeleteConfirm = document.getElementById('comment-delete-confirm');
        if (commentDeleteClose) commentDeleteClose.addEventListener('click', closeDeleteModal);
        if (commentDeleteCancel) commentDeleteCancel.addEventListener('click', closeDeleteModal);
        if (deleteModal) {
            deleteModal.addEventListener('click', function (e) { if (e.target === deleteModal) closeDeleteModal(); });
        }

        if (commentList) {
            commentList.addEventListener('click', function (e) {
                var delBtn = e.target.closest('.delete-comment-btn');
                if (delBtn) {
                    deletingCommentId = delBtn.dataset.commentId;
                    openModal(deleteModal);
                }
            });
        }

        if (commentDeleteConfirm) {
            commentDeleteConfirm.addEventListener('click', function () {
                if (!deletingCommentId) return;
                var btn = this;
                btn.disabled = true;

                postAPI('delete_comment', { project_id: PROJECT_ID, comment_id: deletingCommentId })
                .then(function () {
                    btn.disabled = false;
                    var el = document.getElementById('comment-' + deletingCommentId);
                    if (el) el.remove();
                    var cnt = document.getElementById('comment-count');
                    if (cnt) {
                        var n = parseInt(cnt.textContent.replace(/\D/g, ''), 10) || 1;
                        cnt.textContent = '(' + Math.max(0, n - 1) + ')';
                    }
                    closeDeleteModal();
                })
                .catch(function () { btn.disabled = false; });
            });
        }
    }

    function buildCommentEl(data) {
        var el = document.createElement('div');
        el.className = 'updoc-comment';
        el.id        = 'comment-' + escAttr(data.id || '');
        el.dataset.content = data.content || '';

        var dateStr;
        if (data.created_at) {
            var d = new Date(data.created_at);
            dateStr = d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        } else {
            var now = new Date();
            dateStr = now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        }

        var author  = data.username || data.author_name || ('@' + (data.user_id || CURRENT_USER_ID));
        var canEdit = String(data.user_id) === String(CURRENT_USER_ID);
        var canDelete = canEdit || IS_OWNER;

        var actionsHtml = '';
        if (canEdit) {
            actionsHtml += '<button class="updoc-comment-action-btn edit-comment-btn" data-comment-id="' + escAttr(data.id || '') + '">' +
                '<i class="fa-solid fa-pen"></i> Edit' +
            '</button>';
        }
        if (canDelete) {
            actionsHtml += '<button class="updoc-comment-action-btn danger delete-comment-btn" data-comment-id="' + escAttr(data.id || '') + '">' +
                '<i class="fa-solid fa-trash"></i> Delete' +
            '</button>';
        }

        el.innerHTML =
            '<div class="updoc-comment-meta">' +
              '<span class="updoc-comment-author">' + escHtml(author) + '</span>' +
              '<span>' + escHtml(dateStr) + '</span>' +
            '</div>' +
            '<div class="updoc-comment-text">' + escHtml(data.content || '') + '</div>' +
            (actionsHtml ? '<div class="updoc-comment-actions">' + actionsHtml + '</div>' : '');
        return el;
    }

    function showStepsEmpty(message) {
        var stepsSkeleton = document.getElementById('steps-skeleton');
        var stepsContainer = document.getElementById('steps-container');
        var emptyMessage = document.getElementById('steps-empty-message');
        var meta = document.getElementById('step-count-meta');
        if (stepsSkeleton) stepsSkeleton.style.display = 'none';
        if (stepsContainer) stepsContainer.style.display = 'none';
        if (emptyMessage) emptyMessage.style.display = '';
        if (meta) meta.textContent = message || 'No steps yet';
    }

    function hideStepsEmpty() {
        var emptyMessage = document.getElementById('steps-empty-message');
        if (emptyMessage) emptyMessage.style.display = 'none';
    }

    function fetchSteps() {
        postAPI('get_steps', { project_id: PROJECT_ID })
        .then(function (steps) {
            console.debug('updoc-view: fetched steps', steps);
            if (!Array.isArray(steps)) {
                console.error('updoc-view: expected steps array, got:', steps);
                showStepsEmpty('Unable to load steps');
                return;
            }
            if (steps.length === 0) {
                showStepsEmpty(' ');
                return;
            }
            allSteps = steps;
            currentPage = 1;
            renderStepsPage();
        })
        .catch(function (error) {
            console.error('updoc-view: failed to fetch steps', error);
            showStepsEmpty('Unable to load steps');
        });
    }

    function renderStepsPage() {
        var skel      = document.getElementById('steps-skeleton');
        var container = document.getElementById('steps-container');
        var heading   = document.getElementById('steps-heading');
        var meta      = document.getElementById('step-count-meta');
        var emptyMessage = document.getElementById('steps-empty-message');
        var pagination = document.getElementById('steps-pagination');

        var totalPages = Math.ceil(allSteps.length / STEPS_PER_PAGE);
        var start = (currentPage - 1) * STEPS_PER_PAGE;
        var pageSteps = allSteps.slice(start, start + STEPS_PER_PAGE);

        if (emptyMessage) {
            emptyMessage.style.display = 'none';
        }
        container.innerHTML = pageSteps.map(function (step, i) {
            return buildStepViewCard(step, start + i);
        }).join('');

        if (skel)    skel.style.display    = 'none';
        if (heading) heading.style.display = '';
        container.style.display = '';
        if (meta) meta.textContent = allSteps.length + ' step' + (allSteps.length !== 1 ? 's' : '');

        if (!pagination) {
            pagination = document.createElement('div');
            pagination.id = 'steps-pagination';
            pagination.className = 'updoc-steps-pagination';
            container.parentNode.insertBefore(pagination, container.nextSibling);
        }

        if (totalPages <= 1) {
            pagination.style.display = 'none';
        } else {
            pagination.style.display = '';
            pagination.innerHTML =
                '<button class="updoc-page-btn" id="steps-prev" ' + (currentPage <= 1 ? 'disabled' : '') + '>' +
                    '<i class="fa-solid fa-chevron-left"></i>' +
                '</button>' +
                '<span class="updoc-page-info">' + currentPage + ' / ' + totalPages + '</span>' +
                '<button class="updoc-page-btn" id="steps-next" ' + (currentPage >= totalPages ? 'disabled' : '') + '>' +
                    '<i class="fa-solid fa-chevron-right"></i>' +
                '</button>';

            document.getElementById('steps-prev').addEventListener('click', function () {
                if (currentPage > 1) { currentPage--; renderStepsPage(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
            });
            document.getElementById('steps-next').addEventListener('click', function () {
                if (currentPage < totalPages) { currentPage++; renderStepsPage(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
            });
        }
    }

    function buildStepViewCard(step, idx) {
        var matHtml = '';
        if (step.materials && step.materials.length) {
            matHtml = '<div class="updoc-step-mats">' +
                step.materials.map(function (m) {
                    return '<span class="updoc-mat-chip">' +
                        escHtml(m.nom || m.name || '') +
                        (m.quantity ? ' &times; ' + escHtml(String(m.quantity)) : '') +
                        (m.unit     ? ' ' + escHtml(m.unit) : '') +
                    '</span>';
                }).join('') +
            '</div>';
        }

        return '<div class="updoc-step-view-card">' +
            '<div class="updoc-step-view-head">' +
                '<div class="updoc-step-view-num">' + (idx + 1) + '</div>' +
                '<h3 class="updoc-step-view-title">' + escHtml(step.title || '') + '</h3>' +
            '</div>' +
            '<div class="updoc-step-view-body">' +
                (step.description
                    ? '<div class="updoc-step-view-desc">' + escHtml(step.description).replace(/\n/g, '<br>') + '</div>'
                    : '') +
                matHtml +
            '</div>' +
        '</div>';
    }

    function fetchComments() {
        postAPI('get_comments', { project_id: PROJECT_ID })
        .then(function (comments) {
            renderComments(Array.isArray(comments) ? comments : []);
        })
        .catch(function () {
            var commentsSkeleton = document.getElementById('comments-skeleton');
            if (commentsSkeleton) commentsSkeleton.style.display = 'none';
            if (commentList) commentList.style.display = '';
        });
    }

    function renderComments(comments) {
        var skel = document.getElementById('comments-skeleton');
        if (skel) skel.style.display = 'none';
        if (!commentList) {
            return;
        }
        commentList.innerHTML = '';
        if (comments.length === 0) {
            if (noMsg) noMsg.style.display = '';
            commentList.style.display = 'none';
        } else {
            comments.forEach(function (c) { commentList.appendChild(buildCommentEl(c)); });
            if (noMsg) noMsg.style.display = 'none';
            commentList.style.display = '';
        }
        var cnt = document.getElementById('comment-count');
        if (cnt) cnt.textContent = comments.length ? '(' + comments.length + ')' : '';
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) { return escHtml(str); }

})();
