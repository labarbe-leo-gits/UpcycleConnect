// global-search.js
// moved from admin-header.php - handles the floating suggestions dropdown

document.addEventListener('DOMContentLoaded', function () {
    console.log('global search helper initialised');
    const input = document.getElementById('global-search');
    const dropdown = document.getElementById('global-search-dropdown');
    let timer;

    function positionDropdown() {
        const rect = input.getBoundingClientRect();
        dropdown.style.left = rect.left + 'px';
        dropdown.style.top = rect.bottom + 'px';
        dropdown.style.width = rect.width + 'px';
    }

    function hideDropdown() {
        dropdown.style.display = 'none';
        dropdown.setAttribute('aria-hidden','true');
    }

    function showDropdown() {
        positionDropdown();
        dropdown.style.display = 'block';
        dropdown.setAttribute('aria-hidden','false');
    }

    input.addEventListener('input', e => {
        const q = input.value.trim();
        clearTimeout(timer);
        if (!q) {
            hideDropdown();
            return;
        }
        timer = setTimeout(() => {
            const apiBase = location.pathname.replace(/\/[^\/]+$/, '/') ;
            fetch(apiBase + 'global-search-api.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(items => {
                    console.log('global search returned', items);
                    if (Array.isArray(items) && items.length) {
                        const typeIcons = {
                            user: '<i class="fa-solid fa-user" style="width:1em;text-align:center;"></i>',
                            annonce: '<i class="fa-solid fa-box-open" style="width:1em;text-align:center;"></i>',
                            service: '<i class="fa-solid fa-calendar-days" style="width:1em;text-align:center;"></i>',
                            project: '<i class="fa-solid fa-diagram-project" style="width:1em;text-align:center;"></i>',
                        };
                        dropdown.innerHTML = '';
                        items.forEach(item => {
                            const a = document.createElement('a');
                            a.className = 'search-item';
                            a.href = item.href;
                            const iconHtml = typeIcons[item.type] || '';
                            a.innerHTML = iconHtml + ' ' + item.label + (item.type ? ` <span style="color:#6b7280;font-size:.85em;">(${item.type})</span>` : '');
                            dropdown.appendChild(a);
                        });
                        dropdown.querySelectorAll('a').forEach(link => {
                            link.addEventListener('click', e => {
                                e.preventDefault();
                                window.location.href = link.href;
                            });
                        });
                        showDropdown();
                    } else {
                        dropdown.innerHTML = '<div class="search-item empty">No results</div>';
                        showDropdown();
                    }
                })
                .catch(err => {
                    console.error('global search fetch failed', err);
                    hideDropdown();
                });
        }, 300);
    });

    window.addEventListener('resize', positionDropdown);
    window.addEventListener('scroll', positionDropdown, true);

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            const q = input.value.trim();
            if (q) {
                const base = location.pathname.replace(/\/[^\/]+$/, '/');
                window.location.href = base + 'search.php?q=' + encodeURIComponent(q);
            }
        }
    });

    document.addEventListener('click', function(ev) {
        if (!input.contains(ev.target) && !dropdown.contains(ev.target)) {
            hideDropdown();
        }
    });
});
