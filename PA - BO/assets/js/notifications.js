(function() {
    'use strict';

    function showContent() {
        var skeleton = document.getElementById('notifications-skeleton');
        var content = document.getElementById('notifications-content');
        if (skeleton) skeleton.style.display = 'none';
        if (content) content.style.display = 'block';
    }

    function setupMarkAllAsRead() {
        var root = document.getElementById('notifications-root');
        var markAllBtn = document.getElementById('mark-all-read-btn');
        var readAllUrl = root ? root.getAttribute('data-read-all-url') : '';
    
        if (!root || !readAllUrl || !markAllBtn) {
            return;
        }

        markAllBtn.addEventListener('click', function() {
            markAllBtn.disabled = true;

            var resolvedUrl = readAllUrl;
            if (readAllUrl.indexOf('http://') !== 0 && readAllUrl.indexOf('https://') !== 0) {
                resolvedUrl = new URL(readAllUrl, window.location.href).href;
            }

            fetch(resolvedUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }

            })
            .then(function(response) {
                if (!response.ok) {
                    markAllBtn.disabled = false;
                    return;
                }

                window.location.reload();
            })
            .catch(function() {
                markAllBtn.disabled = false;
            });
        });



    }

    function setupMarkAsRead() {
        var root = document.getElementById('notifications-root');
        var list = document.getElementById('notifications-list');
        var emptyState = document.getElementById('notifications-empty');
        var readUrl = root ? root.getAttribute('data-read-url') : '';
        var badge = document.getElementById('notifications-count');
        var pollUrl = 'notifications-poll';
        var lastUnreadCount = list ? list.children.length : 0;

        if (!root || !readUrl) {
            return;
        }

        document.addEventListener('click', function(event) {
            var target = event.target.closest('.notif-read-btn');
            if (!target) {
                return;
            }

            var notificationId = target.getAttribute('data-notification-id');
            if (!notificationId) {
                return;
            }

            var item = target.closest('.notification-item');
            var placeholder = null;
            if (item && item.parentNode) {
                placeholder = item;
                item.parentNode.removeChild(item);
            }

            if (list && emptyState) {
                emptyState.style.display = list.children.length === 0 ? 'block' : 'none';
                lastUnreadCount = list.children.length;
            }

            target.disabled = true;

            var resolvedUrl = readUrl;
            if (readUrl.indexOf('http://') !== 0 && readUrl.indexOf('https://') !== 0) {
                resolvedUrl = new URL(readUrl, window.location.href).href;
            }

            fetch(resolvedUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            })
                .then(function(response) {
                    return response.text().then(function(text) {
                        var data = null;
                        if (text) {
                            try {
                                data = JSON.parse(text);
                            } catch (error) {
                                data = null;
                            }
                        }
                        return { response: response, data: data };
                    });
                })
                .then(function(result) {
                    if (!result.response.ok || (result.data && result.data.success === false)) {
                        if (placeholder && list) {
                            list.appendChild(placeholder);
                        }
                        if (list && list.children.length > 0 && emptyState) {
                            emptyState.style.display = 'none';
                        }
                        target.disabled = false;
                        return;
                    }

                    if (list && emptyState) {
                        emptyState.style.display = list.children.length === 0 ? 'block' : 'none';
                        lastUnreadCount = list.children.length;
                    }

                    if (badge) {
                        var currentCount = parseInt(badge.textContent || '0', 10);
                        var nextCount = Number.isFinite(currentCount) ? Math.max(currentCount - 1, 0) : 0;
                        badge.textContent = String(nextCount);
                        badge.hidden = nextCount === 0;
                    }
                })
                .catch(function() {
                    if (placeholder && list) {
                        list.appendChild(placeholder);
                    }
                    if (list && emptyState) {
                        emptyState.style.display = list.children.length === 0 ? 'block' : 'none';
                        lastUnreadCount = list.children.length;
                    }
                    target.disabled = false;
                });
        });

        setInterval(function() {
            fetch(pollUrl, {
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function(response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (!Array.isArray(data)) {
                        return;
                    }
                    var unreadCount = data.filter(function(item) { return !item.read; }).length;
                    if (unreadCount > lastUnreadCount) {
                        window.location.reload();
                    }
                    lastUnreadCount = unreadCount;
                })
                .catch(function() {});
        }, 5000);
    }

    window.addEventListener('load', function() {
        setTimeout(showContent, 500);
        setupMarkAsRead();
        setupMarkAllAsRead();
    });
})();
