(function() {
    'use strict';

    const pageSize = 6;
    let currentPage = 1;
    let totalPages = 1;

    document.addEventListener('DOMContentLoaded', function() {
        currentPage = getPageFromUrl();
        requestPage(currentPage, true);
    });

    function requestPage(page, replaceHistory) {
        const container = document.getElementById('tips-container');
        const pagination = document.getElementById('tips-pagination');

        if (!container) {
            console.error('Tips container not found');
            return;
        }

        renderSkeletons(container, pageSize);

        fetch(`tips-api?page=${page}&limit=${pageSize}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                const data = JSON.parse(text);
                if (data.error) {
                    container.innerHTML = `<p class="error-message">${escapeHtml(data.error)}</p>`;
                    if (pagination) pagination.innerHTML = '';
                    return;
                }

                const tips = Array.isArray(data.items) ? data.items : [];
                const total = Number.isFinite(data.total) ? data.total : tips.length;
                totalPages = total > 0 ? Math.ceil(total / pageSize) : 1;

                if (!tips || tips.length === 0) {
                    const msg = data.error || 'No tips available at the moment.';
                    container.innerHTML = `<p class="empty-tips">${escapeHtml(msg)}</p>`;
                    if (pagination) pagination.innerHTML = '';
                    updateUrlPage(1, true);
                    return;
                }

                if (page > totalPages) {
                    currentPage = totalPages;
                    updateUrlPage(currentPage, true);
                    requestPage(currentPage, true);
                    return;
                }

                currentPage = page;
                updateUrlPage(currentPage, replaceHistory);

                container.innerHTML = '';
                if (pagination) pagination.innerHTML = '';

                renderTips(tips, container);
                renderPagination(pagination);
            })
            .catch(error => {
                console.error('Error loading tips:', error);
                container.innerHTML = '<p class="error-message">An error occurred while loading tips. Please try again later.</p>';
                if (pagination) pagination.innerHTML = '';
            });
    }

    function renderTips(tips, container) {
        tips.forEach(tip => {
            const tipItem = createTipElement(tip);
            container.appendChild(tipItem);
        });
    }

    function renderPagination(pagination) {
        if (!pagination) {
            return;
        }

        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        const prevButton = createPageButton('Prev', currentPage === 1, function() {
            if (currentPage > 1) {
                requestPage(currentPage - 1);
            }
        });
        pagination.appendChild(prevButton);

        for (let i = 1; i <= totalPages; i += 1) {
            const pageButton = createPageButton(String(i), false, function() {
                requestPage(i);
            });
            if (i === currentPage) {
                pageButton.classList.add('active');
                pageButton.setAttribute('aria-current', 'page');
            }
            pagination.appendChild(pageButton);
        }

        const nextButton = createPageButton('Next', currentPage === totalPages, function() {
            if (currentPage < totalPages) {
                requestPage(currentPage + 1);
            }
        });
        pagination.appendChild(nextButton);
    }

    function createPageButton(label, disabled, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-btn';
        button.textContent = label;
        if (disabled) {
            button.disabled = true;
            button.classList.add('disabled');
        } else {
            button.addEventListener('click', onClick);
        }
        return button;
    }

    function createTipElement(tip) {
        const div = document.createElement('div');
        div.className = 'tip-item';

        // header with title and button
        const header = document.createElement('div');
        header.className = 'tip-header';

        const title = document.createElement('h3');
        title.textContent = tip.title || 'Untitled tip';
        header.appendChild(title);

        const button = document.createElement('button');
        button.className = 'btn-primary tip-button';
        button.textContent = 'Learn more';
        button.onclick = function() {
            window.location.href = `tip?uuid=${tip.id}`;
        };
        header.appendChild(button);

        div.appendChild(header);

        if (tip.created_by_name || tip.updated_by_name || tip.created_by || tip.updated_by) {
            const meta = document.createElement('p');
            meta.className = 'tip-meta';
            let parts = [];
            if (tip.created_by_name || tip.created_by) {
                const name = tip.created_by_name ? tip.created_by_name : tip.created_by;
                parts.push(`<i class="fa-solid fa-user-plus"></i> ${escapeHtml(name)}`);
            }
            if (tip.updated_by_name || tip.updated_by) {
                const name = tip.updated_by_name ? tip.updated_by_name : tip.updated_by;
                let upd = `<i class="fa-solid fa-user-pen"></i> ${escapeHtml(name)}`;
                if (tip.updated_at) {
                    upd += ` (${formatDate(tip.updated_at)})`;
                }
                parts.push(upd);
            }
            meta.innerHTML = parts.join(' &bull; ');
            div.appendChild(meta);
        }

        return div;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        const d = new Date(dateString);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const pageParam = parseInt(params.get('page'), 10);
        if (Number.isNaN(pageParam) || pageParam < 1) {
            return 1;
        }
        return pageParam;
    }

    function updateUrlPage(page, replace) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(page));
        if (replace) {
            window.history.replaceState({}, '', url.toString());
        } else {
            window.history.pushState({}, '', url.toString());
        }
    }

    function renderSkeletons(container, count) {
        const skeletons = [];

        for (let i = 0; i < count; i += 1) {
            skeletons.push(
                '<div class="skeleton-tip-item">' +
                    '<div class="skeleton skeleton-title"></div>' +
                '</div>'
            );
        }

        container.innerHTML = skeletons.join('');
    }
})();
