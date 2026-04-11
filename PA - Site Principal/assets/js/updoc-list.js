(function () {
    'use strict';

    var defaults = {
        page: 1,
        limit: 12,
        sort: 'newest',
        search: '',
        author_id: '',
        author_name: '',
        ai_generated: ''
    };

    function parseQuery() {
        var params = new URLSearchParams(window.location.search);
        return {
            page: parseInt(params.get('page')) || defaults.page,
            limit: parseInt(params.get('limit')) || defaults.limit,
            sort: params.get('sort') || defaults.sort,
            search: params.get('search') || defaults.search,
            author_id: params.get('author_id') || defaults.author_id,
            author_name: params.get('author_name') || defaults.author_name,
            ai_generated: params.get('ai_generated') || defaults.ai_generated
        };
    }

    function toQuery(params) {
        var qs = new URLSearchParams();
        if (params.page && params.page > 1) qs.set('page', params.page);
        if (params.limit && params.limit !== defaults.limit) qs.set('limit', params.limit);
        if (params.sort && params.sort !== defaults.sort) qs.set('sort', params.sort);
        if (params.search && params.search.trim() !== '') qs.set('search', params.search.trim());
        if (params.author_id && params.author_id.trim() !== '') {
            qs.set('author_id', params.author_id.trim());
            if (params.author_name && params.author_name.trim() !== '') {
                qs.set('author_name', params.author_name.trim());
            }
        }
        if (params.ai_generated !== '' && params.ai_generated !== undefined) qs.set('ai_generated', params.ai_generated);
        return qs.toString();
    }

    function makeUrl(params) {
        var q = toQuery(params);
        return 'updoc-api' + (q ? '?' + q : '');
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtDate(str) {
        if (!str) return '';
        var ts = Date.parse(str);
        if (isNaN(ts)) return str;
        return new Date(ts).toLocaleDateString('fr-FR');
    }

    function show(el, display) { if (!el) return; el.style.display = display || 'block'; }
    function hide(el) { if (!el) return; el.style.display = 'none'; }

    var state = parseQuery();
    var loading = false;

    var grid              = document.getElementById('updoc-grid');
    var selectionSection  = document.getElementById('updoc-selection-section');
    var selectionGrid     = document.getElementById('updoc-selection-grid');
    var emptyMsg          = document.getElementById('updoc-empty-msg');
    var pagination        = document.getElementById('updoc-pagination');
    var prevBtn           = document.getElementById('updoc-prev-btn');
    var nextBtn           = document.getElementById('updoc-next-btn');
    var pageInfo          = document.getElementById('updoc-page-info');

    var searchInput       = document.getElementById('updoc-search');
    var sortSelect        = document.getElementById('updoc-sort');
    var authorInput       = document.getElementById('updoc-author');
    var authorSuggestions = document.getElementById('updoc-author-suggestions');
    var aiSwitcher        = document.getElementById('updoc-ai-switcher');
    var limitSelect       = document.getElementById('updoc-limit');
    var resetBtn          = document.getElementById('updoc-reset-filters');

    if (!grid || !searchInput || !sortSelect || !limitSelect || !authorInput || !aiSwitcher) {
        return;
    }

    function buildSkeletons(count) {
        grid.innerHTML = '';
        var n = Math.min(count, 12);
        for (var i = 0; i < n; i++) {
            var item = document.createElement('div');
            item.className = 'skeleton-service-item';
            item.innerHTML =
                '<div class="skeleton skeleton-image"></div>' +
                '<div class="skeleton-service-header">' +
                  '<div class="skeleton skeleton-title"></div>' +
                  '<div class="skeleton skeleton-badge"></div>' +
                '</div>' +
                '<div class="skeleton skeleton-description"></div>' +
                '<div class="skeleton skeleton-description"></div>' +
                '<div class="skeleton skeleton-price"></div>';
            grid.appendChild(item);
        }
    }

    function buildProjectCard(item) {
        var el = document.createElement('div');
        el.className = 'service-item';

        var statusNum  = parseInt(item.status ?? 0, 10);
        var statusLabel = statusNum === 1 ? 'Published' : 'Draft';
        var statusCls   = statusNum === 1 ? 'type-formation' : 'type-event';
        var desc        = (item.description || '').replace(/[#*_`]/g, '').trim();
        var date        = fmtDate(item.created_at);
        var projId      = escHtml(item.id || '');
        var authorName  = escHtml(item.author_name || '');
        var views       = item.views != null ? parseInt(item.views, 10) : 0;
        var aiGenerated = parseInt(item.ai_generated ?? 0, 10) === 1;

        var badgeHtml = '<span class="service-type-badge ' + statusCls + '">' + escHtml(statusLabel) + '</span>';
        if (aiGenerated) {
            badgeHtml += '<span class="service-type-badge type-event"><i class="fa-solid fa-wand-magic-sparkles"></i> AI</span>';
        }

        el.innerHTML =
            '<div class="service-header">' +
              '<h3>' + escHtml(item.title || 'Untitled') + '</h3>' +
              '<div style="display:flex;gap:10px;">' + badgeHtml + '</div>' +
            '</div>' +
            '<div class="service-description">' + escHtml(desc) + '</div>' +
            '<div class="service-price">' +
              (views ? '<i class="fa-solid fa-eye"></i> ' + escHtml(String(views)) : '') +
              (authorName ? ' <i class="fa-solid fa-user"></i> ' + authorName : '') +
            '</div>' +
            '<div class="service-date" style="justify-content:center;"><i class="fa-regular fa-calendar"></i> ' + escHtml(date) + '</div>' +
            '<div class="service-actions">' +
              '<a href="updoc-view?id=' + projId + '" class="btn-secondary offer-btns">' +
                '<i class="fa-solid fa-eye"></i> View' +
              '</a>' +
            '</div>';

        return el;
    }

    function setPagination(total) {
        var from = (state.page - 1) * state.limit + 1;
        var to = Math.min(state.page * state.limit, total);
        pageInfo.textContent = total > 0 ? (from + '–' + to + ' of ' + total) : '';
        prevBtn.disabled = state.page <= 1;
        nextBtn.disabled = state.page * state.limit >= total;
        pagination.style.display = total > state.limit ? 'flex' : 'none';
    }

    function render(items, total) {
        grid.innerHTML = '';
        if (!items || items.length === 0) {
            hide(grid);
            show(emptyMsg);
            pagination.style.display = 'none';
            return;
        }

        hide(emptyMsg);
        show(grid, 'grid');

        items.forEach(function (item) {
            grid.appendChild(buildProjectCard(item));
        });

        setPagination(total);
    }

    function renderSelection(items) {
        if (!selectionSection || !selectionGrid) return;
        if (!items || items.length === 0) {
            hide(selectionSection);
            return;
        }

        selectionGrid.innerHTML = '';
        items.forEach(function (item) {
            selectionGrid.appendChild(buildProjectCard(item));
        });
        show(selectionSection, 'grid');
    }

    function fetchLatestSelection() {
        if (!selectionSection || !selectionGrid) return;

        selectionGrid.innerHTML = '';
        selectionSection.style.display = 'grid';

        var url = 'updoc-api?page=1&limit=3&sort=newest';
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var items = Array.isArray(data.items) ? data.items : [];
                renderSelection(items);
            })
            .catch(function () {
                hide(selectionSection);
            });
    }

    function updateUrl() {
        var q = toQuery(state);
        var url = window.location.pathname + (q ? '?' + q : '');
        window.history.replaceState({}, '', url);
    }

    function loadAuthors() {
        if (!authorInput) return;

        if (state.author_id) {
            fetch('users-api?id=' + encodeURIComponent(state.author_id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.id) {
                        var name = ((data.first_name || '') + ' ' + (data.last_name || '')).trim() || data.username || data.email || '';
                        authorInput.value = name;
                    }
                })
                .catch(function () {
                    // Coucou :)
                    // Total d'heure perdue sur cette fonction : 4h
                    // Incrémente si jamais ça bug aussi et que tu galère
                    // Bisous,
                });
        }
    }

    function clearAuthorSelection() {
        state.author_id = '';
        state.author_name = '';
    }

    function hideAuthorSuggestions() {
        if (!authorSuggestions) return;
        authorSuggestions.style.display = 'none';
        authorSuggestions.innerHTML = '';
    }

    function showAuthorSuggestions(items) {
        if (!authorSuggestions) return;
        authorSuggestions.innerHTML = '';
        if (!items || !items.length) {
            authorSuggestions.style.display = 'none';
            return;
        }

        items.slice(0, 10).forEach(function (user) {
            var el = document.createElement('div');
            el.className = 'lookup-suggestion';
            var name = ((user.first_name || '') + ' ' + (user.last_name || '')).trim() || user.username || user.email || 'Unknown';
            el.textContent = name;
            el.dataset.userId = user.id || '';
            el.addEventListener('click', function () {
                state.author_id = el.dataset.userId || '';
                state.author_name = name;
                authorInput.value = name;
                hideAuthorSuggestions();
                applyFilters();
            });
            authorSuggestions.appendChild(el);
        });

        authorSuggestions.style.display = 'block';
    }

    function fetchAuthorSuggestions(query) {
        if (!query || query.trim().length < 2) {
            hideAuthorSuggestions();
            return;
        }

        fetch('users-list-api?search=' + encodeURIComponent(query) + '&limit=10', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !Array.isArray(data.items)) {
                    hideAuthorSuggestions();
                    return;
                }
                showAuthorSuggestions(data.items);
            })
            .catch(function () {
                hideAuthorSuggestions();
            });
    }

    function fetchProjects(page) {
        if (loading) return;
        loading = true;
        if (typeof page === 'number') state.page = page;

        grid.innerHTML = '';
        hide(emptyMsg);
        buildSkeletons(state.limit || 8);

        var url = makeUrl(state);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loading = false;
                var items = Array.isArray(data.items) ? data.items : [];
                render(items, data.total || 0);
                updateUrl();
            })
            .catch(function () {
                loading = false;
                grid.innerHTML = '';
                hide(grid);
                show(emptyMsg);
                emptyMsg.textContent = 'Unable to load projects. Please try again.';
            });
    }

    function resetFilters() {
        state = Object.assign({}, defaults);
        state.page = 1;

        searchInput.value = '';
        sortSelect.value = defaults.sort;
        limitSelect.value = defaults.limit;
        authorInput.value = '';
        clearAuthorSelection();

        if (aiSwitcher) {
            aiSwitcher.querySelectorAll('.svc-loc-opt').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.value === '');
            });
        }

        fetchProjects(state.page);
    }

    function applyFilters() {
        state.search = searchInput.value.trim();
        state.sort = sortSelect.value;
        state.limit = parseInt(limitSelect.value, 10) || defaults.limit;

        if (aiSwitcher) {
            var active = aiSwitcher.querySelector('.svc-loc-opt.is-active');
            state.ai_generated = active ? (active.dataset.value || '') : '';
        }

        state.page = 1;
        fetchProjects(state.page);
    }

    function initControls() {
        searchInput.value = state.search;
        sortSelect.value = state.sort;
        limitSelect.value = state.limit;
        if (authorInput) {
            authorInput.value = state.author_name || '';
        }
        if (aiSwitcher) {
            var active = aiSwitcher.querySelector('.svc-loc-opt');
            aiSwitcher.querySelectorAll('.svc-loc-opt').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.value === state.ai_generated);
                if (btn.classList.contains('is-active')) {
                    active = btn;
                }
            });

            if (!active) {
                var first = aiSwitcher.querySelector('.svc-loc-opt');
                if (first) {
                    first.classList.add('is-active');
                }
            }
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(searchInput._debounce);
            searchInput._debounce = setTimeout(applyFilters, 350);
        });
        sortSelect.addEventListener('change', applyFilters);
        limitSelect.addEventListener('change', applyFilters);

        if (authorInput) {
            authorInput.addEventListener('input', function () {
                state.author_id = '';
                state.author_name = '';
                clearTimeout(authorInput._debounce);
                authorInput._debounce = setTimeout(function () {
                    fetchAuthorSuggestions(authorInput.value.trim());
                }, 250);
            });
            authorInput.addEventListener('blur', function () {
                setTimeout(hideAuthorSuggestions, 150);
            });
        }

        if (aiSwitcher) {
            aiSwitcher.addEventListener('click', function (event) {
                var btn = event.target.closest('.svc-loc-opt');
                if (!btn) return;
                aiSwitcher.querySelectorAll('.svc-loc-opt').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                applyFilters();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                resetFilters();
            });
        }

        if (prevBtn) prevBtn.addEventListener('click', function () {
            if (state.page > 1) {
                state.page -= 1;
                fetchProjects(state.page);
            }
        });
        if (nextBtn) nextBtn.addEventListener('click', function () {
            state.page += 1;
            fetchProjects(state.page);
        });
    }

    initControls();
    buildSkeletons(state.limit);
    loadAuthors();
    fetchLatestSelection();
    fetchProjects(state.page);
})();
