(function () {
    'use strict';

    const initialSize = 8;
    const moreSize = 4;
    let offset = 0;
    let limit = initialSize;
    let totalCount = 0;
    let searchTerm = '';
    let statusFilterValue = '';
    let easyMDE;

    let currentEditingId = '';
    let _searchTimer = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindToolbar();
        loadEditorOnce();
        requestChunk(false);

        // Bind modal controls
        const modalCloseBtn = document.getElementById('newsletter-form-modal-close');
        const modalCancelBtn = document.getElementById('newsletter-form-cancel');
        const modalSubmitBtn = document.getElementById('newsletter-form-submit');
        const modalSendBtn = document.getElementById('newsletter-form-send');
        const modalPreviewBtn = document.getElementById('newsletter-form-preview');

        const confirmCloseBtn = document.getElementById('newsletter-confirm-close');
        const confirmCancelBtn = document.getElementById('newsletter-confirm-cancel');

        const previewCloseBtn = document.getElementById('newsletter-preview-close');
        const previewBackBtn = document.getElementById('newsletter-preview-back');

        if (modalCloseBtn) modalCloseBtn.addEventListener('click', () => closeModal('newsletter-form-modal'));
        if (modalCancelBtn) modalCancelBtn.addEventListener('click', () => closeModal('newsletter-form-modal'));
        if (modalSubmitBtn) modalSubmitBtn.addEventListener('click', saveNewsletter);
        if (modalSendBtn) modalSendBtn.addEventListener('click', sendNewsletter);
        if (modalPreviewBtn) modalPreviewBtn.addEventListener('click', previewNewsletter);

        if (confirmCloseBtn) confirmCloseBtn.addEventListener('click', () => closeModal('newsletter-confirm-modal'));
        if (confirmCancelBtn) confirmCancelBtn.addEventListener('click', () => closeModal('newsletter-confirm-modal'));

        if (previewCloseBtn) previewCloseBtn.addEventListener('click', () => closeModal('newsletter-preview-modal'));
        if (previewBackBtn) previewBackBtn.addEventListener('click', () => {
            closeModal('newsletter-preview-modal');
            openModal('newsletter-form-modal');
        });

        // Click outside modal to close
        ['newsletter-form-modal', 'newsletter-confirm-modal', 'newsletter-preview-modal'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === this) {
                        closeModal(id);
                    }
                });
            }
        });
    });

    function loadEditorOnce() {
        // Initialize EasyMDE only once when page loads
        if (!easyMDE) {
            easyMDE = new EasyMDE({
                element: document.getElementById('newsletter-content'),
                spellChecker: false,
                toolbar: [
                    'bold', 'italic', 'heading', '|',
                    'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|',
                    'preview', 'side-by-side', 'fullscreen', '|',
                    'guide'
                ],
                placeholder: 'Write your newsletter content here using markdown…',
                maxHeight: '400px',
                shortcuts: {
                    'drawLink': 'Ctrl-K',
                    'drawImage': 'Ctrl-Alt-I'
                }
            });
        }
    }

    function bindToolbar() {
        const createBtn = document.getElementById('create-newsletter-btn');
        const searchInput = document.getElementById('newsletter-search');
        const statusFilter = document.getElementById('newsletter-status-filter');

        if (createBtn) {
            createBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showCreateModal();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(_searchTimer);
                searchTerm = this.value.trim();
                _searchTimer = setTimeout(() => {
                    offset = 0;
                    limit = initialSize;
                    requestChunk(false);
                }, 300);
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                statusFilterValue = this.value;
                offset = 0;
                limit = initialSize;
                requestChunk(false);
            });
        }
    }

    function requestChunk(isMore) {
        if (!isMore) {
            offset = 0;
            limit = initialSize;
        }

        const params = new URLSearchParams({
            action: 'list',
            page: Math.floor(offset / limit) + 1,
            search: searchTerm,
            status: statusFilterValue
        });

        fetch('newsletter-api.php?' + params, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(r => {
                if (!r.ok) {
                    throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                }
                return r.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        renderNewsletters(data.newsletters, isMore);
                        renderPagination(data.pagination);
                        totalCount = data.pagination.total_count;
                    } else {
                        showError(data.error || 'Failed to load newsletters');
                    }
                } catch (err) {
                    console.error('JSON parse error:', err);
                    console.error('Response text:', text);
                    showError('Invalid response from server. Check console for details.');
                }
            })
            .catch(err => {
                console.error('Error loading newsletters:', err);
                showError('Failed to load newsletters: ' + err.message);
            });
    }

    function renderNewsletters(newsletters, append) {
        const container = document.getElementById('newsletters-container');
        const emptyState = document.getElementById('newsletters-empty');

        // Remove skeletons
        container.querySelectorAll('.skeleton-service-item').forEach(el => el.remove());

        if (!newsletters.length) {
            container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-envelope-open-text"></i><p>No newsletters found</p></div>';
            return;
        }

        const html = newsletters.map(nl => `
            <div class="admin-list-item">
                <div class="admin-list-item-header">
                    <div>
                        <h3>${escapeHtml(nl.title)}</h3>
                        <p style="font-size:14px;color:#9ca3af;margin:4px 0 0 0;">
                            <span class="status-badge status-${nl.status}">${nl.status_label}</span>
                            ${nl.created_at ? ' • ' + new Date(nl.created_at).toLocaleDateString() : ''}
                        </p>
                    </div>
                </div>
                <div class="admin-list-actions">
                    <button class="btn-icon" title="Edit" onclick="window.editNewsletter('${nl.id}')">
                        <i class="fa-solid fa-pencil"></i>
                    </button>
                    <button class="btn-icon btn-icon-success" title="Preview" onclick="window.previewNewsletter('${nl.id}')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    ${nl.status !== 'Sent' ? `
                        <button class="btn-icon btn-icon-danger" title="Delete" onclick="window.deleteNewsletter('${nl.id}', '${escapeHtml(nl.title)}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');

        if (append) {
            container.innerHTML += html;
        } else {
            container.innerHTML = html;
        }
    }

    function renderPagination(pagination) {
        const container = document.getElementById('newsletters-pagination');
        if (pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="pagination">';
        
        if (pagination.page > 1) {
            html += `<button onclick="window.goToPage(${pagination.page - 1})" class="btn-pagination">← Previous</button>`;
        }

        for (let i = Math.max(1, pagination.page - 2); i <= Math.min(pagination.total_pages, pagination.page + 2); i++) {
            if (i === pagination.page) {
                html += `<span class="pagination-current">${i}</span>`;
            } else {
                html += `<button onclick="window.goToPage(${i})" class="btn-pagination">${i}</button>`;
            }
        }

        if (pagination.page < pagination.total_pages) {
            html += `<button onclick="window.goToPage(${pagination.page + 1})" class="btn-pagination">Next →</button>`;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    function showCreateModal() {
        currentEditingId = '';
        document.getElementById('newsletter-id').value = '';
        document.getElementById('newsletter-title').value = '';
        easyMDE.value('');
        document.getElementById('newsletter-status').value = '0';
        document.getElementById('newsletter-form-title').textContent = 'Create Newsletter';
        document.getElementById('newsletter-form-send').style.display = 'inline-block';
        document.getElementById('newsletter-form-preview').style.display = 'inline-block';
        clearError();
        openModal('newsletter-form-modal');
    }

    function showError(message) {
        const errorEl = document.getElementById('newsletter-form-error');
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    function clearError() {
        const errorEl = document.getElementById('newsletter-form-error');
        errorEl.style.display = 'none';
        errorEl.textContent = '';
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-open');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-open');
        }
    }

    function saveNewsletter(e) {
        e.preventDefault();

        const id = document.getElementById('newsletter-id').value;
        const title = document.getElementById('newsletter-title').value.trim();
        const content = easyMDE.value().trim();
        const status = parseInt(document.getElementById('newsletter-status').value);

        if (!title || !content) {
            showError('Title and content are required');
            return;
        }

        clearError();

        const method = id ? 'update' : 'create';
        const payload = {
            action: method,
            id: id,
            title: title,
            content: content,
            status: status
        };

        // If status is "Sent" (2), ask for confirmation before saving
        if (status === 2) {
            if (!confirm('This will mark the newsletter as sent and send it to all subscribers.\n\nAre you sure you want to proceed?')) {
                return;
            }
        }

        fetch('newsletter-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        closeModal('newsletter-form-modal');
                        if (status === 2) {
                            alert(`Newsletter sent to ${data.sent_count || 0} subscribers`);
                        } else {
                            alert(method === 'create' ? 'Newsletter created successfully' : 'Newsletter saved successfully');
                        }
                        requestChunk(false);
                    } else {
                        showError(data.error || 'Failed to save newsletter');
                    }
                } catch (err) {
                    console.error('JSON parse error:', err, 'Response:', text);
                    showError('Invalid response from server');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showError('An error occurred while saving: ' + err.message);
            });
    }

    function sendNewsletter(e) {
        e.preventDefault();

        const id = document.getElementById('newsletter-id').value;

        if (!id) {
            showError('Cannot send unsaved newsletter');
            return;
        }

        if (!confirm('Are you sure you want to send this newsletter to all subscribers? This action cannot be undone.')) {
            return;
        }

        fetch('newsletter-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'send', id: id })
        })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        closeModal('newsletter-form-modal');
                        alert(`Newsletter sent to ${data.sent_count} subscribers`);
                        requestChunk(false);
                    } else {
                        showError(data.error || 'Failed to send newsletter');
                    }
                } catch (err) {
                    console.error('JSON parse error:', err, 'Response:', text);
                    showError('Invalid response from server');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showError('An error occurred while sending: ' + err.message);
            });
    }

    function previewNewsletter(e) {
        if (typeof e === 'string') {
            // Called from view action
            const id = e;
            fetch('newsletter-api.php?action=get&id=' + encodeURIComponent(id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showPreview(data.newsletter.title, data.newsletter.content);
                        } else {
                            alert('Failed to load newsletter: ' + (data.error || 'Unknown error'));
                        }
                    } catch (err) {
                        console.error('JSON parse error:', err, 'Response:', text);
                        alert('Error: Invalid response from server');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('An error occurred');
                });
        } else {
            e.preventDefault();
            // Preview from editor
            const title = document.getElementById('newsletter-title').value.trim();
            const content = easyMDE.value().trim();

            if (!content) {
                alert('Please enter some content to preview');
                return;
            }

            showPreview(title, content);
        }
    }

    function showPreview(title, markdown) {
        closeModal('newsletter-form-modal');
        const previewEl = document.getElementById('newsletter-preview-content');
        
        // Convert markdown to HTML client-side using marked.js if available
        let html = markdown;
        if (typeof marked !== 'undefined') {
            html = marked.parse(markdown, { breaks: true });
        } else {
            // Fallback to basic conversion
            html = basicMarkdownToHtml(markdown);
        }

        previewEl.innerHTML = html;
        openModal('newsletter-preview-modal');
    }

    function basicMarkdownToHtml(markdown) {
        let html = markdown;
        // Headers
        html = html.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.*?)$/gm, '<h1>$1</h1>');
        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Code
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');
        // Links
        html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>');
        // Line breaks
        html = html.replace(/\n\n/g, '</p><p>');
        html = '<p>' + html + '</p>';
        return html;
    }

    // Window functions for onclick handlers
    window.editNewsletter = function (id) {
        fetch('newsletter-api.php?action=get&id=' + encodeURIComponent(id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const nl = data.newsletter;
                        currentEditingId = nl.id;
                        document.getElementById('newsletter-id').value = nl.id;
                        document.getElementById('newsletter-title').value = nl.title;
                        easyMDE.value(nl.content);
                        document.getElementById('newsletter-status').value = nl.status;
                        document.getElementById('newsletter-form-title').textContent = 'Edit Newsletter';
                        
                        // Show send button only for drafts and scheduled
                        const sendBtn = document.getElementById('newsletter-form-send');
                        sendBtn.style.display = nl.status < 2 ? 'inline-block' : 'none';
                        
                        clearError();
                        openModal('newsletter-form-modal');
                    } else {
                        alert('Failed to load newsletter: ' + (data.error || 'Unknown error'));
                    }
                } catch (err) {
                    console.error('JSON parse error:', err, 'Response:', text);
                    alert('Error: Invalid response from server');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('An error occurred while loading the newsletter');
            });
    };

    window.previewNewsletter = function (id) {
        previewNewsletter(id);
    };

    window.deleteNewsletter = function (id, title) {
        document.getElementById('newsletter-confirm-title').textContent = title;
        openModal('newsletter-confirm-modal');

        const confirmBtn = document.getElementById('newsletter-confirm-delete');
        confirmBtn.onclick = function () {
            fetch('newsletter-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ action: 'delete', id: id })
            })
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            closeModal('newsletter-confirm-modal');
                            requestChunk(false);
                        } else {
                            document.getElementById('newsletter-confirm-error').textContent = data.error || 'Failed to delete';
                            document.getElementById('newsletter-confirm-error').style.display = 'block';
                        }
                    } catch (err) {
                        console.error('JSON parse error:', err, 'Response:', text);
                        document.getElementById('newsletter-confirm-error').textContent = 'Invalid response from server';
                        document.getElementById('newsletter-confirm-error').style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    document.getElementById('newsletter-confirm-error').textContent = 'An error occurred: ' + err.message;
                    document.getElementById('newsletter-confirm-error').style.display = 'block';
                });
        };
    };

    window.goToPage = function (page) {
        offset = (page - 1) * limit;
        requestChunk(false);
    };

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
})();
