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
        console.log('==== fetchTipById START, uuid:', uuid);
        if (!uuid) {
            console.log('UUID is falsy, returning null');
            return null;
        }

        try {
            console.log('Fetching tip from API...');
            const response = await fetch(`tip-api?action=get&id=${encodeURIComponent(uuid)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            console.log('Response received, status:', response.status, 'ok:', response.ok);

            if (!response.ok) {
                console.error('Response not ok, throwing error');
                throw new Error('Failed to load tip details: HTTP ' + response.status);
            }

            const text = await response.text();
            console.log('Response text:', text);
            
            if (!text || text === 'null') {
                console.error('Response text is empty or "null"');
                throw new Error('Empty response from server');
            }

            let tip;
            try {
                console.log('Parsing JSON...');
                tip = JSON.parse(text);
                console.log('Parsed tip:', tip);
            } catch (e) {
                console.error('JSON parse failed:', e.message);
                throw new Error('Invalid JSON response: ' + e.message);
            }

            if (!tip || typeof tip !== 'object') {
                console.error('Tip is not an object:', tip);
                throw new Error('Invalid tip data structure');
            }

            if (tip.error) {
                console.error('Tip has error property:', tip.error);
                throw new Error(tip.error);
            }

            console.log('==== fetchTipById SUCCESS, returning:', tip);
            return tip;
        } catch (err) {
            console.error('==== fetchTipById CATCH ERROR:', err);
            throw err;
        }
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
        console.log('==== fetchPollOptions START, pollId:', pollId);
        if (!pollId) {
            console.log('pollId is falsy, returning []');
            return [];
        }

        try {
            const resp = await fetch(`tip-api?action=poll_options&poll_id=${encodeURIComponent(pollId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            console.log('Poll options response status:', resp.status);
            if (!resp.ok) {
                console.warn('Poll options response not ok, returning []');
                return [];
            }

            const text = await resp.text();
            console.log('Poll options response text:', text);
            if (!text) {
                console.warn('Poll options response empty, returning []');
                return [];
            }

            const data = JSON.parse(text);
            console.log('Poll options data:', data);
            if (data && data.error) {
                console.warn('Poll options API error:', data.error);
                return [];
            }
            console.log('Returning poll options:', data);
            return data || [];
        } catch (err) {
            console.error('fetchPollOptions error:', err);
            return [];
        }
    }

    async function fetchPollVotes(pollId) {
        console.log('==== fetchPollVotes START, pollId:', pollId);
        if (!pollId) {
            console.log('pollId is falsy, returning []');
            return [];
        }

        try {
            const resp = await fetch(`tip-api?action=poll_votes&poll_id=${encodeURIComponent(pollId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            console.log('Poll votes response status:', resp.status);
            if (!resp.ok) {
                console.warn('Poll votes response not ok, returning []');
                return [];
            }

            const text = await resp.text();
            console.log('Poll votes response text:', text);
            if (!text) {
                console.warn('Poll votes response empty, returning []');
                return [];
            }

            const data = JSON.parse(text);
            console.log('Poll votes data:', data);
            if (data && data.error) {
                console.warn('Poll votes API error:', data.error);
                return [];
            }
            console.log('Returning poll votes:', data);
            return data || [];
        } catch (err) {
            console.error('fetchPollVotes error:', err);
            return [];
        }
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
        console.log('==== votePoll START, pollId:', pollId, 'optionId:', optionId);
        try {
            const resp = await fetch(`tip-api?action=vote_poll&poll_id=${encodeURIComponent(pollId)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ option_id: optionId })
            });
            console.log('Vote poll response status:', resp.status);
            if (!resp.ok) {
                const data = await resp.json().catch(() => ({}));
                console.error('Vote poll error response:', data);
                throw new Error((data && data.error) ? data.error : 'Vote failed');
            }
            const result = await resp.json();
            console.log('Vote poll result:', result);
            return result;
        } catch (err) {
            console.error('votePoll error:', err);
            throw err;
        }
    }

    async function fetchTipReactions(tipId) {
        const defaultReactions = { likes: 0, dislikes: 0, current_user_reaction: -1 };
        
        if (!tipId) return defaultReactions;
        
        try {
            const resp = await fetch(`tip-api?action=tip_reactions&id=${encodeURIComponent(tipId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!resp.ok) {
                console.warn('Reactions response not ok:', resp.status);
                return defaultReactions;
            }
            
            const text = await resp.text();
            console.log('Reactions response text:', text);
            
            if (!text || text === 'null') {
                console.warn('Reactions response is empty or null');
                return defaultReactions;
            }
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse reactions JSON:', e, 'text was:', text);
                return defaultReactions;
            }
            
            // Safely check for error
            if (data && data.error) {
                console.warn('Reactions API returned error:', data.error);
                return defaultReactions;
            }
            
            // Return the data if it looks valid
            if (data && typeof data === 'object') {
                return {
                    likes: data.likes || 0,
                    dislikes: data.dislikes || 0,
                    current_user_reaction: data.current_user_reaction !== undefined ? data.current_user_reaction : -1
                };
            }
            
            return defaultReactions;
        } catch (err) {
            console.error('fetchTipReactions error:', err);
            return defaultReactions;
        }
    }

    async function setTipReaction(tipId, reactionType) {
        console.log('==== setTipReaction START, tipId:', tipId, 'type:', reactionType);
        
        try {
            const resp = await fetch(`tip-api?action=set_reaction&id=${encodeURIComponent(tipId)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ reaction: reactionType })
            });
            console.log('Set reaction response status:', resp.status);
            if (!resp.ok) {
                const data = await resp.json().catch(() => ({}));
                console.error('Set reaction error:', data);
                throw new Error((data && data.error) ? data.error : 'Reaction failed');
            }
            const data = await resp.json();
            console.log('Set reaction result:', data);
            return data;
        } catch (err) {
            console.error('setTipReaction error:', err);
            throw err;
        }
    }

    async function removeTipReaction(tipId) {
        console.log('==== removeTipReaction START, tipId:', tipId);
        
        try {
            const resp = await fetch(`tip-api?action=remove_reaction&id=${encodeURIComponent(tipId)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({})
            });
            console.log('Remove reaction response status:', resp.status);
            if (!resp.ok) {
                const data = await resp.json().catch(() => ({}));
                console.error('Remove reaction error:', data);
                throw new Error((data && data.error) ? data.error : 'Remove reaction failed');
            }
            const result = await resp.json();
            console.log('Remove reaction result:', result);
            return result;
        } catch (err) {
            console.error('removeTipReaction error:', err);
            throw err;
        }
    }

    function renderTip(tip) {
        console.log('==== renderTip START, tip:', tip);
        
        if (!tip || typeof tip !== 'object') {
            console.error('renderTip: tip is not an object');
            tipContent.innerHTML = '<p class="error-message">Tip not found or invalid data.</p>';
            return;
        }

        currentTip = tip;
        console.log('Tip assigned to currentTip, building HTML...');
        console.log('tip.title:', tip.title);
        console.log('tip.created_by:', tip.created_by);
        console.log('tip.id:', tip.id);
        
        let html = `<div class="tip-header">
            <h2>${escapeHtml(tip.title || 'Untitled')}</h2>
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

        console.log('HTML built, setting innerHTML...');
        tipContent.innerHTML = html;
        console.log('innerHTML set successfully');

        const likeBtn = document.getElementById('like-btn');
        const dislikeBtn = document.getElementById('dislike-btn');
        const likeCount = document.getElementById('like-count');
        const dislikeCount = document.getElementById('dislike-count');
        console.log('DOM elements found - likeBtn:', !!likeBtn, 'dislikeBtn:', !!dislikeBtn);

        async function refreshReactions() {
            console.log('==== refreshReactions START, tip.id:', tip.id);
            if (!tip || !tip.id) {
                console.warn('Cannot refresh reactions - tip or tip.id missing');
                return;
            }
            
            const reactions = await fetchTipReactions(tip.id);
            console.log('Reactions received:', reactions);
            
            if (likeCount) likeCount.textContent = reactions.likes || 0;
            if (dislikeCount) dislikeCount.textContent = reactions.dislikes || 0;

            const currentReaction = Number(reactions.current_user_reaction);
            console.log('Setting active states, currentReaction:', currentReaction);
            if (likeBtn) likeBtn.classList.toggle('active', currentReaction === 1);
            if (dislikeBtn) dislikeBtn.classList.toggle('active', currentReaction === 0);
            console.log('==== refreshReactions END');
        }

        async function toggleReaction(isLike) {
            if (!likeBtn || !dislikeBtn || !tip || !tip.id) return;
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

        refreshReactions().catch(err => console.error('==== refreshReactions PROMISE ERROR:', err));

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
        console.log('==== DOMContentLoaded START');
        const tipId = getUuid();
        console.log('Tip UUID from URL:', tipId);
        
        if (!tipId) {
            console.error('No tip ID in URL');
            tipContent.innerHTML = '<p class="error-message">Tip ID missing in URL</p>';
            return;
        }

        showTipSkeleton();
        showCommentsSkeleton();

        try {
            console.log('Starting to fetch tip...');
            const tip = await fetchTipById(tipId);
            console.log('Tip fetched successfully:', tip);
            
            console.log('Starting to render tip...');
            await renderTip(tip);
            console.log('Tip rendered successfully');
        } catch (err) {
            console.error('==== DOMContentLoaded ERROR:', err);
            tipContent.innerHTML = `<p class="error-message">${escapeHtml(err.message)}</p>`;
        } finally {
            console.log('Hiding skeleton...');
            hideTipSkeleton();
        }

        loadComments(1);

        if (commentInput) {
            commentInput.addEventListener('input', updateCommentCounter);
            updateCommentCounter();
        }
        console.log('==== DOMContentLoaded END');
    });

    commentSubmit.addEventListener('click', postComment);
})();