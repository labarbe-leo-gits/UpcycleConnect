(function() {
    'use strict';

    const tipContent = document.getElementById('tip-content');
    const tipLoading = document.getElementById('tip-loading');
    const commentsList = document.getElementById('comments-list');
    const commentsLoading = document.getElementById('comments-loading');
    const commentsPagination = document.getElementById('comments-pagination');
    const commentInput = document.getElementById('comment-input');
    const commentSubmit = document.getElementById('comment-submit');

    const PAGE_SIZE = 5;
    const COMMENT_LIMIT = 1500;
    let commentPage = 1;
    let totalCommentPages = 1;
    let currentTip = null;

    function getUuid() {
        const params = new URLSearchParams(window.location.search);
        return params.get('uuid');
    }

    function showTipSkeleton() {
        tipLoading.style.display = 'block';
        tipContent.style.display = 'none';
    }

    function showCommentsSkeleton() {
        commentsLoading.style.display = 'block';
        commentsList.style.display = 'none';
        commentsPagination.style.display = 'none';
    }

    function hideTipSkeleton() {
        tipLoading.style.display = 'none';
        tipContent.style.display = 'block';
    }

    function hideCommentsSkeleton() {
        commentsLoading.style.display = 'none';
        commentsList.style.display = 'block';
        commentsPagination.style.display = 'flex';
    }

    function updateCommentCounter() {
        const counter = document.getElementById('comment-counter');
        if (!counter || !commentInput) return;

        const length = commentInput.value.length;
        const remaining = Math.max(0, COMMENT_LIMIT - length);
        counter.textContent = `${length} / ${COMMENT_LIMIT} ${remaining === 0 ? '(limit reached)' : ''}`;

        if (length > COMMENT_LIMIT) {
            counter.style.color = '#dc2626';
            commentSubmit.disabled = true;
        } else {
            counter.style.color = '#374151';
            commentSubmit.disabled = false;
        }
    }

    async function fetchTipById(uuid) {
        if (!uuid) return null;

        const response = await fetch(`tip-api?action=get&id=${encodeURIComponent(uuid)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error('Failed to load tip details');
        }

        const tip = await response.json();
        if (tip.error) {
            throw new Error(tip.error);
        }

        return tip;
    }

    async function fetchPoll(pollId) {
        if (!pollId) return null;

        const resp = await fetch(`tip-api?action=get&id=${encodeURIComponent(pollId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) return null;

        const data = await resp.json();
        if (data.error) return null;
        return data;
    }

    async function fetchPollOptions(pollId) {
        if (!pollId) return [];

        const resp = await fetch(`tip-api?action=poll_options&poll_id=${encodeURIComponent(pollId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) return [];

        const data = await resp.json();
        if (data.error) return [];
        return data;
    }

    async function fetchPollVotes(pollId) {
        if (!pollId) return [];

        const resp = await fetch(`tip-api?action=poll_votes&poll_id=${encodeURIComponent(pollId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) return [];

        const data = await resp.json();
        if (data.error) return [];
        return data;
    }

    function getCurrentUserId() {
        if (window.CURRENT_USER_ID) {
            return window.CURRENT_USER_ID;
        }
        const header = document.querySelector('header');
        return header ? header.dataset.userId : '';
    }

    function updatePollStats(options, votes) {
        const totalVotes = Array.isArray(votes) ? votes.length : 0;
        document.querySelectorAll('#poll-options .poll-option').forEach(opt => {
            const optionId = opt.dataset.optionId;
            const voteCount = Array.isArray(votes)
                ? votes.filter(v => (v.option_id || '').toLowerCase() === (optionId || '').toLowerCase()).length
                : 0;
            const percent = totalVotes > 0 ? Math.round((voteCount / totalVotes) * 100) : 0;
            const stats = opt.querySelector('.poll-option-stats');
            if (stats) {
                stats.textContent = `${percent}% (${voteCount})`;
            }
            const fill = opt.querySelector('.poll-option-bar-fill');
            if (fill) {
                fill.style.width = `${percent}%`;
            }
            opt.setAttribute('data-vote-percent', percent);
        });

        const totalNodeId = 'poll-vote-total';
        let totalNode = document.getElementById(totalNodeId);
        if (!totalNode) {
            const container = document.getElementById('poll-options');
            if (container) {
                totalNode = document.createElement('div');
                totalNode.id = totalNodeId;
                totalNode.className = 'poll-total-votes';
                container.prepend(totalNode);
            }
        }
        if (totalNode) {
            totalNode.textContent = totalVotes > 0 ? `${totalVotes} votes total` : 'No votes yet';
        }
    }

    function disablePollButtonsAndHighlight(selectedOptionId) {
        const options = document.querySelectorAll('#poll-options .poll-option');
        options.forEach(opt => {
            const button = opt.querySelector('button');
            const id = opt.dataset.optionId || '';
            if (id === selectedOptionId) {
                opt.classList.add('poll-option-selected');
            } else {
                opt.classList.remove('poll-option-selected');
            }
            if (button) {
                button.style.display = 'none';
            }
        });
    }

    async function votePoll(pollId, optionId) {
        const resp = await fetch(`tip-api?action=vote_poll&poll_id=${encodeURIComponent(pollId)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ option_id: optionId })
        });
        if (!resp.ok) {
            const data = await resp.json().catch(() => ({}));
            throw new Error(data.error || 'Vote failed');
        }
        return await resp.json();
    }

    async function fetchTipReactions(tipId) {
        if (!tipId) return { likes: 0, dislikes: 0, current_user_reaction: -1 };
        const resp = await fetch(`tip-api?action=tip_reactions&id=${encodeURIComponent(tipId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) return { likes: 0, dislikes: 0, current_user_reaction: 'none' };
        const data = await resp.json();
        if (data.error) return { likes: 0, dislikes: 0, current_user_reaction: 'none' };
        return data;
    }

    async function setTipReaction(tipId, reactionType) {
        
        const resp = await fetch(`tip-api?action=set_reaction&id=${encodeURIComponent(tipId)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reaction: reactionType })
        });
        if (!resp.ok) {
            const data = await resp.json().catch(() => ({}));
            
            throw new Error(data.error || 'Reaction failed');
        }
        const data = await resp.json();
        
        return data;
    }

    async function removeTipReaction(tipId) {
        const resp = await fetch(`tip-api?action=remove_reaction&id=${encodeURIComponent(tipId)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({})
        });
        if (!resp.ok) {
            const data = await resp.json().catch(() => ({}));
            throw new Error(data.error || 'Remove reaction failed');
        }
        return await resp.json();
    }

    function renderTip(tip) {
        if (!tip) {
            tipContent.innerHTML = '<p class="error-message">Tip not found.</p>';
            return;
        }

        currentTip = tip;
        let html = `<div class="tip-header">
            <h2>${escapeHtml(tip.title)}</h2>
            <div class="tip-reaction-icons">
                <span id="like-btn" class="reaction-icon" data-reaction="1"><i class="fa fa-thumbs-up"></i> <span id="like-count">0</span></span>
                <span id="dislike-btn" class="reaction-icon" data-reaction="0"><i class="fa fa-thumbs-down"></i> <span id="dislike-count">0</span></span>
            </div>
        </div>`;
        if (tip.created_by_name || tip.created_by) {
            const createdBy = tip.created_by_name || tip.created_by;
            html += `<p class="tip-meta">By ${escapeHtml(createdBy)}</p>`;
        }
        if (tip.created_at) {
            html += `<p class="tip-meta">Created: ${escapeHtml(formatDate(tip.created_at))}</p>`;
        }
        if (tip.updated_at && tip.updated_at !== tip.created_at) {
            html += `<p class="tip-meta">Updated: ${escapeHtml(formatDate(tip.updated_at))}</p>`;
        }

        html += `<div class="tip-markdown">${marked.parse(tip.description || '')}</div>`;

        if (tip.poll_id || (tip.poll && tip.poll.id)) {
            const pollQuestion = (tip.poll && tip.poll.question) ? tip.poll.question : 'Voice your opinion';
            html += `<div class="tip-poll"><h3>Poll</h3><p>${escapeHtml(pollQuestion)}</p><div id="poll-options"></div></div>`;
        }

        tipContent.innerHTML = html;

        const likeBtn = document.getElementById('like-btn');
        const dislikeBtn = document.getElementById('dislike-btn');
        const likeCount = document.getElementById('like-count');
        const dislikeCount = document.getElementById('dislike-count');

        async function refreshReactions() {
            const reactions = await fetchTipReactions(tip.id);
            
            if (likeCount) likeCount.textContent = reactions.likes || 0;
            if (dislikeCount) dislikeCount.textContent = reactions.dislikes || 0;

            const currentReaction = Number(reactions.current_user_reaction);
            if (likeBtn) likeBtn.classList.toggle('active', currentReaction === 1);
            if (dislikeBtn) dislikeBtn.classList.toggle('active', currentReaction === 0);
        }

        async function toggleReaction(isLike) {
            if (!likeBtn || !dislikeBtn) return;
            const activeBtn = isLike ? likeBtn : dislikeBtn;
            const otherBtn = isLike ? dislikeBtn : likeBtn;
            const currentlyActive = activeBtn.classList.contains('active');

            activeBtn.classList.add('loading');
            otherBtn.classList.add('loading');
            activeBtn.style.pointerEvents = 'none';
            otherBtn.style.pointerEvents = 'none';

            try {
                await (currentlyActive ? removeTipReaction(tip.id) : setTipReaction(tip.id, isLike ? 1 : 0));
                await refreshReactions();
            } catch (err) {
                showToast(err.message || 'Reaction failed', 'error');
            } finally {
                activeBtn.classList.remove('loading');
                otherBtn.classList.remove('loading');
                activeBtn.style.pointerEvents = '';
                otherBtn.style.pointerEvents = '';
            }
        }

        if (likeBtn) {
            likeBtn.addEventListener('click', async () => toggleReaction(true));
        }

        if (dislikeBtn) {
            dislikeBtn.addEventListener('click', async () => toggleReaction(false));
        }

        refreshReactions();

        if (tip.poll_id) {
            return fetchPollOptions(tip.poll_id).then(async options => {
                const optionsContainer = document.getElementById('poll-options');
                if (!optionsContainer) return;
                if (!options.length) {
                    optionsContainer.innerHTML = '<p>No poll options available.</p>';
                    return;
                }

                optionsContainer.innerHTML = options.map(opt => {
                    return `<div class="poll-option" data-option-id="${escapeHtml(opt.id)}">
                        <div class="poll-option-content">
                            <div class="poll-option-label"><span>${escapeHtml(opt.option_text || opt.text || 'Option')}</span><span class="poll-option-stats">0% (0)</span></div>
                            <div class="poll-option-bar"><div class="poll-option-bar-fill"></div></div>
                        </div>
                        <div class="poll-option-right"><button class="btn-secondary" data-option-id="${escapeHtml(opt.id)}">Vote</button></div>
                    </div>`;
                }).join('');

                optionsContainer.querySelectorAll('button').forEach(button => {
                    button.addEventListener('click', async () => {
                        const optionId = button.dataset.optionId;
                        button.disabled = true;
                        try {
                            await votePoll(tip.poll_id, optionId);
                            const votes = await fetchPollVotes(tip.poll_id);
                            updatePollStats(options, votes);
                            disablePollButtonsAndHighlight(optionId);
                            showToast('Vote registered', 'success');
                        } catch (err) {
                            showToast(err.message || 'Unable to vote', 'error');
                        } finally {
                            button.disabled = false;
                        }
                    });
                });

                const currentUserId = getCurrentUserId();
                const votes = await fetchPollVotes(tip.poll_id);
                updatePollStats(options, votes);

                if (currentUserId) {
                    const myVote = Array.isArray(votes) ? votes.find(v => v.user_id === currentUserId || v.user_id === currentUserId.toLowerCase()) : null;
                    if (myVote) {
                        disablePollButtonsAndHighlight(myVote.option_id);
                    }
                }
            });
        }

        return Promise.resolve();
    }

    function renderComments(comments) {
        if (!Array.isArray(comments)) {
            commentsList.innerHTML = '<p>No comments yet.</p>';
            return;
        }

        if (comments.length === 0) {
            commentsList.innerHTML = '<p>No comments yet. Be first!</p>';
            return;
        }

        commentsList.innerHTML = comments.map(comment => {
            return `<div class="comment-item"><strong>${escapeHtml(comment.username || comment.user_id || 'Unknown')}</strong> <span style="font-size:12px;color:#6b7280;">${escapeHtml(formatDate(comment.created_at))}</span><p>${escapeHtml(comment.content)}</p></div>`;
        }).join('');
    }

    async function loadComments(page) {
        commentsList.innerHTML = '';
        showCommentsSkeleton();

        const tipId = getUuid();
        if (!tipId) {
            commentsList.innerHTML = '<p>Tip ID not found.</p>';
            hideCommentsSkeleton();
            return;
        }

        const resp = await fetch(`tip-api?action=comments&id=${encodeURIComponent(tipId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) {
            commentsList.innerHTML = '<p>Unable to load comments.</p>';
            hideCommentsSkeleton();
            return;
        }

        const data = await resp.json();
        if (data.error) {
            commentsList.innerHTML = `<p>${escapeHtml(data.error)}</p>`;
            hideCommentsSkeleton();
            return;
        }

        const allComments = Array.isArray(data) ? data : [];
        totalCommentPages = Math.max(1, Math.ceil(allComments.length / PAGE_SIZE));
        commentPage = Math.min(page, totalCommentPages);

        const pageItems = allComments.slice((commentPage - 1) * PAGE_SIZE, commentPage * PAGE_SIZE);

        renderComments(pageItems);

        if (allComments.length <= 10) {
            commentsPagination.style.display = 'none';
        } else {
            commentsPagination.style.display = 'flex';
            commentsPagination.innerHTML = '';

            for (let i = 1; i <= totalCommentPages; i += 1) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = String(i);
                if (i === commentPage) btn.classList.add('active');
                btn.addEventListener('click', () => loadComments(i));
                commentsPagination.appendChild(btn);
            }
        }

        hideCommentsSkeleton();
    }

    function setCommentButtonLoading(isLoading) {
        if (!commentSubmit) return;

        if (isLoading) {
            commentSubmit.disabled = true;
            commentSubmit.dataset.originalText = commentSubmit.innerHTML;
            commentSubmit.innerHTML = '<span class="spinner"></span> Posting...';
        } else {
            commentSubmit.disabled = false;
            commentSubmit.innerHTML = commentSubmit.dataset.originalText || 'Submit';
        }
    }

    async function postComment() {
        const tipId = getUuid();
        const content = commentInput.value.trim();
        if (!tipId || !content) {
            showToast('Comment cannot be empty', 'error');
            return;
        }

        setCommentButtonLoading(true);

        try {
            const resp = await fetch(`tip-api?action=post_comment&id=${encodeURIComponent(tipId)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content })
            });
            if (!resp.ok) {
                const data = await resp.json().catch(() => ({}));
                throw new Error(data.error || 'Failed to post comment');
            }
            commentInput.value = '';
            updateCommentCounter();
            loadComments(commentPage);
            showToast('Comment added', 'success');
        } catch (err) {
            showToast(err.message || 'Unable to post comment', 'error');
        } finally {
            setCommentButtonLoading(false);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        
        let normalized = String(dateString).trim();
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(normalized)) {
            normalized = normalized.replace(' ', 'T');
        }
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(normalized)) {
            normalized += 'Z';
        }

        const d = new Date(normalized);
        if (Number.isNaN(d.getTime())) return '';

        return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    }

    function showToast(message, type='info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        alert(message);
    }

    document.addEventListener('DOMContentLoaded', async function() {
        const tipId = getUuid();
        if (!tipId) {
            tipContent.innerHTML = '<p class="error-message">Tip ID missing in URL</p>';
            return;
        }

        showTipSkeleton();
        showCommentsSkeleton();

        try {
            const tip = await fetchTipById(tipId);
            await renderTip(tip);
        } catch (err) {
            tipContent.innerHTML = `<p class="error-message">${escapeHtml(err.message)}</p>`;
        } finally {
            hideTipSkeleton();
        }

        loadComments(1);

        if (commentInput) {
            commentInput.addEventListener('input', updateCommentCounter);
            updateCommentCounter();
        }
    });

    commentSubmit.addEventListener('click', postComment);
})();