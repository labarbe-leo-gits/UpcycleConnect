(function() {
    'use strict';

    function showContent() {
        var skeleton = document.getElementById('notifications-skeleton');
        var content = document.getElementById('notifications-content');
        if (skeleton) skeleton.style.display = 'none';
        if (content) content.style.display = 'block';
    }

    function setupMarkAsRead() {
        var root = document.getElementById('notifications-root');
        var list = document.getElementById('notifications-list');
        var emptyState = document.getElementById('notifications-empty');
        var readUrl = root ? root.getAttribute('data-read-url') : '';
        var badge = document.getElementById('notifications-count');

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

            if (list && list.children.length === 0 && emptyState) {
                emptyState.style.display = 'block';
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
                    if (list && list.children.length > 0 && emptyState) {
                        emptyState.style.display = 'none';
                    }
                    target.disabled = false;
                });
        });
    }

    window.addEventListener('load', function() {
        setTimeout(showContent, 500);
        setupMarkAsRead();
    });
})();
